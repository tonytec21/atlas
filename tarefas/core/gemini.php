<?php
/**
 * Atlas · Tarefas — integração com a API Gemini (Google AI Studio).
 *
 * Três responsabilidades:
 *   1. Guardar a configuração (chave, modelo padrão, recursos ligados) na
 *      tabela `tarefas_ia_config`.
 *   2. Manter o catálogo de modelos em `tarefas_ia_modelos`, com cadastro e
 *      exclusão pelo próprio sistema — nada de lista fixa no código.
 *   3. Falar com o endpoint generateContent e registrar o uso.
 *
 * Sobre os modelos: em agosto de 2026 a linha 3.x é a ativa. A migração
 * semeia o catálogo com gemini-3.1-flash-lite, gemini-3.5-flash e
 * gemini-3.1-pro-preview (o identificador de API do Gemini 3.1 Pro), além
 * de gemini-3.6-flash e gemini-3.5-flash-lite. A família 2.5 foi deixada de
 * fora de propósito: o Google anunciou o desligamento dela para outubro de
 * 2026. Se algum identificador mudar, use o botão "Sincronizar" na tela de
 * configurações — ele lê a lista real da sua chave via ListModels.
 */

require_once __DIR__ . '/bootstrap.php';

define('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');

/* ================================================================== */
/* Configuração                                                       */
/* ================================================================== */

/**
 * Lê toda a configuração da IA como um array chave => valor.
 *
 * @return array
 */
function ia_config()
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = array(
        'api_key'          => '',
        'modelo_padrao'    => 'gemini-3.5-flash',
        'ativo'            => '0',
        'temperatura'      => '0.4',
        'max_tokens'       => '2048',
        'timeout'          => '60',
        'contexto_cartorio' => '',
    );

    if (!db_tem_tabela('tarefas_ia_config')) {
        return $cfg;
    }

    try {
        foreach (db_all('SELECT chave, valor FROM tarefas_ia_config') as $l) {
            $cfg[$l['chave']] = (string) $l['valor'];
        }
    } catch (Exception $e) {
        error_log('[tarefas] ia_config: ' . $e->getMessage());
    }

    return $cfg;
}

/** Grava um item de configuração. */
function ia_config_salvar($chave, $valor)
{
    if (!db_tem_tabela('tarefas_ia_config')) {
        return false;
    }
    db_exec(
        'INSERT INTO tarefas_ia_config (chave, valor, atualizado_em)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()',
        array((string) $chave, (string) $valor)
    );
    return true;
}

/** A IA está configurada e ligada? */
function ia_disponivel()
{
    $cfg = ia_config();
    return $cfg['ativo'] === '1' && trim($cfg['api_key']) !== '';
}

/**
 * Mascara a chave para exibição na tela — nunca devolvemos a chave inteira
 * para o navegador.
 */
function ia_chave_mascarada()
{
    $cfg = ia_config();
    $k = trim($cfg['api_key']);
    if ($k === '') {
        return '';
    }
    if (mb_strlen($k) <= 10) {
        return str_repeat('•', mb_strlen($k));
    }
    return mb_substr($k, 0, 6) . str_repeat('•', 12) . mb_substr($k, -4);
}

/* ================================================================== */
/* Catálogo de modelos                                                */
/* ================================================================== */

/**
 * Lista os modelos cadastrados.
 *
 * @param bool $somenteAtivos filtra os desativados
 * @return array
 */
function ia_modelos($somenteAtivos = false)
{
    if (!db_tem_tabela('tarefas_ia_modelos')) {
        return array();
    }
    $sql = 'SELECT id, modelo_id, apelido, descricao, ativo, favorito, origem,
                   suporta_arquivos, criado_em
              FROM tarefas_ia_modelos';
    if ($somenteAtivos) {
        $sql .= ' WHERE ativo = 1';
    }
    $sql .= ' ORDER BY favorito DESC, apelido ASC';
    return db_all($sql);
}

/** Busca um modelo pelo identificador de API. */
function ia_modelo_por_id($modeloId)
{
    if (!db_tem_tabela('tarefas_ia_modelos')) {
        return null;
    }
    return db_one('SELECT * FROM tarefas_ia_modelos WHERE modelo_id = ? LIMIT 1', array((string) $modeloId));
}

/**
 * Cadastra ou atualiza um modelo.
 *
 * @return array{success:bool,error?:string,id?:int}
 */
