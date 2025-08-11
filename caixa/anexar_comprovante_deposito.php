<?php
// anexar_comprovante_deposito.php
header('Content-Type: application/json; charset=utf-8');

try {
    // Sessão e DB
    include(__DIR__ . '/session_check.php');
    checkSession();
    include(__DIR__ . '/db_connection.php');
    date_default_timezone_set('America/Sao_Paulo');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    // Campos obrigatórios
    $depositoId = isset($_POST['deposito_id_anexo']) ? (int)$_POST['deposito_id_anexo'] : 0;
    if ($depositoId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID do depósito inválido.']);
        exit;
    }

    if (!isset($_FILES['arquivo_comprovante']) || $_FILES['arquivo_comprovante']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'error' => 'Selecione um arquivo para anexar.']);
        exit;
    }

    $file = $_FILES['arquivo_comprovante'];

    // Erros de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Erro no upload (código ' . $file['error'] . ').';
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    // Valida tamanho (máx. 10MB)
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        echo json_encode(['success' => false, 'error' => 'Arquivo maior que 10MB.']);
        exit;
    }

    // Valida extensão/MIME
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
    $pathInfo    = pathinfo($file['name']);
    $ext         = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';

    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['success' => false, 'error' => 'Formato inválido. Use PDF, JPG ou PNG.']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mime, $allowedMimes, true)) {
        echo json_encode(['success' => false, 'error' => 'MIME inválido.']);
        exit;
    }

    // Carrega depósito para obter funcionario e data_caixa corretos
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT funcionario, data_caixa, caminho_anexo FROM deposito_caixa WHERE id = :id');
    $stmt->bindValue(':id', $depositoId, PDO::PARAM_INT);
    $stmt->execute();
    $dep = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dep) {
        echo json_encode(['success' => false, 'error' => 'Depósito não encontrado.']);
        exit;
    }

    $funcionario = $dep['funcionario'];
    $dataCaixa   = $dep['data_caixa']; // esperado no formato YYYY-MM-DD

    // Monta diretório no padrão usado pelo front (dd-mm-aa / funcionario)
    $dt = DateTime::createFromFormat('Y-m-d', substr($dataCaixa, 0, 10));
    if (!$dt) {
        echo json_encode(['success' => false, 'error' => 'Data do depósito inválida.']);
        exit;
    }
    $dirDate   = $dt->format('d-m-y'); // igual ao formatDateForDir() do front
    $baseDir   = __DIR__ . '/anexos/' . $dirDate . '/' . $funcionario . '/';

    // Cria pastas se necessário
    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Falha ao criar diretório de anexos.']);
        exit;
    }

    // Gera nome de arquivo único
    $filename = 'comprovante_' . $depositoId . '_' . time() . '.' . $ext;
    $dest     = $baseDir . $filename;

    // Move o arquivo
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'error' => 'Falha ao salvar o arquivo enviado.']);
        exit;
    }

    // (Opcional) Remove anexo anterior se existir e estiver no mesmo diretório
    if (!empty($dep['caminho_anexo'])) {
        $old = $baseDir . $dep['caminho_anexo'];
        if (is_file($old)) { @unlink($old); }
    }

    // Atualiza o registro com o NOME do arquivo (o front monta o caminho)
    $up = $conn->prepare('UPDATE deposito_caixa SET caminho_anexo = :anexo WHERE id = :id');
    $up->bindValue(':anexo', $filename, PDO::PARAM_STR);
    $up->bindValue(':id', $depositoId, PDO::PARAM_INT);
    $up->execute();

    echo json_encode(['success' => true, 'filename' => $filename]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Exceção: ' . $e->getMessage()]);
}
