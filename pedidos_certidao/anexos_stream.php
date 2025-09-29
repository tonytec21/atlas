<?php
// pedidos_certidao/anexos_stream.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$acao   = $_GET['acao']   ?? 'inline'; // inline | download | thumb | imgpdf
$pedido = (int)($_GET['pedido'] ?? 0);
$anexo  = (int)($_GET['anexo']  ?? 0);
$csrf   = $_GET['csrf'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);

if ($pedido<=0 || $anexo<=0) { http_response_code(400); echo 'Parâmetros inválidos'; exit; }
if (empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $csrf)) {
  http_response_code(403); echo 'CSRF inválido'; exit;
}

$conn = getDatabaseConnection();
$st = $conn->prepare("SELECT * FROM pedido_anexos WHERE id=? AND pedido_id=? LIMIT 1");
$st->execute([$anexo, $pedido]);
$ax = $st->fetch(PDO::FETCH_ASSOC);
if (!$ax) { http_response_code(404); echo 'Anexo não encontrado'; exit; }

$path = $ax['path'];
if (!file_exists($path)) { http_response_code(404); echo 'Arquivo ausente'; exit; }

$mime = $ax['mime_type'];
$name = $ax['original_filename'];
$ext  = strtolower($ax['ext']);

function streamFile($path, $mime, $name, $disposition='inline'){
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($path));
  header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"');
  header('X-Content-Type-Options: nosniff');
  readfile($path);
  exit;
}

switch ($acao) {
  case 'download':
    streamFile($path, $mime, $name, 'attachment');
    break;

  case 'thumb':
    if ($ext==='pdf') {
      // tenta primeira página convertida
      $baseDir = __DIR__ . '/uploads/' . $pedido;
      $first = glob($baseDir . '/pdf_' . $ax['id'] . '_page_001.jpg');
      if ($first && file_exists($first[0])) {
        streamFile($first[0], 'image/jpeg', basename($first[0]), 'inline');
      } else {
        // fallback: PNG transparente de 1x1
        header('Content-Type: image/png');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
      }
    } else {
      // miniatura da própria imagem
      streamFile($path, $mime, $name, 'inline');
    }
    break;

  case 'imgpdf':
    // retorna uma página JPG gerada de um PDF
    if ($ext!=='pdf') { http_response_code(400); echo 'Não é PDF'; exit; }
    $baseDir = __DIR__ . '/uploads/' . $pedido;
    $file = $baseDir . '/pdf_' . $ax['id'] . '_page_' . str_pad($pagina, 3, '0', STR_PAD_LEFT) . '.jpg';
    if (!file_exists($file)) { http_response_code(404); echo 'Página não encontrada'; exit; }
    streamFile($file, 'image/jpeg', basename($file), 'inline');
    break;

  case 'inline':
  default:
    // para imagem: exibir inline
    // para PDF: exibir inline e deixar o navegador carregar visualizador nativo
    streamFile($path, $mime, $name, 'inline');
    break;
}
