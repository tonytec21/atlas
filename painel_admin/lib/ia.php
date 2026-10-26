<?php
/**
 * painel_admin/lib/ia.php — Análise automática com IA (Google Gemini ou Anthropic Claude)
 * ---------------------------------------------------------------------------------------
 * - As chaves de API ficam criptografadas (AES-256-GCM) com uma chave-mestra
 *   guardada em painel_admin/seguranca/.masterkey (fora do banco).
 * - A lista de modelos vem da própria API de cada provedor, com a chave
 *   cadastrada: só aparecem os modelos que a chave realmente pode usar.
 * - Só indicadores agregados são enviados. Nomes de clientes, CPFs e
 *   parâmetros das operações nunca saem do servidor; os nomes dos
 *   funcionários podem ser trocados por apelidos (opção na configuração).
 */

require_once __DIR__ . '/consultas.php';

if (!defined('PA_GEMINI_BASE')) {
    $ep = __DIR__ . '/../seguranca/endpoints.php';   // opcional (testes/proxy corporativo)
    $e = is_file($ep) ? (include $ep) : [];
    define('PA_GEMINI_BASE', $e['gemini'] ?? 'https://generativelanguage.googleapis.com/v1beta');
    define('PA_CLAUDE_BASE', $e['claude'] ?? 'https://api.anthropic.com/v1');
}

/* ============================ Configuração ============================ */

function pa_cfg_estrutura(): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    pa_db()->exec("CREATE TABLE IF NOT EXISTS painel_config (
        chave VARCHAR(80) NOT NULL PRIMARY KEY,
        valor MEDIUMTEXT NULL,
        atualizado_por VARCHAR(100) NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pa_db()->exec("CREATE TABLE IF NOT EXISTS painel_ia_analises (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        criado_em DATETIME NOT NULL,
        usuario VARCHAR(100) NULL,
        automatica TINYINT(1) NOT NULL DEFAULT 0,
        provedor VARCHAR(20) NOT NULL,
        modelo VARCHAR(120) NOT NULL,
        periodo_ini DATE NOT NULL,
        periodo_fim DATE NOT NULL,
        foco VARCHAR(40) NOT NULL DEFAULT 'geral',
        texto MEDIUMTEXT NULL,
        tokens_entrada INT NOT NULL DEFAULT 0,
        tokens_saida INT NOT NULL DEFAULT 0,
        duracao_ms INT NOT NULL DEFAULT 0,
        ok TINYINT(1) NOT NULL DEFAULT 1,
        erro VARCHAR(500) NULL,
        KEY ix_criado (criado_em),
        KEY ix_periodo (periodo_ini, periodo_fim)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ok = true;
}

function pa_cfg_get(string $k, $padrao = null)
{
    pa_cfg_estrutura();
    $v = pa_val('SELECT valor FROM painel_config WHERE chave = ?', [$k]);
    return $v === null ? $padrao : $v;
}

function pa_cfg_set(string $k, $v): void
{
    pa_cfg_estrutura();
    $st = pa_db()->prepare('INSERT INTO painel_config (chave, valor, atualizado_por, atualizado_em) VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por), atualizado_em = NOW()');
    $st->execute([$k, $v, pa_usuario()['usuario']]);
}

function pa_ia_padrao(): array
{
    return [
        'ativa' => 0,
        'provedor' => 'gemini',
        'modelo_gemini' => '',
        'modelo_claude' => '',
        'automatica' => 0,            // gerar sozinha ao abrir o painel (no máximo 1x por dia)
        'anonimizar' => 0,            // troca o nome dos funcionários por apelidos
        'instrucoes' => '',
        'modelos_gemini' => [],       // cache da última consulta à API
        'modelos_claude' => [],
        'modelos_em' => ['gemini' => null, 'claude' => null],
    ];
}

function pa_ia_config(): array
{
    $c = pa_ia_padrao();
    $j = json_decode((string) pa_cfg_get('ia', ''), true);
    if (is_array($j)) {
        foreach ($c as $k => $v) {
            if (array_key_exists($k, $j)) {
                $c[$k] = $j[$k];
            }
        }
    }
    return $c;
}

function pa_ia_config_gravar(array $c): void
{
    $limpo = [];
    foreach (pa_ia_padrao() as $k => $v) {
        $limpo[$k] = $c[$k] ?? $v;
    }
    pa_cfg_set('ia', json_encode($limpo, JSON_UNESCAPED_UNICODE));
}

/** Configuração para a interface (as chaves aparecem só mascaradas). */
function pa_ia_config_publica(): array
{
    $c = pa_ia_config();
    foreach (['gemini', 'claude'] as $p) {
        $k = pa_ia_chave($p);
        $c['chave_' . $p] = $k === '' ? '' : (substr($k, 0, 4) . str_repeat('•', 10) . substr($k, -4));
        $c['tem_chave_' . $p] = $k !== '';
    }
    return $c;
}

/* ============================ Chaves (criptografia) ============================ */

function pa_dir_seg(): string
{
    $d = __DIR__ . '/../seguranca';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
    }
    if (!is_file($d . '/.htaccess')) {
        @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file($d . '/index.html')) {
        @file_put_contents($d . '/index.html', '');
    }
    return $d;
}

