<?php
/**
 * =====================================================================
 * nfse_transporte.php — Transporte HTTP direto com a SEFIN Nacional
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-transporte-resiliente
 *
 * Por que existir, se o SDK já fala com a SEFIN?
 *
 * 1. DIAGNÓSTICO. O SDK usa Guzzle, que embrulha a falha numa mensagem
 *    resumida e trunca o corpo da resposta. Com isso, um 503 do IIS
 *    ("Service Unavailable", servidor fora do ar) e um 500 da aplicação
 *    chegam ao log com a mesma cara de "erro genérico", quando são
 *    coisas completamente diferentes. Aqui a resposta é capturada
 *    inteira: status, cabeçalhos e corpo.
 *
 * 2. RETENTATIVA. Falhas 5xx e de rede no Ambiente Nacional são
 *    frequentes e passageiras. Repetir o MESMO envelope depois de uma
 *    pausa curta resolve a maior parte delas sem gerar rejeição, sem
 *    queimar número de DPS e sem trabalho manual.
 *
 * 3. ANTIDUPLICIDADE. Esta é a parte crítica. Um 503 do IIS é devolvido
 *    antes de a requisição chegar à aplicação — a DPS não foi
 *    processada, e reemitir é seguro. Já um 500 é uma exceção DENTRO da
 *    aplicação, que pode ter ocorrido DEPOIS de a NFS-e ter sido
 *    gravada do lado deles. Reemitir nesse caso, com um número de DPS
 *    novo, produziria duas notas para o mesmo fato gerador.
 *    O procedimento correto é consultar a DPS pelo seu Id antes de
 *    tentar de novo: se ela já gerou NFS-e, recupera-se a chave em vez
 *    de emitir outra.
 * =====================================================================
 */

require_once __DIR__ . '/nfse_lib.php';

/** Tentativas de envio antes de desistir (a 1ª não é retentativa). */
if (!defined('NFSE_TENTATIVAS')) {
    define('NFSE_TENTATIVAS', 3);
}

/** Pausa base entre tentativas, em segundos (cresce a cada rodada). */
if (!defined('NFSE_ESPERA_BASE')) {
    define('NFSE_ESPERA_BASE', 2);
}

/**
 * URL base da SEFIN conforme o ambiente configurado.
 */
function nfse_sefin_base(array $cfg): string
{
    return ((string) ($cfg['ambiente'] ?? '2') === '1')
        ? 'https://sefin.nfse.gov.br/SefinNacional'
        : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
}

/**
 * Requisição HTTP à SEFIN com o certificado A1, devolvendo a resposta
 * crua e completa.
 *
 * @param  string     $metodo  GET ou POST
 * @param  string     $caminho ex.: 'nfse', 'dps/DPS3512...'
 * @param  array|null $json    corpo da requisição (POST)
 * @param  int        $timeout segundos até desistir da resposta
 * @return array{status:int, body:string, headers:string[], erro:string, errno:int, ms:int}
 */
function nfse_http_sefin(string $metodo, string $caminho, ?array $json, ?array $cfg = null, int $timeout = 90): array
{
    $cfg = $cfg ?: nfse_config(true);
    $ctx = nfse_context($cfg);

    $tmp = tempnam(sys_get_temp_dir(), 'nfse_tx_');
    file_put_contents($tmp, $ctx->certificateContent);

    $cabecalhos = [];
    $ch = curl_init();

    $opts = [
        CURLOPT_URL            => rtrim(nfse_sefin_base($cfg), '/') . '/' . ltrim($caminho, '/'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLCERTTYPE    => 'P12',
        CURLOPT_SSLCERT        => $tmp,
        CURLOPT_SSLCERTPASSWD  => (string) $ctx->certificatePassword,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => min(30, max(5, (int) ($timeout / 3))),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$cabecalhos) {
            $t = trim($linha);
            if ($t !== '') { $cabecalhos[] = $t; }
            return strlen($linha);
        },
    ];

    if (strtoupper($metodo) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);

    $ini  = microtime(true);
    $body = curl_exec($ch);
    $ms   = (int) round((microtime(true) - $ini) * 1000);

    $r = [
        'status'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'body'    => ($body === false) ? '' : (string) $body,
        'headers' => $cabecalhos,
        'erro'    => curl_error($ch),
        'errno'   => curl_errno($ch),
        'ms'      => $ms,
    ];

    curl_close($ch);
    @unlink($tmp);

    return $r;
}

