<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/pagamento_anexos_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!pa_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    pa_ensure_schema();

    $pid = (int)($_POST['pagamento_id'] ?? 0);
    if ($pid <= 0) throw new RuntimeException('Pagamento inválido.');
    $pg = pa_pagamento($pid);
    if (!$pg) throw new RuntimeException('Pagamento não encontrado.');
    if (!pa_forma_permite_anexo($pg['forma_de_pagamento'])) throw new RuntimeException('Pagamentos em espécie não recebem comprovante.');

    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $map = [UPLOAD_ERR_INI_SIZE=>'Arquivo maior que o permitido.', UPLOAD_ERR_FORM_SIZE=>'Arquivo muito grande.', UPLOAD_ERR_PARTIAL=>'Envio incompleto.', UPLOAD_ERR_NO_FILE=>'Nenhum arquivo enviado.'];
        throw new RuntimeException($map[$_FILES['arquivo']['error'] ?? -1] ?? 'Falha no upload.');
    }
    $f = $_FILES['arquivo'];
    if ($f['size'] > 15 * 1024 * 1024) throw new RuntimeException('O arquivo excede 15 MB.');

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $aceitos = pa_tipos_aceitos();
    if (!isset($aceitos[$ext])) throw new RuntimeException('Formato não aceito. Use PDF, JPG, PNG, GIF ou WEBP.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']) ?: ($aceitos[$ext] ?? 'application/octet-stream');
    if (!in_array($mime, array_values($aceitos), true)) throw new RuntimeException('Conteúdo do arquivo não confere com um PDF/imagem.');

    $novo = 'comp_' . $pid . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = pa_dir() . '/' . $novo;
    if (!move_uploaded_file($f['tmp_name'], $destino)) throw new RuntimeException('Falha ao salvar o arquivo.');
    @chmod($destino, 0644);

    $osId = (int)$pg['ordem_de_servico_id'];
    $nomeOrig = mb_substr((string)$f['name'], 0, 255);
    $por = $_SESSION['username'] ?? null; $agora = date('Y-m-d H:i:s'); $tam = (int)$f['size'];
    $conn = pa_db();
    $stmt = $conn->prepare("INSERT INTO pagamento_os_anexos (pagamento_id, os_id, nome_original, arquivo, mime, tamanho, enviado_por, enviado_em) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssiss', $pid, $osId, $nomeOrig, $novo, $mime, $tam, $por, $agora);
    $stmt->execute(); $id = $stmt->insert_id; $stmt->close();

    echo json_encode(['success'=>true,'id'=>$id,'nome'=>$nomeOrig,'mime'=>$mime,'total'=>count(pa_lista($pid))], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