function ia_modelo_salvar($modeloId, $apelido, $descricao = '', $ativo = 1, $suportaArquivos = 1, $origem = 'manual')
{
    if (!db_tem_tabela('tarefas_ia_modelos')) {
        return array('success' => false, 'error' => 'Execute a migração antes de cadastrar modelos.');
    }

    $modeloId = trim((string) $modeloId);
    if ($modeloId === '') {
        return array('success' => false, 'error' => 'Informe o identificador do modelo.');
    }
    // Aceita "models/gemini-x" e normaliza para "gemini-x".
    if (strpos($modeloId, 'models/') === 0) {
        $modeloId = substr($modeloId, 7);
    }
    if (!preg_match('/^[a-zA-Z0-9._\-]{3,120}$/', $modeloId)) {
        return array('success' => false, 'error' => 'Identificador inválido. Use apenas letras, números, ponto, hífen e sublinhado.');
    }

    $apelido = trim((string) $apelido);
    if ($apelido === '') {
        $apelido = $modeloId;
    }

    db_exec(
        'INSERT INTO tarefas_ia_modelos
            (modelo_id, apelido, descricao, ativo, suporta_arquivos, origem, criado_em, atualizado_em)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            apelido = VALUES(apelido),
            descricao = VALUES(descricao),
            ativo = VALUES(ativo),
            suporta_arquivos = VALUES(suporta_arquivos),
            atualizado_em = NOW()',
        array($modeloId, mb_substr($apelido, 0, 120), mb_substr((string) $descricao, 0, 400),
              $ativo ? 1 : 0, $suportaArquivos ? 1 : 0, mb_substr((string) $origem, 0, 20))
    );

    return array('success' => true, 'modelo_id' => $modeloId);
}

/**
 * Remove um modelo do catálogo.
 *
 * Trava de segurança: não deixa excluir o modelo que está definido como
 * padrão, para o sistema nunca ficar sem um modelo utilizável.
 */
function ia_modelo_excluir($modeloId)
{
    if (!db_tem_tabela('tarefas_ia_modelos')) {
        return array('success' => false, 'error' => 'Catálogo de modelos indisponível.');
    }

    $modeloId = trim((string) $modeloId);
    $cfg = ia_config();

    if ($modeloId === $cfg['modelo_padrao']) {
        return array(
            'success' => false,
            'error'   => 'Este é o modelo padrão. Escolha outro modelo padrão antes de excluí-lo.',
        );
    }

    $n = db_exec('DELETE FROM tarefas_ia_modelos WHERE modelo_id = ?', array($modeloId));
    if ($n === 0) {
        return array('success' => false, 'error' => 'Modelo não encontrado.');
    }
    return array('success' => true);
}

/** Marca/desmarca um modelo como favorito. */
function ia_modelo_favorito($modeloId, $favorito)
{
    if (!db_tem_tabela('tarefas_ia_modelos')) {
        return false;
    }
    db_exec(
        'UPDATE tarefas_ia_modelos SET favorito = ?, atualizado_em = NOW() WHERE modelo_id = ?',
        array($favorito ? 1 : 0, (string) $modeloId)
    );
    return true;
}

/**
 * Consulta a API pela lista real de modelos disponíveis para a chave
 * configurada. É o jeito seguro de descobrir se algum identificador foi
 * descontinuado ou renomeado.
 *
 * @return array{success:bool,error?:string,modelos?:array}
 */