/**
 * A falha é passageira (vale insistir) ou definitiva (vale rejeitar)?
 *
 * Passageiras: qualquer 5xx, 408 (timeout), 429 (excesso de requisições)
 * e os erros de rede do cURL (DNS, conexão recusada, resposta vazia).
 * Definitivas: 4xx com corpo estruturado — aí o problema é o conteúdo
 * da DPS, e insistir só repetiria a mesma recusa.
 */
function nfse_falha_transitoria(array $r): bool
{
    if ($r['errno'] !== 0) {
        // 6 DNS, 7 conexão, 28 timeout, 35 SSL, 52 resposta vazia, 56 recv
        return in_array($r['errno'], [5, 6, 7, 28, 35, 52, 55, 56], true);
    }

    return $r['status'] >= 500 || in_array($r['status'], [408, 429], true);
}

/**
 * Resumo legível de uma resposta, para gravar em nfse_notas.mensagem.
 * O corpo é limitado para não estourar a coluna, mas o suficiente para
 * distinguir 503 do IIS de 500 da aplicação e de uma rejeição 4xx.
 */
function nfse_resumir_resposta(array $r): string
{
    if ($r['errno'] !== 0) {
        return 'Falha de rede (cURL ' . $r['errno'] . '): ' . $r['erro'];
    }

    $corpo = trim($r['body']);

    // A página de erro do IIS vem em HTML; extrai só o essencial.
    if ($corpo !== '' && stripos($corpo, '<html') !== false) {
        if (preg_match('~<TITLE>(.*?)</TITLE>~is', $corpo, $m)) {
            $corpo = trim(strip_tags($m[1]));
        } else {
            $corpo = trim(preg_replace('/\s+/', ' ', strip_tags($corpo)));
        }
        $corpo = '[HTML do servidor] ' . $corpo;
    }

    if (function_exists('mb_substr')) {
        $corpo = mb_substr($corpo, 0, 1500, 'UTF-8');
    }

    return 'HTTP ' . $r['status'] . ($corpo !== '' ? ' — ' . $corpo : '');
}

/**
 * Monta o envelope da DPS: XML pelo builder do SDK, assinatura em
 * infDPS, GZIP e Base64. Devolve também o XML assinado, que passa a ser
 * guardado em nfse_notas.xml_dps — sem ele não há como reenviar o mesmo
 * documento nem provar depois o que foi transmitido.
 *
 * @return array{xml:string, payload:string}
 */
function nfse_assinar_dps(array $cfg, array $montado): array
{
    nfse_autoload();

    $ctx     = nfse_context($cfg);
    $builder = new \Nfse\Xml\DpsXmlBuilder;
    $cert    = \Nfse\Signer\Certificate::fromContent($ctx->certificateContent, (string) $ctx->certificatePassword);
    $signer  = new \Nfse\Signer\XmlSigner($cert);

    $xml = $signer->sign($builder->build($montado['dps']), 'infDPS');

    return ['xml' => $xml, 'payload' => base64_encode(gzencode($xml))];
}

/**
 * Transmite o envelope, insistindo enquanto a falha for passageira.
 *
 * @return array{
 *   ok:bool, status:int, tentativas:int, transitoria:bool,
 *   mensagem:string, nfse_xml:?string, bruto:array
 * }
 */
function nfse_transmitir_dps(array $cfg, string $payloadB64, int $tentativas = NFSE_TENTATIVAS): array
{
    $r = ['status' => 0, 'body' => '', 'headers' => [], 'erro' => 'não executado', 'errno' => 0, 'ms' => 0];

    for ($i = 1; $i <= max(1, $tentativas); $i++) {
        $r = nfse_http_sefin('POST', 'nfse', ['dpsXmlGZipB64' => $payloadB64], $cfg);

        if ($r['status'] === 200) {
            $dec = json_decode($r['body'], true);

            if (is_array($dec) && !empty($dec['erros'])) {
                return [
                    'ok' => false, 'status' => 200, 'tentativas' => $i, 'transitoria' => false,
                    'mensagem' => 'Rejeição: ' . json_encode($dec['erros'], JSON_UNESCAPED_UNICODE),
                    'nfse_xml' => null, 'bruto' => $r,
                ];
            }

            if (is_array($dec) && !empty($dec['nfseXmlGZipB64'])) {
                return [
                    'ok' => true, 'status' => 200, 'tentativas' => $i, 'transitoria' => false,
                    'mensagem' => '', 'nfse_xml' => gzdecode(base64_decode($dec['nfseXmlGZipB64'])),
                    'bruto' => $r,
                ];
            }

            return [
                'ok' => false, 'status' => 200, 'tentativas' => $i, 'transitoria' => false,
                'mensagem' => 'Resposta 200 sem XML da NFS-e: ' . nfse_resumir_resposta($r),
                'nfse_xml' => null, 'bruto' => $r,
            ];
        }

        if (!nfse_falha_transitoria($r)) {
            break; // 4xx: insistir não muda nada
        }

        if ($i < $tentativas) {
            nfse_log('emissao', 'Tentativa ' . $i . '/' . $tentativas . ' falhou (' . nfse_resumir_resposta($r) . '). Repetindo.', 'warn');
            sleep(NFSE_ESPERA_BASE * $i);   // 2s, 4s, 6s…
        }
    }

    return [
        'ok' => false,
        'status' => $r['status'],
        'tentativas' => max(1, $tentativas),
        'transitoria' => nfse_falha_transitoria($r),
        'mensagem' => nfse_resumir_resposta($r),
        'nfse_xml' => null,
        'bruto' => $r,
    ];
}

