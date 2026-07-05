<?php
/** anexos_upload.php — recebe arquivos (comprovantes etc.) de uma nota. */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
header('Content-Type: application/json; charset=utf-8');

function au_fail($h){ echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }

const ND_MAX_BYTES = 20971520; // 20 MB
const ND_ALLOWED = [
    'pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
    'webp'=>'image/webp','gif'=>'image/gif','txt'=>'text/plain','csv'=>'text/csv',
    'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip'=>'application/zip','p7s'=>'application/pkcs7-signature','xml'=>'application/xml',
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') au_fail('Método inválido.');
    if (!nd_csrf_check($_POST['csrf'] ?? '')) au_fail('Sessão expirada. Recarregue a página.');
    nd_ensure_schema();

    $numero = trim((string)($_POST['numero'] ?? ''));
    if ($numero === '') au_fail('Número da nota não informado.');
    $descricao = substr(trim((string)($_POST['descricao'] ?? '')), 0, 255);

    $conn = nd_db();
    $stmt = $conn->prepare("SELECT id FROM notas_devolutivas WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) au_fail('Nota devolutiva não encontrada.');
    $stmt->close();

    if (empty($_FILES['arquivos'])) au_fail('Nenhum arquivo enviado.');
    $files = $_FILES['arquivos'];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmps  = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errs  = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    $dir = nd_dir_anexos() . '/' . nd_safe($numero);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) au_fail('Falha ao preparar pasta de anexos.');
    // .htaccess defensivo (impede execução de PHP na pasta de anexos)
    if (!is_file(nd_dir_anexos() . '/.htaccess')) @file_put_contents(nd_dir_anexos() . '/.htaccess', "php_flag engine off\nOptions -Indexes\n");

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $salvos = [];
    for ($i = 0; $i < count($names); $i++) {
        if ($errs[$i] !== UPLOAD_ERR_OK) continue;
        if ($sizes[$i] <= 0 || $sizes[$i] > ND_MAX_BYTES) au_fail('Arquivo excede 20 MB: ' . htmlspecialchars($names[$i]));
        if (!is_uploaded_file($tmps[$i])) au_fail('Envio inválido.');

        $orig = $names[$i];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(ND_ALLOWED[$ext])) au_fail('Tipo não permitido: .' . htmlspecialchars($ext));
        $mime = $finfo ? finfo_file($finfo, $tmps[$i]) : (ND_ALLOWED[$ext]);
        // sanidade: não permitir scripts disfarçados
        if (preg_match('~php|x-httpd|x-sh|executable~i', (string)$mime)) au_fail('Arquivo rejeitado por segurança.');

        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        if (!@move_uploaded_file($tmps[$i], $dir . '/' . $stored)) au_fail('Falha ao salvar ' . htmlspecialchars($orig));
        @chmod($dir . '/' . $stored, 0644);

        $rel = 'anexos/' . nd_safe($numero) . '/' . $stored;
        $origSafe = substr($orig, 0, 255);
        $enviadoPor = $_SESSION['username'] ?? null; $agora = date('Y-m-d H:i:s');
        $st = $conn->prepare("INSERT INTO nota_anexos (nota_numero, nome_original, arquivo, mime, tamanho, descricao, enviado_por, enviado_em) VALUES (?,?,?,?,?,?,?,?)");
        $tam = (int)$sizes[$i];
        $st->bind_param('ssssisss', $numero, $origSafe, $rel, $mime, $tam, $descricao, $enviadoPor, $agora);
        $st->execute();
        $salvos[] = ['id'=>$st->insert_id,'nome'=>$origSafe,'tamanho'=>$tam,'mime'=>$mime];
        $st->close();
    }
    if ($finfo) finfo_close($finfo);
    if (!$salvos) au_fail('Nenhum arquivo válido foi enviado.');
    nd_log("Anexos enviados p/ nota {$numero}: " . count($salvos));
    echo json_encode(['status'=>'success','arquivos'=>$salvos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    au_fail('Erro no upload: ' . $e->getMessage());
}
