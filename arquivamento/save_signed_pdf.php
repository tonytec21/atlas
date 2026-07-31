<?php
/**
 * Gravação de PDF assinado.
 *
 * A versão anterior aceitava qualquer nome de arquivo vindo do cliente e
 * gravava o conteúdo direto no disco — o que permitia escrever um .php
 * dentro do servidor. Agora o nome é gerado aqui e o conteúdo precisa ser
 * de fato um PDF.
 */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();

header('Content-Type: application/json; charset=utf-8');

$dados = isset($_POST['pdfData']) ? (string) $_POST['pdfData'] : '';
if ($dados === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Conteudo ausente.']);
    exit;
}

$bin = base64_decode($dados, true);
if ($bin === false || strncmp($bin, '%PDF-', 5) !== 0) {
    http_response_code(415);
    echo json_encode(['message' => 'O conteudo enviado nao e um PDF valido.']);
    exit;
}
if (strlen($bin) > ARQ_UPLOAD_MAX_BYTES) {
    http_response_code(413);
    echo json_encode(['message' => 'Arquivo acima do limite permitido.']);
    exit;
}

$dir = __DIR__ . '/arquivos-assinados';
if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
    http_response_code(500);
    echo json_encode(['message' => 'Nao foi possivel criar a pasta de destino.']);
    exit;
}

$sugerido = arq_nome_seguro(isset($_POST['fileName']) ? $_POST['fileName'] : 'documento');
$base = pathinfo($sugerido, PATHINFO_FILENAME);
if ($base === '') { $base = 'documento'; }
$nome = arq_nome_unico($dir, date('Ymd-His') . '-' . $base . '.pdf');
$destino = $dir . DIRECTORY_SEPARATOR . $nome;

if (@file_put_contents($destino, $bin, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao salvar o PDF.']);
    exit;
}
@chmod($destino, 0640);

arq_auditar('assinar', $nome, ['bytes' => strlen($bin)]);
echo json_encode(['message' => 'PDF assinado salvo com sucesso.', 'arquivo' => $nome]);
