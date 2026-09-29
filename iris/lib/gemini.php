<?php
/**
 * Atlas Iris — cliente da API Gemini (generateContent).
 *
 * - Chave enviada no cabeçalho x-goog-api-key (não aparece em logs de URL).
 * - Resolução de mídia por parte (ULTRA_HIGH para manuscritos) com recuo automático
 *   quando o modelo não aceita algum parâmetro (HTTP 400).
 * - Novas tentativas com espera em 429/500/502/503/504 e troca para o modelo reserva.
 * - Contabiliza tokens (entrada, saída, raciocínio) para as estatísticas de uso.
 */

/** Localiza um CA bundle (cacert.pem) para o cURL — necessário no XAMPP/Windows. */
function iris_cacert()
{
    $env = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($env && is_file($env)) return $env;
    foreach ([
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem',
        __DIR__ . '/../cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
    ] as $c) if (@is_file($c)) return $c;
    return null;
}

/** Requisição HTTP crua à API. @return array [code, json|null, erroCurl] */
function iris_gemini_http($metodo, $caminho, $apiKey, $payload = null, $timeout = 240)
{
    if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP não está habilitada.');
    $ch = curl_init(rtrim(IRIS_GEMINI_BASE, '/') . '/' . ltrim($caminho, '/'));
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
    ];
    if ($metodo === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $ca = iris_cacert();
    if (stripos(IRIS_GEMINI_BASE, 'https://') === 0) {
        if ($ca) { $opts[CURLOPT_SSL_VERIFYPEER] = true; $opts[CURLOPT_CAINFO] = $ca; }
        else { $opts[CURLOPT_SSL_VERIFYPEER] = false; $opts[CURLOPT_SSL_VERIFYHOST] = 0; }
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return [0, null, $err ?: 'sem resposta'];
    return [$code, json_decode($resp, true), ''];
}

/** Traduz erros HTTP da API em mensagens claras. */
function iris_gemini_msg_erro($code, $j, $modelo)
{
    $msg = $j['error']['message'] ?? ('Erro HTTP ' . $code);
    if ($code === 400 && stripos($msg, 'API key') !== false) return 'Chave da API inválida.';
    if ($code === 401 || $code === 403) return 'Acesso negado (verifique a chave e as permissões da API).';
    if ($code === 404) return 'Modelo "' . $modelo . '" não encontrado. Ajuste o identificador em Configurar.';
    if ($code === 429) return 'Limite de uso da API atingido. Aguarde alguns instantes e tente de novo.';
    if ($code >= 500) return 'O serviço do Gemini está instável no momento (HTTP ' . $code . '). Tente novamente.';
    return $msg;
}

/**
 * Chamada genérica ao generateContent.
 *
 * @param array $partes lista de ['text'=>string] ou ['bytes'=>string,'mime'=>string,'res'=>'ultra'|'high'|'medium'|null]
 * @param array $op modelo, reserva, json(bool), schema(array|null), thinking('low'|'medium'|'high'|null),
 *                  resolucao(global: 'high'|'medium'|null), max_tokens, timeout, tentativas
 * @return array ['texto','truncado','finish','modelo','uso'=>['entrada','saida','pensamento'],'ms']
 */
function iris_gemini_gerar($apiKey, array $partes, array $op)
{
    if ($apiKey === '') throw new RuntimeException('Configure a chave da API do Gemini em "Configurar".');
    $modelo = $op['modelo'];
    $inicio = microtime(true);

    // Monta as partes no formato da API
    $apiParts = []; $temResParte = false;
    foreach ($partes as $p) {
        if (isset($p['text'])) { $apiParts[] = ['text' => $p['text']]; continue; }
        $part = ['inline_data' => ['mime_type' => $p['mime'], 'data' => base64_encode($p['bytes'])]];
        $lvl = ['ultra' => 'MEDIA_RESOLUTION_ULTRA_HIGH', 'high' => 'MEDIA_RESOLUTION_HIGH', 'medium' => 'MEDIA_RESOLUTION_MEDIUM'][$p['res'] ?? ''] ?? null;
        if ($lvl) { $part['media_resolution'] = ['level' => $lvl]; $temResParte = true; }
        $apiParts[] = $part;
    }

    // Configuração completa + recuos progressivos (aplicados cumulativamente em caso de HTTP 400)
    $gen = ['maxOutputTokens' => (int)($op['max_tokens'] ?? 65536)];
    if (!empty($op['thinking'])) $gen['thinkingConfig'] = ['thinkingLevel' => $op['thinking']];
    $resG = ['high' => 'MEDIA_RESOLUTION_HIGH', 'medium' => 'MEDIA_RESOLUTION_MEDIUM'][$op['resolucao'] ?? ''] ?? null;
    if ($resG) $gen['mediaResolution'] = $resG;
    if (!empty($op['json'])) {
        $gen['responseMimeType'] = 'application/json';
        if (!empty($op['schema'])) $gen['responseJsonSchema'] = $op['schema'];
    }
    $recuos = [];
    if ($temResParte) $recuos[] = function (&$g, &$parts) { foreach ($parts as &$pp) unset($pp['media_resolution']); };
    if (!empty($gen['responseJsonSchema'])) $recuos[] = function (&$g, &$parts) { unset($g['responseJsonSchema']); };
    if (isset($gen['thinkingConfig'])) $recuos[] = function (&$g, &$parts) { unset($g['thinkingConfig']); };
    if (isset($gen['mediaResolution'])) $recuos[] = function (&$g, &$parts) { unset($g['mediaResolution']); };
    $recuos[] = function (&$g, &$parts) { $g['maxOutputTokens'] = min($g['maxOutputTokens'], 8192); };

    $modelos = [$modelo];
    if (!empty($op['reserva']) && $op['reserva'] !== $modelo) $modelos[] = $op['reserva'];
    $tentativas = (int)($op['tentativas'] ?? 3);
    $ultimoErro = '';

    foreach ($modelos as $mod) {
        $g = $gen; $parts = $apiParts; $iRecuo = 0; $falhasTransitorias = 0;
        while (true) {
            $payload = ['contents' => [['role' => 'user', 'parts' => $parts]], 'generationConfig' => $g];
            [$code, $j, $cerr] = iris_gemini_http('POST', 'models/' . rawurlencode($mod) . ':generateContent', $apiKey, $payload, (int)($op['timeout'] ?? 240));

            if ($code === 400 && $iRecuo < count($recuos) && stripos($j['error']['message'] ?? '', 'API key') === false) {
                $ultimoErro = $j['error']['message'] ?? 'HTTP 400';
                $recuos[$iRecuo++]($g, $parts);
                continue;
            }
            $transitorio = ($code === 0 || $code === 429 || $code >= 500);
            if ($transitorio && ++$falhasTransitorias < $tentativas) {
                sleep(min(12, 2 * $falhasTransitorias * $falhasTransitorias)); // 2s, 8s
                continue;
            }
            if ($code === 0) { $ultimoErro = 'Falha de conexão com a API Gemini: ' . $cerr; break; }
            if ($code !== 200) {
                $ultimoErro = iris_gemini_msg_erro($code, $j, $mod);
                if ($transitorio || $code === 404) break;          // tenta o modelo reserva
                throw new RuntimeException('Gemini: ' . $ultimoErro);
            }
            if (isset($j['promptFeedback']['blockReason']))
                throw new RuntimeException('A extração foi bloqueada pela política do Gemini (' . $j['promptFeedback']['blockReason'] . ').');

            $cand = $j['candidates'][0] ?? [];
            $texto = '';
            foreach ($cand['content']['parts'] ?? [] as $p)
                if (isset($p['text']) && empty($p['thought'])) $texto .= $p['text'];
            $finish = $cand['finishReason'] ?? '';
            $u = $j['usageMetadata'] ?? [];
            $uso = ['entrada' => (int)($u['promptTokenCount'] ?? 0), 'saida' => (int)($u['candidatesTokenCount'] ?? 0),
                    'pensamento' => (int)($u['thoughtsTokenCount'] ?? 0)];

            if ($texto === '' && $finish === 'MAX_TOKENS')
                throw new RuntimeException('A página é extensa demais para uma única resposta. Use "Selecionar região" para extrair por partes.');
            if ($texto === '' && in_array($finish, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST'], true))
                throw new RuntimeException('O Gemini recusou a resposta (motivo: ' . $finish . ').');
            if ($texto === '') { $ultimoErro = 'A API não retornou texto' . ($finish ? ' (motivo: ' . $finish . ')' : ''); break; }

            return ['texto' => $texto, 'truncado' => $finish === 'MAX_TOKENS', 'finish' => $finish, 'modelo' => $mod,
                    'uso' => $uso, 'ms' => (int)round((microtime(true) - $inicio) * 1000)];
        }
    }
    throw new RuntimeException('Gemini: ' . ($ultimoErro ?: 'não foi possível processar a requisição.'));
}

/** Extrai um objeto JSON de uma resposta (tolera cercas ``` e texto ao redor). */
function iris_json_da_resposta($texto)
{
    $t = trim($texto);
    $t = preg_replace('~^```(?:json)?\s*|\s*```$~i', '', $t);
    $j = json_decode($t, true);
    if (is_array($j)) return $j;
    $a = strpos($t, '{'); $b = strrpos($t, '}');
    if ($a !== false && $b > $a) { $j = json_decode(substr($t, $a, $b - $a + 1), true); if (is_array($j)) return $j; }
    throw new RuntimeException('A resposta estruturada do modelo não é um JSON válido. Tente novamente ou use outro modelo.');
}

/** Lista os modelos disponíveis para a chave (para Configurar). */
function iris_gemini_listar_modelos($apiKey)
{
    [$code, $j, $cerr] = iris_gemini_http('GET', 'models?pageSize=200', $apiKey, null, 30);
    if ($code === 0) throw new RuntimeException('Falha de conexão com a API Gemini: ' . $cerr);
    if ($code !== 200) throw new RuntimeException('Gemini: ' . iris_gemini_msg_erro($code, $j, ''));
    $out = [];
    foreach ($j['models'] ?? [] as $m) {
        if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) continue;
        $id = preg_replace('~^models/~', '', $m['name'] ?? '');
        if ($id === '' || stripos($id, 'gemini') !== 0) continue;
        if (preg_match('~(image|tts|audio|embedding|live|robotics|computer-use)~i', $id)) continue;
        $out[] = ['identificador' => $id, 'rotulo' => $m['displayName'] ?? $id, 'descricao' => mb_substr($m['description'] ?? '', 0, 200)];
    }
    usort($out, function ($a, $b) { return strnatcasecmp($b['identificador'], $a['identificador']); });
    return $out;
}

/** Compatibilidade v1: OCR de um arquivo inteiro. */
function iris_gemini_ocr($apiKey, $modelo, $bytes, $mime, $prompt)
{
    $r = iris_gemini_gerar($apiKey, [['bytes' => $bytes, 'mime' => $mime, 'res' => null], ['text' => $prompt]],
                           ['modelo' => $modelo, 'thinking' => 'high', 'resolucao' => 'high']);
    return ['texto' => $r['texto'], 'truncado' => $r['truncado']];
}
