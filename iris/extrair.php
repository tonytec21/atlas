<?php
error_reporting(0); @ini_set('display_errors', '0'); @set_time_limit(0);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!iris_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Envie um arquivo (imagem ou PDF).');

    $f = $_FILES['arquivo'];
    if ($f['size'] > 20 * 1024 * 1024) throw new RuntimeException('Arquivo muito grande (máx. 20 MB).');

    $bytes = file_get_contents($f['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: $f['type'];
    $aceitos = iris_mimes_aceitos();
    if (!isset($aceitos[$mime])) throw new RuntimeException('Formato não suportado (' . $mime . '). Use PDF, PNG, JPG ou WEBP.');

    if (!iris_tem_chave()) throw new RuntimeException('A chave da API do Gemini ainda não foi configurada. Peça ao administrador.');
    $modelo = iris_modelo_padrao();  // sempre o modelo padrão definido nas configurações
    if (!$modelo) throw new RuntimeException('Nenhum modelo de extração configurado.');

    $cfg = iris_config();
    $prompt = iris_prompt_padrao();
    if (!empty($cfg['prompt_extra'])) $prompt .= "\n\nInstruções adicionais:\n" . $cfg['prompt_extra'];

    $r = iris_gemini_ocr(iris_api_key(), $modelo['identificador'], $bytes, $mime, $prompt);

    echo json_encode(['status' => 'success', 'texto' => $r['texto'], 'truncado' => $r['truncado'],
                      'arquivo' => $f['name'], 'chars' => mb_strlen($r['texto'])], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
