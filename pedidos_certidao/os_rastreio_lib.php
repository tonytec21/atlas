<?php
/**
 * os_rastreio_lib.php
 * --------------------------------------------------------------------------
 * Integração de rastreio das Ordens de Serviço com a MESMA API usada pelos
 * pedidos de certidão (api_secrets.json / ingest assinado por HMAC).
 *
 * Cria um registro em `pedidos_certidao` vinculado à O.S. (ordem_servico_id),
 * gera protocolo + token público, enfileira em `api_outbox` e envia à API.
 * Também atualiza o status (pendente -> em_andamento -> emitida) conforme a
 * liquidação dos atos da O.S.
 *
 * Todas as funções são "best-effort": qualquer falha de API/banco é capturada
 * internamente para NUNCA interromper o salvamento/liquidação da O.S.
 * --------------------------------------------------------------------------
 */

if (!function_exists('os_rastreio_cfg')) {

    /** Carrega a configuração da API (mesmo arquivo dos pedidos). */
    function os_rastreio_cfg() {
        $apiConfig = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
        $BASE_URL  = $apiConfig['base_url'] ?? 'https://consultapedido.sistemaatlas.com.br';
        return [
            'BASE_URL'    => $BASE_URL,
            'INGEST_URL'  => $apiConfig['ingest_url']  ?? (rtrim($BASE_URL, '/') . '/api/ingest.php'),
            'API_KEY'     => $apiConfig['api_key']     ?? null,
            'HMAC_SECRET' => $apiConfig['hmac_secret'] ?? null,
            'VERIFY_SSL'  => array_key_exists('verify_ssl', $apiConfig) ? (bool)$apiConfig['verify_ssl'] : true,
            // Base usada na URL pública do QR (mesma do recibo térmico do pedido)
            'PUBLIC_BASE' => $apiConfig['public_base'] ?? 'https://sistemaatlas.com.br',
        ];
    }

    /** Razão social da serventia (mesma fonte usada nos pedidos de certidão). */
    function os_rastreio_razao_social(PDO $conn) {
        try {
            $st = $conn->query("SELECT razao_social FROM cadastro_serventia LIMIT 1");
            if ($st && ($row = $st->fetch(PDO::FETCH_ASSOC))) {
                $rs = trim((string)($row['razao_social'] ?? ''));
                return $rs !== '' ? $rs : null;
            }
        } catch (Throwable $e) { /* ignora */ }
        return null;
    }

    /** Conexão PDO própria (não interfere no mysqli usado na liquidação). */
    function os_rastreio_pdo() {
        if (!function_exists('getDatabaseConnection')) {
            include_once(__DIR__ . '/../os/db_connection.php');
        }
        return getDatabaseConnection();
    }

    /** POST assinado (HMAC-SHA256 sobre timestampMs + json). Cópia do padrão dos pedidos. */
    function os_rastreio_post_signed($url, $apiKey, $hmacSecret, array $body, $requestId, $timestampMs, $verifySsl) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $sig  = hash_hmac('sha256', $timestampMs . $json, $hmacSecret);
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'X-Api-Key: '      . $apiKey,
            'X-Timestamp-Ms: ' . $timestampMs,
            'X-Request-Id: '   . $requestId,
            'X-Signature: '    . $sig,
            'Expect:',
            'Connection: close'
        ];
        $attempts = 3; $delayMs = [250, 800];
        $last = ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => null, 'signature' => $sig];
        for ($i = 0; $i < $attempts; $i++) {
            $resp = null; $http = 0; $err = null;
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => $json,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT        => 25,
                    CURLOPT_SSL_VERIFYPEER => $verifySsl ? 1 : 0,
                    CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                    CURLOPT_NOSIGNAL       => 1,
                    CURLOPT_FORBID_REUSE   => 1,
                    CURLOPT_FRESH_CONNECT  => 1,
                ]);
                $resp = curl_exec($ch);
                if ($resp === false) { $errno = curl_errno($ch); $err = 'cURL(' . $errno . '): ' . curl_error($ch); }
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            } else {
                $context = stream_context_create(['http' => [
                    'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $json, 'timeout' => 25,
                ]]);
                $resp = @file_get_contents($url, false, $context);
                if ($resp === false) { $err = 'HTTP stream falhou'; }
                if (isset($http_response_header) && is_array($http_response_header)) {
                    foreach ($http_response_header as $h) {
                        if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) { $http = (int)$m[1]; break; }
                    }
                }
            }
            $ok = false;
            if ($resp !== null && $http >= 200 && $http < 300) {
                $j = json_decode($resp, true);
                $ok = is_array($j) && !empty($j['success']);
            }
            $last = ['ok' => $ok, 'http_code' => $http, 'response' => $resp, 'error' => $err, 'signature' => $sig];
            if ($ok || ($http >= 400 && $http < 600)) break;
            if ($http === 0 || $err) {
                if ($i < $attempts - 1) { usleep(($delayMs[min($i, count($delayMs) - 1)]) * 1000); continue; }
            }
            break;
        }
        return $last;
    }

    /** Enfileira em api_outbox e tenta enviar imediatamente. */
    function os_rastreio_enfileirar_enviar(PDO $conn, $topic, $protocolo, $token, array $body) {
        $cfg = os_rastreio_cfg();
        $timestamp = (int) round(microtime(true) * 1000);
        $requestId = bin2hex(random_bytes(12));
        $signature = $cfg['HMAC_SECRET'] ? hash_hmac('sha256', $timestamp . json_encode($body, JSON_UNESCAPED_UNICODE), $cfg['HMAC_SECRET']) : null;

        $stmt = $conn->prepare("INSERT INTO api_outbox (topic,protocolo,token_publico,payload_json,api_key,signature,timestamp_utc,request_id)
                                VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $topic, $protocolo, $token,
            json_encode($body, JSON_UNESCAPED_UNICODE),
            $cfg['API_KEY'] ?: null, $signature, $timestamp, $requestId
        ]);
        $outbox_id = (int)$conn->lastInsertId();

        if ($cfg['INGEST_URL'] && $cfg['API_KEY'] && $cfg['HMAC_SECRET']) {
            $res = os_rastreio_post_signed($cfg['INGEST_URL'], $cfg['API_KEY'], $cfg['HMAC_SECRET'], $body, $requestId, $timestamp, $cfg['VERIFY_SSL']);
            if (!empty($res['ok'])) {
                $conn->prepare("UPDATE api_outbox SET delivered_at=NOW(), last_error=NULL WHERE id=?")->execute([$outbox_id]);
            } else {
                $err = trim(($res['error'] ?? '') . ' ' . substr((string)($res['response'] ?? ''), 0, 600));
                $conn->prepare("UPDATE api_outbox SET retries=retries+1, last_error=? WHERE id=?")->execute([$err ?: 'falha desconhecida', $outbox_id]);
            }
        }
        return $outbox_id;
    }

    /** Busca o pedido (rastreio) vinculado a uma O.S. */
    function os_rastreio_get_by_os(PDO $conn, $os_id) {
        $st = $conn->prepare("SELECT * FROM pedidos_certidao WHERE ordem_servico_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$os_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Cria (idempotente) o rastreio para a O.S. e envia 'pedido_criado' à API.
     * Retorna ['protocolo','token_publico','pedido_id','status'] ou null.
     */
    function os_rastreio_criar_para_os(PDO $conn, $os_id, array $opts = []) {
        try {
            $existing = os_rastreio_get_by_os($conn, $os_id);
            if ($existing) {
                return ['protocolo' => $existing['protocolo'], 'token_publico' => $existing['token_publico'],
                        'pedido_id' => (int)$existing['id'], 'status' => $existing['status']];
            }

            $st = $conn->prepare("SELECT cliente, cpf_cliente, total_os, descricao_os, base_de_calculo, criado_por
                                    FROM ordens_de_servico WHERE id = ?");
            $st->execute([$os_id]);
            $os = $st->fetch(PDO::FETCH_ASSOC);
            if (!$os) return null;

            $protocolo = strtoupper(bin2hex(random_bytes(6)));   // 12 hex
            $token     = bin2hex(random_bytes(20));              // 40 hex
            $username  = $opts['usuario'] ?? ($os['criado_por'] ?? 'sistema');
            $atribuicao = $opts['atribuicao'] ?? 'OS';
            $tipoBase  = trim((string)($os['descricao_os'] ?? ''));
            // Mantém "Ordem de Serviço" e ACRESCENTA o título da O.S. quando existir.
            $tipo = $opts['tipo'] ?? ('Ordem de Serviço' . ($tipoBase !== '' ? ' - ' . $tipoBase : ''));
            $tipo = mb_substr($tipo, 0, 50, 'UTF-8'); // coluna VARCHAR(50)
            $requerente = trim((string)($os['cliente'] ?? '')) ?: 'Apresentante';
            $doc       = $os['cpf_cliente'] ?? null;
            $totalOs   = (float)($os['total_os'] ?? 0);
            $base      = (float)($os['base_de_calculo'] ?? 0);
            $razaoSocial = os_rastreio_razao_social($conn);

            $conn->beginTransaction();

            $stmtP = $conn->prepare("INSERT INTO pedidos_certidao
                (protocolo, token_publico, atribuicao, tipo, status, requerente_nome, requerente_doc, base_calculo, total_os, ordem_servico_id, criado_por)
                VALUES (:prot,:token,:atr,:tipo,'pendente',:rn,:rd,:base,:tot,:os,:user)");
            $stmtP->execute([
                ':prot' => $protocolo, ':token' => $token, ':atr' => $atribuicao, ':tipo' => $tipo,
                ':rn' => $requerente, ':rd' => $doc, ':base' => $base, ':tot' => $totalOs,
                ':os' => $os_id, ':user' => $username
            ]);
            $pedido_id = (int)$conn->lastInsertId();

            $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                            VALUES (?,?,?,?,?,?,?)")
                 ->execute([$pedido_id, null, 'pendente', 'Rastreio criado a partir da O.S.', $username,
                            $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            $conn->commit(); // persiste o pedido ANTES de tentar enviar à API

            $body = [
                'topic' => 'pedido_criado', 'protocolo' => $protocolo, 'token_publico' => $token,
                'atribuicao' => $atribuicao, 'tipo' => $tipo, 'status' => 'pendente',
                'pedido_id' => $pedido_id, 'ordem_servico_id' => (int)$os_id, 'isento_ato' => false,
                'criado_em' => date('c'),
                'resumo' => ['requerente' => mb_substr($requerente, 0, 1, 'UTF-8') . '***', 'total_os' => $totalOs],
                'razao_social' => $razaoSocial,
                'source' => 'atlas-app', 'event_time' => date('c')
            ];
            try {
                os_rastreio_enfileirar_enviar($conn, 'pedido_criado', $protocolo, $token, $body);
            } catch (Throwable $eQ) {
                error_log('[os_rastreio_criar_para_os][outbox] ' . $eQ->getMessage());
            }

            return ['protocolo' => $protocolo, 'token_publico' => $token, 'pedido_id' => $pedido_id, 'status' => 'pendente'];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log('[os_rastreio_criar_para_os] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualiza o status do rastreio da O.S. (somente para frente).
     * pendente(0) -> em_andamento(1) -> emitida(2) -> entregue(3)
     */
    function os_rastreio_status_por_os(PDO $conn, $os_id, $novo_status, $usuario = 'sistema', $obs = '') {
        try {
            $ped = os_rastreio_get_by_os($conn, $os_id);
            if (!$ped) {
                os_rastreio_criar_para_os($conn, $os_id, ['usuario' => $usuario]);
                $ped = os_rastreio_get_by_os($conn, $os_id);
                if (!$ped) return null;
            }

            $anterior = $ped['status'];
            $rank = ['pendente' => 0, 'em_andamento' => 1, 'emitida' => 2, 'entregue' => 3, 'cancelada' => 99];
            if (in_array($anterior, ['cancelada', 'entregue'], true)) return null;     // não mexe
            if (!isset($rank[$novo_status]) || $novo_status === 'cancelada') return null;
            if (($rank[$novo_status] ?? -1) <= ($rank[$anterior] ?? 0)) return null;    // já está igual/à frente

            $protocolo = $ped['protocolo']; $token = $ped['token_publico']; $pedido_id = (int)$ped['id'];
            $razaoSocial = os_rastreio_razao_social($conn);

            $conn->beginTransaction();
            $conn->prepare("UPDATE pedidos_certidao SET status=:st, atualizado_por=:u, atualizado_em=NOW() WHERE id=:id")
                 ->execute([':st' => $novo_status, ':u' => $usuario, ':id' => $pedido_id]);
            $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                            VALUES (?,?,?,?,?,?,?)")
                 ->execute([$pedido_id, $anterior, $novo_status, ($obs ?: 'Atualização automática pela O.S.'),
                            $usuario, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            $conn->commit(); // persiste o status ANTES de tentar enviar à API

            // Espelha o payload do alterar_status_auto.php (a API usa 'atualizado_em' para a data/hora do histórico)
            $body = [
                'topic'           => 'status_atualizado',
                'protocolo'       => $protocolo,
                'token_publico'   => $token,
                'status'          => $novo_status,
                'status_anterior' => $anterior,
                'atualizado_em'   => date('c'),
                'observacao'      => $obs !== '' ? $obs : null,
                'pedido_id'       => $pedido_id,
                'ordem_servico_id'=> (int)$os_id,
                'razao_social'    => $razaoSocial,
                'source'          => 'atlas-app',
                'event_time'      => date('c')
            ];
            try {
                os_rastreio_enfileirar_enviar($conn, 'status_atualizado', $protocolo, $token, $body);
            } catch (Throwable $eQ) {
                error_log('[os_rastreio_status_por_os][outbox] ' . $eQ->getMessage());
            }

            return ['protocolo' => $protocolo, 'token_publico' => $token, 'status' => $novo_status];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log('[os_rastreio_status_por_os] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Marca o rastreio como 'entregue', registrando quem recebeu (retirado_por),
     * e envia 'status_atualizado' à API. Garante que a O.S. esteja 'emitida' antes.
     */
    function os_rastreio_entregar(PDO $conn, $os_id, $retirado_por, $usuario = 'sistema', $obs = '') {
        try {
            // Garante a existência do rastreio e que esteja ao menos 'emitida'
            $ped = os_rastreio_get_by_os($conn, $os_id);
            if (!$ped) {
                os_rastreio_criar_para_os($conn, $os_id, ['usuario' => $usuario]);
                $ped = os_rastreio_get_by_os($conn, $os_id);
                if (!$ped) return null;
            }
            // Se ainda não está emitida, promove para 'emitida' (regra: emitida -> entregue)
            if (!in_array($ped['status'], ['emitida', 'entregue', 'cancelada'], true)) {
                os_rastreio_status_por_os($conn, $os_id, 'emitida', $usuario, 'Promovido para emissão (entrega)');
                $ped = os_rastreio_get_by_os($conn, $os_id);
                if (!$ped) return null;
            }

            $anterior = $ped['status'];
            if ($anterior === 'cancelada') return null;
            if ($anterior === 'entregue') {
                return ['status' => 'entregue', 'protocolo' => $ped['protocolo'],
                        'token_publico' => $ped['token_publico'], 'retirado_por' => $ped['retirado_por'] ?? $retirado_por];
            }

            $protocolo = $ped['protocolo']; $token = $ped['token_publico']; $pedido_id = (int)$ped['id'];
            $razaoSocial = os_rastreio_razao_social($conn);
            $retirado_por = trim((string)$retirado_por);

            $conn->beginTransaction();
            $conn->prepare("UPDATE pedidos_certidao SET status='entregue', retirado_por=:r, atualizado_por=:u, atualizado_em=NOW() WHERE id=:id")
                 ->execute([':r' => ($retirado_por !== '' ? $retirado_por : null), ':u' => $usuario, ':id' => $pedido_id]);
            $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                            VALUES (?,?,?,?,?,?,?)")
                 ->execute([$pedido_id, $anterior, 'entregue',
                            ($obs ?: ('Entregue a: ' . ($retirado_por !== '' ? $retirado_por : 'não informado'))),
                            $usuario, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            $conn->commit(); // persiste o status ANTES de tentar enviar à API

            // Envio à API é best-effort e NÃO pode reverter o status já gravado
            $body = [
                'topic'           => 'status_atualizado',
                'protocolo'       => $protocolo,
                'token_publico'   => $token,
                'status'          => 'entregue',
                'status_anterior' => $anterior,
                'atualizado_em'   => date('c'),
                'retirado_por'    => $retirado_por !== '' ? $retirado_por : null,
                'observacao'      => $obs !== '' ? $obs : null,
                'pedido_id'       => $pedido_id,
                'ordem_servico_id'=> (int)$os_id,
                'razao_social'    => $razaoSocial,
                'source'          => 'atlas-app',
                'event_time'      => date('c')
            ];
            try {
                os_rastreio_enfileirar_enviar($conn, 'status_atualizado', $protocolo, $token, $body);
            } catch (Throwable $eQ) {
                error_log('[os_rastreio_entregar][outbox] ' . $eQ->getMessage());
            }

            return ['status' => 'entregue', 'protocolo' => $protocolo, 'token_publico' => $token, 'retirado_por' => $retirado_por];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log('[os_rastreio_entregar] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancela o rastreio da O.S. (status 'cancelada'), registrando o motivo e
     * enviando à API como observação. Não envia se já cancelada ou entregue
     * (entregue não tem transição válida para cancelada na API).
     */
    function os_rastreio_cancelar(PDO $conn, $os_id, $motivo, $usuario = 'sistema') {
        try {
            $ped = os_rastreio_get_by_os($conn, $os_id);
            if (!$ped) {
                os_rastreio_criar_para_os($conn, $os_id, ['usuario' => $usuario]);
                $ped = os_rastreio_get_by_os($conn, $os_id);
                if (!$ped) return null;
            }
            $anterior = $ped['status'];
            if (in_array($anterior, ['cancelada', 'entregue'], true)) return null;

            $protocolo = $ped['protocolo']; $token = $ped['token_publico']; $pedido_id = (int)$ped['id'];
            $razaoSocial = os_rastreio_razao_social($conn);
            $motivo = trim((string)$motivo);

            $conn->beginTransaction();
            $conn->prepare("UPDATE pedidos_certidao SET status='cancelada', cancelado_motivo=:m, atualizado_por=:u, atualizado_em=NOW() WHERE id=:id")
                 ->execute([':m' => ($motivo !== '' ? $motivo : null), ':u' => $usuario, ':id' => $pedido_id]);
            $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                            VALUES (?,?,?,?,?,?,?)")
                 ->execute([$pedido_id, $anterior, 'cancelada', ($motivo ?: 'Cancelamento de O.S.'),
                            $usuario, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            $conn->commit(); // persiste o status ANTES de tentar enviar à API

            $body = [
                'topic'           => 'status_atualizado',
                'protocolo'       => $protocolo,
                'token_publico'   => $token,
                'status'          => 'cancelada',
                'status_anterior' => $anterior,
                'atualizado_em'   => date('c'),
                'observacao'      => $motivo !== '' ? $motivo : null,
                'cancelado_motivo'=> $motivo ?: null,
                'pedido_id'       => $pedido_id,
                'ordem_servico_id'=> (int)$os_id,
                'razao_social'    => $razaoSocial,
                'source'          => 'atlas-app',
                'event_time'      => date('c')
            ];
            try {
                os_rastreio_enfileirar_enviar($conn, 'status_atualizado', $protocolo, $token, $body);
            } catch (Throwable $eQ) {
                error_log('[os_rastreio_cancelar][outbox] ' . $eQ->getMessage());
            }

            return ['status' => 'cancelada', 'protocolo' => $protocolo, 'token_publico' => $token, 'motivo' => $motivo];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log('[os_rastreio_cancelar] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sincroniza o status do rastreio conforme a liquidação da O.S.:
     *  - todos os itens liquidados  -> 'emitida'
     *  - ao menos 1 item liquidado  -> 'em_andamento'
     */
    function os_rastreio_sync_liquidacao(PDO $conn, $os_id, $usuario = 'sistema') {
        try {
            $st = $conn->prepare("SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN quantidade_liquidada >= quantidade THEN 1 ELSE 0 END) AS concluidos,
                    SUM(CASE WHEN quantidade_liquidada > 0 THEN 1 ELSE 0 END) AS com_liquidacao
                  FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
            $st->execute([$os_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'concluidos' => 0, 'com_liquidacao' => 0];

            $total = (int)$r['total']; $concl = (int)$r['concluidos']; $comLiq = (int)$r['com_liquidacao'];

            if ($total > 0 && $concl >= $total) {
                return os_rastreio_status_por_os($conn, $os_id, 'emitida', $usuario, 'O.S. totalmente liquidada');
            }
            if ($comLiq > 0) {
                return os_rastreio_status_por_os($conn, $os_id, 'em_andamento', $usuario, 'Ato(s) liquidado(s)');
            }
            return null;
        } catch (Throwable $e) {
            error_log('[os_rastreio_sync_liquidacao] ' . $e->getMessage());
            return null;
        }
    }

} // function_exists guard