function ia_sincronizar_modelos()
{
    $cfg = ia_config();
    $chave = trim($cfg['api_key']);
    if ($chave === '') {
        return array('success' => false, 'error' => 'Cadastre a chave da API antes de sincronizar.');
    }

    $resp = gemini_http('GET', GEMINI_BASE_URL . '/models?pageSize=200&key=' . urlencode($chave), null, 45);
    if (!$resp['success']) {
        return $resp;
    }

    $dados = json_decode($resp['corpo'], true);
    if (!is_array($dados) || empty($dados['models'])) {
        return array('success' => false, 'error' => 'A API não retornou nenhum modelo.');
    }

    $encontrados = array();
    foreach ($dados['models'] as $m) {
        $nome = isset($m['name']) ? (string) $m['name'] : '';
        if (strpos($nome, 'models/') === 0) {
            $nome = substr($nome, 7);
        }
        if ($nome === '') {
            continue;
        }

        // Só interessam modelos de geração de texto.
        $metodos = isset($m['supportedGenerationMethods']) ? (array) $m['supportedGenerationMethods'] : array();
        if ($metodos && !in_array('generateContent', $metodos, true)) {
            continue;
        }
        // Ignora modelos de imagem, áudio, embedding e afins.
        if (preg_match('/(embedding|aqa|imagen|image|tts|audio|live|veo)/i', $nome)) {
            continue;
        }

        $encontrados[$nome] = array(
            'modelo_id' => $nome,
            'apelido'   => isset($m['displayName']) && $m['displayName'] !== '' ? (string) $m['displayName'] : $nome,
            'descricao' => isset($m['description']) ? mb_substr((string) $m['description'], 0, 400) : '',
        );
    }

    if (!$encontrados) {
        return array('success' => false, 'error' => 'Nenhum modelo de texto disponível para esta chave.');
    }

    $novos = 0;
    $atualizados = 0;
    $existentes = array();
    foreach (ia_modelos() as $m) {
        $existentes[$m['modelo_id']] = true;
    }

    foreach ($encontrados as $id => $m) {
        if (isset($existentes[$id])) {
            db_exec(
                'UPDATE tarefas_ia_modelos
                    SET descricao = ?, disponivel_api = 1, atualizado_em = NOW()
                  WHERE modelo_id = ?',
                array($m['descricao'], $id)
            );
            $atualizados++;
        } else {
            ia_modelo_salvar($id, $m['apelido'], $m['descricao'], 1, 1, 'api');
            db_exec('UPDATE tarefas_ia_modelos SET disponivel_api = 1 WHERE modelo_id = ?', array($id));
            $novos++;
        }
    }

    // Marca como indisponíveis os que a chave não enxerga mais — sem excluir,
    // para não perder configurações do usuário.
    $lista = array_keys($encontrados);
    $marcadores = implode(',', array_fill(0, count($lista), '?'));
    db_exec(
        'UPDATE tarefas_ia_modelos SET disponivel_api = 0
          WHERE modelo_id NOT IN (' . $marcadores . ')',
        $lista
    );

    return array(
        'success'      => true,
        'novos'        => $novos,
        'atualizados'  => $atualizados,
        'total_api'    => count($encontrados),
    );
}

/* ================================================================== */
/* Chamada HTTP                                                       */
/* ================================================================== */

/**
 * Requisição HTTP com cURL, com fallback para streams quando o cURL não
 * estiver habilitado no XAMPP.
 *
 * @return array{success:bool,codigo?:int,corpo?:string,error?:string}
 */
function gemini_http($metodo, $url, $corpoJson = null, $timeout = 60)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_TIMEOUT        => (int) $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json; charset=utf-8'),
        ));
        if ($corpoJson !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpoJson);
        }

        $corpo  = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro   = curl_error($ch);
        curl_close($ch);

        if ($corpo === false) {
            // Causa comum no XAMPP: cacert.pem não configurado no php.ini.
            return array(
                'success' => false,
                'error'   => 'Falha de conexão com a API Gemini: ' . $erro
                           . '. Se a mensagem citar certificado SSL, configure curl.cainfo no php.ini.',
            );
        }
        return gemini_avaliar_resposta($codigo, (string) $corpo);
    }

    // Fallback sem cURL.
    $opcoes = array('http' => array(
        'method'        => $metodo,
        'header'        => "Content-Type: application/json; charset=utf-8\r\n",
        'timeout'       => (int) $timeout,
        'ignore_errors' => true,
    ));
    if ($corpoJson !== null) {
        $opcoes['http']['content'] = $corpoJson;
    }

    $corpo = @file_get_contents($url, false, stream_context_create($opcoes));
    if ($corpo === false) {
        return array('success' => false, 'error' => 'Não foi possível contatar a API Gemini (cURL desabilitado e allow_url_fopen indisponível).');
    }

    $codigo = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $codigo = (int) $m[1];
    }
    return gemini_avaliar_resposta($codigo, (string) $corpo);
}

/** Traduz códigos de erro da API em mensagens compreensíveis. */
function gemini_avaliar_resposta($codigo, $corpo)
{
    if ($codigo >= 200 && $codigo < 300) {
        return array('success' => true, 'codigo' => $codigo, 'corpo' => $corpo);
    }

    $json = json_decode($corpo, true);
    $msg = '';
    if (is_array($json) && isset($json['error']['message'])) {
        $msg = (string) $json['error']['message'];
    }

    $amigavel = 'A API Gemini respondeu com erro ' . $codigo . '.';
    if ($codigo === 400 && stripos($msg, 'API key') !== false) {
        $amigavel = 'Chave da API inválida. Verifique em Configurações da IA.';
    } elseif ($codigo === 403) {
        $amigavel = 'Acesso negado pela API. Confirme se a chave tem permissão para este modelo.';
    } elseif ($codigo === 404) {
        $amigavel = 'Modelo não encontrado ou descontinuado. Use "Sincronizar modelos" para atualizar o catálogo.';
    } elseif ($codigo === 429) {
        $amigavel = 'Limite de uso da API atingido. Aguarde alguns instantes e tente de novo.';
    } elseif ($codigo >= 500) {
        $amigavel = 'A API Gemini está indisponível no momento. Tente novamente em instantes.';
    }

    if ($msg !== '') {
        $amigavel .= ' (' . mb_substr($msg, 0, 240) . ')';
    }

    return array('success' => false, 'codigo' => $codigo, 'error' => $amigavel);
}

