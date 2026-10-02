<?php
/**
 * Geração do arquivo de carga CRC (usado pelos três gerar_carga*.php).
 * Aceita os IDs como selected_ids[] (formulário antigo) ou ids="1,2,3"
 * (novo, evita o limite max_input_vars do PHP em cargas grandes).
 */
declare(strict_types=1);

require_once __DIR__ . '/crc.php';

function ix_carga_ids(): array
{
    $ids = [];
    if (!empty($_POST['selected_ids']) && is_array($_POST['selected_ids'])) $ids = $_POST['selected_ids'];
    if (!empty($_POST['ids'])) $ids = array_merge($ids, explode(',', (string)$_POST['ids']));
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    return $ids;
}

function ix_carga_alerta(string $msg): void
{
    echo "<script>alert(" . json_encode($msg, JSON_UNESCAPED_UNICODE) . "); window.history.back();</script>";
    exit;
}

function ix_carga_download(string $tipoKey): void
{
    if (!ix_logged()) ix_carga_alerta('Sessão expirada. Faça login novamente.');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ix_carga_alerta('Nenhum registro selecionado.');

    $T = ix_tipo($tipoKey);
    $ids = ix_carga_ids();
    if (!$ids) ix_carga_alerta('Nenhum registro selecionado.');

    @set_time_limit(600);
    $db = ix_db();
    $rows = [];
    foreach (array_chunk($ids, 1000) as $chunk) {
        $st = $db->prepare("SELECT * FROM `{$T['table']}` WHERE status = ? AND id IN (" . implode(',', $chunk) . ") ORDER BY id ASC");
        $st->bind_param('s', $T['status_ativo']);
        $st->execute();
        $rows = array_merge($rows, $st->get_result()->fetch_all(MYSQLI_ASSOC));
        $st->close();
    }
    usort($rows, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

    // Opcional: deixa de fora registros que não passam na validação
    if (!empty($_POST['somente_validos'])) {
        $res = crc_audit_rows($T, $rows);
        $rows = array_values(array_filter($rows, fn($r) => empty($res[(int)$r['id']]['errors'])));
    }
    if (!$rows) ix_carga_alerta('Nenhum registro válido encontrado para gerar a carga.');

    $doc = crc_build($T['crc_xml'], $rows);
    $meta = crc_meta($T['crc_xml']);
    $xml = $doc->saveXML();

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $meta['arquivo'] . '"');
    header('Content-Length: ' . strlen($xml));
    header('Cache-Control: no-store');
    echo $xml;
    exit;
}
