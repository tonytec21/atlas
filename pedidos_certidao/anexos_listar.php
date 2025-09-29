<?php
// pedidos_certidao/anexos_listar.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

$id   = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';
if ($id<=0) { echo json_encode(['error'=>'ID inválido']); exit; }
if (empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $csrf)) {
  echo json_encode(['error'=>'CSRF inválido']); exit;
}

$conn = getDatabaseConnection();
$st = $conn->prepare("SELECT id, original_filename, mime_type, ext, path, size_bytes, paginas_pdf FROM pedido_anexos WHERE pedido_id=? ORDER BY id DESC");
$st->execute([$id]);
$itens = [];
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  $thumbUrl = null;
  if ($r['ext'] !== 'pdf') {
    // usar o próprio arquivo como thumb
    $thumbUrl = 'anexos_stream.php?acao=thumb&pedido='.$id.'&anexo='.$r['id'].'&csrf='.urlencode($_SESSION['csrf_pedidos']);
  } else {
    // se existir primeira página convertida, usar como thumb
    $baseDir = __DIR__ . '/uploads/' . $id;
    $first = glob($baseDir . '/pdf_' . $r['id'] . '_page_001.jpg');
    if ($first && file_exists($first[0])) {
      $thumbUrl = 'anexos_stream.php?acao=imgpdf&pedido='.$id.'&anexo='.$r['id'].'&pagina=1&csrf='.urlencode($_SESSION['csrf_pedidos']);
    }
  }

  $sizeHuman = number_format(($r['size_bytes']/1024/1024), 2, ',', '.') . ' MB';

  $itens[] = [
    'id' => (int)$r['id'],
    'original_filename' => $r['original_filename'],
    'ext' => $r['ext'],
    'mime_type' => $r['mime_type'],
    'size_human' => $sizeHuman,
    'paginas_pdf' => $r['paginas_pdf'] ? (int)$r['paginas_pdf'] : null,
    'thumbnail_url' => $thumbUrl
  ];
}

echo json_encode(['success'=>true, 'itens'=>$itens]);