/* ================================================================== */
/* Geração                                                            */
/* ================================================================== */

/**
 * Envia um prompt ao Gemini e devolve o texto gerado.
 *
 * @param string      $prompt        pergunta/instrução do usuário
 * @param array       $opcoes        modelo, instrucao_sistema, json, temperatura, arquivos
 * @return array{success:bool,texto?:string,error?:string,modelo?:string,tokens?:int}
 */
function gemini_gerar($prompt, array $opcoes = array())
{
    $cfg = ia_config();

    if (trim($cfg['api_key']) === '') {
        return array('success' => false, 'error' => 'A integração com o Gemini ainda não foi configurada. Cadastre a chave da API em Configurações da IA.');
    }
    if ($cfg['ativo'] !== '1') {
        return array('success' => false, 'error' => 'A integração com o Gemini está desativada. Ative-a em Configurações da IA.');
    }

    $modelo = isset($opcoes['modelo']) && $opcoes['modelo'] !== ''
        ? (string) $opcoes['modelo']
        : $cfg['modelo_padrao'];

    // Só usa modelos que estejam no catálogo e ativos.
    $reg = ia_modelo_por_id($modelo);
    if ($reg && (int) $reg['ativo'] !== 1) {
        return array('success' => false, 'error' => 'O modelo "' . $modelo . '" está desativado no catálogo.');
    }

    $partes = array(array('text' => (string) $prompt));

    // Anexos enviados junto (PDF/imagem) em base64.
    if (!empty($opcoes['arquivos']) && is_array($opcoes['arquivos'])) {
        foreach ($opcoes['arquivos'] as $arq) {
            if (empty($arq['caminho']) || !is_file($arq['caminho'])) {
                continue;
            }
            if (filesize($arq['caminho']) > 18 * 1024 * 1024) {
                continue; // acima disso o envio inline não é aceito
            }
            $conteudo = @file_get_contents($arq['caminho']);
            if ($conteudo === false) {
                continue;
            }
            $partes[] = array('inline_data' => array(
                'mime_type' => isset($arq['mime']) ? $arq['mime'] : 'application/pdf',
                'data'      => base64_encode($conteudo),
            ));
        }
    }

    $corpo = array(
        'contents' => array(array('role' => 'user', 'parts' => $partes)),
        'generationConfig' => array(
            'temperature'     => isset($opcoes['temperatura']) ? (float) $opcoes['temperatura'] : (float) $cfg['temperatura'],
            'maxOutputTokens' => isset($opcoes['max_tokens']) ? (int) $opcoes['max_tokens'] : (int) $cfg['max_tokens'],
        ),
    );

    if (!empty($opcoes['json'])) {
        $corpo['generationConfig']['responseMimeType'] = 'application/json';
    }

    $instrucao = isset($opcoes['instrucao_sistema']) ? trim((string) $opcoes['instrucao_sistema']) : '';
    $contexto  = trim($cfg['contexto_cartorio']);
    if ($contexto !== '') {
        $instrucao = $instrucao === '' ? $contexto : $instrucao . "\n\nContexto da serventia:\n" . $contexto;
    }
    if ($instrucao !== '') {
        $corpo['systemInstruction'] = array('parts' => array(array('text' => $instrucao)));
    }

    $url = GEMINI_BASE_URL . '/models/' . rawurlencode($modelo) . ':generateContent?key=' . urlencode(trim($cfg['api_key']));
    $inicio = microtime(true);

    $resp = gemini_http('POST', $url, json_encode($corpo, JSON_UNESCAPED_UNICODE), (int) $cfg['timeout']);

    if (!$resp['success']) {
        ia_registrar_uso($modelo, isset($opcoes['recurso']) ? $opcoes['recurso'] : 'geral', 0, false, $resp['error']);
        return $resp;
    }

    $dados = json_decode($resp['corpo'], true);
    $texto = '';

    if (isset($dados['candidates'][0]['content']['parts'])) {
        foreach ($dados['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) {
                $texto .= $p['text'];
            }
        }
    }

    if (trim($texto) === '') {
        $motivo = isset($dados['candidates'][0]['finishReason']) ? (string) $dados['candidates'][0]['finishReason'] : '';
        if ($motivo === 'SAFETY') {
            $erro = 'O conteúdo foi bloqueado pelos filtros de segurança do Gemini.';
        } elseif ($motivo === 'MAX_TOKENS') {
            $erro = 'A resposta excedeu o limite de tokens. Aumente o limite em Configurações da IA.';
        } else {
            $erro = 'O modelo não retornou texto' . ($motivo !== '' ? ' (' . $motivo . ')' : '') . '.';
        }
        ia_registrar_uso($modelo, isset($opcoes['recurso']) ? $opcoes['recurso'] : 'geral', 0, false, $erro);
        return array('success' => false, 'error' => $erro);
    }

    $tokens = isset($dados['usageMetadata']['totalTokenCount']) ? (int) $dados['usageMetadata']['totalTokenCount'] : 0;
    $ms = (int) round((microtime(true) - $inicio) * 1000);

    ia_registrar_uso($modelo, isset($opcoes['recurso']) ? $opcoes['recurso'] : 'geral', $tokens, true, '', $ms);

    return array(
        'success' => true,
        'texto'   => trim($texto),
        'modelo'  => $modelo,
        'tokens'  => $tokens,
        'ms'      => $ms,
    );
}