function pa_chave_mestra(): string
{
    $f = pa_dir_seg() . '/.masterkey';
    if (!is_file($f)) {
        @file_put_contents($f, base64_encode(random_bytes(32)));
        @chmod($f, 0600);
    }
    return base64_decode(trim((string) file_get_contents($f)));
}

function pa_cifrar(string $txt): string
{
    if ($txt === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($txt, 'aes-256-gcm', pa_chave_mestra(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}

function pa_decifrar(string $b): string
{
    if ($b === '') {
        return '';
    }
    $raw = base64_decode($b, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', pa_chave_mestra(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? '' : $pt;
}

function pa_ia_chave(string $provedor): string
{
    return pa_decifrar((string) pa_cfg_get('ia_api_key_' . $provedor, ''));
}

function pa_ia_chave_gravar(string $provedor, string $chave): void
{
    pa_cfg_set('ia_api_key_' . $provedor, $chave === '' ? '' : pa_cifrar($chave));
}

/* ============================ HTTP ============================ */

function pa_http(string $metodo, string $url, array $headers, ?array $corpo, int $timeout = 60): array
{
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json'], $headers);
    $op = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $h,
    ];
    if ($corpo !== null) {
        $op[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
        $op[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $op);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [0, null, $err ?: 'Falha de conexão'];
    }
    $j = json_decode((string) $resp, true);
    if ($code >= 400) {
        $msg = $j['error']['message'] ?? ($j['error']['type'] ?? null) ?? ('HTTP ' . $code);
        return [$code, $j, $msg];
    }
    return [$code, $j, null];
}

/* ============================ Modelos disponíveis ============================ */

/**
 * Consulta a API do provedor e devolve só os modelos de texto que a chave pode usar.
 * @return array{0: array, 1: ?string} [lista, erro]
 */
function pa_ia_listar_modelos(string $provedor, ?string $chave = null): array
{
    $chave = $chave ?? pa_ia_chave($provedor);
    if ($chave === '') {
        return [[], 'Cadastre a chave da API primeiro.'];
    }
    $lista = [];
    if ($provedor === 'gemini') {
        $pagina = '';
        for ($i = 0; $i < 5; $i++) {
            [$code, $j, $err] = pa_http('GET', PA_GEMINI_BASE . '/models?pageSize=1000' . ($pagina ? '&pageToken=' . urlencode($pagina) : ''), ['x-goog-api-key: ' . $chave], null, 30);
            if ($err) {
                return [[], 'Gemini: ' . $err];
            }
            foreach ((array) ($j['models'] ?? []) as $m) {
                $id = preg_replace('#^models/#', '', (string) ($m['name'] ?? ''));
                $metodos = (array) ($m['supportedGenerationMethods'] ?? []);
                if ($id === '' || !in_array('generateContent', $metodos, true) || stripos($id, 'gemini') === false) {
                    continue;
                }
                if (preg_match('/embedding|tts|image|imagen|audio|live|robotics|computer-use|aqa|veo|learnlm|native|banana/i', $id)) {
                    continue;
                }
                $lista[] = ['id' => $id, 'nome' => (string) ($m['displayName'] ?? $id), 'descricao' => mb_substr((string) ($m['description'] ?? ''), 0, 160),
                            'entrada' => (int) ($m['inputTokenLimit'] ?? 0), 'saida' => (int) ($m['outputTokenLimit'] ?? 0)];
            }
            $pagina = (string) ($j['nextPageToken'] ?? '');
            if ($pagina === '') {
                break;
            }
        }
        // Mais novos primeiro (pelo número da versão no nome), "pro" antes de "flash"
        usort($lista, function ($a, $b) {
            $va = preg_match('/gemini-(\d+(?:\.\d+)?)/', $a['id'], $x) ? (float) $x[1] : 0;
            $vb = preg_match('/gemini-(\d+(?:\.\d+)?)/', $b['id'], $y) ? (float) $y[1] : 0;
            $tier = function ($id) { return preg_match('/lite/i', $id) ? 3 : (preg_match('/flash/i', $id) ? 2 : (preg_match('/pro/i', $id) ? 1 : 4)); };
            return $vb <=> $va ?: ($tier($a['id']) <=> $tier($b['id']) ?: strcmp($a['id'], $b['id']));
        });
    } else {
        $depois = '';
        for ($i = 0; $i < 5; $i++) {
            [$code, $j, $err] = pa_http('GET', PA_CLAUDE_BASE . '/models?limit=1000' . ($depois ? '&after_id=' . urlencode($depois) : ''),
                ['x-api-key: ' . $chave, 'anthropic-version: 2023-06-01'], null, 30);
            if ($err) {
                return [[], 'Claude: ' . $err];
            }
            foreach ((array) ($j['data'] ?? []) as $m) {
                if (($m['type'] ?? 'model') !== 'model' || empty($m['id'])) {
                    continue;
                }
                $lista[] = ['id' => $m['id'], 'nome' => (string) ($m['display_name'] ?? $m['id']), 'descricao' => '', 'criado' => $m['created_at'] ?? null];
            }
            if (empty($j['has_more']) || empty($j['last_id'])) {
                break;
            }
            $depois = (string) $j['last_id'];
        }
        usort($lista, function ($a, $b) {
            return strcmp((string) ($b['criado'] ?? ''), (string) ($a['criado'] ?? ''));
        });
    }
    return [$lista, $lista ? null : 'A API não devolveu nenhum modelo de texto para esta chave.'];
}

/* ============================ Dados enviados à IA ============================ */

/** Indicadores agregados do período (sem dados de clientes). */
function pa_ia_contexto(array $per, bool $anonimizar): array
{
    $vg = pa_visao_geral($per);
    $fat = $vg['faturamento'];
    $eq = pa_equipe($per);
    $serv = pa_serventia($per);
    $aces = pa_acessos($per);
    $apelidos = [];
    $nome = function (string $login, string $nomeReal) use ($anonimizar, &$apelidos) {
        if (!$anonimizar) {
            return $nomeReal;
        }
        if (!isset($apelidos[$login])) {
            $apelidos[$login] = 'Colaborador ' . chr(65 + count($apelidos));
        }
        return $apelidos[$login];
    };
    $equipe = [];
    foreach ($eq['membros'] as $m) {
        $equipe[] = [
            'nome' => $nome($m['usuario'], $m['nome']), 'cargo' => $m['cargo'], 'pontuacao' => $m['pontuacao'],
            'os_criadas' => $m['os_criadas'], 'os_canceladas' => $m['os_canceladas'], 'atos_liquidados' => $m['atos_qtd'], 'valor_atos' => $m['atos_valor'],
            'recebimentos' => $m['recebimentos'], 'valor_recebido' => $m['recebido'], 'pedidos_certidao' => $m['pedidos'],
            'tarefas_concluidas' => $m['tarefas_concluidas'], 'tarefas_no_prazo' => $m['tarefas_no_prazo'], 'tarefas_abertas' => $m['tarefas_abertas'],
            'tarefas_atrasadas' => $m['tarefas_atrasadas'], 'operacoes_registradas' => $m['operacoes'], 'exclusoes_estornos' => $m['exclusoes'],
            'falhas' => $m['falhas'], 'acessos_negados' => $m['negados'], 'operacoes_fora_expediente' => $m['fora_expediente'],
            'entradas_no_sistema' => $m['logins'], 'dias_ativos' => $m['dias_ativos'], 'verificacao_2_etapas' => $m['tfa'] ? 'sim' : 'não',
        ];
    }
    $mapa = $eq['mapa_calor'];
    $horas = array_fill(0, 24, 0);
    $dias = array_fill(0, 7, 0);
    foreach ($mapa as $d => $hs) {
        foreach ($hs as $h => $n) {
            $horas[$h] += $n;
            $dias[$d] += $n;
        }
    }
    $nomesDias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
    $alertas = array_map(function ($a) use ($anonimizar) {
        $t = $a['texto'];
        return ['nivel' => $a['nivel'], 'titulo' => $a['titulo'], 'texto' => $anonimizar ? preg_replace('/"[^"]+"/', '"(usuário)"', $t) : $t];
    }, $vg['alertas']);
    if ($anonimizar) {
        // Retira nomes reais que possam ter ficado nos textos dos alertas
        foreach (pa_funcionarios()['por_login'] as $l => $r) {
            foreach ($alertas as &$al) {
                $al['texto'] = str_replace($r['nome_completo'], $apelidos[$l] ?? 'um colaborador', $al['texto']);
            }
            unset($al);
        }
    }
    $aud = $vg['auditoria'];
    return [
        'serventia' => ['cidade' => $serv['serventia']['cidade']],
        'periodo' => ['inicio' => $per['ini'], 'fim' => $per['fim'], 'dias' => $per['dias'], 'periodo_anterior' => $per['ant_ini'] . ' a ' . $per['ant_fim']],
        'financeiro' => [
            'periodo' => $fat['totais'], 'periodo_anterior' => $fat['anterior'], 'variacao_percentual' => $fat['variacao'],
            'formas_de_pagamento' => array_map(function ($f) { return ['forma' => $f['f'], 'qtd' => (int) $f['n'], 'valor' => $f['v']]; }, $fat['formas']),
            'atos_mais_rentaveis' => array_map(function ($a) { return ['ato' => $a['ato'], 'descricao' => $a['descricao'], 'qtd' => $a['qtd'], 'valor' => $a['v'], 'emolumentos' => $a['e']]; }, array_slice($fat['atos_top'], 0, 8)),
            'recebido_por_dia_da_semana' => array_combine($nomesDias, $fat['dia_semana']),
            'ultimos_12_meses_recebido' => $fat['historico_meses'],
            'deposito_previo' => ['saldo_total' => $fat['deposito']['saldo_total'], 'os_com_saldo' => $fat['deposito']['os_com_saldo'],
                                  'os_paradas_mais_30_dias' => $fat['deposito']['paradas_qtd'], 'valor_parado' => $fat['deposito']['paradas_valor']],
        ],
        'servicos' => ['ordens_de_servico' => $serv['os'], 'tarefas' => array_diff_key($serv['tarefas'], ['por_responsavel' => 1]), 'grupos_de_atos' => $serv['grupos_atos']],
        'equipe' => $equipe,
        'atividade' => ['por_hora_do_dia' => $horas, 'por_dia_da_semana' => array_combine($nomesDias, $dias),
                        'uso_por_modulo' => array_map(function ($u) { return [$u['rotulo'] => $u['n']]; }, $serv['uso_modulos'])],
        'auditoria' => ['operacoes' => $aud['operacoes'], 'criticas' => $aud['criticas'], 'falhas' => $aud['falhas'], 'acessos_negados' => $aud['negados'],
                        'fora_do_expediente' => $aud['fora'], 'registros_alterados' => $aud['dados'], 'telas_abertas' => $aud['telas'],
                        'por_categoria' => array_map(function ($c) { return [$c['rotulo'] => $c['n']]; }, $aud['categorias'] ?? [])],
        'acessos' => $aces['totais'] + ['expediente' => $aces['expediente']['inicio'] . '–' . $aces['expediente']['fim']],
        'saude_calculada' => $vg['saude'],
        'alertas_automaticos' => $alertas,
        '_apelidos' => $apelidos,
    ];
}

function pa_ia_prompt_sistema(string $extra): string
{
    $s = "Você é um consultor sênior de gestão de serventias extrajudiciais (cartórios) no Brasil, com experiência em "
       . "controladoria, auditoria interna, produtividade de equipes e conformidade com as normas do CNJ e das corregedorias. "
       . "Analise os indicadores do sistema Atlas recebidos em JSON e escreva um relatório para o Oficial/administrador da serventia.\n\n"
       . "Regras:\n"
       . "- Escreva em português do Brasil, em Markdown, direto ao ponto, com números concretos tirados dos dados (R\$ no padrão brasileiro: R\$ 1.234,56).\n"
       . "- Use exatamente estas seções, nesta ordem, com títulos de nível 2 (##):\n"
       . "  1. Resumo executivo (3 a 5 frases, com a nota geral da serventia de 0 a 10 e o porquê)\n"
       . "  2. Faturamento e finanças (tendência, comparação com o período anterior, formas de pagamento, depósito prévio, composição emolumentos × fundos)\n"
       . "  3. Desempenho da equipe (destaques, quem precisa de apoio, equilíbrio da carga; seja justo e profissional, sem expor ninguém de forma depreciativa)\n"
       . "  4. Desempenho da serventia (prazos, tarefas, pedidos de certidão, gargalos, horários de pico)\n"
       . "  5. Auditoria, segurança e riscos (exclusões, estornos, falhas, acessos negados, operações fora do expediente, 2FA, integridade)\n"
       . "  6. Recomendações priorizadas (lista numerada: ação concreta, responsável sugerido pelo cargo, prazo e impacto esperado)\n"
       . "- Use uma tabela Markdown quando comparar pessoas ou períodos.\n"
       . "- Não invente dados. Se um indicador estiver zerado ou ausente, diga que não há dados suficientes em vez de supor.\n"
       . "- Considere que valores zerados na auditoria podem significar que a trilha foi instalada há pouco tempo.\n"
       . "- Não repita o JSON nem explique como fez a análise.";
    if (trim($extra) !== '') {
        $s .= "\n\nOrientações adicionais da serventia:\n" . trim($extra);
    }
    return $s;
}

/**
 * Gera a análise do período com o provedor e o modelo configurados.
 * @return array{ok:bool, id?:int, texto?:string, erro?:string}
 */
function pa_ia_analisar(array $per, bool $automatica = false, string $foco = 'geral'): array
{
    pa_cfg_estrutura();
    $cfg = pa_ia_config();
    if (empty($cfg['ativa'])) {
        return ['ok' => false, 'erro' => 'A análise com IA está desligada. Ative em Configurações › Análise com IA.'];
    }
    $prov = $cfg['provedor'] === 'claude' ? 'claude' : 'gemini';
    $modelo = (string) ($cfg['modelo_' . $prov] ?? '');
    $chave = pa_ia_chave($prov);
    if ($chave === '' || $modelo === '') {
        return ['ok' => false, 'erro' => 'Configure a chave da API e escolha o modelo em Configurações › Análise com IA.'];
    }
    $ctx = pa_ia_contexto($per, !empty($cfg['anonimizar']));
    $apelidos = $ctx['_apelidos'];
    unset($ctx['_apelidos']);
    $focos = [
        'geral' => 'Faça a análise completa.',
        'financeiro' => 'Dê ênfase especial a faturamento, finanças e depósito prévio, mas mantenha todas as seções.',
        'equipe' => 'Dê ênfase especial ao desempenho individual e coletivo da equipe, mas mantenha todas as seções.',
        'auditoria' => 'Dê ênfase especial à auditoria, segurança e riscos, mas mantenha todas as seções.',
    ];
    $msg = ($focos[$foco] ?? $focos['geral']) . "\n\nIndicadores (JSON):\n" . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $sistema = pa_ia_prompt_sistema((string) $cfg['instrucoes']);
    $t0 = microtime(true);
    $texto = '';
    $tin = 0;
    $tout = 0;
    $erro = null;
    $r = $prov === 'gemini' ? pa_ia_gemini($chave, $modelo, $sistema, $msg) : pa_ia_claude($chave, $modelo, $sistema, $msg);
    [$texto, $tin, $tout, $erro, $parcial] = [$r['texto'], $r['tin'], $r['tout'], $r['erro'], $r['parcial']];
    if ($texto !== '' && $parcial) {
        $texto .= "\n\n> *A resposta atingiu o limite de tamanho do modelo e pode ter ficado incompleta. Gere de novo com uma ênfase específica para um texto mais curto.*";
    }
    // Devolve os nomes reais no lugar dos apelidos (a anonimização vale só para o que saiu do servidor)
    if ($texto !== '' && $apelidos) {
        $troca = [];
        foreach ($apelidos as $login => $ap) {
            $troca[$ap] = pa_nome_de($login);
        }
        uksort($troca, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        $texto = strtr($texto, $troca);
    }
    $dur = (int) round((microtime(true) - $t0) * 1000);
    $st = pa_db()->prepare('INSERT INTO painel_ia_analises (criado_em, usuario, automatica, provedor, modelo, periodo_ini, periodo_fim, foco, texto, tokens_entrada, tokens_saida, duracao_ms, ok, erro)
                            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([pa_usuario()['usuario'], $automatica ? 1 : 0, $prov, $modelo, $per['ini'], $per['fim'], $foco, $texto ?: null, $tin, $tout, $dur, $erro ? 0 : 1, $erro ? mb_substr($erro, 0, 500) : null]);
    $id = (int) pa_db()->lastInsertId();
    if ($erro) {
        return ['ok' => false, 'erro' => $erro, 'id' => $id];
    }
    return ['ok' => true, 'id' => $id, 'texto' => $texto, 'provedor' => $prov, 'modelo' => $modelo, 'tokens_entrada' => $tin, 'tokens_saida' => $tout, 'duracao_ms' => $dur];
}

/**
 * Claude: os modelos novos (4.5/5.x) pensam antes de responder (raciocínio adaptativo) e esse
 * raciocínio divide o max_tokens com a resposta. Por isso: limite folgado, esforço "medium"
 * (output_config.effort) e, se ainda assim faltar espaço, nova tentativa com esforço "low"
 * e limite maior. Modelos antigos que não aceitam output_config recebem o pedido sem ele.
 */
function pa_ia_claude(string $chave, string $modelo, string $sistema, string $msg): array
{
    $max = 32000;
    $esforco = 'medium';
    $tin = 0;
    $tout = 0;
    $ultimoErro = null;
    for ($tent = 0; $tent < 6; $tent++) {
        $corpo = ['model' => $modelo, 'max_tokens' => $max, 'system' => $sistema, 'messages' => [['role' => 'user', 'content' => $msg]]];
        if ($esforco !== null) {
            $corpo['output_config'] = ['effort' => $esforco];
        }
        [$code, $j, $err] = pa_http('POST', PA_CLAUDE_BASE . '/messages', ['x-api-key: ' . $chave, 'anthropic-version: 2023-06-01'], $corpo, 300);
        if ($err) {
            $ultimoErro = $err;
            if ($code === 400 && $esforco !== null && preg_match('/output_config|effort/i', $err)) {
                $esforco = null;            // modelo antigo: sem controle de esforço
                continue;
            }
            if ($code === 400 && preg_match('/max_tokens/i', $err) && $max > 4096) {
                $max = $max > 8192 ? 8192 : 4096;   // modelo com limite de saída menor
                continue;
            }
            return ['texto' => '', 'tin' => $tin, 'tout' => $tout, 'erro' => 'Claude: ' . $err, 'parcial' => false];
        }
        $texto = '';
        foreach ((array) ($j['content'] ?? []) as $p) {
            if (($p['type'] ?? '') === 'text') {
                $texto .= $p['text'];
            }
        }
        $tin += (int) ($j['usage']['input_tokens'] ?? 0);
        $tout += (int) ($j['usage']['output_tokens'] ?? 0);
        $parada = (string) ($j['stop_reason'] ?? '');
        if (trim($texto) !== '') {
            return ['texto' => $texto, 'tin' => $tin, 'tout' => $tout, 'erro' => null, 'parcial' => $parada === 'max_tokens'];
        }
        if ($parada === 'max_tokens') {
            // O raciocínio consumiu todo o limite: pensa menos e dá mais espaço
            if ($esforco !== null && $esforco !== 'low') {
                $esforco = 'low';
            }
            if ($max < 64000) {
                $max = min(64000, $max * 2);
            } elseif ($esforco === null || $esforco === 'low') {
                break;
            }
            $ultimoErro = 'o modelo usou todo o limite de tokens raciocinando, sem escrever a resposta';
            continue;
        }
        if ($parada === 'refusal') {
            return ['texto' => '', 'tin' => $tin, 'tout' => $tout, 'erro' => 'O modelo recusou o pedido.', 'parcial' => false];
        }
        $ultimoErro = 'o modelo não devolveu texto (' . ($parada ?: 'sem motivo informado') . ')';
        break;
    }
    return ['texto' => '', 'tin' => $tin, 'tout' => $tout, 'erro' => 'Claude: ' . ($ultimoErro ?: 'sem resposta') . '. Tente outro modelo em Configurações.', 'parcial' => false];
}

/**
 * Gemini: os modelos 2.5 e 3 também pensam antes de responder, e o raciocínio conta no
 * maxOutputTokens. Limite folgado e, se faltar espaço, nova tentativa com raciocínio
 * reduzido (thinkingLevel nos modelos 3; thinkingBudget nos 2.5).
 */
function pa_ia_gemini(string $chave, string $modelo, string $sistema, string $msg): array
{
    $max = 32768;
    $pensar = null;   // null = padrão do modelo
    $tin = 0;
    $tout = 0;
    $ultimoErro = null;
    for ($tent = 0; $tent < 6; $tent++) {
        $gc = ['maxOutputTokens' => $max];
        if ($pensar !== null) {
            $gc['thinkingConfig'] = $pensar;
        }
        [$code, $j, $err] = pa_http('POST', PA_GEMINI_BASE . '/models/' . rawurlencode($modelo) . ':generateContent', ['x-goog-api-key: ' . $chave], [
            'systemInstruction' => ['parts' => [['text' => $sistema]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $msg]]]],
            'generationConfig' => $gc,
        ], 300);
        if ($err) {
            $ultimoErro = $err;
            if ($code === 400 && $pensar !== null && preg_match('/thinking/i', $err)) {
                $pensar = isset($pensar['thinkingLevel']) ? ['thinkingBudget' => 2048] : false;
                if ($pensar === false) {
                    break;
                }
                continue;
            }
            if ($code === 400 && preg_match('/max_?output_?tokens/i', $err) && $max > 8192) {
                $max = 8192;
                continue;
            }
            return ['texto' => '', 'tin' => $tin, 'tout' => $tout, 'erro' => 'Gemini: ' . $err, 'parcial' => false];
        }
        $texto = '';
        foreach ((array) ($j['candidates'][0]['content']['parts'] ?? []) as $p) {
            if (empty($p['thought']) && isset($p['text'])) {
                $texto .= $p['text'];
            }
        }
        $tin += (int) ($j['usageMetadata']['promptTokenCount'] ?? 0);
        $tout += (int) ($j['usageMetadata']['candidatesTokenCount'] ?? 0) + (int) ($j['usageMetadata']['thoughtsTokenCount'] ?? 0);
        $fim = (string) ($j['candidates'][0]['finishReason'] ?? ($j['promptFeedback']['blockReason'] ?? ''));
        if (trim($texto) !== '') {
            return ['texto' => $texto, 'tin' => $tin, 'tout' => $tout, 'erro' => null, 'parcial' => $fim === 'MAX_TOKENS'];
        }
        if ($fim === 'MAX_TOKENS') {
            $ultimoErro = 'o modelo usou todo o limite de tokens raciocinando, sem escrever a resposta';
            if ($pensar === null) {
                $pensar = ['thinkingLevel' => 'low'];
                continue;
            }
            if (isset($pensar['thinkingLevel'])) {
                $pensar = ['thinkingBudget' => 2048];
                continue;
            }
            break;
        }
        $ultimoErro = 'o modelo não devolveu texto (' . ($fim ?: 'sem motivo informado') . ')';
        break;
    }
    return ['texto' => '', 'tin' => $tin, 'tout' => $tout, 'erro' => 'Gemini: ' . ($ultimoErro ?: 'sem resposta') . '. Tente outro modelo em Configurações.', 'parcial' => false];
}

function pa_ia_historico(int $limite = 30): array
{
    pa_cfg_estrutura();
    return pa_all("SELECT id, criado_em, usuario, automatica, provedor, modelo, periodo_ini, periodo_fim, foco, ok, erro, tokens_entrada, tokens_saida, duracao_ms,
                          LEFT(texto, 260) previa FROM painel_ia_analises ORDER BY id DESC LIMIT " . (int) $limite);
}

function pa_ia_ultima(array $per): ?array
{
    pa_cfg_estrutura();
    return pa_one('SELECT * FROM painel_ia_analises WHERE ok = 1 AND periodo_ini = ? AND periodo_fim = ? ORDER BY id DESC LIMIT 1', [$per['ini'], $per['fim']])
        ?: pa_one('SELECT * FROM painel_ia_analises WHERE ok = 1 ORDER BY id DESC LIMIT 1');
}

/** A análise automática do dia já foi feita para este período? */
function pa_ia_precisa_automatica(array $per): bool
{
    $cfg = pa_ia_config();
    if (empty($cfg['ativa']) || empty($cfg['automatica'])) {
        return false;
    }
    pa_cfg_estrutura();
    return !(int) pa_val('SELECT COUNT(*) FROM painel_ia_analises WHERE periodo_ini = ? AND periodo_fim = ? AND DATE(criado_em) = CURDATE() AND (ok = 1 OR automatica = 1)', [$per['ini'], $per['fim']]);
}
