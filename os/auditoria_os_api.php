<?php
/**
 * =====================================================================
 * auditoria_os_api.php — Detalhes da auditoria (JSON com HTML pronto)
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 * RESTRITO A ADMINISTRADOR.
 *
 *   ?acao=evento&id=N            um registro
 *   ?acao=grupo&os=N&grupo=TOK   sessão de trabalho (1ª versão × última)
 *   ?acao=legado&id=N            registro antigo (logs_ordens_de_servico)
 *   ?acao=cadeia&os=N            verificação da cadeia de integridade
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/../checar_acesso_de_administrador.php');
require_once __DIR__ . '/auditoria_os_view.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function osaud_api_resp(array $r, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Nome completo dos usuários (funcionarios). */
function osaud_api_nome(PDO $pdo, ?string $usuario): string
{
    if ($usuario === null || $usuario === '') {
        return '—';
    }
    static $cache = [];
    if (!array_key_exists($usuario, $cache)) {
        try {
            $st = $pdo->prepare('SELECT nome_completo FROM funcionarios WHERE usuario = ? LIMIT 1');
            $st->execute([$usuario]);
            $cache[$usuario] = $st->fetchColumn() ?: null;
        } catch (Throwable $e) {
            $cache[$usuario] = null;
        }
    }
    $n = $cache[$usuario];
    return osaud_h($n ? $n . ' (' . $usuario . ')' : $usuario);
}

/** Link para ver arquivo (comprovante/anexo) referenciado por um evento. */
function osaud_api_linker(int $evAntes, int $evDepois): callable
{
    return static function (string $sec, string $lado, array $linha) use ($evAntes, $evDepois) {
        $ev = $lado === 'antes' ? $evAntes : $evDepois;
        if ($ev <= 0 || empty($linha['id'])) {
            return null;
        }
        $tipo = $sec === 'comprovantes' ? 'comprovante' : 'anexo';
        return 'auditoria_os_arquivo.php?ev=' . $ev . '&lado=' . $lado . '&tipo=' . $tipo . '&rid=' . (int) $linha['id'];
    };
}

