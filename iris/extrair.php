<?php
/**
 * Atlas Iris — endpoint de compatibilidade (v1): extrai o texto de um arquivo inteiro numa única chamada.
 * A interface 2.x usa api.php (página a página). Mantido para integrações de outros módulos.
 * POST: csrf, arquivo (PDF/imagem até 14 MB)  →  {status, texto, truncado, arquivo, chars}
 */
error_reporting(0); @ini_set('display_errors', '0'); @set_time_limit(0);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!iris_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Envie um arquivo (imagem ou PDF).');

    $f = $_FILES['arquivo'];
    if ($f['size'] > 14.5 * 1024 * 1024) throw new RuntimeException('Arquivo muito grande para envio direto (máx. 14 MB). Use a tela do Iris, que processa página a página.');

    $bytes = file_get_contents($f['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: $f['type'];
    if (!isset(iris_mimes_aceitos()[$mime])) throw new RuntimeException('Formato não suportado (' . $mime . '). Use PDF, PNG, JPG ou WEBP.');
    if (!iris_tem_chave()) throw new RuntimeException('A chave da API do Gemini ainda não foi configurada. Peça ao administrador.');

    $modelo = iris_modelo_escolhido($_POST['modelo'] ?? '');
    $cfg = iris_config();
    $prompt = iris_prompt_transcricao(['vocabulario' => $cfg['vocabulario'] ?? '', 'prompt_extra' => $cfg['prompt_extra'] ?? '']);
    if ($mime === 'application/pdf') $prompt = str_replace('da página anexada', 'do documento anexado (todas as páginas, na ordem)', $prompt);

    try {
        $r = iris_gemini_gerar(iris_api_key(), [['bytes' => $bytes, 'mime' => $mime, 'res' => null], ['text' => $prompt]],
                               ['modelo' => $modelo, 'reserva' => $cfg['modelo_reserva'] ?? '', 'thinking' => 'high', 'resolucao' => 'high']);
        iris_registrar_chamada('transcrever', $modelo, $r);
    } catch (Throwable $e) { iris_registrar_chamada('transcrever', $modelo, null, $e->getMessage()); throw $e; }

    echo json_encode(['status' => 'success', 'texto' => trim($r['texto']), 'truncado' => $r['truncado'],
                      'arquivo' => $f['name'], 'chars' => mb_strlen($r['texto'])], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