/**
 * Igual ao gemini_gerar, mas garante que a resposta seja um array vindo de
 * JSON. Modelos às vezes embrulham o JSON em blocos ```json.
 *
 * @return array{success:bool,dados?:array,error?:string}
 */
function gemini_gerar_json($prompt, array $opcoes = array())
{
    $opcoes['json'] = true;
    $r = gemini_gerar($prompt, $opcoes);
    if (!$r['success']) {
        return $r;
    }

    $texto = trim($r['texto']);
    $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto);
    $texto = preg_replace('/\s*```$/', '', (string) $texto);

    $dados = json_decode((string) $texto, true);
    if (!is_array($dados)) {
        // Última tentativa: recorta do primeiro { ou [ até o fechamento.
        if (preg_match('/[\{\[].*[\}\]]/s', (string) $texto, $m)) {
            $dados = json_decode($m[0], true);
        }
    }

    if (!is_array($dados)) {
        return array('success' => false, 'error' => 'A resposta do modelo não veio em JSON válido.', 'bruto' => $r['texto']);
    }

    return array('success' => true, 'dados' => $dados, 'modelo' => $r['modelo'], 'tokens' => $r['tokens']);
}

/* ================================================================== */
/* Registro de uso                                                    */
/* ================================================================== */

function ia_registrar_uso($modelo, $recurso, $tokens, $sucesso, $erro = '', $ms = 0)
{
    if (!db_tem_tabela('tarefas_ia_uso')) {
        return;
    }
    try {
        $u = usuario_atual();
        db_exec(
            'INSERT INTO tarefas_ia_uso (modelo, recurso, tokens, sucesso, erro, duracao_ms, usuario, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            array(
                mb_substr((string) $modelo, 0, 120),
                mb_substr((string) $recurso, 0, 60),
                (int) $tokens,
                $sucesso ? 1 : 0,
                mb_substr((string) $erro, 0, 400),
                (int) $ms,
                mb_substr($u['usuario'], 0, 100),
            )
        );
    } catch (Exception $e) {
        error_log('[tarefas] ia_uso: ' . $e->getMessage());
    }
}

/** Resumo de consumo dos últimos 30 dias. */
function ia_estatisticas()
{
    if (!db_tem_tabela('tarefas_ia_uso')) {
        return array('chamadas' => 0, 'tokens' => 0, 'erros' => 0, 'por_recurso' => array());
    }
    $tot = db_one(
        'SELECT COUNT(*) AS chamadas, COALESCE(SUM(tokens),0) AS tokens,
                SUM(CASE WHEN sucesso = 0 THEN 1 ELSE 0 END) AS erros
           FROM tarefas_ia_uso
          WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    $porRecurso = db_all(
        'SELECT recurso, COUNT(*) AS n, COALESCE(SUM(tokens),0) AS tokens
           FROM tarefas_ia_uso
          WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY recurso ORDER BY n DESC LIMIT 12'
    );
    return array(
        'chamadas'    => (int) $tot['chamadas'],
        'tokens'      => (int) $tot['tokens'],
        'erros'       => (int) $tot['erros'],
        'por_recurso' => $porRecurso,
    );
}
