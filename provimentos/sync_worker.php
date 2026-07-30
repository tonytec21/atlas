<?php
/**
 * provimentos/sync_worker.php
 * Endpoint AJAX da sincronizacao. Um lote por requisicao, como no indexador.
 */
include(__DIR__ . '/session_check.php');
checkSession();
require_once __DIR__ . '/sync_lib.php';

header('Content-Type: application/json; charset=utf-8');
kbBlindarJson();
date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(180);

$conn = getDatabaseConnection();
$quem = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$acao = isset($_POST['acao']) ? $_POST['acao'] : 'status';

try {
    if (!syncSchemaExiste($conn)) { syncGarantirSchema($conn); }

    switch ($acao) {
        case 'verificar':
            $fid = (int) $_POST['fonte_id'];
            $st = $conn->prepare("SELECT * FROM kb_fontes WHERE id = :id AND ativo = 1");
            $st->execute(array(':id' => $fid));
            $fonte = $st->fetch(PDO::FETCH_ASSOC);
            if (!$fonte) { throw new RuntimeException('Fonte não encontrada ou desativada.'); }

            $modo = (isset($_POST['modo']) && $_POST['modo'] === 'completo') ? 'completo' : 'novos';
            try {
                $r = ($fonte['adaptador'] === 'cnj')
                    ? syncCnjDescobrir($conn, $fonte, 30, $modo)
                    : syncCgjmaDescobrir($conn, $fonte, 40, $modo);
            } catch (Throwable $e) {
                $conn->prepare("UPDATE kb_fontes SET ultima_verif=NOW(), ultimo_erro=:e WHERE id=:id")
                     ->execute(array(':e' => mb_substr($e->getMessage(), 0, 480), ':id' => $fid));
                throw $e;
            }
            responder(array(
                'achados'   => $r['achados'],
                'novos'     => isset($r['novos']) ? $r['novos'] : 0,
                'ate_id'    => $r['ate_id'],
                'concluido' => !empty($r['concluido']),
                'status'    => status($conn)));
            break;

        case 'importar':
            $r = syncImportar($conn, (int) $_POST['item_id'], $quem);
            $r['status'] = status($conn);
            responder($r);
            break;

        case 'importar_lote':
            // Provimentos primeiro (prioridade 1), Resolucoes depois.
            $ids = $conn->query("SELECT id FROM kb_sync_itens
                                  WHERE status IN ('novo','atualizado')
                                  ORDER BY prioridade, ano DESC, CAST(numero AS UNSIGNED) DESC
                                  LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
            $feitos = array();
            foreach ($ids as $id) { $feitos[] = syncImportar($conn, (int) $id, $quem); }
            responder(array('feitos' => count($feitos), 'resultados' => $feitos,
                            'concluido' => count($ids) === 0, 'status' => status($conn)));
            break;

        case 'ignorar':
            $conn->prepare("UPDATE kb_sync_itens SET status='ignorado' WHERE id=:id")
                 ->execute(array(':id' => (int) $_POST['item_id']));
            responder(array('status' => status($conn)));
            break;

        case 'fonte_toggle':
            $conn->prepare("UPDATE kb_fontes SET ativo = 1 - ativo WHERE id = :id")
                 ->execute(array(':id' => (int) $_POST['fonte_id']));
            responder(array('status' => status($conn)));
            break;

        case 'reset_fonte':
            $fid = (int) $_POST['fonte_id'];
            $conn->prepare("UPDATE kb_fontes SET ultimo_id = NULL, pagina = NULL WHERE id = :id")
                 ->execute(array(':id' => $fid));
            @$conn->exec("DROP TABLE IF EXISTS kb_sync_fila");   // mecanismo aposentado
            responder(array('status' => status($conn)));
            break;

        case 'reanexar':
            // Rebaixa o PDF dos que foram importados sem anexo (bug do regex
            // anterior, que so aceitava href absoluto com aspas duplas).
            $lote = $conn->query("
                SELECT i.id FROM kb_sync_itens i
                  JOIN provimentos p ON p.id = i.provimento_id
                 WHERE i.status = 'importado'
                   AND (p.caminho_anexo IS NULL OR p.caminho_anexo = '')
                 LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

            $ok = 0; $falhas = array();
            foreach ($lote as $iid) {
                $r = syncReanexar($conn, (int) $iid);
                if ($r['ok']) { $ok++; } else { $falhas[] = $r['mensagem']; }
            }
            $restam = (int) $conn->query("
                SELECT COUNT(*) FROM kb_sync_itens i
                  JOIN provimentos p ON p.id = i.provimento_id
                 WHERE i.status='importado' AND (p.caminho_anexo IS NULL OR p.caminho_anexo='')")
                ->fetchColumn();
            responder(array('anexados' => $ok, 'restam' => $restam,
                            'concluido' => count($lote) === 0,
                            'falhas' => array_slice($falhas, 0, 3),
                            'status' => status($conn)));
            break;

        case 'checar_alteracoes':
            $r = syncChecarAlteracoes($conn, 10);
            $r['status'] = status($conn);
            responder($r);
            break;

        case 'buscar_lacunas':
            $fid = (int) $_POST['fonte_id'];
            $st = $conn->prepare("SELECT * FROM kb_fontes WHERE id = :id AND ativo = 1");
            $st->execute(array(':id' => $fid));
            $fonte = $st->fetch(PDO::FETCH_ASSOC);
            if (!$fonte) { throw new RuntimeException('Fonte não encontrada ou desativada.'); }

            try {
                $r = syncBuscarLacunas($conn, $fonte, 40);
            } catch (Throwable $e) {
                $conn->prepare("UPDATE kb_fontes SET ultimo_erro=:e WHERE id=:id")
                     ->execute(array(':e' => mb_substr($e->getMessage(), 0, 480), ':id' => $fid));
                throw $e;
            }
            $r['status'] = status($conn);
            responder($r);
            break;

        case 'reset_lacunas':
            $conn->prepare("UPDATE kb_fontes SET lacuna_cursor = NULL WHERE id = :id")
                 ->execute(array(':id' => (int) $_POST['fonte_id']));
            responder(array('status' => status($conn)));
            break;

        case 'reabrir_lacunas':
            $r = syncReabrirLacunas($conn);
            $r['status'] = status($conn);
            responder($r);
            break;

        case 'leis_listar':
            $leis = $conn->query("SELECT * FROM kb_leis ORDER BY ativo DESC, ano DESC, numero")
                         ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($leis as $i => $l) {
                $leis[$i]['atualizado_em'] = $l['atualizado_em']
                    ? date('d/m/Y H:i', strtotime($l['atualizado_em'])) : null;
            }
            responder(array('leis' => $leis));
            break;

        case 'lei_importar':
            responder(syncLeiImportar($conn, (int) $_POST['lei_id'], $quem));
            break;

        case 'leis_importar_todas':
            $ids = $conn->query("SELECT id FROM kb_leis WHERE ativo = 1
                                  AND (atualizado_em IS NULL
                                       OR atualizado_em < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                                  ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
            $res = array();
            foreach ($ids as $id) { $res[] = syncLeiImportar($conn, (int) $id, $quem); }
            $restam = (int) $conn->query("SELECT COUNT(*) FROM kb_leis WHERE ativo = 1
                          AND (atualizado_em IS NULL
                               OR atualizado_em < DATE_SUB(NOW(), INTERVAL 1 HOUR))")->fetchColumn();
            responder(array('feitas' => count($res), 'restam' => $restam,
                            'resultados' => $res, 'concluido' => count($ids) === 0));
            break;

        case 'lei_add':
            $url = trim($_POST['url']);
            if (!preg_match('#^https?://(www\.)?planalto\.gov\.br/#i', $url)) {
                responder(array('ok' => false,
                    'mensagem' => 'Informe um endereço do planalto.gov.br.'));
            }
            $st = $conn->prepare(
                "INSERT IGNORE INTO kb_leis (url, apelido, ativo, criado_em)
                 VALUES (:u, :a, 1, NOW())");
            $st->execute(array(':u' => $url,
                ':a' => trim(isset($_POST['apelido']) ? $_POST['apelido'] : '') ?: null));
            $st = $conn->prepare("SELECT id FROM kb_leis WHERE url = :u");
            $st->execute(array(':u' => $url));
            responder(syncLeiImportar($conn, (int) $st->fetchColumn(), $quem));
            break;

        case 'lei_toggle':
            $conn->prepare("UPDATE kb_leis SET ativo = 1 - ativo WHERE id = :id")
                 ->execute(array(':id' => (int) $_POST['lei_id']));
            responder(array());
            break;

        case 'lei_remover':
            $conn->prepare("DELETE FROM kb_leis WHERE id = :id")
                 ->execute(array(':id' => (int) $_POST['lei_id']));
            responder(array());
            break;

        case 'lacunas':
            responder(array('lacunas' => syncLacunas($conn,
                isset($_POST['ano']) ? (int) $_POST['ano'] : null)));
            break;

        case 'importar_url':
            $r = syncImportarPorUrl($conn, isset($_POST['url']) ? $_POST['url'] : '', $quem);
            $r['status'] = status($conn);
            responder($r);
            break;

        case 'testar_listagem':
            $st = $conn->prepare("SELECT * FROM kb_fontes WHERE id = :id");
            $st->execute(array(':id' => (int) $_POST['fonte_id']));
            $fonte = $st->fetch(PDO::FETCH_ASSOC);
            if (!$fonte) { throw new RuntimeException('Fonte não encontrada.'); }
            responder(array('diag' => syncTestarListagem($conn, $fonte,
                isset($_POST['pagina']) ? (int) $_POST['pagina'] : 1)));
            break;

        case 'testar_anexo':
            responder(array('diag' => syncTestarAnexo($conn, (int) $_POST['item_id'])));
            break;

        case 'diagnostico':
            responder(array('diag' => diagnostico($conn)));
            break;

        default:
            responder(array('status' => status($conn)));
    }
} catch (Throwable $e) {
    error_log('[sync] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('ok' => false, 'mensagem' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

function responder($d)
{
    if (!isset($d['ok'])) { $d['ok'] = true; }
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Checagem do ambiente. Existe porque um HTTP 500 sem contexto e inutil:
 * quase sempre e extensao ausente, permissao de pasta ou saida de rede.
 */
function diagnostico(PDO $conn)
{
    $d = array();
    $d['php']       = PHP_VERSION;
    $d['curl']      = function_exists('curl_init') ? 'ok' : 'AUSENTE (obrigatorio)';
    $d['openssl']   = extension_loaded('openssl') ? 'ok' : 'AUSENTE (obrigatorio p/ https)';
    $d['mbstring']  = extension_loaded('mbstring') ? 'ok' : 'AUSENTE (obrigatorio)';
    $d['memoria']   = ini_get('memory_limit');
    $d['tempo_max'] = ini_get('max_execution_time') . 's';

    $pasta = __DIR__ . DIRECTORY_SEPARATOR . 'anexo';
    if (!is_dir($pasta)) {
        $d['pasta_anexo'] = @mkdir($pasta, 0775, true)
            ? 'criada agora' : 'NAO EXISTE e nao consegui criar';
    } else {
        $d['pasta_anexo'] = is_writable($pasta) ? 'ok (gravavel)' : 'SEM PERMISSAO DE ESCRITA';
    }

    $d['chave_gemini'] = kbApiKey() !== '' ? 'configurada' : 'ausente (so afeta PDF digitalizado)';

    // Schema
    $log = syncGarantirSchema($conn);
    $d['schema'] = implode(' | ', array_filter($log, function ($l) {
        return strpos($l, '[ERRO]') === 0 || strpos($l, '[OK]') === 0;
    })) ?: 'tudo ja existia';

    // Alcance dos portais
    foreach (array('CNJ' => 'https://atos.cnj.jus.br/atos/detalhar/5243',
                   'CGJ/MA' => 'https://www.tjma.jus.br/atos/cgj/geral/0/205/pnao/provimentoscgj') as $nome => $url) {
        try {
            $t0 = microtime(true);
            $body = syncGet($url, $http);
            $d['rede_' . $nome] = sprintf('HTTP %d, %d KB, %.1fs',
                $http, strlen($body) / 1024, microtime(true) - $t0);
        } catch (Throwable $e) {
            $d['rede_' . $nome] = 'FALHOU: ' . $e->getMessage();
        }
    }
    return $d;
}

function status(PDO $conn)
{
    $fontes = $conn->query("SELECT * FROM kb_fontes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fontes as $i => $f) {
        $fontes[$i]['ultima_verif'] = $f['ultima_verif']
            ? date('d/m/Y H:i', strtotime($f['ultima_verif'])) : null;
    }
    $c = $conn->query("SELECT
            SUM(status='novo') novos, SUM(status='atualizado') atualizados,
            SUM(status='importado') importados, SUM(status='erro') erros,
            SUM(status IN ('novo','atualizado') AND prioridade=1) pend_prov,
            SUM(status IN ('novo','atualizado') AND prioridade=2) pend_res
          FROM kb_sync_itens")->fetch(PDO::FETCH_ASSOC);

    return array(
        'fontes'      => $fontes,
        'novos'       => (int) $c['novos'],
        'atualizados' => (int) $c['atualizados'],
        'importados'  => (int) $c['importados'],
        'erros'       => (int) $c['erros'],
        'sem_anexo'   => (int) $conn->query("
            SELECT COUNT(*) FROM kb_sync_itens i JOIN provimentos p ON p.id = i.provimento_id
             WHERE i.status='importado' AND (p.caminho_anexo IS NULL OR p.caminho_anexo='')")
            ->fetchColumn(),
        'pend_prov'   => (int) $c['pend_prov'],
        'pend_res'    => (int) $c['pend_res'],
        'tem_chave'   => kbApiKey() !== '',
    );
}