try {
    $pdo = osaud_pdo();
    osaud_migrar($pdo);
    $acao = (string) ($_GET['acao'] ?? '');

    /* ---------------------------------------------------- um evento */
    if ($acao === 'evento') {
        $e = osaud_evento($pdo, (int) ($_GET['id'] ?? 0));
        if (!$e) {
            osaud_api_resp(['ok' => false, 'mensagem' => 'Registro não encontrado.'], 404);
        }
        $ai = osaud_acao_info($e['acao']);
        $id = (int) $e['id'];
        $html = osaud_html_detalhe([
            'antes'  => $e['antes'],
            'depois' => $e['depois'],
            'diff'   => $e['diff'],
            'resumo' => osaud_resumo($e['diff']),
            'meta'   => [
                'Registro'   => '#' . $id,
                'O.S.'       => '<a href="auditoria_os.php?os=' . (int) $e['os_id'] . '">nº ' . (int) $e['os_id'] . '</a>',
                'Data/hora'  => osaud_h(osaud_data_br($e['criado_em'])),
                'Usuário'    => osaud_api_nome($pdo, $e['usuario']),
                'Motivo'     => $e['motivo'] !== null ? osaud_h($e['motivo']) : null,
                'IP'         => osaud_h($e['ip'] ?? '—'),
                'Origem'     => osaud_h($e['origem'] ?? '—'),
                'Navegador'  => $e['user_agent'] ? '<span class="ua">' . osaud_h(mb_strimwidth($e['user_agent'], 0, 90, '…')) . '</span>' : null,
                'Integridade'=> '<code title="' . osaud_h($e['hash']) . '">' . osaud_h(substr($e['hash'], 0, 16)) . '…</code>',
            ],
            'impresso' => [
                'antes'  => $e['antes']  ? 'auditoria_os_impresso.php?tipo=evento&id=' . $id . '&lado=antes'  : null,
                'depois' => $e['depois'] ? 'auditoria_os_impresso.php?tipo=evento&id=' . $id . '&lado=depois' : null,
            ],
            'link' => osaud_api_linker($id, $id),
        ]);
        osaud_api_resp([
            'ok' => true,
            'titulo' => $ai['rotulo'] . ' — O.S. nº ' . (int) $e['os_id'],
            'icone' => $ai['icone'], 'cor' => $ai['cor'],
            'html' => $html,
        ]);
    }

    /* ------------------------------------- sessão de trabalho (grupo) */
    if ($acao === 'grupo') {
        $os = (int) ($_GET['os'] ?? 0);
        $grupo = (string) ($_GET['grupo'] ?? '');
        $st = $pdo->prepare('SELECT id, acao, usuario, resumo, criado_em FROM os_auditoria WHERE os_id = ? AND grupo = ? ORDER BY id ASC');
        $st->execute([$os, $grupo]);
        $evs = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$evs) {
            osaud_api_resp(['ok' => false, 'mensagem' => 'Sessão não encontrada.'], 404);
        }
        $primeiro = osaud_evento($pdo, (int) $evs[0]['id']);
        $ultimo   = osaud_evento($pdo, (int) end($evs)['id']);
        $antes  = $primeiro['antes'];
        $depois = $ultimo['depois'];
        $diff   = osaud_diff($antes, $depois);
        $usuarios = array_unique(array_map(static fn($r) => $r['usuario'], $evs));

        $html = osaud_html_detalhe([
            'antes' => $antes, 'depois' => $depois, 'diff' => $diff, 'resumo' => osaud_resumo($diff),
            'aviso' => 'Comparação da <b>primeira versão</b> (antes da 1ª gravação) com a <b>última</b> (depois da '
                     . count($evs) . 'ª gravação) desta sessão de trabalho. Cada gravação individual está na aba “Gravações”.',
            'meta' => [
                'O.S.'      => '<a href="auditoria_os.php?os=' . $os . '">nº ' . $os . '</a>',
                'Início'    => osaud_h(osaud_data_br($evs[0]['criado_em'])),
                'Fim'       => osaud_h(osaud_data_br(end($evs)['criado_em'])),
                'Gravações' => count($evs),
                'Usuário'   => implode(', ', array_map(static fn($u) => osaud_api_nome($pdo, $u), $usuarios)),
            ],
            'impresso' => [
                'antes'  => $antes  ? 'auditoria_os_impresso.php?tipo=evento&id=' . (int) $evs[0]['id'] . '&lado=antes' : null,
                'depois' => $depois ? 'auditoria_os_impresso.php?tipo=evento&id=' . (int) end($evs)['id'] . '&lado=depois' : null,
            ],
            'link' => osaud_api_linker((int) $evs[0]['id'], (int) end($evs)['id']),
            'eventos' => $evs,
        ]);
        osaud_api_resp(['ok' => true, 'titulo' => 'Sessão de trabalho — O.S. nº ' . $os, 'icone' => 'fa-clone', 'cor' => 'edit', 'html' => $html]);
    }

    /* ------------------------------------------------ registro antigo */
    if ($acao === 'legado') {
        $logId = (int) ($_GET['id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM logs_ordens_de_servico WHERE id = ?');
        $st->execute([$logId]);
        $log = $st->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            osaud_api_resp(['ok' => false, 'mensagem' => 'Registro antigo não encontrado.'], 404);
        }
        $osId = (int) $log['ordem_de_servico_id'];
        $prox = $pdo->prepare('SELECT id, data_edicao FROM logs_ordens_de_servico WHERE ordem_de_servico_id = ? AND id > ? ORDER BY id ASC LIMIT 1');
        $prox->execute([$osId, $logId]);
        $proximo = $prox->fetch(PDO::FETCH_ASSOC);

        $antes = osaud_legado_restringir(osaud_legado_snapshot($pdo, $logId));
        if ($proximo) {
            $depois = osaud_legado_restringir(osaud_legado_snapshot($pdo, (int) $proximo['id']));
            $refDepois = 'a cópia seguinte, feita em ' . osaud_data_br($proximo['data_edicao']) . ' (nova abertura da edição)';
            $urlDepois = 'auditoria_os_impresso.php?tipo=legado&id=' . (int) $proximo['id'];
        } else {
            $depois = osaud_legado_restringir(osaud_legado_atual($pdo, $osId));
            $refDepois = 'o <b>estado atual</b> da O.S. (não há cópia posterior)';
            $urlDepois = 'auditoria_os_impresso.php?tipo=atual&os=' . $osId;
        }
        $diff = osaud_diff($antes, $depois);
        $html = osaud_html_detalhe([
            'antes' => $antes, 'depois' => $depois, 'diff' => $diff, 'resumo' => osaud_resumo($diff),
            'aviso' => 'Registro do <b>histórico antigo</b>: o sistema copiava a O.S. ao <b>abrir</b> a tela de edição. '
                     . 'O “antes” é essa cópia; o “depois” é ' . $refDepois . '. O histórico antigo guarda só os dados '
                     . 'principais e os atos — pagamentos e anexos não eram registrados.',
            'meta' => [
                'Registro antigo' => '#' . $logId,
                'O.S.'            => '<a href="auditoria_os.php?os=' . $osId . '">nº ' . $osId . '</a>',
                'Edição aberta em'=> osaud_h(osaud_data_br($log['data_edicao'] ?? null)),
                'Editado por'     => osaud_api_nome($pdo, $log['editado_por'] ?? null),
            ],
            'impresso' => [
                'antes'  => $antes ? 'auditoria_os_impresso.php?tipo=legado&id=' . $logId : null,
                'depois' => $depois ? $urlDepois : null,
            ],
        ]);
        osaud_api_resp(['ok' => true, 'titulo' => 'Histórico antigo — O.S. nº ' . $osId, 'icone' => 'fa-history', 'cor' => 'info', 'html' => $html]);
    }

    /* --------------------------------------------------- integridade */
    if ($acao === 'cadeia') {
        osaud_api_resp(['ok' => true] + osaud_verificar_cadeia($pdo, (int) ($_GET['os'] ?? 0)));
    }

    osaud_api_resp(['ok' => false, 'mensagem' => 'Ação desconhecida.'], 400);

} catch (Throwable $e) {
    error_log('[auditoria_os_api] ' . $e->getMessage());
    osaud_api_resp(['ok' => false, 'mensagem' => 'Falha ao carregar a auditoria: ' . $e->getMessage()], 500);
}
