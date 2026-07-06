<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function au_fail($h){ echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }
const CAP_MAX = 20971520;
const CAP_ALLOWED = ['pdf'=>1,'png'=>1,'jpg'=>1,'jpeg'=>1,'webp'=>1,'gif'=>1,'txt'=>1,'csv'=>1,'doc'=>1,'docx'=>1,'xls'=>1,'xlsx'=>1,'zip'=>1,'xml'=>1,'ofx'=>1];
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') au_fail('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) au_fail('Sessão expirada.');
    cap_ensure_schema();
    $conta = (int)($_POST['conta_id'] ?? 0);
    if ($conta <= 0) au_fail('Conta inválida.');
    $descricao = substr(trim((string)($_POST['descricao'] ?? '')), 0, 255);
    $conn = cap_db();
    $chk = $conn->prepare("SELECT id FROM contas_a_pagar WHERE id=? LIMIT 1"); $chk->bind_param('i',$conta); $chk->execute();
    if ($chk->get_result()->num_rows === 0) au_fail('Conta não encontrada.'); $chk->close();
    if (empty($_FILES['arquivos'])) au_fail('Nenhum arquivo enviado.');
    $f = $_FILES['arquivos'];
    $names = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps  = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errs  = is_array($f['error']) ? $f['error'] : [$f['error']];
    $sizes = is_array($f['size']) ? $f['size'] : [$f['size']];
    $dir = cap_dir_anexos() . '/' . $conta;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) au_fail('Falha ao preparar pasta.');
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $salvos = [];
    for ($i=0;$i<count($names);$i++){
        if ($errs[$i] !== UPLOAD_ERR_OK) continue;
        if ($sizes[$i] <= 0 || $sizes[$i] > CAP_MAX) au_fail('Arquivo excede 20 MB: ' . htmlspecialchars($names[$i]));
        if (!is_uploaded_file($tmps[$i])) au_fail('Envio inválido.');
        $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
        if (!isset(CAP_ALLOWED[$ext])) au_fail('Tipo não permitido: .' . htmlspecialchars($ext));
        $mime = $finfo ? finfo_file($finfo, $tmps[$i]) : 'application/octet-stream';
        if (preg_match('~php|x-httpd|x-sh|executable~i', (string)$mime)) au_fail('Arquivo rejeitado por segurança.');
        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        if (!@move_uploaded_file($tmps[$i], $dir.'/'.$stored)) au_fail('Falha ao salvar ' . htmlspecialchars($names[$i]));
        @chmod($dir.'/'.$stored, 0644);
        $rel = 'anexos/' . $conta . '/' . $stored; $orig = substr($names[$i],0,255);
        $por = $_SESSION['username'] ?? null; $agora = date('Y-m-d H:i:s'); $tam=(int)$sizes[$i];
        $st = $conn->prepare("INSERT INTO conta_anexos (conta_id,nome_original,arquivo,mime,tamanho,descricao,enviado_por,enviado_em) VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param('isssisss', $conta,$orig,$rel,$mime,$tam,$descricao,$por,$agora);
        $st->execute(); $salvos[] = ['id'=>$st->insert_id,'nome'=>$orig]; $st->close();
    }
    if ($finfo) finfo_close($finfo);
    if (!$salvos) au_fail('Nenhum arquivo válido.');
    echo json_encode(['status'=>'success','arquivos'=>$salvos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){ au_fail('Erro no upload: ' . $e->getMessage()); }
