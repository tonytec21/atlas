<?php
/**
 * painel_admin/lib/consultas.php — Auditoria, acessos, integridade, alertas
 * e a visão geral do Painel do Administrador.
 */

require_once __DIR__ . '/painel.php';

/* ============================ Trilha central: lista e detalhe ============================ */

function pa_aud_filtros(array $q, array $per, string $alias = ''): array
{
    $a = $alias ? $alias . '.' : '';
    $w = ["{$a}criado_em BETWEEN ? AND ?"];
    $p = [$per['ini_dt'], $per['fim_dt']];
    foreach (['modulo', 'categoria', 'severidade', 'resultado'] as $k) {
        if (!empty($q[$k])) {
            $vals = array_filter(explode(',', (string) $q[$k]), 'strlen');
            if ($vals) {
                $w[] = "{$a}$k IN (" . implode(',', array_fill(0, count($vals), '?')) . ')';
                $p = array_merge($p, $vals);
            }
        }
    }
    if (!empty($q['usuario'])) {
        $w[] = "{$a}usuario = ?";
        $p[] = (string) $q['usuario'];
    }
    if (!empty($q['fora'])) {
        $w[] = "{$a}fora_expediente = 1";
    }
    if (!empty($q['sem_navegacao'])) {
        $w[] = "{$a}categoria <> 'navegacao'";
    }
    if (isset($q['busca']) && trim((string) $q['busca']) !== '') {
        $b = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $q['busca'])) . '%';
        $w[] = "({$a}acao LIKE ? OR {$a}pagina LIKE ? OR {$a}alvo LIKE ? OR {$a}mensagem LIKE ? OR {$a}parametros LIKE ? OR {$a}ip LIKE ? OR {$a}nome LIKE ? OR {$a}dados_resumo LIKE ?)";
        array_push($p, $b, $b, $b, $b, $b, $b, $b, $b);
    }
    return [implode(' AND ', $w), $p];
}

/** Mostra textos de editor (HTML com acentos codificados) de forma legível; registros antigos inclusive. */
function pa_legivel($v)
{
    if (is_array($v)) {
        foreach ($v as $k => $x) {
            $v[$k] = pa_legivel($x);
        }
        return $v;
    }
    return is_string($v) ? aud_html_para_texto($v) : $v;
}

function pa_aud_linha(array $r): array
{
    $cats = aud_categorias();
    $c = $cats[$r['categoria']] ?? $cats['outros'];
    return [
        'id' => (int) $r['id'], 'criado_em' => substr($r['criado_em'], 0, 19), 'usuario' => $r['usuario'], 'nome' => $r['nome'] ?: pa_nome_de((string) $r['usuario']),
        'ip' => $r['ip'], 'modulo' => $r['modulo'], 'modulo_rotulo' => aud_modulo_rotulo($r['modulo']), 'pagina' => $r['pagina'], 'metodo' => $r['metodo'],
        'acao' => $r['acao'], 'categoria' => $r['categoria'], 'categoria_rotulo' => $c[0], 'icone' => $c[1], 'cor' => $c[2],
        'severidade' => $r['severidade'], 'resultado' => $r['resultado'], 'mensagem' => $r['mensagem'], 'alvo' => $r['alvo'],
        'dados_resumo' => $r['dados_resumo'], 'dados_linhas' => (int) $r['dados_linhas'], 'fora_expediente' => (int) $r['fora_expediente'],
        'selado' => !empty($r['hash']), 'duracao_ms' => $r['duracao_ms'] !== null ? (int) $r['duracao_ms'] : null,
    ];
}

