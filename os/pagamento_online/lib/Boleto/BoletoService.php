<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Boleto;

use TCloud\ParcelaExpress\Config;
use TCloud\ParcelaExpress\Endpoints;
use TCloud\ParcelaExpress\HttpClient;

/**
 * Emissao e consulta de boletos.
 *
 * REGRA CENTRAL: emitir boleto nao e receber. Este servico nunca lanca
 * pagamento; ele apenas cria e consulta o titulo. O lancamento em
 * pagamento_os acontece quando a consulta retorna status "pago" — e so
 * entao.
 */
final class BoletoService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {
    }

    /**
     * Emite o boleto: POST /v2/billets/{seller_id} com uma LISTA de um item,
     * A resposta tambem e uma lista.
     */
    public function create(BoletoRequest $request, string $localReference): Boleto
    {
        $request->validate();

        $response = $this->http->post(
            Endpoints::build(Endpoints::path('BOLETO_CREATE'), ['seller_id' => $this->http->sellerId()]),
            [$request->toArray($localReference, $this->config)],
        );

        $boleto = Boleto::fromApi($response, $localReference, $this->config);

        /* Lista vazia ou item com erro proprio. */
        $item = $boleto->raw;
        if ($boleto->id === '' && $boleto->saleId === null) {
            $msg = $item['message'] ?? $item['error'] ?? null;
            if (is_array($msg)) {
                $msg = implode(' | ', array_map('strval', $msg));
            }
            if (is_string($msg) && $msg !== '') {
                throw new \TCloud\ParcelaExpress\ValidationException($msg, 400, $item);
            }
        }

        return $boleto;
    }

    /**
     * Situacao do boleto.
     *
     * Com sale_id (emissao v2): GET /v2/sellers/{seller}/sales/{sale_id},
     * Sem ele (boletos antigos): GET /v1/billets/{id}.
     */
    public function find(string $boletoId, ?string $saleId = null): Boleto
    {
        if ($saleId !== null && $saleId !== '') {
            try {
                $response = $this->http->get(
                    Endpoints::build(Endpoints::path('BOLETO_STATUS'), [
                        'seller_id' => $this->http->sellerId(),
                        'sale_id' => $saleId,
                    ])
                );

                return Boleto::fromApi($response, null, $this->config);
            } catch (\TCloud\ParcelaExpress\ApiException $e) {
                if (!$e->isRouteMissing() || $boletoId === '') {
                    throw $e;
                }
                /* rota da venda indisponivel: tenta pelo titulo */
            }
        }

        $response = $this->http->get(
            Endpoints::build(Endpoints::path('BOLETO_SHOW'), ['billet_id' => $boletoId])
        );

        return Boleto::fromApi($response, null, $this->config);
    }

    /**
     * URL do PDF.
     *
     * Vem em chamada separada da emissao — o POST de criacao nao devolve o
     * link. Retorna null em vez de lancar: um boleto sem PDF ainda e um
     * boleto valido, com linha digitavel utilizavel.
     */
    public function url(string $boletoId): ?string
    {
        try {
            $response = $this->http->get(
                Endpoints::build(Endpoints::path('BOLETO_URL'), ['billet_id' => $boletoId])
            );

            $url = $response['url'] ?? $response['data']['url'] ?? $response['link'] ?? null;

            return is_string($url) && $url !== '' ? $url : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Baixa do titulo.
     *
     * E POST em .../void, nao DELETE, e exige seller e boleto na URL.
     */
    public function cancel(string $boletoId): Boleto
    {
        $response = $this->http->post(
            Endpoints::build(Endpoints::path('BOLETO_CANCEL'), [
                'seller_id' => $this->http->sellerId(),
                'billet_id' => $boletoId,
            ])
        );

        return Boleto::fromApi($response, null, $this->config);
    }

    /**
     * Marca o boleto como pago. SOMENTE SANDBOX.
     *
     * A propria API descreve a rota como "em ambiente de desenvolvimento".
     * Serve para exercitar o ciclo completo sem esperar compensacao real.
     */
    public function pagarSandbox(string $boletoId): Boleto
    {
        if (!$this->config->isSandbox()) {
            throw new \LogicException('Pagamento simulado só existe em sandbox.');
        }

        $response = $this->http->post(
            Endpoints::build(Endpoints::path('BOLETO_PAGAR_SANDBOX'), ['billet_id' => $boletoId])
        );

        return Boleto::fromApi($response, null, $this->config);
    }
}

/**
 * Dados de emissao.
 */
final class BoletoRequest
{
    public function __construct(
        public readonly int $amountCents,
        public readonly \DateTimeInterface $vencimento,
        public readonly string $pagadorNome,
        public readonly string $pagadorDocumento,
        public readonly ?string $pagadorEmail = null,
        public readonly ?string $descricao = null,
        public readonly ?string $instrucoes = null,
        public readonly ?string $protocol = null,
        /**
         * Endereco do pagador.
         *
         * Chaves: cep, logradouro, numero, complemento, bairro, cidade, uf.
         *
         * A API nao exige isto na validacao, mas o codigo dela quebra sem —
         * o erro "Cannot read properties of undefined (reading 'complement')"
         * e exatamente isso: um 500, nao um 400. Entao na pratica e
         * obrigatorio.
         *
         * @var array<string,string>
         */
        public readonly array $endereco = [],
        /** @var array<int,array<string,mixed>> */
        public readonly array $splitRules = [],
        /** Telefone do pagador (shopper.phone). */
        public readonly ?string $pagadorTelefone = null,
    ) {
    }

    public function validate(): void
    {
        if ($this->amountCents <= 0) {
            throw new \InvalidArgumentException('O valor do boleto deve ser maior que zero.');
        }

        if (trim($this->pagadorNome) === '') {
            throw new \InvalidArgumentException('Informe o nome do pagador.');
        }

        $doc = preg_replace('/\D/', '', $this->pagadorDocumento) ?? '';

        if (strlen($doc) !== 11 && strlen($doc) !== 14) {
            throw new \InvalidArgumentException('CPF ou CNPJ do pagador inválido.');
        }

        if (!self::documentoValido($doc)) {
            throw new \InvalidArgumentException('CPF ou CNPJ do pagador não confere.');
        }

        $hoje = new \DateTimeImmutable('today');

        if ($this->vencimento < $hoje) {
            throw new \InvalidArgumentException('O vencimento não pode ser anterior a hoje.');
        }

        /* O e-mail do pagador e obrigatorio na Parcela Express (shopper.email). */
        if ($this->pagadorEmail === null || trim($this->pagadorEmail) === '') {
            throw new \InvalidArgumentException(
                'Informe o e-mail do pagador (obrigatório para emitir o boleto) '
                . 'ou cadastre um e-mail padrão na configuração do Pagamento Online.'
            );
        }

        if (!filter_var(trim($this->pagadorEmail), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail do pagador inválido.');
        }

        $fone = preg_replace('/\D/', '', (string) $this->pagadorTelefone) ?? '';
        if ($fone !== '' && (strlen($fone) < 10 || strlen($fone) > 11)) {
            throw new \InvalidArgumentException('Telefone do pagador inválido (use DDD + número).');
        }

        /* O endereco nao e opcional na pratica — sem ele a API responde 500.
           Validamos aqui para que a falha vire uma mensagem util em vez de
           erro interno do servidor deles. */
        $cep = preg_replace('/\D/', '', (string) ($this->endereco['cep'] ?? '')) ?? '';

        if (strlen($cep) !== 8) {
            throw new \InvalidArgumentException('Informe um CEP válido para o pagador.');
        }

        foreach ([
            'logradouro' => 'Logradouro',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
        ] as $chave => $rotulo) {
            if (trim((string) ($this->endereco[$chave] ?? '')) === '') {
                throw new \InvalidArgumentException("Informe {$rotulo} do endereço do pagador.");
            }
        }

        if (strlen(trim((string) $this->endereco['uf'])) !== 2) {
            throw new \InvalidArgumentException('UF deve ter 2 letras.');
        }
    }

    /**
     * Corpo de UM boleto para POST /v2/billets/{seller_id} — o envio e uma
     * lista com este item:
     *
     *   value (centavos), social_security_number, shopper_statement, description,
     *   delivery_date (ISO, UTC), shopper { first_name, last_name, email, phone,
     *   billing_address {...} }, with_payment_link, protocol
     *
     * @return array<string,mixed>
     */
    public function toArray(string $localReference, ?\TCloud\ParcelaExpress\Config $config = null): array
    {
        [$primeiroNome, $sobrenome] = self::separarNome($this->pagadorNome);

        $descricao = trim((string) $this->descricao);
        if ($descricao === '') {
            $descricao = 'Emolumentos';
        }

        $instrucoes = trim((string) $this->instrucoes);

        /* Vencimento ao meio-dia, em UTC
           (Y-m-d\TH:i:s\Z). Meio-dia evita que o fuso mude o dia. */
        $venc = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->vencimento->format('Y-m-d') . ' 12:00:00')
            ?: new \DateTimeImmutable($this->vencimento->format('Y-m-d') . ' 12:00:00');
        $venc = $venc->setTimezone(new \DateTimeZone('UTC'));

        $shopper = [
            'first_name' => $primeiroNome,
            'last_name' => $sobrenome,
            'email' => trim((string) $this->pagadorEmail),
            'billing_address' => $this->enderecoParaApi(),
        ];

        $fone = preg_replace('/\D/', '', (string) $this->pagadorTelefone) ?? '';
        if ($fone !== '') {
            $shopper['phone'] = $fone;
        }

        $payload = [
            /* Em CENTAVOS (inteiro): R$ 13,37 vai como 1337. A API le o
               numero como centavos — com 13.37 ela entendeu R$ 0,13. */
            'value' => $config ? $config->paraApi($this->amountCents, 'boleto') : $this->amountCents,
            'social_security_number' => preg_replace('/\D/', '', $this->pagadorDocumento),
            /* shopper_statement: texto que o pagador ve; description: descricao da cobranca. */
            'shopper_statement' => mb_substr($instrucoes !== '' ? $instrucoes : $descricao, 0, 255),
            'description' => mb_substr($descricao, 0, 255),
            'delivery_date' => $venc->format('Y-m-d\TH:i:s\Z'),
            'shopper' => $shopper,
            'with_payment_link' => true,
        ];

        if ($this->protocol !== null && $this->protocol !== '') {
            $payload['protocol'] = $this->protocol;
        }

        if ($this->splitRules !== []) {
            $payload['split_rules'] = $this->splitRules;
        }

        return $payload;
    }

    /**
     * Monta o endereco no formato da API.
     *
     * Os nomes NAO seguem o CustomerDTO do checkout, ao contrario do que
     * parecia: a API recusou `state` e `country`, e cobrou `state_or_province`
     * e `district`. Confirmado campo a campo pela propria validacao.
     *
     * Nenhum valor vai como null: a API tolera string vazia, mas quebra em
     * campo ausente.
     *
     * @return array<string,string>
     */
    private function enderecoParaApi(): array
    {
        $e = $this->endereco;

        $v = static fn (string $k): string => trim((string) ($e[$k] ?? ''));

        return [
            'street' => $v('logradouro'),
            'house_number_or_name' => $v('numero') !== '' ? $v('numero') : 'S/N',
            'complement' => $v('complemento'),
            'district' => $v('bairro'),
            'postal_code' => preg_replace('/\D/', '', $v('cep')) ?? '',
            'city' => $v('cidade'),
            'state_or_province' => strtoupper($v('uf')),
        ];
    }

    /**
     * Separa nome e sobrenome.
     *
     * A API pede os dois campos, mas o cartorio guarda um nome so. Sem
     * sobrenome a validacao reclama, entao repetimos o primeiro nome em vez
     * de mandar vazio.
     *
     * @return array{0:string,1:string}
     */
    private static function separarNome(string $completo): array
    {
        $partes = preg_split('/\s+/', trim($completo), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($partes === []) {
            return ['Pagador', 'Pagador'];
        }

        if (count($partes) === 1) {
            return [$partes[0], $partes[0]];
        }

        $primeiro = array_shift($partes);

        return [$primeiro, implode(' ', $partes)];
    }

    /** Valida CPF (11) ou CNPJ (14) por digito verificador. */
    public static function documentoValido(string $digitos): bool
    {
        if (strlen($digitos) === 11) {
            if (preg_match('/^(\d)\1{10}$/', $digitos)) {
                return false;
            }

            for ($t = 9; $t < 11; $t++) {
                $soma = 0;

                for ($i = 0; $i < $t; $i++) {
                    $soma += (int) $digitos[$i] * (($t + 1) - $i);
                }

                $dv = ((10 * $soma) % 11) % 10;

                if ((int) $digitos[$t] !== $dv) {
                    return false;
                }
            }

            return true;
        }

        if (strlen($digitos) === 14) {
            if (preg_match('/^(\d)\1{13}$/', $digitos)) {
                return false;
            }

            $calc = static function (string $d, array $pesos): int {
                $soma = 0;

                foreach ($pesos as $i => $p) {
                    $soma += (int) $d[$i] * $p;
                }

                $r = $soma % 11;

                return $r < 2 ? 0 : 11 - $r;
            };

            $dv1 = $calc($digitos, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            $dv2 = $calc($digitos, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

            return (int) $digitos[12] === $dv1 && (int) $digitos[13] === $dv2;
        }

        return false;
    }
}