/**
 * Consulta uma DPS pelo Id e, se ela já tiver gerado NFS-e, devolve a
 * chave e o XML da nota.
 *
 * É a trava contra duplicidade: antes de reemitir algo que falhou com
 * 500, é preciso saber se a nota existe lá do outro lado.
 *
 * @return array{chave:string, xml:?string}|null  null = não há NFS-e para essa DPS
 */
function nfse_recuperar_por_dps(array $cfg, string $idDps, int $timeout = 25): ?array
{
    $r = nfse_http_sefin('GET', 'dps/' . rawurlencode($idDps), null, $cfg, $timeout);

    if ($r['status'] !== 200) {
        // 404 = a DPS não gerou nota; 5xx/rede = ambiente fora, nada a afirmar.
        // O status é devolvido pela referência para que o chamador saiba
        // distinguir "não existe" de "não deu para verificar".
        return null;
    }

    $dec   = json_decode($r['body'], true);
    $chave = is_array($dec) ? ($dec['chaveAcesso'] ?? null) : null;

    if (!$chave) {
        return null;
    }

    $xml = null;
    $rn  = nfse_http_sefin('GET', 'nfse/' . rawurlencode($chave), null, $cfg, $timeout);
    if ($rn['status'] === 200) {
        $dn = json_decode($rn['body'], true);
        if (is_array($dn) && !empty($dn['nfseXmlGZipB64'])) {
            $xml = gzdecode(base64_decode($dn['nfseXmlGZipB64']));
        }
    }

    return ['chave' => $chave, 'xml' => $xml];
}

/**
 * Varre as notas rejeitadas da O.S. e tenta recuperá-las pelo Id da DPS.
 * Se alguma já tiver virado NFS-e na SEFIN, ela é promovida a autorizada
 * e a emissão de uma nova DPS deixa de ser necessária.
 *
 * Só entram na varredura as rejeições que podem ter chegado à aplicação:
 * status HTTP desconhecido ou >= 500. Uma rejeição 4xx nunca gerou nota.
 *
 * @return array|null a nota recuperada, ou null se não houver
 */
function nfse_recuperar_rejeitadas_da_os(int $osId, ?array $cfg = null): ?array
{
    $cfg = $cfg ?: nfse_config(true);
    $pdo = nfse_pdo();

    $st = $pdo->prepare(
        "SELECT id, id_dps, http_status
           FROM nfse_notas
          WHERE ordem_servico_id = :os
            AND ambiente = :amb
            AND status = 'rejeitada'
            AND (http_status IS NULL OR http_status = 0 OR http_status >= 500)
          ORDER BY id DESC
          LIMIT 2"
    );
    $st->execute([':os' => $osId, ':amb' => $cfg['ambiente']]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        // Timeout curto: esta verificação roda antes de cada emissão e não
        // pode deixar o usuário esperando quando a SEFIN está fora do ar.
        try {
            $achado = nfse_recuperar_por_dps($cfg, (string) $n['id_dps'], 20);
        } catch (Throwable $e) {
            break;
        }

        if (!$achado) {
            continue;
        }

        $pdo->prepare(
            "UPDATE nfse_notas
                SET status='autorizada', chave_acesso=:c, xml_nfse=:x,
                    mensagem='Recuperada pela consulta da DPS após falha de transporte.'
              WHERE id=:id"
        )->execute([':c' => $achado['chave'], ':x' => $achado['xml'], ':id' => $n['id']]);

        nfse_log('emissao',
            'NFS-e recuperada pela consulta da DPS ' . $n['id_dps'] . '. Chave: ' . $achado['chave'] .
            ' — a nota já existia na SEFIN; nova emissão evitada.',
            'info', $osId, (int) $n['id']);

        $st2 = $pdo->prepare('SELECT * FROM nfse_notas WHERE id = ?');
        $st2->execute([$n['id']]);

        return $st2->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return null;
}