function pa_auditoria_lista(array $q, array $per): array
{
    aud_estrutura();
    aud_selar();
    $pag = max(1, (int) ($q['pagina'] ?? 1));
    $tam = min(200, max(10, (int) ($q['tamanho'] ?? 50)));
    [$w, $p] = pa_aud_filtros($q, $per);
    $total = (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria WHERE $w", $p);
    $linhas = pa_all("SELECT id, criado_em, usuario, nome, ip, modulo, pagina, metodo, acao, categoria, severidade, resultado, mensagem, alvo,
                             dados_resumo, dados_linhas, fora_expediente, hash, duracao_ms
                        FROM atlas_auditoria WHERE $w ORDER BY criado_em DESC, id DESC LIMIT $tam OFFSET " . (($pag - 1) * $tam), $p);
    // Facetas (sem o próprio filtro de categoria, para os chips mostrarem o total de cada uma)
    $q2 = $q;
    unset($q2['categoria']);
    [$w2, $p2] = pa_aud_filtros($q2, $per);
    $fac = [];
    foreach (pa_all("SELECT categoria, COUNT(*) n FROM atlas_auditoria WHERE $w2 GROUP BY categoria", $p2) as $r) {
        $fac[$r['categoria']] = (int) $r['n'];
    }
    return [
        'total' => $total, 'pagina' => $pag, 'tamanho' => $tam, 'paginas' => (int) ceil($total / $tam),
        'linhas' => array_map('pa_aud_linha', $linhas), 'facetas' => $fac,
    ];
}

/** Detalhe de uma operação: parâmetros, registros alterados (antes/depois) e contexto da sessão. */
function pa_auditoria_evento(int $id): ?array
{
    aud_estrutura();
    $r = pa_one('SELECT * FROM atlas_auditoria WHERE id = ?', [$id]);
    if (!$r) {
        return null;
    }
    $ev = pa_aud_linha($r);
    $ev['user_agent'] = $r['user_agent'];
    $ev['dispositivo'] = pa_dispositivo((string) $r['user_agent']);
    $ev['status_http'] = $r['status_http'];
    $ev['parametros'] = $r['parametros'] ? pa_legivel(json_decode($r['parametros'], true)) : null;
    $ev['req_id'] = $r['req_id'];
    $ev['selo'] = ['seq' => $r['selo_seq'], 'hash' => $r['hash'], 'anterior' => $r['hash_anterior']];
    $ev['dados'] = pa_dados_da_requisicao($r['req_id']);
    $ev['contexto'] = [];
    if ($r['sessao']) {
        foreach (pa_all("SELECT id, criado_em, acao, categoria, resultado, modulo FROM atlas_auditoria
                          WHERE sessao = ? AND id <> ? AND criado_em BETWEEN DATE_SUB(?, INTERVAL 2 HOUR) AND DATE_ADD(?, INTERVAL 2 HOUR)
                          ORDER BY criado_em DESC LIMIT 12", [$r['sessao'], $id, $r['criado_em'], $r['criado_em']]) as $c) {
            $c['modulo_rotulo'] = aud_modulo_rotulo($c['modulo']);
            $c['criado_em'] = substr($c['criado_em'], 0, 19);
            $ev['contexto'][] = $c;
        }
    }
    return $ev;
}

function pa_dados_da_requisicao(string $req): array
{
    $out = [];
    foreach (pa_all('SELECT id, criado_em, banco, tabela, operacao, registro_id, campos, antes, depois, hash FROM atlas_auditoria_dados WHERE req_id = ? ORDER BY id LIMIT 100', [$req]) as $d) {
        $out[] = pa_dado_linha($d);
    }
    return $out;
}

function pa_dado_linha(array $d): array
{
    $antes = $d['antes'] !== null ? pa_legivel(json_decode($d['antes'], true)) : null;
    $depois = $d['depois'] !== null ? pa_legivel(json_decode($d['depois'], true)) : null;
    $campos = [];
    $chaves = array_unique(array_merge(array_keys((array) $antes), array_keys((array) $depois)));
    foreach ($chaves as $k) {
        $campos[] = ['campo' => $k, 'antes' => $antes[$k] ?? null, 'depois' => $depois[$k] ?? null,
                     'mudou' => $d['operacao'] !== 'U' || (($antes[$k] ?? null) !== ($depois[$k] ?? null))];
    }
    return [
        'id' => (int) $d['id'], 'criado_em' => substr($d['criado_em'], 0, 19), 'tabela' => $d['tabela'], 'banco' => $d['banco'],
        'modulo_rotulo' => aud_modulo_rotulo(aud_modulo_da_tabela($d['tabela'])),
        'operacao' => $d['operacao'], 'operacao_rotulo' => ['I' => 'Inclusão', 'U' => 'Alteração', 'D' => 'Exclusão'][$d['operacao']] ?? $d['operacao'],
        'registro_id' => $d['registro_id'], 'campos' => $campos, 'selado' => !empty($d['hash']),
        'usuario' => $d['usuario'] ?? null, 'req_id' => $d['req_id'] ?? null,
    ];
}

/** Toda a história de um registro (tabela + chave), de qualquer usuário. */
function pa_historico_registro(string $tabela, string $id): array
{
    aud_estrutura();
    $out = [];
    foreach (pa_all('SELECT * FROM atlas_auditoria_dados WHERE tabela = ? AND registro_id = ? ORDER BY id DESC LIMIT 100', [$tabela, $id]) as $d) {
        $l = pa_dado_linha($d);
        $l['nome'] = $d['usuario'] ? pa_nome_de((string) $d['usuario']) : ($d['req_id'] ? 'não identificado' : 'direto no banco (fora do Atlas)');
        $op = $d['req_id'] ? pa_one('SELECT id, acao, pagina FROM atlas_auditoria WHERE req_id = ? LIMIT 1', [$d['req_id']]) : null;
        $l['operacao_id'] = $op['id'] ?? null;
        $l['operacao_acao'] = $op['acao'] ?? null;
        $out[] = $l;
    }
    return $out;
}

/** Trilha de dados (alterações registro a registro), com filtros. */
function pa_dados_lista(array $q, array $per): array
{
    aud_estrutura();
    $w = ['criado_em BETWEEN ? AND ?'];
    $p = [$per['ini_dt'], $per['fim_dt']];
    if (!empty($q['tabela'])) {
        $w[] = 'tabela = ?';
        $p[] = $q['tabela'];
    }
    if (!empty($q['operacao'])) {
        $w[] = 'operacao = ?';
        $p[] = $q['operacao'];
    }
    if (!empty($q['usuario'])) {
        $w[] = 'usuario = ?';
        $p[] = $q['usuario'];
    }
    if (!empty($q['registro'])) {
        $w[] = 'registro_id = ?';
        $p[] = $q['registro'];
    }
    if (isset($q['busca']) && trim((string) $q['busca']) !== '') {
        $b = '%' . trim((string) $q['busca']) . '%';
        $w[] = '(antes LIKE ? OR depois LIKE ? OR tabela LIKE ? OR registro_id LIKE ?)';
        array_push($p, $b, $b, $b, $b);
    }
    $ws = implode(' AND ', $w);
    $pag = max(1, (int) ($q['pagina'] ?? 1));
    $tam = 40;
    $total = (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria_dados WHERE $ws", $p);
    $linhas = [];
    foreach (pa_all("SELECT * FROM atlas_auditoria_dados WHERE $ws ORDER BY id DESC LIMIT $tam OFFSET " . (($pag - 1) * $tam), $p) as $d) {
        $l = pa_dado_linha($d);
        $l['nome'] = $d['usuario'] ? pa_nome_de((string) $d['usuario']) : ($d['req_id'] ? 'não identificado' : 'direto no banco (fora do Atlas)');
        $linhas[] = $l;
    }
    $tabelas = pa_all("SELECT tabela, COUNT(*) n FROM atlas_auditoria_dados WHERE criado_em BETWEEN ? AND ? GROUP BY tabela ORDER BY n DESC LIMIT 60", [$per['ini_dt'], $per['fim_dt']]);
    return ['total' => $total, 'pagina' => $pag, 'paginas' => (int) ceil($total / $tam), 'linhas' => $linhas, 'tabelas' => $tabelas];
}

/** Telas abertas (navegação), com filtros. */
function pa_navegacao_lista(array $q, array $per): array
{
    aud_estrutura();
    $w = ['criado_em BETWEEN ? AND ?'];
    $p = [$per['ini_dt'], $per['fim_dt']];
    if (!empty($q['usuario'])) {
        $w[] = 'usuario = ?';
        $p[] = $q['usuario'];
    }
    if (!empty($q['modulo'])) {
        $w[] = 'modulo = ?';
        $p[] = $q['modulo'];
    }
    if (isset($q['busca']) && trim((string) $q['busca']) !== '') {
        $b = '%' . trim((string) $q['busca']) . '%';
        $w[] = '(pagina LIKE ? OR consulta LIKE ? OR ip LIKE ? OR nome LIKE ?)';
        array_push($p, $b, $b, $b, $b);
    }
    $ws = implode(' AND ', $w);
    $pag = max(1, (int) ($q['pagina'] ?? 1));
    $tam = 50;
    $total = (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria_navegacao WHERE $ws", $p);
    $linhas = [];
    foreach (pa_all("SELECT * FROM atlas_auditoria_navegacao WHERE $ws ORDER BY id DESC LIMIT $tam OFFSET " . (($pag - 1) * $tam), $p) as $r) {
        $d = pa_dispositivo((string) $r['user_agent']);
        $linhas[] = ['criado_em' => substr($r['criado_em'], 0, 19), 'usuario' => $r['usuario'], 'nome' => $r['nome'] ?: pa_nome_de((string) $r['usuario']), 'ip' => $r['ip'],
                     'modulo' => $r['modulo'], 'modulo_rotulo' => aud_modulo_rotulo($r['modulo']), 'pagina' => $r['pagina'], 'consulta' => $r['consulta'],
                     'fora_expediente' => (int) $r['fora_expediente'], 'dispositivo' => $d['tipo'] . ' · ' . $d['navegador']];
    }
    return ['total' => $total, 'pagina' => $pag, 'paginas' => (int) ceil($total / $tam), 'linhas' => $linhas];
}

/* ============================ Trilhas próprias dos módulos ============================ */

/**
 * Junta as trilhas que cada módulo já mantinha antes da auditoria central
 * (O.S., caixa, transferências, tarefas, pedidos, notas devolutivas, NFS-e,
 * arquivamento...). Útil para o histórico anterior à instalação.
 */
function pa_trilhas_modulos(array $q, array $per): array
{
    $fontes = [
        ['os_auditoria', "SELECT criado_em q, usuario u, 'os' m, acao a, resumo d, CONCAT('O.S. #', os_id) r FROM os_auditoria WHERE criado_em BETWEEN ? AND ?"],
        ['caixa_auditoria', "SELECT criado_em q, usuario u, 'caixa' m, acao a, CONCAT(descricao, IF(resultado <> 'sucesso', CONCAT(' [', resultado, ']'), '')) d, CONCAT('Caixa de ', funcionario_caixa, ' ', DATE_FORMAT(data_caixa,'%d/%m/%Y')) r FROM caixa_auditoria WHERE criado_em BETWEEN ? AND ?"],
        ['os_transferencias_saldo', "SELECT data_transferencia q, usuario u, 'os' m, 'Transferência de saldo' a, CONCAT(codigo, ': R$ ', REPLACE(FORMAT(valor,2),',',''), ' da O.S. #', os_origem_id, ' para #', os_destino_id, ' — ', motivo) d, codigo r FROM os_transferencias_saldo WHERE data_transferencia BETWEEN ? AND ?"],
        ['os_transferencias_saldo', "SELECT estornado_em q, estornado_por u, 'os' m, 'Estorno de transferência' a, CONCAT(codigo, ' — ', COALESCE(motivo_estorno,'')) d, codigo r FROM os_transferencias_saldo WHERE estornado_em BETWEEN ? AND ?"],
        ['os_liberacao_log', "SELECT criado_em q, usuario_nome u, 'os' m, 'Liberação de O.S. (atos liquidados desfeitos)' a, CONCAT(deletados_liquidados + deletados_manuais, ' liquidação(ões) removida(s), ', itens_atualizados, ' item(ns) reaberto(s)') d, CONCAT('O.S. #', ordem_servico_id) r FROM os_liberacao_log WHERE criado_em BETWEEN ? AND ?"],
        ['caixa_fechamento_desfeito', "SELECT criado_em q, administrador u, 'caixa' m, 'Fechamento de caixa desfeito' a, justificativa d, CONCAT('Caixa de ', funcionario_caixa, ' ', DATE_FORMAT(data_caixa,'%d/%m/%Y')) r FROM caixa_fechamento_desfeito WHERE criado_em BETWEEN ? AND ?"],
        ['caixa_saldo_inicial_log', "SELECT criado_em q, administrador u, 'caixa' m, 'Saldo inicial alterado' a, CONCAT('De R$ ', valor_anterior, ' para R$ ', valor_novo, IF(motivo IS NULL,'',CONCAT(' — ', motivo))) d, CONCAT('Caixa de ', funcionario_caixa, ' ', DATE_FORMAT(data_caixa,'%d/%m/%Y')) r FROM caixa_saldo_inicial_log WHERE criado_em BETWEEN ? AND ?"],
        ['tarefas_historico', "SELECT criado_em q, usuario u, 'tarefas' m, acao a, CONCAT(COALESCE(descricao,''), IF(valor_novo IS NULL,'',CONCAT(' → ', valor_novo))) d, CONCAT('Tarefa #', tarefa_id) r FROM tarefas_historico WHERE criado_em BETWEEN ? AND ?"],
        ['tarefas_pedido_log', "SELECT criado_em q, usuario u, 'tarefas' m, CONCAT('Pedido de tarefa: ', acao) a, CONCAT(COALESCE(de_valor,''), ' → ', COALESCE(para_valor,''), IF(observacao IS NULL,'',CONCAT(' — ', observacao))) d, CONCAT('Pedido #', tarefa_id) r FROM tarefas_pedido_log WHERE criado_em BETWEEN ? AND ?"],
        ['pedidos_certidao_status_log', "SELECT criado_em q, usuario u, 'pedidos_certidao' m, 'Status do pedido alterado' a, CONCAT(COALESCE(status_anterior,'—'), ' → ', novo_status, IF(observacao IS NULL,'',CONCAT(' — ', observacao))) d, CONCAT('Pedido #', pedido_id) r FROM pedidos_certidao_status_log WHERE criado_em BETWEEN ? AND ?"],
        ['logs_notas_devolutivas', "SELECT data_log q, usuario_log u, 'nota_devolutiva' m, CONCAT('Nota devolutiva: ', acao_log) a, titulo d, CONCAT('Nota ', numero) r FROM logs_notas_devolutivas WHERE data_log BETWEEN ? AND ?"],
        ['logs_ordens_de_servico', "SELECT COALESCE(data_edicao, data_criacao) q, COALESCE(editado_por, criado_por) u, 'os' m, 'Versão anterior de O.S. guardada (edição)' a, CONCAT('Total R$ ', total_os) d, CONCAT('O.S. #', ordem_de_servico_id) r FROM logs_ordens_de_servico WHERE COALESCE(data_edicao, data_criacao) BETWEEN ? AND ?"],
        ['logs_tarefas', "SELECT data_edicao q, atualizado_por u, 'tarefas' m, 'Tarefa editada (versão anterior guardada)' a, titulo d, CONCAT('Tarefa #', task_id) r FROM logs_tarefas WHERE data_edicao BETWEEN ? AND ?"],
        ['logs_sistema', "SELECT data_hora q, usuario u, 'sistema' m, acao a, CONCAT(tabela_afetada, ' #', id_registro) d, tabela_afetada r FROM logs_sistema WHERE data_hora BETWEEN ? AND ?"],
        ['nfse_log', "SELECT criado_em q, usuario u, 'os' m, CONCAT('NFS-e: ', evento) a, mensagem d, CONCAT('O.S. #', COALESCE(os_id,'—')) r FROM nfse_log WHERE criado_em BETWEEN ? AND ?"],
        ['logs_de_acesso', "SELECT data_hora q, usuario u, 'acesso' m, 'Entrada no sistema' a, CONCAT('IP ', ip) d, '' r FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ?"],
        ['api_log', "SELECT criado_em q, COALESCE(client_id,'API') u, 'os' m, CONCAT('API ', metodo, ' ', rota) a, CONCAT('HTTP ', COALESCE(status_http,'—'), IF(codigo_erro IS NULL,'',CONCAT(' — ', codigo_erro))) d, CONCAT('O.S. #', COALESCE(os_id,'—')) r FROM api_log WHERE criado_em BETWEEN ? AND ?"],
    ];
    $linhas = [];
    $porFonte = [];
    foreach ($fontes as [$t, $sql]) {
        if (!pa_tabela_existe($t)) {
            continue;
        }
        if (!empty($q['modulo_t']) && strpos($sql, "'" . $q['modulo_t'] . "' m") === false) {
            continue;
        }
        try {
            $rs = pa_all("SELECT * FROM ($sql) x ORDER BY q DESC LIMIT 1500", [$per['ini_dt'], $per['fim_dt']]);
        } catch (Throwable $e) {
            continue;
        }
        foreach ($rs as $r) {
            $r['f'] = $t;
            $linhas[] = $r;
        }
        $porFonte[$t] = ($porFonte[$t] ?? 0) + count($rs);
    }
    // Arquivamento: trilha em arquivos JSONL por mês
    $dir = __DIR__ . '/../../arquivamento/logs';
    if ((empty($q['modulo_t']) || $q['modulo_t'] === 'arquivamento') && is_dir($dir)) {
        foreach (glob($dir . '/auditoria-*.jsonl') ?: [] as $arq) {
            if (!preg_match('/auditoria-(\d{4}-\d{2})\.jsonl$/', $arq, $mm) || $mm[1] < substr($per['ini'], 0, 7) || $mm[1] > substr($per['fim'], 0, 7)) {
                continue;
            }
            foreach (@file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
                $j = json_decode($ln, true);
                if (!$j || empty($j['ts'])) {
                    continue;
                }
                $quando = date('Y-m-d H:i:s', strtotime($j['ts']));
                if ($quando < $per['ini_dt'] || $quando > $per['fim_dt']) {
                    continue;
                }
                $det = $j['detalhes'] ?? [];
                $linhas[] = ['q' => $quando, 'u' => $j['usuario'] ?? '', 'm' => 'arquivamento', 'a' => 'Arquivamento: ' . ($j['acao'] ?? ''),
                             'd' => is_array($det) && $det ? mb_substr(json_encode($det, JSON_UNESCAPED_UNICODE), 0, 300) : '', 'r' => 'Arquivo ' . ($j['alvo'] ?? ''), 'f' => 'arquivamento/logs'];
                $porFonte['arquivamento/logs'] = ($porFonte['arquivamento/logs'] ?? 0) + 1;
            }
        }
    }
    $busca = mb_strtolower(trim((string) ($q['busca'] ?? '')));
    $usuario = (string) ($q['usuario'] ?? '');
    $linhas = array_values(array_filter($linhas, function ($r) use ($busca, $usuario) {
        if ($usuario !== '' && pa_login_de($r['u']) !== $usuario) {
            return false;
        }
        if ($busca !== '' && mb_strpos(mb_strtolower(pa_utf8($r['a'] . ' ' . $r['d'] . ' ' . $r['r'] . ' ' . $r['u'])), $busca) === false) {
            return false;
        }
        return true;
    }));
    usort($linhas, function ($a, $b) {
        return strcmp((string) $b['q'], (string) $a['q']);
    });
    $total = count($linhas);
    $pag = max(1, (int) ($q['pagina'] ?? 1));
    $tam = 50;
    $saida = [];
    foreach (array_slice($linhas, ($pag - 1) * $tam, $tam) as $r) {
        $login = pa_login_de($r['u']);
        $saida[] = ['quando' => $r['q'], 'usuario' => $login, 'nome' => pa_nome_de($login), 'modulo' => $r['m'], 'modulo_rotulo' => aud_modulo_rotulo($r['m']),
                    'acao' => pa_utf8($r['a']), 'detalhe' => mb_substr(pa_utf8((string) $r['d']), 0, 400), 'referencia' => pa_utf8((string) $r['r']), 'fonte' => $r['f']];
    }
    return ['total' => $total, 'pagina' => $pag, 'paginas' => (int) ceil($total / $tam), 'linhas' => $saida, 'fontes' => $porFonte];
}

/* ============================ Acessos ============================ */

function pa_dispositivo(string $ua): array
{
    $nav = 'Outro';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $k => $v) {
        if (strpos($ua, $k) !== false) {
            $nav = $v;
            break;
        }
    }
    $so = 'Outro';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $k => $v) {
        if (strpos($ua, $k) !== false) {
            $so = $v;
            break;
        }
    }
    $tipo = preg_match('/Mobile|Android|iPhone/i', $ua) ? 'Celular' : (preg_match('/iPad|Tablet/i', $ua) ? 'Tablet' : 'Computador');
    return ['navegador' => $nav, 'sistema' => $so, 'tipo' => $ua === '' ? 'Desconhecido' : $tipo];
}

function pa_acessos(array $per): array
{
    aud_estrutura();
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    $cfg = aud_config();
    [$ex, $rot] = pa_eixo($per, 'data_hora');
    $logins = pa_all("SELECT $ex k, COUNT(*) v, COUNT(DISTINCT usuario) u FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ? GROUP BY k", [$ini, $fim]);
    [$ex2] = pa_eixo($per, 'criado_em');
    $falhas = pa_all("SELECT $ex2 k, COUNT(*) v FROM atlas_auditoria WHERE categoria = 'acesso' AND resultado = 'falha' AND criado_em BETWEEN ? AND ? GROUP BY k", [$ini, $fim]);

    $tot = [
        'logins' => (int) pa_val('SELECT COUNT(*) FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ?', [$ini, $fim]),
        'usuarios' => (int) pa_val('SELECT COUNT(DISTINCT usuario) FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ?', [$ini, $fim]),
        'falhas' => (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria WHERE categoria = 'acesso' AND resultado = 'falha' AND criado_em BETWEEN ? AND ?", [$ini, $fim]),
        'negados' => (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria WHERE resultado = 'negado' AND criado_em BETWEEN ? AND ?", [$ini, $fim]),
        'ips' => (int) pa_val('SELECT COUNT(DISTINCT ip) FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ?', [$ini, $fim]),
    ];
    // Entradas fora do expediente (calculado no PHP com a configuração atual)
    $fora = 0;
    $porHora = array_fill(0, 24, 0);
    $foraLista = [];
    foreach (pa_all('SELECT usuario, nome_completo, ip, data_hora FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ? ORDER BY data_hora DESC', [$ini, $fim]) as $r) {
        $porHora[(int) substr($r['data_hora'], 11, 2)]++;
        if (aud_fora_expediente($r['data_hora'], $cfg)) {
            $fora++;
            if (count($foraLista) < 15) {
                $foraLista[] = ['quando' => $r['data_hora'], 'usuario' => $r['usuario'], 'nome' => pa_utf8($r['nome_completo']), 'ip' => $r['ip']];
            }
        }
    }
    $tot['fora_expediente'] = $fora;

    // Por usuário
    $f = pa_funcionarios();
    $porUsuario = [];
    foreach ($f['por_login'] as $l => $info) {
        $porUsuario[$l] = ['usuario' => $l, 'nome' => $info['nome_completo'], 'cargo' => $info['cargo'], 'nivel' => $info['nivel_de_acesso'],
                           'status' => $info['status'], 'tfa' => (int) $info['tfa_enabled'], 'logins' => 0, 'falhas' => 0, 'ips' => 0, 'ultimo' => null, 'negados' => 0];
    }
    foreach (pa_all('SELECT usuario, COUNT(*) n, COUNT(DISTINCT ip) ips FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ? GROUP BY usuario', [$ini, $fim]) as $r) {
        $l = pa_login_de($r['usuario']);
        if (isset($porUsuario[$l])) {
            $porUsuario[$l]['logins'] = (int) $r['n'];
            $porUsuario[$l]['ips'] = (int) $r['ips'];
        }
    }
    foreach (pa_all('SELECT usuario, MAX(data_hora) d FROM logs_de_acesso GROUP BY usuario') as $r) {
        $l = pa_login_de($r['usuario']);
        if (isset($porUsuario[$l])) {
            $porUsuario[$l]['ultimo'] = $r['d'];
        }
    }
    foreach (pa_all("SELECT usuario, SUM(categoria = 'acesso' AND resultado = 'falha') fa, SUM(resultado = 'negado') ng FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? GROUP BY usuario", [$ini, $fim]) as $r) {
        $l = pa_login_de($r['usuario']);
        if (isset($porUsuario[$l])) {
            $porUsuario[$l]['falhas'] = (int) $r['fa'];
            $porUsuario[$l]['negados'] = (int) $r['ng'];
        }
    }
    $porUsuario = array_values($porUsuario);
    usort($porUsuario, function ($a, $b) {
        return $b['logins'] <=> $a['logins'];
    });

    $ips = pa_all('SELECT ip, COUNT(*) n, COUNT(DISTINCT usuario) u, MAX(data_hora) ult FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ? GROUP BY ip ORDER BY n DESC LIMIT 10', [$ini, $fim]);
    // IP usado pela primeira vez por um usuário dentro do período
    $novos = pa_all('SELECT l.usuario, l.ip, MIN(l.data_hora) primeiro FROM logs_de_acesso l
                      WHERE l.data_hora BETWEEN ? AND ?
                        AND NOT EXISTS (SELECT 1 FROM logs_de_acesso x WHERE x.usuario = l.usuario AND x.ip = l.ip AND x.data_hora < ?)
                      GROUP BY l.usuario, l.ip ORDER BY primeiro DESC LIMIT 15', [$ini, $fim, $ini]);
    foreach ($novos as &$n) {
        $n['nome'] = pa_nome_de(pa_login_de($n['usuario']));
    }
    unset($n);

    // Tentativas recusadas recentes e dispositivos
    $tentativas = [];
    foreach (pa_all("SELECT criado_em, usuario, ip, acao, mensagem FROM atlas_auditoria WHERE categoria = 'acesso' AND resultado = 'falha' AND criado_em BETWEEN ? AND ? ORDER BY id DESC LIMIT 15", [$ini, $fim]) as $r) {
        $r['criado_em'] = substr($r['criado_em'], 0, 19);
        $tentativas[] = $r;
    }
    $disp = ['tipo' => [], 'navegador' => [], 'sistema' => []];
    foreach (pa_all('SELECT user_agent, COUNT(*) n FROM (SELECT user_agent FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? UNION ALL SELECT user_agent FROM atlas_auditoria_navegacao WHERE criado_em BETWEEN ? AND ?) x GROUP BY user_agent', [$ini, $fim, $ini, $fim]) as $r) {
        $d = pa_dispositivo((string) $r['user_agent']);
        foreach ($d as $k => $v) {
            $disp[$k][$v] = ($disp[$k][$v] ?? 0) + (int) $r['n'];
        }
    }
    foreach ($disp as &$x) {
        arsort($x);
    }
    unset($x);

    return [
        'totais' => $tot, 'eixo' => array_values($rot), 'serie_logins' => pa_serie($rot, $logins), 'serie_usuarios' => pa_serie($rot, $logins, 'u'),
        'serie_falhas' => pa_serie($rot, $falhas), 'por_hora' => $porHora, 'por_usuario' => $porUsuario, 'ips' => $ips, 'ips_novos' => $novos,
        'fora_expediente_lista' => $foraLista, 'tentativas' => $tentativas, 'dispositivos' => $disp,
        'expediente' => ['inicio' => $cfg['expediente_inicio'], 'fim' => $cfg['expediente_fim'], 'dias' => $cfg['expediente_dias']],
    ];
}

/* ============================ Integridade e cobertura ============================ */

function pa_integridade(): array
{
    aud_estrutura();
    $res = ['cadeias' => [], 'gatilhos' => null, 'cobertura' => [], 'verificado_em' => aud_agora()];
    foreach (aud_cadeias() as $c => $rot) {
        try {
            $v = aud_verificar($c);
            $res['cadeias'][] = ['chave' => $c, 'rotulo' => 'Auditoria central · ' . $rot, 'ok' => $v['ok'], 'total' => $v['total'], 'motivo' => $v['motivo'],
                                 'quebras' => array_slice($v['quebras'], 0, 10), 'pendentes' => $v['pendentes']];
        } catch (Throwable $e) {
            $res['cadeias'][] = ['chave' => $c, 'rotulo' => 'Auditoria central · ' . $rot, 'ok' => false, 'total' => 0, 'motivo' => $e->getMessage(), 'quebras' => [], 'pendentes' => 0];
        }
    }
    // Trilhas com selo próprio dos módulos
    $raiz = __DIR__ . '/../..';
    if (pa_tabela_existe('os_auditoria') && is_file($raiz . '/os/auditoria_os_lib.php')) {
        try {
            require_once $raiz . '/os/auditoria_os_lib.php';
            $ids = array_column(pa_all('SELECT DISTINCT os_id FROM os_auditoria'), 'os_id');
            $tot = 0;
            $falhas = [];
            foreach ($ids as $id) {
                $v = osaud_verificar_cadeia(pa_db(), (int) $id);
                $tot += $v['total'];
                if (!$v['ok']) {
                    $falhas[] = 'O.S. #' . $id;
                }
            }
            $res['cadeias'][] = ['chave' => 'os', 'rotulo' => 'Ordens de Serviço · retratos de alteração', 'ok' => !$falhas, 'total' => $tot,
                                 'motivo' => $falhas ? 'Cadeia quebrada em: ' . implode(', ', array_slice($falhas, 0, 8)) : '', 'quebras' => [], 'pendentes' => 0];
        } catch (Throwable $e) {
        }
    }
    if (pa_tabela_existe('caixa_auditoria') && is_file($raiz . '/caixa/caixa_auditoria.php')) {
        try {
            require_once $raiz . '/caixa/caixa_auditoria.php';
            $v = cx_auditoria_verificar(pa_db());
            $res['cadeias'][] = ['chave' => 'caixa', 'rotulo' => 'Fluxo de Caixa · trilha do caixa', 'ok' => $v['ok'], 'total' => $v['total'],
                                 'motivo' => $v['ok'] ? '' : ('Registro #' . $v['quebra_id'] . ': ' . $v['motivo']), 'quebras' => [], 'pendentes' => 0];
        } catch (Throwable $e) {
        }
    }
    if (pa_tabela_existe('os_transferencias_saldo') && is_file($raiz . '/os/transferencia_saldo_lib.php')) {
        try {
            require_once $raiz . '/os/transferencia_saldo_lib.php';
            $v = ts_verificar_integridade();
            $res['cadeias'][] = ['chave' => 'transferencias', 'rotulo' => 'O.S. · transferências de saldo', 'ok' => $v['ok'],
                                 'total' => (int) pa_val('SELECT COUNT(*) FROM os_transferencias_saldo'),
                                 'motivo' => $v['ok'] ? '' : 'Alteradas: ' . implode(', ', $v['codigos']), 'quebras' => [], 'pendentes' => 0];
        } catch (Throwable $e) {
        }
    }
    $estado = json_decode((string) aud_meta_get('gatilhos_estado', ''), true) ?: null;
    $qtd = (int) pa_val("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_NAME LIKE 'atlas\\_aud\\_%'");
    $res['gatilhos'] = ['estado' => $estado, 'instalados' => $qtd, 'config' => aud_config()];
    // Cobertura: operações por módulo nos últimos 30 dias
    $desde = date('Y-m-d H:i:s', strtotime('-30 days'));
    $mods = [];
    foreach (pa_all("SELECT modulo, COUNT(*) n, MAX(criado_em) ult FROM atlas_auditoria WHERE criado_em >= ? GROUP BY modulo", [$desde]) as $r) {
        $mods[$r['modulo']] = ['op' => (int) $r['n'], 'ult' => substr($r['ult'], 0, 19), 'nav' => 0];
    }
    foreach (pa_all("SELECT modulo, COUNT(*) n, MAX(criado_em) ult FROM atlas_auditoria_navegacao WHERE criado_em >= ? GROUP BY modulo", [$desde]) as $r) {
        $mods[$r['modulo']] = ($mods[$r['modulo']] ?? ['op' => 0, 'ult' => null]) + ['nav' => 0];
        $mods[$r['modulo']]['nav'] = (int) $r['n'];
        if (!$mods[$r['modulo']]['ult'] || $r['ult'] > $mods[$r['modulo']]['ult']) {
            $mods[$r['modulo']]['ult'] = substr($r['ult'], 0, 19);
        }
    }
    $dadosMod = [];
    foreach (pa_all("SELECT tabela, COUNT(*) n FROM atlas_auditoria_dados WHERE criado_em >= ? GROUP BY tabela", [$desde]) as $r) {
        $m = aud_modulo_da_tabela($r['tabela']);
        $dadosMod[$m] = ($dadosMod[$m] ?? 0) + (int) $r['n'];
    }
    $pastas = [];
    foreach (glob($raiz . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $pastas[] = basename($d);
    }
    $especiais = ['acesso', 'inicio', 'funcionarios', 'serventia', 'sistema'];
    foreach (aud_modulos() as $k => $rot) {
        // módulos com pasta nesta instalação + áreas da raiz do Atlas
        if (!in_array($k, $especiais, true) && !in_array($k, $pastas, true)) {
            continue;
        }
        $res['cobertura'][] = ['modulo' => $k, 'rotulo' => $rot, 'operacoes' => $mods[$k]['op'] ?? 0, 'telas' => $mods[$k]['nav'] ?? 0,
                               'dados' => $dadosMod[$k] ?? 0, 'ultimo' => $mods[$k]['ult'] ?? null];
    }
    usort($res['cobertura'], function ($a, $b) {
        return ($b['operacoes'] + $b['telas'] + $b['dados']) <=> ($a['operacoes'] + $a['telas'] + $a['dados']);
    });
    $res['totais'] = [
        'operacoes' => (int) pa_val('SELECT COUNT(*) FROM atlas_auditoria'),
        'dados' => (int) pa_val('SELECT COUNT(*) FROM atlas_auditoria_dados'),
        'navegacao' => (int) pa_val('SELECT COUNT(*) FROM atlas_auditoria_navegacao'),
        'instalado_em' => aud_meta_get('instalado_em', null),
    ];
    $ok = !in_array(false, array_column($res['cadeias'], 'ok'), true);
    aud_meta_set('ultima_verificacao', json_encode(['ok' => $ok, 'em' => $res['verificado_em'],
        'falhas' => array_values(array_map(function ($c) { return $c['rotulo']; }, array_filter($res['cadeias'], function ($c) { return !$c['ok']; })))], JSON_UNESCAPED_UNICODE));
    $res['ok'] = $ok;
    return $res;
}

/* ============================ Alertas ============================ */

function pa_alertas(array $per, array $fat, array $tar, array $eq): array
{
    $a = [];
    $add = function ($nivel, $icone, $titulo, $texto, $acao = null) use (&$a) {
        $a[] = compact('nivel', 'icone', 'titulo', 'texto', 'acao');
    };
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    try {
        aud_estrutura();
        $ver = json_decode((string) aud_meta_get('ultima_verificacao', ''), true);
        if ($ver && empty($ver['ok'])) {
            $add('critico', 'fa-unlink', 'Trilha de auditoria adulterada', 'A última verificação de integridade encontrou quebra em: ' . implode(', ', (array) $ver['falhas']) . '.', ['aba' => 'integridade']);
        }
        $estado = json_decode((string) aud_meta_get('gatilhos_estado', ''), true);
        $cfg = aud_config();
        if (!empty($cfg['dados']) && (!$estado || empty($estado['ligado']))) {
            $add('aviso', 'fa-database', 'Trilha de dados ainda não instalada', 'Os gatilhos que registram o antes/depois de cada registro ainda não foram instalados neste banco.', ['aba' => 'integridade']);
        } elseif ($estado && !empty($estado['erros'])) {
            $add('aviso', 'fa-database', 'Algumas tabelas sem trilha de dados', count($estado['erros']) . ' gatilho(s) não puderam ser criados. Veja em Integridade.', ['aba' => 'integridade']);
        }
        // Tentativas de entrada recusadas em sequência (força bruta)
        foreach (pa_all("SELECT COALESCE(usuario,'?') u, ip, COUNT(*) n, MAX(criado_em) ult FROM atlas_auditoria
                          WHERE categoria = 'acesso' AND resultado = 'falha' AND criado_em BETWEEN ? AND ?
                          GROUP BY u, ip, DATE(criado_em), HOUR(criado_em) HAVING n >= 3 ORDER BY ult DESC LIMIT 3", [$ini, $fim]) as $r) {
            $add('critico', 'fa-user-lock', 'Várias tentativas de entrada recusadas', $r['n'] . ' tentativas para o usuário "' . $r['u'] . '" a partir do IP ' . $r['ip'] . ' em menos de uma hora (' . date('d/m H:i', strtotime($r['ult'])) . ').',
                 ['aba' => 'acessos']);
        }
        $exc = pa_one("SELECT COUNT(*) n, SUM(fora_expediente) fe FROM atlas_auditoria WHERE categoria IN ('exclusao','estorno','cancelamento') AND criado_em BETWEEN ? AND ?", [$ini, $fim]);
        if ((int) $exc['fe'] > 0) {
            $add('critico', 'fa-moon', 'Exclusões ou estornos fora do expediente', (int) $exc['fe'] . ' operação(ões) de exclusão, cancelamento ou estorno foram feitas fora do horário de expediente.',
                 ['aba' => 'auditoria', 'filtro' => ['categoria' => 'exclusao,estorno,cancelamento', 'fora' => 1]]);
        }
        if ((int) $exc['n'] >= 1) {
            $add('info', 'fa-trash-alt', 'Exclusões, cancelamentos e estornos no período', (int) $exc['n'] . ' operação(ões) sensíveis registradas. Vale conferir quem fez e por quê.',
                 ['aba' => 'auditoria', 'filtro' => ['categoria' => 'exclusao,estorno,cancelamento']]);
        }
        $neg = (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria WHERE resultado = 'negado' AND criado_em BETWEEN ? AND ?", [$ini, $fim]);
        if ($neg >= 3) {
            $add('aviso', 'fa-hand-paper', 'Acessos negados repetidos', "$neg tentativas de abrir telas ou fazer operações sem permissão.", ['aba' => 'auditoria', 'filtro' => ['resultado' => 'negado']]);
        }
        $seg = (int) pa_val("SELECT COUNT(*) FROM atlas_auditoria WHERE categoria = 'seguranca' AND resultado = 'sucesso' AND criado_em BETWEEN ? AND ?", [$ini, $fim]);
        if ($seg > 0) {
            $add('aviso', 'fa-shield-alt', 'Mudanças de segurança', "$seg alteração(ões) em senhas, permissões, 2FA ou cadastro de funcionários.", ['aba' => 'auditoria', 'filtro' => ['categoria' => 'seguranca']]);
        }
    } catch (Throwable $e) {
    }
    // Segurança das contas
    $f = pa_funcionarios();
    $semTfa = [];
    foreach ($f['por_login'] as $l => $r) {
        if (strtolower((string) $r['status']) === 'ativo' && strtolower((string) $r['nivel_de_acesso']) === 'administrador' && empty($r['tfa_enabled'])) {
            $semTfa[] = $r['nome_completo'];
        }
    }
    if ($semTfa) {
        $add('aviso', 'fa-key', 'Administrador sem verificação em duas etapas', implode(', ', $semTfa) . ' tem acesso total e entra só com a senha. Recomenda-se ativar o 2FA.', ['aba' => 'acessos']);
    }
    // Caixa
    if (pa_tabela_existe('caixa_fechamento_desfeito')) {
        $n = (int) pa_val('SELECT COUNT(*) FROM caixa_fechamento_desfeito WHERE criado_em BETWEEN ? AND ?', [$ini, $fim]);
        if ($n) {
            $add('critico', 'fa-cash-register', 'Fechamento de caixa desfeito', "$n fechamento(s) de caixa desfeito(s) no período.", ['aba' => 'trilhas', 'filtro' => ['modulo_t' => 'caixa']]);
        }
    }
    if (pa_tabela_existe('caixa_saldo_inicial_log')) {
        $n = (int) pa_val('SELECT COUNT(*) FROM caixa_saldo_inicial_log WHERE criado_em BETWEEN ? AND ?', [$ini, $fim]);
        if ($n) {
            $add('aviso', 'fa-cash-register', 'Saldo inicial de caixa alterado', "$n alteração(ões) manuais de saldo inicial pelo administrador.", ['aba' => 'trilhas', 'filtro' => ['modulo_t' => 'caixa']]);
        }
    }
    if (pa_tabela_existe('os_liberacao_log')) {
        $n = (int) pa_val('SELECT COUNT(*) FROM os_liberacao_log WHERE criado_em BETWEEN ? AND ?', [$ini, $fim]);
        if ($n) {
            $add('aviso', 'fa-lock-open', 'O.S. liberadas (liquidações desfeitas)', "$n O.S. tiveram atos liquidados desfeitos para nova edição.", ['aba' => 'trilhas', 'filtro' => ['modulo_t' => 'os']]);
        }
    }
    // Financeiro
    $v = $fat['variacao']['recebido'] ?? null;
    if ($v !== null && $v <= -20) {
        $add('aviso', 'fa-chart-line', 'Queda no valor recebido', 'O recebido no período caiu ' . number_format(abs($v), 1, ',', '.') . '% em relação ao período anterior equivalente.', ['aba' => 'faturamento']);
    } elseif ($v !== null && $v >= 20) {
        $add('ok', 'fa-chart-line', 'Recebimentos em alta', 'O recebido no período subiu ' . number_format($v, 1, ',', '.') . '% em relação ao período anterior equivalente.', ['aba' => 'faturamento']);
    }
    $dep = $fat['deposito'] ?? null;
    if ($dep && $dep['paradas_qtd'] > 0) {
        $add('aviso', 'fa-hourglass-half', 'Depósitos prévios parados', $dep['paradas_qtd'] . ' O.S. com saldo de depósito prévio sem movimento há mais de 30 dias (R$ ' . number_format($dep['paradas_valor'], 2, ',', '.') . ').', ['aba' => 'faturamento']);
    }
    $canc = (int) ($fat['totais']['os_canceladas'] ?? 0);
    if ($canc >= 3 && $fat['totais']['os_criadas'] > 0 && $canc / $fat['totais']['os_criadas'] >= 0.05) {
        $add('aviso', 'fa-ban', 'Cancelamentos de O.S. acima do normal', "$canc O.S. canceladas (" . number_format($canc / $fat['totais']['os_criadas'] * 100, 1, ',', '.') . '% das criadas).', ['aba' => 'auditoria', 'filtro' => ['categoria' => 'cancelamento']]);
    }
    // Tarefas
    if ($tar['atrasadas'] > 0) {
        $add($tar['atrasadas'] >= 5 ? 'aviso' : 'info', 'fa-tasks', 'Tarefas atrasadas', $tar['atrasadas'] . ' tarefa(s) em aberto com o prazo vencido.', ['aba' => 'serventia']);
    }
    $ordem = ['critico' => 0, 'aviso' => 1, 'info' => 2, 'ok' => 3];
    usort($a, function ($x, $y) use ($ordem) {
        return $ordem[$x['nivel']] <=> $ordem[$y['nivel']];
    });
    return $a;
}

/* ============================ Visão geral ============================ */

function pa_saude(array $fat, array $tar, array $aces, array $eq): array
{
    $comp = [];
    $v = $fat['variacao']['recebido'];
    $comp['financeiro'] = $v === null ? 70 : (int) max(0, min(100, 60 + $v));
    $abertas = max(1, $tar['abertas']);
    $noPrazo = $tar['concluidas'] > 0 ? $tar['concluidas_no_prazo'] / $tar['concluidas'] * 100 : 80;
    $comp['tarefas'] = (int) round(max(0, min(100, $noPrazo * 0.6 + (100 - $tar['atrasadas'] / $abertas * 100) * 0.4)));
    $f = pa_funcionarios();
    $ativos = array_filter($f['por_login'], function ($r) { return strtolower((string) $r['status']) === 'ativo'; });
    $com2fa = count(array_filter($ativos, function ($r) { return !empty($r['tfa_enabled']); }));
    $tfaPct = $ativos ? $com2fa / count($ativos) * 100 : 0;
    $falhas = (int) $aces['totais']['falhas'];
    $comp['seguranca'] = (int) round(max(0, min(100, 55 + $tfaPct * 0.45 - min(40, $falhas * 4) - min(20, (int) $aces['totais']['negados'] * 2))));
    $ver = json_decode((string) aud_meta_get('ultima_verificacao', ''), true);
    $comp['integridade'] = $ver ? (!empty($ver['ok']) ? 100 : 0) : 85;
    $dep = $fat['deposito'];
    $comp['deposito'] = (int) max(0, 100 - min(100, $dep['paradas_qtd'] * 6));
    $pesos = ['financeiro' => 0.25, 'tarefas' => 0.2, 'seguranca' => 0.2, 'integridade' => 0.2, 'deposito' => 0.15];
    $s = 0;
    foreach ($pesos as $k => $p) {
        $s += $comp[$k] * $p;
    }
    $s = (int) round($s);
    $rot = $s >= 85 ? 'Excelente' : ($s >= 70 ? 'Boa' : ($s >= 50 ? 'Atenção' : 'Crítica'));
    return ['pontuacao' => $s, 'rotulo' => $rot, 'componentes' => $comp, 'tfa_percentual' => round($tfaPct)];
}

function pa_visao_geral(array $per): array
{
    $fat = pa_faturamento($per);
    $tar = pa_tarefas($per);
    $aces = pa_acessos($per);
    $eq = pa_equipe($per);
    $aud = ['operacoes' => 0, 'criticas' => 0, 'falhas' => 0, 'negados' => 0, 'fora' => 0, 'dados' => 0, 'telas' => 0, 'recentes' => []];
    try {
        aud_estrutura();
        $r = pa_one("SELECT COUNT(*) n, SUM(severidade = 'critico') c, SUM(resultado = 'falha') f, SUM(resultado = 'negado') ng, SUM(fora_expediente) fe
                       FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ?", [$per['ini_dt'], $per['fim_dt']]);
        $aud['operacoes'] = (int) $r['n'];
        $aud['criticas'] = (int) $r['c'];
        $aud['falhas'] = (int) $r['f'];
        $aud['negados'] = (int) $r['ng'];
        $aud['fora'] = (int) $r['fe'];
        $aud['dados'] = (int) pa_val('SELECT COUNT(*) FROM atlas_auditoria_dados WHERE criado_em BETWEEN ? AND ?', [$per['ini_dt'], $per['fim_dt']]);
        $aud['telas'] = (int) pa_val('SELECT COUNT(*) FROM atlas_auditoria_navegacao WHERE criado_em BETWEEN ? AND ?', [$per['ini_dt'], $per['fim_dt']]);
        $aud['recentes'] = array_map('pa_aud_linha', pa_all("SELECT id, criado_em, usuario, nome, ip, modulo, pagina, metodo, acao, categoria, severidade, resultado, mensagem, alvo,
                     dados_resumo, dados_linhas, fora_expediente, hash, duracao_ms FROM atlas_auditoria
                     WHERE criado_em BETWEEN ? AND ? AND (severidade IN ('critico','aviso') OR resultado <> 'sucesso') ORDER BY id DESC LIMIT 8", [$per['ini_dt'], $per['fim_dt']]));
        [$ex, $rot] = pa_eixo($per, 'criado_em');
        $aud['eixo'] = array_values($rot);
        $aud['serie'] = pa_serie($rot, pa_all("SELECT $ex k, COUNT(*) v FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? AND categoria <> 'acesso' GROUP BY k", [$per['ini_dt'], $per['fim_dt']]));
        $aud['serie_criticas'] = pa_serie($rot, pa_all("SELECT $ex k, COUNT(*) v FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? AND severidade = 'critico' GROUP BY k", [$per['ini_dt'], $per['fim_dt']]));
        $aud['categorias'] = [];
        foreach (pa_all("SELECT categoria, COUNT(*) n FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? GROUP BY categoria ORDER BY n DESC", [$per['ini_dt'], $per['fim_dt']]) as $c) {
            $cat = aud_categorias()[$c['categoria']] ?? aud_categorias()['outros'];
            $aud['categorias'][] = ['categoria' => $c['categoria'], 'rotulo' => $cat[0], 'cor' => $cat[2], 'n' => (int) $c['n']];
        }
    } catch (Throwable $e) {
    }
    return [
        'faturamento' => $fat, 'tarefas' => $tar, 'acessos' => ['totais' => $aces['totais']], 'auditoria' => $aud,
        'equipe_top' => array_slice($eq['membros'], 0, 6), 'saude' => pa_saude($fat, $tar, $aces, $eq),
        'alertas' => pa_alertas($per, $fat, $tar, $eq),
    ];
}
