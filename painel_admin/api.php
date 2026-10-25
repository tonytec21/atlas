<?php
/**
 * painel_admin/api.php — Endpoints JSON do Painel do Administrador.
 * Acesso restrito ao nível "administrador". Operações que alteram algo
 * exigem POST com o token CSRF da sessão.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/lib/ia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pa_responder($dados, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($code < 400 ? ['ok' => true, 'dados' => $dados] : ['ok' => false, 'erro' => $dados],
        JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (empty($_SESSION['username'])) {
    pa_responder('Sessão expirada. Entre novamente no Atlas.', 401);
}
if (!pa_eh_admin()) {
    pa_responder('Acesso negado: o Painel do Administrador é exclusivo do administrador.', 403);
}

$acao = (string) ($_REQUEST['acao'] ?? '');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$escrita = ['auditoria_config_salvar', 'gatilhos_reinstalar', 'ia_config_salvar', 'ia_modelos', 'ia_analisar', 'expurgar'];
if (in_array($acao, $escrita, true)) {
    if (!$post || !hash_equals((string) ($_SESSION['painel_csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        pa_responder('Requisição inválida (token de segurança). Recarregue a página.', 400);
    }
}
// As consultas não precisam segurar a sessão (evita travar outras abas do usuário)
if (!in_array($acao, $escrita, true)) {
    session_write_close();
}

$per = pa_periodo($_REQUEST['ini'] ?? null, $_REQUEST['fim'] ?? null);
@set_time_limit(180);

try {
    switch ($acao) {
        case 'visao_geral':
            $r = pa_visao_geral($per);
            $r['periodo'] = $per;
            $r['ia'] = ['ativa' => (bool) pa_ia_config()['ativa'], 'automatica_pendente' => pa_ia_precisa_automatica($per)];
            $u = pa_ia_ultima($per);
            $r['ia']['ultima'] = $u ? ['id' => (int) $u['id'], 'criado_em' => $u['criado_em'], 'modelo' => $u['modelo'], 'provedor' => $u['provedor'],
                                       'periodo_ini' => $u['periodo_ini'], 'periodo_fim' => $u['periodo_fim'], 'texto' => $u['texto']] : null;
            pa_responder($r);

        case 'faturamento':
            pa_responder(pa_faturamento($per) + ['periodo' => $per]);

        case 'equipe':
            $eq = pa_equipe($per);
            if (!empty($_GET['usuario'])) {
                $eq['mapa_usuario'] = pa_mapa_calor($per, (string) $_GET['usuario']);
            }
            pa_responder($eq + ['periodo' => $per]);

        case 'mapa_usuario':
            pa_responder(pa_mapa_calor($per, (string) ($_GET['usuario'] ?? '')));

        case 'serventia':
            pa_responder(pa_serventia($per) + ['periodo' => $per]);

        case 'auditoria':
            pa_responder(pa_auditoria_lista($_GET, $per) + ['periodo' => $per]);

        case 'evento':
            $ev = pa_auditoria_evento((int) ($_GET['id'] ?? 0));
            $ev ? pa_responder($ev) : pa_responder('Registro não encontrado.', 404);

        case 'dados':
            pa_responder(pa_dados_lista($_GET, $per));

        case 'historico_registro':
            pa_responder(pa_historico_registro((string) ($_GET['tabela'] ?? ''), (string) ($_GET['registro'] ?? '')));

        case 'navegacao':
            pa_responder(pa_navegacao_lista($_GET, $per));

        case 'trilhas':
            pa_responder(pa_trilhas_modulos($_GET, $per));

        case 'acessos':
            pa_responder(pa_acessos($per) + ['periodo' => $per]);

        case 'integridade':
            pa_responder(pa_integridade());

        case 'filtros':
            aud_estrutura();
            $us = [];
            foreach (pa_funcionarios()['por_login'] as $l => $r) {
                $us[] = ['usuario' => $l, 'nome' => $r['nome_completo'], 'status' => $r['status']];
            }
            $mods = [];
            foreach (aud_modulos() as $k => $v) {
                $mods[] = ['chave' => $k, 'rotulo' => $v];
            }
            usort($mods, function ($a, $b) { return strcoll($a['rotulo'], $b['rotulo']); });
            $cats = [];
            foreach (aud_categorias() as $k => $v) {
                $cats[] = ['chave' => $k, 'rotulo' => $v[0], 'icone' => $v[1], 'cor' => $v[2]];
            }
            pa_responder(['usuarios' => $us, 'modulos' => $mods, 'categorias' => $cats]);

        case 'config':
            aud_estrutura();
            $tabs = [];
            foreach (pa_all("SELECT TABLE_NAME t, TABLE_ROWS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME") as $t) {
                if (!aud_tabela_ignorada($t['t'])) {
                    $tabs[] = ['tabela' => $t['t'], 'modulo' => aud_modulo_rotulo(aud_modulo_da_tabela($t['t'])), 'linhas' => (int) $t['n']];
                }
            }
            pa_responder([
                'auditoria' => aud_config(), 'tabelas' => $tabs, 'gatilhos' => json_decode((string) aud_meta_get('gatilhos_estado', ''), true),
                'ia' => pa_ia_config_publica(), 'mysql' => aud_mysql_suporta(aud_pdo()),
            ]);

        case 'auditoria_config_salvar':
            $novo = json_decode((string) ($_POST['config'] ?? '{}'), true) ?: [];
            $antes = aud_config();
            $cfg = aud_config_salvar($novo);
            $g = null;
            if ((int) $antes['dados'] !== (int) $cfg['dados'] || $antes['tabelas_excluidas'] != $cfg['tabelas_excluidas']) {
                $g = aud_gatilhos_sincronizar(true);
            }
            pa_responder(['config' => $cfg, 'gatilhos' => $g]);

        case 'gatilhos_reinstalar':
            pa_responder(aud_gatilhos_sincronizar(true));

        case 'expurgar':
            pa_responder(aud_expurgar());

        case 'ia_config_salvar':
            $c = pa_ia_config();
            foreach (['ativa', 'automatica', 'anonimizar'] as $k) {
                if (isset($_POST[$k])) {
                    $c[$k] = (int) (bool) $_POST[$k];
                }
            }
            if (isset($_POST['provedor'])) {
                $c['provedor'] = $_POST['provedor'] === 'claude' ? 'claude' : 'gemini';
            }
            foreach (['gemini', 'claude'] as $p) {
                if (isset($_POST['modelo_' . $p])) {
                    $m = (string) $_POST['modelo_' . $p];
                    $validos = array_column((array) $c['modelos_' . $p], 'id');
                    if ($m === '' || in_array($m, $validos, true)) {
                        $c['modelo_' . $p] = $m;
                    }
                }
                if (isset($_POST['chave_' . $p]) && strpos((string) $_POST['chave_' . $p], '•') === false) {
                    $nova = trim((string) $_POST['chave_' . $p]);
                    if ($nova !== pa_ia_chave($p)) {
                        pa_ia_chave_gravar($p, $nova);
                        if ($nova === '') {
                            $c['modelos_' . $p] = [];
                            $c['modelo_' . $p] = '';
                        }
                    }
                }
            }
            if (isset($_POST['instrucoes'])) {
                $c['instrucoes'] = mb_substr((string) $_POST['instrucoes'], 0, 3000);
            }
            if ($c['ativa'] && ($c['modelo_' . $c['provedor']] ?? '') === '') {
                pa_ia_config_gravar($c);
                pa_responder(['config' => pa_ia_config_publica(), 'aviso' => 'Configuração salva. Para usar a análise, carregue os modelos e escolha um.']);
            }
            pa_ia_config_gravar($c);
            pa_responder(['config' => pa_ia_config_publica()]);

        case 'ia_modelos':
            $p = ($_POST['provedor'] ?? '') === 'claude' ? 'claude' : 'gemini';
            $chaveTela = trim((string) ($_POST['chave'] ?? ''));
            if ($chaveTela !== '' && strpos($chaveTela, '•') === false) {
                pa_ia_chave_gravar($p, $chaveTela);
            }
            [$lista, $erro] = pa_ia_listar_modelos($p);
            if ($erro && !$lista) {
                pa_responder($erro, 422);
            }
            $c = pa_ia_config();
            $c['modelos_' . $p] = $lista;
            $c['modelos_em'][$p] = date('Y-m-d H:i:s');
            if (!in_array($c['modelo_' . $p], array_column($lista, 'id'), true)) {
                $c['modelo_' . $p] = '';
            }
            pa_ia_config_gravar($c);
            pa_responder(['modelos' => $lista, 'config' => pa_ia_config_publica()]);

        case 'ia_analisar':
            $foco = in_array($_POST['foco'] ?? '', ['geral', 'financeiro', 'equipe', 'auditoria'], true) ? $_POST['foco'] : 'geral';
            $auto = !empty($_POST['automatica']);
            if ($auto && !pa_ia_precisa_automatica($per)) {
                pa_responder(['pulada' => true]);
            }
            session_write_close();
            $r = pa_ia_analisar($per, $auto, $foco);
            $r['ok'] ? pa_responder($r) : pa_responder($r['erro'], 422);

        case 'ia_historico':
            pa_responder(pa_ia_historico());

        case 'ia_ver':
            pa_cfg_estrutura();
            $a = pa_one('SELECT * FROM painel_ia_analises WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
            $a ? pa_responder($a) : pa_responder('Análise não encontrada.', 404);

        case 'exportar':
            pa_exportar((string) ($_GET['tipo'] ?? 'auditoria'), $per);

        default:
            pa_responder('Ação desconhecida.', 400);
    }
} catch (Throwable $e) {
    error_log('[painel_admin] ' . $acao . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    pa_responder('Erro ao montar os dados: ' . $e->getMessage(), 500);
}

/** Exporta a trilha filtrada em CSV (separador ;, UTF-8 com BOM para abrir direto no Excel). */
function pa_exportar(string $tipo, array $per): void
{
    $nome = 'auditoria_' . $tipo . '_' . $per['ini'] . '_a_' . $per['fim'] . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $q = $_GET;
    if ($tipo === 'dados') {
        fputcsv($out, ['Data/hora', 'Usuário', 'Módulo', 'Tabela', 'Registro', 'Operação', 'Campos', 'Antes', 'Depois', 'Selado'], ';');
        $q['pagina'] = 1;
        $lim = 0;
        do {
            $r = pa_dados_lista($q, $per);
            foreach ($r['linhas'] as $l) {
                $a = [];
                $d = [];
                foreach ($l['campos'] as $c) {
                    if ($c['mudou']) {
                        $a[] = $c['campo'] . '=' . (is_scalar($c['antes']) || $c['antes'] === null ? (string) $c['antes'] : json_encode($c['antes'], JSON_UNESCAPED_UNICODE));
                        $d[] = $c['campo'] . '=' . (is_scalar($c['depois']) || $c['depois'] === null ? (string) $c['depois'] : json_encode($c['depois'], JSON_UNESCAPED_UNICODE));
                    }
                }
                fputcsv($out, [$l['criado_em'], $l['nome'], $l['modulo_rotulo'], $l['tabela'], $l['registro_id'], $l['operacao_rotulo'],
                               implode(', ', array_column(array_filter($l['campos'], function ($c) { return $c['mudou']; }), 'campo')),
                               mb_substr(implode(' | ', $a), 0, 30000), mb_substr(implode(' | ', $d), 0, 30000), $l['selado'] ? 'sim' : 'não'], ';');
            }
            $q['pagina']++;
            $lim++;
        } while ($q['pagina'] <= $r['paginas'] && $lim < 500);
    } elseif ($tipo === 'trilhas') {
        fputcsv($out, ['Data/hora', 'Usuário', 'Módulo', 'Ação', 'Detalhe', 'Referência', 'Fonte'], ';');
        $q['pagina'] = 1;
        do {
            $r = pa_trilhas_modulos($q, $per);
            foreach ($r['linhas'] as $l) {
                fputcsv($out, [$l['quando'], $l['nome'], $l['modulo_rotulo'], $l['acao'], $l['detalhe'], $l['referencia'], $l['fonte']], ';');
            }
            $q['pagina']++;
        } while ($q['pagina'] <= $r['paginas']);
    } else {
        fputcsv($out, ['Data/hora', 'Usuário', 'Nome', 'Módulo', 'Ação', 'Categoria', 'Gravidade', 'Resultado', 'Mensagem', 'Alvo', 'Registros alterados',
                       'Fora do expediente', 'IP', 'Tela', 'Selado'], ';');
        $q['tamanho'] = 200;
        $q['pagina'] = 1;
        do {
            $r = pa_auditoria_lista($q, $per);
            foreach ($r['linhas'] as $l) {
                fputcsv($out, [$l['criado_em'], $l['usuario'], $l['nome'], $l['modulo_rotulo'], $l['acao'], $l['categoria_rotulo'], $l['severidade'], $l['resultado'],
                               $l['mensagem'], $l['alvo'], $l['dados_resumo'], $l['fora_expediente'] ? 'sim' : 'não', $l['ip'], $l['pagina'], $l['selado'] ? 'sim' : 'não'], ';');
            }
            $q['pagina']++;
        } while ($q['pagina'] <= $r['paginas'] && $q['pagina'] < 1000);
    }
    fclose($out);
    exit;
}
