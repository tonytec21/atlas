<?php
// pedidos_certidao/anexos_upload.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

header('Content-Type: application/json; charset=utf-8');

/**
 * Lê id e csrf tanto de POST quanto de GET, para compatibilidade.
 */
$id   = (int)($_POST['id']   ?? $_GET['id']   ?? 0);
$csrf =        $_POST['csrf']?? $_GET['csrf'] ?? '';

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}
if (empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], (string)$csrf)) {
  http_response_code(403);
  echo json_encode(['error' => 'CSRF inválido']);
  exit;
}

$conn = getDatabaseConnection();

/**
 * Caminho do ImageMagick.
 * Se o executável não existir, a conversão de PDF -> JPG é ignorada silenciosamente.
 */
define('IM_CONVERT_WIN', 'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe');
define('IM_CONVERT_LINUX', 'convert');

function im_has_convert(): bool {
  // Windows
  if (stripos(PHP_OS, 'WIN') === 0 && file_exists(IM_CONVERT_WIN)) return true;
  // Linux/Unix: tenta localizar via `which convert`
  $which = @trim((string)@shell_exec('which ' . IM_CONVERT_LINUX));
  return $which !== '';
}
function im_cmd(string $args): string {
  if (stripos(PHP_OS, 'WIN') === 0 && file_exists(IM_CONVERT_WIN)) {
    return '"' . IM_CONVERT_WIN . "\" $args";
  }
  return IM_CONVERT_LINUX . " $args";
}

/**
 * Garante diretório base do pedido
 */
$baseDir = __DIR__ . '/uploads/' . $id;
if (!is_dir($baseDir)) { @mkdir($baseDir, 0777, true); }

/**
 * Normaliza estrutura de $_FILES para lidar com 1 ou N arquivos
 */
if (!isset($_FILES['arquivo'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Nenhum arquivo enviado']);
  exit;
}
$files = [];
if (is_array($_FILES['arquivo']['name'])) {
  $count = count($_FILES['arquivo']['name']);
  for ($i=0; $i<$count; $i++) {
    $files[] = [
      'name'     => $_FILES['arquivo']['name'][$i],
      'type'     => $_FILES['arquivo']['type'][$i],
      'tmp_name' => $_FILES['arquivo']['tmp_name'][$i],
      'error'    => $_FILES['arquivo']['error'][$i],
      'size'     => $_FILES['arquivo']['size'][$i],
    ];
  }
} else {
  $files[] = $_FILES['arquivo'];
}

/**
 * Valida e grava um único arquivo
 */
function handle_one_upload(PDO $conn, int $pedidoId, array $file, string $baseDir): array {
  if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
    return ['success'=>false, 'error' => 'Falha no upload (código '.$file['error'].')'];
  }
  if (!is_uploaded_file($file['tmp_name'])) {
    return ['success'=>false, 'error' => 'Upload inválido'];
  }

  // Tipos permitidos
  $allowed = ['application/pdf','image/jpeg','image/png'];
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']) ?: mime_content_type($file['tmp_name']);
  if (!in_array($mime, $allowed, true)) {
    return ['success'=>false, 'error' => 'Tipo de arquivo não permitido'];
  }

  $origName = $file['name'];
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if ($ext === 'jpeg') $ext = 'jpg';

  // Salvar
  $saveName = uniqid('anx_', true) . '.' . $ext;
  $savePath = $baseDir . '/' . $saveName;
  if (!@move_uploaded_file($file['tmp_name'], $savePath)) {
    return ['success'=>false, 'error' => 'Falha ao salvar arquivo'];
  }

  // Persistir
  $stmt = $conn->prepare("INSERT INTO pedido_anexos (pedido_id, original_filename, mime_type, ext, path, size_bytes)
                          VALUES (?,?,?,?,?,?)");
  $stmt->execute([$pedidoId, $origName, $mime, $ext, $savePath, @filesize($savePath)]);
  $anexoId = (int)$conn->lastInsertId();
  $paginas = null;

  // Se PDF, tenta converter páginas em JPG (se ImageMagick disponível)
  if ($ext === 'pdf' && im_has_convert()) {
    $inQuoted = escapeshellarg($savePath);

    // Conta páginas
    $cmdPages = im_cmd('identify -format %n ' . $inQuoted);
    $pagesOutput = @shell_exec($cmdPages);
    $numPages = (int)trim($pagesOutput ?: '0');
    if ($numPages <= 0) $numPages = 1;

    // Converte
    $outPattern = $baseDir . '/pdf_' . $anexoId . '_page_%03d.jpg';
    $cmdConv = im_cmd('-density 200 ' . $inQuoted . ' -quality 90 -background white -alpha remove -alpha off ' . escapeshellarg($outPattern));
    @shell_exec($cmdConv);

    // Registra imagens
    $imgs = glob($baseDir . '/pdf_' . $anexoId . '_page_*.jpg');
    sort($imgs);
    $pag = 0;
    if ($imgs) {
      $insImg = $conn->prepare("INSERT INTO pedido_anexo_imagens (anexo_id, page_number, path, width, height) VALUES (?,?,?,?,?)");
      foreach ($imgs as $i => $imgPath) {
        $size = @getimagesize($imgPath);
        $w = $size ? ($size[0] ?? null) : null;
        $h = $size ? ($size[1] ?? null) : null;
        $insImg->execute([$anexoId, $i+1, $imgPath, $w, $h]);
        $pag++;
      }
    }
    $paginas = $pag;
    $conn->prepare("UPDATE pedido_anexos SET paginas_pdf=? WHERE id=?")->execute([$paginas, $anexoId]);
  }

  return ['success'=>true, 'id'=>$anexoId, 'paginas'=>$paginas, 'ext'=>$ext, 'name'=>$origName];
}

/**
 * Processa todos os arquivos do request
 */
$results = [];
foreach ($files as $one) {
  $results[] = handle_one_upload($conn, $id, $one, $baseDir);
}

echo json_encode([
  'success' => true,
  'results' => $results
], JSON_UNESCAPED_UNICODE);
