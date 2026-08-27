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

    public function create(BoletoRequest $request, string $localReference): Boleto
    {
        $request->validate();

        $response = $this->http->post(
            Endpoints::build(Endpoints::path('BOLETO_CREATE'), ['seller_id' => $this->config->sellerId]),
            $request->toArray($localReference),
            idempotencyKey: $localReference,
        );

        return Boleto::fromApi($response, $localReference);
    }

    /**
     * Consulta o boleto.
     *
     * A rota nao e aninhada sob /sellers: GET /v1/billets/{billet_id}.
     */
    public function find(string $boletoId): Boleto
    {
        $response = $this->http->get(
            Endpoints::build(Endpoints::path('BOLETO_SHOW'), ['billet_id' => $boletoId])
        );

        return Boleto::fromApi($response);
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
                'seller_id' => $this->config->sellerId,
                'billet_id' => $boletoId,
            ])
        );

        return Boleto::fromApi($response);
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

        return Boleto::fromApi($response);
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

        if ($this->pagadorEmail !== null && $this->pagadorEmail !== ''
            && !filter_var($this->pagadorEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail do pagador inválido.');
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
     * Corpo da emissao, no formato que a API exige.
     *
     * Os nomes vieram da propria API, que rejeitou a primeira tentativa
     * listando campo a campo. O desenho espelha o Adyen — social_security_number,
     * shopper_statement, delivery_date e shopper sao exatamente os campos que
     * o Adyen pede para boleto bancario, o que faz sentido: o gateway por tras
     * da Parcela Express e Adyen.
     *
     * Note o que NAO existe: nao ha campo de referencia nem de protocolo. A
     * amarracao com a O.S. fica so do nosso lado, em pe_boletos, e aparece
     * para o pagador via shopper_statement.
     *
     * @return array<string,mixed>
     */
    public function toArray(string $localReference): array
    {
        [$primeiroNome, $sobrenome] = self::separarNome($this->pagadorNome);

        /* Ambos os campos de texto sao obrigatorios e nao aceitam vazio,
           entao garantimos um valor mesmo sem descricao informada. */
        $descricao = trim((string) $this->descricao);

        if ($descricao === '') {
            $descricao = 'Emolumentos';
        }

        $payload = [
            /* Em centavos, como no restante da API (o checkout usa
               amount_cents com o mesmo significado). */
            'value' => $this->amountCents,

            /* CPF/CNPJ vai na raiz, so digitos — nao dentro do shopper. */
            'social_security_number' => preg_replace('/\D/', '', $this->pagadorDocumento),

            /**
             * ATENCAO: description e shopper_statement sao campos DISTINTOS e
             * ambos obrigatorios.
             *
             * A API confirmou os dois separadamente: description sobreviveu a
             * primeira rodada de validacao (nao foi listado como "should not
             * exist") e foi cobrado na segunda, quando eu o havia removido
             * achando que shopper_statement o substituia.
             *
             * description  — descricao da cobranca
             * shopper_statement — texto que identifica a venda para o pagador
             */
            'description' => $descricao,
            'shopper_statement' => $descricao,

            /* ISO 8601 completo: a validacao recusa data pura. */
            'delivery_date' => $this->vencimento->format('c'),

            'shopper' => [
                'first_name' => $primeiroNome,
                'last_name' => $sobrenome,
                /* Sempre presente, mesmo incompleto: e a ausencia do objeto
                   que derruba a API com "reading 'complement' of undefined".
                   Campo vazio ela aceita; objeto ausente, nao. */
                'billing_address' => $this->enderecoParaApi(),
            ],
        ];

        if ($this->pagadorEmail !== null && $this->pagadorEmail !== '') {
            $payload['shopper']['email'] = $this->pagadorEmail;
        }

        if ($this->instrucoes !== null && $this->instrucoes !== '') {
            $payload['instructions'] = $this->instrucoes;
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
