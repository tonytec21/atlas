<?php
/**
 * Atlas - Upload de imagens para o editor de ofícios
 * Salva em: oficios/imagens/{dir_token}/
 *   - dir_token vem como "NUMERO_ANO" (ex: "25_2025") ou token temporário
 *   - Aceita upload via CKEditor (callback) ou AJAX (JSON)
 */

session_start();

// ======== Configurações ========
$baseUploadDir = __DIR__ . '/imagens';
$maxSize       = 5 * 1024 * 1024; // 5MB
$allowedExt    = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

// ======== Obter dir_token (GET ou POST) ========
$dirToken = '';
if (!empty($_GET['dir_token']))  $dirToken = $_GET['dir_token'];
if (!empty($_POST['dir_token'])) $dirToken = $_POST['dir_token'];

// Sanitizar: apenas letras, números, _ e -
$dirToken = preg_replace('/[^A-Za-z0-9_\-]/', '', $dirToken);

// Fallback: se não veio token, usar ano corrente + random
if (empty($dirToken)) {
    $dirToken = 'tmp_' . date('Y') . '_' . bin2hex(random_bytes(4));
}

// ======== Montar diretório de destino ========
$uploadDir = $baseUploadDir . '/' . $dirToken;

if (!is_dir($baseUploadDir)) {
    @mkdir($baseUploadDir, 0755, true);
}
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// ======== Verificar arquivo ========
$fileField = isset($_FILES['upload']) ? 'upload' : (isset($_FILES['file']) ? 'file' : '');

if (!$fileField || !isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
    _respondError('Nenhum arquivo enviado ou erro no upload.');
}

$file         = $_FILES[$fileField];
$originalName = basename($file['name']);
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

// Validações
if (!in_array($ext, $allowedExt)) {
    _respondError('Formato não permitido. Use: ' . implode(', ', $allowedExt) . '.');
}
if ($file['size'] > $maxSize) {
    _respondError('Arquivo muito grande. Máximo: 5MB.');
}
$check = @getimagesize($file['tmp_name']);
if ($check === false) {
    _respondError('O arquivo não é uma imagem válida.');
}

// ======== Gerar nome único ========
$uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    _respondError('Falha ao salvar o arquivo no servidor.');
}
@chmod($targetPath, 0644);

// ======== URL relativa da imagem (relativa ao módulo oficios) ========
$imageUrl = 'imagens/' . $dirToken . '/' . $uniqueName;

// ======== Responder ========
if (isset($_GET['CKEditorFuncNum'])) {
    // Callback CKEditor 4
    $funcNum = (int) $_GET['CKEditorFuncNum'];
    header('Content-Type: text/html; charset=utf-8');
    echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction($funcNum, '$imageUrl', 'Imagem enviada com sucesso.');</script>";
} else {
    // JSON (modal customizado)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'    => 'success',
        'url'       => $imageUrl,
        'filename'  => $uniqueName,
        'dir_token' => $dirToken,
        'width'     => $check[0],
        'height'    => $check[1],
        'size'      => $file['size']
    ], JSON_UNESCAPED_UNICODE);
}
exit;

/* ---- Helper ---- */
function _respondError($msg) {
    if (isset($_GET['CKEditorFuncNum'])) {
        $funcNum = (int) $_GET['CKEditorFuncNum'];
        header('Content-Type: text/html; charset=utf-8');
        echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction($funcNum, '', '$msg');</script>";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
