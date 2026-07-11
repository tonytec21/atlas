<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/pagamento_anexos_config.php';
$id = (int)($_GET['id'] ?? 0);
$dl = isset($_GET['download']);
if ($id <= 0) { http_response_code(400); die('ID inválido.'); }
pa_ensure_schema();
$conn = pa_db();
$stmt = $conn->prepare("SELECT * FROM pagamento_os_anexos WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id); $stmt->execute();
$a = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$a) { http_response_code(404); die('Anexo não encontrado.'); }

$base = realpath(pa_dir());
$path = realpath(pa_dir() . '/' . $a['arquivo']);
if ($path === false || strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base)+1) !== 0 || !is_file($path)) { http_response_code(404); die('Arquivo não encontrado.'); }

// Content-Type pela extensão (mime salvo pode estar corrompido em registros antigos)
$aceitos = pa_tipos_aceitos();
$ext  = strtolower(pathinfo($a['arquivo'], PATHINFO_EXTENSION));
$mime = $aceitos[$ext] ?? ($a['mime'] && strpos((string)$a['mime'],'/') !== false ? $a['mime'] : 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
$disp = $dl ? 'attachment' : 'inline';
$nome = preg_replace('~[\r\n"]~', '', (string)$a['nome_original']);
header('Content-Disposition: ' . $disp . '; filename="' . $nome . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
