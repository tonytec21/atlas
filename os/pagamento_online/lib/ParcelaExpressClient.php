<?php
/**
 * =====================================================================
 * ParcelaExpressClient — cliente da API v2 da Parcela Express
 * ---------------------------------------------------------------------
 * Módulo Pagamento Online da O.S. (Atlas) — v3.0.0
 *
 *   POST /v2/auth/login                       -> id_token + seller.id
 *   POST /v2/billets/{sellerId}               -> boleto (corpo = lista)
 *   POST /v2/pos/payments/{seller_id}         -> venda na maquininha
 *   GET  /v2/sellers/{sellerId}/sales/{id}    -> situação da venda/boleto
 *
 * O estabelecimento (seller) é o devolvido pelo login.
 * =====================================================================
 */

if (!class_exists('ParcelaExpressClient')) {

class ParcelaExpressClient
{
    /** Conta de homologação (sandbox), usada quando não há e-mail/senha configurados. */
    const HOMOLOGACAO_EMAIL = 'george@parcelaexpress.com.br';
    const HOMOLOGACAO_SENHA = '123456';

    private $baseUrl;
    private $apiKey = '';
    private $sellerId = '';

    /** Estabelecimento fixo (configuração). Vazio = o informado pelo login. */
    private $sellerFixo = '';

    public function setSellerFixo($id)
    {
        $this->sellerFixo = trim((string) $id);
    }

    /** Estabelecimento usado nas chamadas: o fixo, se configurado; senão o do login. */
    private function sellerEfetivo()
    {
        return $this->sellerFixo !== '' ? $this->sellerFixo : $this->sellerId;
    }

    /** Estabelecimento devolvido pelo login (sem o fixo). */
    public function getSellerLogin()
    {
        $this->ensureAuthenticated();
        return $this->sellerId;
    }
    private $email = '';
    private $password = '';

    public function __construct($sandbox = true)
    {
        $this->baseUrl = $sandbox
            ? 'https://sandbox.parcelaexpress.com.br'
            : 'https://api.parcelaexpress.com.br';
    }

    /** Permite apontar para outro endereço (testes). */
    public function setBaseUrl($url)
    {
        if (is_string($url) && trim($url) !== '') {
            $this->baseUrl = rtrim(trim($url), '/');
        }
    }

    public function setEmailAndPassword($email, $password)
    {
        $this->email = trim((string) $email);
        $this->password = trim((string) $password);

        if ($this->email === '' || $this->password === '') {
            $this->email = self::HOMOLOGACAO_EMAIL;
            $this->password = self::HOMOLOGACAO_SENHA;
        }
    }

    public function getEmail()
    {
        return $this->email;
    }

    /** Login com as credenciais configuradas (teste de conexão). */
    public function testarLogin()
    {
        return $this->login($this->email, $this->password);
    }

    public function getSellerId()
    {
        $this->ensureAuthenticated();
        return $this->sellerEfetivo();
    }

    /** Chave da sessão: amarrada ao endereço e à conta, para não misturar contas. */
    private function chaveSessao()
    {
        return 'pe_px_' . md5($this->baseUrl . '|' . $this->email);
    }

    /**
     * Garante o token (id_token). Reaproveita o da sessão; senão faz login.
     *
     * @return bool
     */
    public function ensureAuthenticated()
    {
        if ($this->apiKey !== '' && $this->sellerId !== '') {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $s = $_SESSION[$this->chaveSessao()] ?? null;
            if (is_array($s) && !empty($s['token']) && !empty($s['seller'])) {
                $this->apiKey = $s['token'];
                $this->sellerId = $s['seller'];
                return true;
            }
        }

        if ($this->email !== '' && $this->password !== '') {
            $res = $this->login($this->email, $this->password);
            if (!empty($res['success']) && $this->apiKey !== '') {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[$this->chaveSessao()] = ['token' => $this->apiKey, 'seller' => $this->sellerId];
                }
                return true;
            }
            $this->ultimoLogin = $res;
        }

        return false;
    }

    /** Resposta do último login que falhou (para mensagem de erro). */
    public $ultimoLogin = null;

    /** Última requisição feita (para a tela de teste): metodo, url, corpo, http, resposta. */
    public $ultimaRequisicao = null;

    /** Descarta o token guardado e força um login novo. */
    public function esquecerToken()
    {
        $this->apiKey = '';
        $this->sellerId = '';
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$this->chaveSessao()]);
        }
    }

    /**
     * Requisição à API v2.
     *
     * @return array{success:bool, http_code?:int, data?:mixed, error?:string}
     */
    private function request($endpoint, $method = 'GET', array $data = array(), $isRetry = false)
    {
        if ($endpoint !== '/v2/auth/login' && $this->apiKey === '') {
            if (!$this->ensureAuthenticated()) {
                return array(
                    'success' => false,
                    'http_code' => 401,
                    'data' => is_array($this->ultimoLogin) ? ($this->ultimoLogin['data'] ?? null) : null,
                    'error' => 'Login recusado pela Parcela Express. Confira o e-mail e a senha na configuração.',
                );
            }
        }

        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
        );

        if ($this->apiKey !== '' && $endpoint !== '/v2/auth/login') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        /* Certificados: usa o pacote que acompanha o módulo quando o PHP não
           tem um configurado (caso do XAMPP no Windows). */
        $ini = (string) ini_get('curl.cainfo');
        $bundle = __DIR__ . '/cacert.pem';
        if (($ini === '' || !is_file($ini)) && is_file($bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        /* Falha de certificado: repete uma vez sem verificação. */
        if ($response === false && in_array($errno, array(60, 77, 35, 51, 58), true)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        }

        if ($response === false || $response === null) {
            return array('success' => false, 'http_code' => 0, 'error' => 'Falha de conexão com a Parcela Express: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($endpoint !== '/v2/auth/login') {
            $this->ultimaRequisicao = array(
                'metodo' => $method,
                'url' => $url,
                'corpo' => $method === 'POST' ? json_encode($data) : null,
                'http' => $httpCode,
                'resposta' => $response,
            );
        }

        // 401: token vencido — refaz o login uma vez.
        if ($httpCode === 401 && !$isRetry && $endpoint !== '/v2/auth/login') {
            $this->apiKey = '';
            $this->sellerId = '';
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION[$this->chaveSessao()]);
            }
            if ($this->ensureAuthenticated()) {
                return $this->request($endpoint, $method, $data, true);
            }
        }

        return array(
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'data' => $decoded !== null ? $decoded : $response,
        );
    }

    /** POST /v2/auth/login */
    public function login($email, $password)
    {
        $res = $this->request('/v2/auth/login', 'POST', array(
            'email' => $email,
            'password' => $password,
        ));

        if ($res['success'] && isset($res['data']['id_token'])) {
            $this->apiKey = $res['data']['id_token'];
            if (isset($res['data']['seller']['id'])) {
                $this->sellerId = (string) $res['data']['seller']['id'];
            }
        }

        return $res;
    }

    /**
     * Emissão de boleto — POST /v2/billets/{sellerId} (lista com um boleto).
     *
     * Chaves de $dadosBoleto: valor, vencimento, cliente_nome, cliente_documento,
     * cliente_email, cliente_telefone, endereco_*, instrucoes, descricao, referencia_id.
     */
    public function emitirBoleto(array $dadosBoleto)
    {
        $this->ensureAuthenticated();
        $sellerId = isset($dadosBoleto['seller_id']) ? $dadosBoleto['seller_id'] : $this->sellerEfetivo();

        $nomeCompleto = trim(!empty($dadosBoleto['cliente_nome']) ? $dadosBoleto['cliente_nome'] : 'Cliente Cartorio');
        $partesNome = explode(' ', $nomeCompleto, 2);
        $firstName = !empty($partesNome[0]) ? $partesNome[0] : 'Cliente';
        $lastName = (isset($partesNome[1]) && trim($partesNome[1]) !== '') ? trim($partesNome[1]) : 'Cartorio';

        $cep = !empty($dadosBoleto['endereco_cep']) ? preg_replace('/\D/', '', $dadosBoleto['endereco_cep']) : '65000000';
        if (strlen($cep) !== 8) {
            $cep = '65000000';
        }

        $uf = !empty($dadosBoleto['endereco_uf']) ? strtoupper(substr($dadosBoleto['endereco_uf'], 0, 2)) : 'MA';

        $item = array(
            /* Em CENTAVOS: R$ 13,37 vai como 1337. Enviado em reais (13.37),
               a API responde "less than operation cost". */
            'value' => self::centavos($dadosBoleto['valor']),
            'social_security_number' => preg_replace('/\D/', '', (string) $dadosBoleto['cliente_documento']),
            'shopper_statement' => isset($dadosBoleto['instrucoes']) ? $dadosBoleto['instrucoes'] : 'Cobrança Cartorária OS',
            'description' => isset($dadosBoleto['descricao']) ? $dadosBoleto['descricao'] : ('Cobrança Ordem ' . (isset($dadosBoleto['referencia_id']) ? $dadosBoleto['referencia_id'] : '')),
            'delivery_date' => $dadosBoleto['vencimento'],
            'shopper' => array(
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => !empty($dadosBoleto['cliente_email']) ? $dadosBoleto['cliente_email'] : 'contato@cartorio.com.br',
                'phone' => isset($dadosBoleto['cliente_telefone']) ? preg_replace('/\D/', '', $dadosBoleto['cliente_telefone']) : '98999999999',
                'billing_address' => array(
                    'street' => !empty($dadosBoleto['endereco_rua']) ? $dadosBoleto['endereco_rua'] : 'Rua Principal',
                    'house_number_or_name' => !empty($dadosBoleto['endereco_numero']) ? $dadosBoleto['endereco_numero'] : '100',
                    'district' => !empty($dadosBoleto['endereco_bairro']) ? $dadosBoleto['endereco_bairro'] : 'Centro',
                    'city' => !empty($dadosBoleto['endereco_cidade']) ? $dadosBoleto['endereco_cidade'] : 'São Luís',
                    'state_or_province' => $uf,
                    'postal_code' => $cep,
                ),
            ),
            'with_payment_link' => true,
        );

        if (isset($dadosBoleto['referencia_id'])) {
            $item['protocol'] = (string) $dadosBoleto['referencia_id'];
        }

        return $this->request('/v2/billets/' . urlencode($sellerId), 'POST', array($item));
    }

    /**
     * Venda na maquininha — POST /v2/pos/payments/{seller_id}.
     *
     * @param string $tipoOperacao CREDITO, DEBITO ou PIX
     */
    public function enviarParaMaquininha($terminalId, $valor, $tipoOperacao = 'CREDITO', $parcelas = 1, $referenciaId = '', $documento = '')
    {
        $this->ensureAuthenticated();

        $tipoUpper = strtoupper($tipoOperacao);
        $isDebit = ($tipoUpper === 'DEBITO');
        $isPix = ($tipoUpper === 'PIX');

        $serviceId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $referenciaId), 0, 10);
        if ($serviceId === '') {
            $serviceId = 'POS' . time();
        }

        $payload = array(
            'terminal_id' => $terminalId,
            'amount' => self::centavos($valor),   // em centavos, como o boleto
            'is_debit' => $isDebit,
            'instalments' => (int) $parcelas,
            'service_id' => $serviceId,
            'is_pix' => $isPix,
            'description' => 'Cobrança Maquininha ' . $referenciaId,
            'document' => preg_replace('/\D/', '', (string) $documento),
            'has_split_rules' => false,
            'split_rules' => array(),
        );

        return $this->request('/v2/pos/payments/' . urlencode($this->sellerEfetivo()), 'POST', $payload);
    }

    /** CPF/CNPJ com máscara; outros tamanhos voltam como vieram (só dígitos). */
    public static function documentoFormatado($documento)
    {
        $d = preg_replace('/\D/', '', (string) $documento);

        if (strlen($d) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $d);
        }
        if (strlen($d) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $d);
        }

        return $d;
    }

    /** Reais -> centavos (inteiro). */
    public static function centavos($valor)
    {
        return (int) round(((float) str_replace(',', '.', (string) $valor)) * 100);
    }

    /** Situação da venda/boleto — GET /v2/sellers/{sellerId}/sales/{saleId}. */
    public function consultarTransacao($saleId, $sellerId = '')
    {
        $this->ensureAuthenticated();
        $targetSeller = $sellerId !== '' ? $sellerId : $this->sellerEfetivo();

        return $this->request('/v2/sellers/' . urlencode($targetSeller) . '/sales/' . urlencode($saleId), 'GET');
    }

    /**
     * Status da API -> 'pago', 'cancelado' ou 'pendente'.
     */
    public static function statusLocal($statusApi)
    {
        $s = strtoupper(trim((string) $statusApi));

        if (in_array($s, array('APPROVED', 'PAID', 'LIQUIDADO', 'SETTLED', 'CONFIRMED'), true)) {
            return 'pago';
        }
        if (in_array($s, array('CANCELED', 'CANCELLED', 'EXPIRED', 'REFUNDED'), true)) {
            return 'cancelado';
        }

        return 'pendente';
    }

    /** Mensagem de erro legível a partir da resposta da API. */
    public static function mensagemErro($res, $padrao = 'Erro no gateway de pagamento')
    {
        if (!isset($res['data']) || !is_array($res['data'])) {
            if (!empty($res['error'])) {
                return $res['error'];
            }
            if (isset($res['data']) && is_string($res['data']) && trim($res['data']) !== '') {
                return $padrao . ' (HTTP ' . ($res['http_code'] ?? '?') . '): ' . mb_substr(strip_tags($res['data']), 0, 200);
            }
            return $padrao . (isset($res['http_code']) ? ' (HTTP ' . $res['http_code'] . ')' : '');
        }

        $data = $res['data'];
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $principal = isset($data['message'])
            ? (is_array($data['message']) ? implode(' | ', array_map('strval', $data['message'])) : (string) $data['message'])
            : (isset($res['error']) ? $res['error'] : $padrao);

        $detalhes = array();
        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $err) {
                if (is_array($err)) {
                    $err = $err['message'] ?? json_encode($err, JSON_UNESCAPED_UNICODE);
                }
                if (!is_string($err) || $err === '') {
                    continue;
                }
                if (strpos($err, 'social_security_number') !== false) {
                    $detalhes[] = 'O CPF/CNPJ do cliente é obrigatório e não foi informado na O.S.';
                } elseif (strpos($err, 'first_name') !== false || strpos($err, 'shopper') !== false) {
                    $detalhes[] = 'O nome do cliente é obrigatório para a emissão de cobrança.';
                } elseif (strpos($err, 'value') !== false) {
                    $detalhes[] = 'O valor da cobrança é inválido ou está abaixo do custo operacional.';
                } else {
                    $detalhes[] = $err;
                }
            }
        }

        /* 403: login aceito, mas a conta não pode operar nesse estabelecimento. */
        if ((int) ($res['http_code'] ?? 0) === 403 && !$detalhes) {
            return 'Acesso negado (Forbidden): a conta configurada não tem permissão para emitir no estabelecimento usado. '
                . 'Confira o campo "ID do estabelecimento" na configuração — deixe-o em branco para usar o da própria conta.';
        }

        if ($detalhes) {
            if (in_array($principal, array('Bad Request Exception', 'Unauthorized', ''), true)) {
                return implode(' | ', $detalhes);
            }
            return $principal . ': ' . implode(' | ', $detalhes);
        }

        return $principal !== '' ? $principal : $padrao;
    }
}

}
