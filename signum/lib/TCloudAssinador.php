<?php
/**
 * TCloudAssinador — cliente PHP do serviço "TCloud Assinador - Servidor" (porta 9480).
 *
 * Implementa a interface descrita no "Guia de integração (Atlas e BookC)" do
 * TCloud Assinador 1.1.x (seção 4), falando direto com a API HTTP (seção 9).
 * Compatível com PHP 5.6+ (sem tipos declarados, sem ??, sem match).
 * Usa cURL se existir; senão, streams.
 *
 * Se o arquivo oficial atlas/TCloudAssinador.php já tiver sido carregado antes,
 * esta definição é ignorada (mesmos nomes de classe e métodos).
 */

if (!class_exists('TCloudAssinadorException')) {
    class TCloudAssinadorException extends Exception
    {
        /** @var string código da falha (sem_token, sem_servidor, token, sem_estacao, …) */
        public $codigo = 'erro';
        /** @var int status HTTP (0 = sem resposta) */
        public $status = 0;

        public function __construct($mensagem, $codigo = 'erro', $status = 0)
        {
            parent::__construct($mensagem);
            $this->codigo = (string)$codigo;
            $this->status = (int)$status;
        }
    }
}

if (!class_exists('TCloudAssinador')) {
    class TCloudAssinador
    {
        const CONFIG_PADRAO = 'C:\\ProgramData\\TCloud\\PdfSigner\\servidor.json';
        const URL_PADRAO    = 'http://127.0.0.1:9480';

        private $url;
        private $token;
        private $sistema;
        private $timeout;

        /**
         * @param array $opcoes sistema, url, token, config, timeout
         * @throws TCloudAssinadorException sem_token
         */
        public function __construct($opcoes = array())
        {
            $this->sistema = isset($opcoes['sistema']) && $opcoes['sistema'] !== '' ? (string)$opcoes['sistema'] : 'Atlas';
            $this->timeout = isset($opcoes['timeout']) ? max(5, (int)$opcoes['timeout']) : 120;
            $config = isset($opcoes['config']) && $opcoes['config'] !== '' ? (string)$opcoes['config'] : self::CONFIG_PADRAO;

            $cfg = self::lerConfig($config);

            $url = isset($opcoes['url']) ? trim((string)$opcoes['url']) : '';
            if ($url === '') {
                $porta = ($cfg && !empty($cfg['Porta'])) ? (int)$cfg['Porta'] : 9480;
                $url = 'http://127.0.0.1:' . $porta;
            }
            if (!preg_match('~^https?://~i', $url)) $url = 'http://' . $url;
            $this->url = rtrim($url, '/');

            $token = isset($opcoes['token']) ? trim((string)$opcoes['token']) : '';
            if ($token === '' && $cfg && !empty($cfg['TokenSistemas'])) $token = trim((string)$cfg['TokenSistemas']);
            if ($token === '') {
                throw new TCloudAssinadorException(
                    'Token do TCloud Assinador não encontrado. Instale o servidor de assinatura nesta VM '
                    . 'ou informe o endereço e o token nas configurações.', 'sem_token', 0);
            }
            $this->token = $token;
        }

        /** Lê o servidor.json (tolerante a BOM). Devolve array ou null. */
        public static function lerConfig($caminho = null)
        {
            $caminho = $caminho ? $caminho : self::CONFIG_PADRAO;
            if (!@is_file($caminho) || !@is_readable($caminho)) return null;
            $txt = @file_get_contents($caminho);
            if ($txt === false || $txt === '') return null;
            if (substr($txt, 0, 3) === "\xEF\xBB\xBF") $txt = substr($txt, 3);
            $j = json_decode($txt, true);
            return is_array($j) ? $j : null;
        }

        public function url()     { return $this->url; }
        public function sistema() { return $this->sistema; }

        /* ========================= Métodos públicos ========================= */

        /** O serviço está respondendo? */
        public function disponivel()
        {
            try { $v = $this->versao(); return !empty($v['ok']); }
            catch (Exception $e) { return false; }
        }

        /** GET /versao (sem autenticação). */
        public function versao()
        {
            return $this->requisicao('GET', '/versao', null, array(), 4, false);
        }

        /** Gera o código de 6 dígitos para parear um computador. */
        public function codigoPareamento($usuario, $nome = '')
        {
            return $this->requisicao('POST', '/sistema/pareamento', array(
                'usuario' => (string)$usuario,
                'nome'    => (string)$nome,
            ));
        }

        /** Computadores pareados (de um usuário, ou todos). */
        public function estacoes($usuario = null)
        {
            $q = array();
            if ($usuario !== null && $usuario !== '') $q['usuario'] = (string)$usuario;
            $r = $this->requisicao('GET', '/sistema/estacoes', null, $q);
            return (isset($r['estacoes']) && is_array($r['estacoes'])) ? $r['estacoes'] : array();
        }

        /** O usuário já pareou algum computador? */
        public function temEstacao($usuario)
        {
            return count($this->estacoes($usuario)) > 0;
        }

        /** Desfaz um pareamento. */
        public function removerEstacao($id)
        {
            $r = $this->requisicao('POST', '/sistema/estacoes/remover', array('id' => (string)$id));
            return !empty($r['ok']);
        }

        /**
         * Cria o pedido de assinatura. Volta na hora.
         * @param string $usuario    login do usuário no sistema
         * @param array  $documentos [['nome'=>…, 'arquivo'=>caminho] | ['nome'=>…, 'pdf'=>bytes], …]
         * @param array  $opcoes     titulo, motivo, local, nivel, aparencia, … (seção 6)
         */
        public function iniciar($usuario, $documentos, $opcoes = array())
        {
            if (!is_array($documentos) || !count($documentos))
                throw new TCloudAssinadorException('Nenhum documento informado para assinar.', 'sem_documentos', 0);

            $docs = array();
            foreach ($documentos as $i => $d) {
                $nome = isset($d['nome']) && $d['nome'] !== '' ? (string)$d['nome'] : ('documento-' . ($i + 1) . '.pdf');
                if (isset($d['pdf']) && $d['pdf'] !== '') {
                    $bytes = $d['pdf'];
                } elseif (isset($d['arquivo']) && $d['arquivo'] !== '') {
                    if (!@is_file($d['arquivo']))
                        throw new TCloudAssinadorException('Arquivo não encontrado: ' . $nome, 'arquivo', 0);
                    $bytes = @file_get_contents($d['arquivo']);
                    if ($bytes === false) throw new TCloudAssinadorException('Não foi possível ler o arquivo: ' . $nome, 'arquivo', 0);
                } else {
                    throw new TCloudAssinadorException('Documento sem conteúdo: ' . $nome, 'documento', 0);
                }
                if ($bytes === '') throw new TCloudAssinadorException('Documento vazio: ' . $nome, 'documento', 0);
                $docs[] = array('nome' => $nome, 'pdf_base64' => base64_encode($bytes));
            }

            $corpo = is_array($opcoes) ? $opcoes : array();
            $corpo['usuario'] = (string)$usuario;
            if (empty($corpo['sistema'])) $corpo['sistema'] = $this->sistema;
            if (empty($corpo['ip_estacao']) && !empty($_SERVER['REMOTE_ADDR'])) $corpo['ip_estacao'] = $_SERVER['REMOTE_ADDR'];
            $corpo['documentos'] = $docs;

            return $this->requisicao('POST', '/sistema/assinar', $corpo);
        }

        /** Situação do pedido. */
        public function status($id)
        {
            return $this->requisicao('GET', '/sistema/pedido', null, array('id' => (string)$id), 20);
        }

        /** PDFs assinados de um pedido concluído (bytes em 'pdf'). */
        public function resultado($id)
        {
            $r = $this->requisicao('GET', '/sistema/resultado', null, array('id' => (string)$id));
            if (isset($r['documentos']) && is_array($r['documentos'])) {
                foreach ($r['documentos'] as $k => $d) {
                    if (!empty($d['pdf_base64'])) {
                        $r['documentos'][$k]['pdf'] = base64_decode($d['pdf_base64']);
                        unset($r['documentos'][$k]['pdf_base64']);
                    }
                }
            } else {
                $r['documentos'] = array();
            }
            return $r;
        }

        /** Cancela um pedido em andamento. */
        public function cancelar($id)
        {
            return $this->requisicao('POST', '/sistema/cancelar', array('id' => (string)$id), array(), 20);
        }

        /** Testa a ACT configurada no servidor.json. */
        public function testarAct()
        {
            return $this->requisicao('POST', '/sistema/testar-act', array(), array(), 60);
        }

        /** Versão bloqueante: cria, espera e devolve o resultado. */
        public function assinarEAguardar($usuario, $documentos, $opcoes = array(), $limite = 180)
        {
            $r = $this->iniciar($usuario, $documentos, $opcoes);
            $id = $r['id'];
            $fim = time() + max(10, (int)$limite);
            while (time() < $fim) {
                usleep(1500000);
                $s = $this->status($id);
                if (empty($s['finalizado'])) continue;
                if ($s['estado'] === 'concluido') return $this->resultado($id);
                $msg = !empty($s['mensagem']) ? $s['mensagem'] : ('Pedido ' . $s['estado'] . '.');
                throw new TCloudAssinadorException($msg, $s['estado'], 0);
            }
            try { $this->cancelar($id); } catch (Exception $e) {}
            throw new TCloudAssinadorException('Tempo esgotado aguardando a assinatura.', 'expirado', 0);
        }

        /* ========================= HTTP ========================= */

        private function requisicao($metodo, $caminho, $corpo = null, $query = array(), $timeout = null, $autenticar = true)
        {
            $url = $this->url . $caminho;
            if ($query) $url .= '?' . http_build_query($query);
            $timeout = $timeout ? (int)$timeout : $this->timeout;

            $json = null;
            if ($corpo !== null) {
                $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    // tenta de novo corrigindo UTF-8 inválido
                    $json = json_encode($corpo, JSON_UNESCAPED_SLASHES | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
                    if ($json === false) throw new TCloudAssinadorException('Falha ao montar o pedido (JSON).', 'json', 0);
                }
            }

            $cab = array('Accept: application/json');
            if ($json !== null) $cab[] = 'Content-Type: application/json; charset=utf-8';
            if ($autenticar) $cab[] = 'X-TCloud-Token: ' . $this->token;

            if (function_exists('curl_init')) {
                list($status, $resp) = $this->viaCurl($metodo, $url, $json, $cab, $timeout);
            } else {
                list($status, $resp) = $this->viaStream($metodo, $url, $json, $cab, $timeout);
            }

            if ($status === 0 || $resp === false || $resp === null) {
                throw new TCloudAssinadorException(
                    'O TCloud Assinador não respondeu em ' . $this->url . '. Verifique se o serviço "TCloud Assinador - Servidor" está em execução.',
                    'sem_servidor', 0);
            }

            if (substr($resp, 0, 3) === "\xEF\xBB\xBF") $resp = substr($resp, 3);
            $dados = json_decode($resp, true);

            if ($status === 401) {
                $msg = (is_array($dados) && !empty($dados['erro'])) ? $dados['erro']
                     : 'O TCloud Assinador recusou o token (ou esta máquina não está autorizada em SistemasPermitidos).';
                throw new TCloudAssinadorException($msg, 'token', 401);
            }
            if (!is_array($dados)) {
                throw new TCloudAssinadorException('Resposta inválida do TCloud Assinador (HTTP ' . $status . ').', 'resposta', $status);
            }
            if (empty($dados['ok']) || $status >= 400) {
                $msg = !empty($dados['erro']) ? $dados['erro'] : ('Erro do TCloud Assinador (HTTP ' . $status . ').');
                $cod = !empty($dados['codigo']) ? $dados['codigo'] : ($status === 404 ? 'nao_encontrado' : 'erro');
                throw new TCloudAssinadorException($msg, $cod, $status);
            }
            return $dados;
        }

        private function viaCurl($metodo, $url, $json, $cab, $timeout)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $cab);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
            if (defined('CURLOPT_PROXY')) curl_setopt($ch, CURLOPT_PROXY, '');   // nunca usa proxy do sistema p/ a rede local
            if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            elseif ($metodo === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            $resp = curl_exec($ch);
            $status = ($resp === false) ? 0 : (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (PHP_VERSION_ID < 80000) curl_close($ch);   // no PHP 8+ o handle é liberado sozinho
            return array($status, $resp);
        }

        private function viaStream($metodo, $url, $json, $cab, $timeout)
        {
            $opts = array('http' => array(
                'method'        => $metodo,
                'header'        => implode("\r\n", $cab) . "\r\n",
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ));
            if ($json !== null) $opts['http']['content'] = $json;
            elseif ($metodo === 'POST') $opts['http']['content'] = '';
            $ctx = stream_context_create($opts);
            $resp = @file_get_contents($url, false, $ctx);
            $status = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int)$m[1];
                }
            }
            if ($resp === false) $status = 0;
            return array($status, $resp);
        }
    }
}
