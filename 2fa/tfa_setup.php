<?php
/**
 * Configuração da verificação em duas etapas (2FA / TOTP).
 * Usado via AJAX pela página atualizar-credenciais.php.
 * Ações: iniciar | ativar | desativar | status
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../lib/totp.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}
$usuario = $_SESSION['username'];

$conn = new mysqli('localhost', 'root', '', 'atlas');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Falha na conexão com o banco.']);
    exit;
}
$conn->set_charset('utf8');

$stmt = $conn->prepare('SELECT id, usuario, tfa_secret, tfa_enabled FROM funcionarios WHERE usuario = ?');
$stmt->bind_param('s', $usuario);
$stmt->execute();
$func = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$func) {
    echo json_encode(['ok' => false, 'erro' => 'Funcionário não encontrado.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'status':
        echo json_encode(['ok' => true, 'enabled' => (int) $func['tfa_enabled'] === 1]);
        break;

    case 'iniciar':
        // Gera um segredo novo e guarda na sessão até a confirmação (não persiste ainda)
        $secret = TOTP::generateSecret();
        $_SESSION['tfa_setup_secret'] = $secret;
        echo json_encode([
            'ok'      => true,
            'secret'  => $secret,
            'otpauth' => TOTP::provisioningUri($secret, $func['usuario'], 'Atlas'),
        ]);
        break;

    case 'ativar':
        $code   = $_POST['code'] ?? '';
        $secret = $_SESSION['tfa_setup_secret'] ?? '';
        if ($secret === '') {
            echo json_encode(['ok' => false, 'erro' => 'Sessão de configuração expirada. Inicie novamente.']);
            break;
        }
        if (!TOTP::verify($secret, $code, 1)) {
            echo json_encode(['ok' => false, 'erro' => 'Código inválido. Verifique o app autenticador e tente novamente.']);
            break;
        }
        $st = $conn->prepare('UPDATE funcionarios SET tfa_secret = ?, tfa_enabled = 1 WHERE usuario = ?');
        $st->bind_param('ss', $secret, $usuario);
        $st->execute();
        $st->close();
        unset($_SESSION['tfa_setup_secret']);
        echo json_encode(['ok' => true]);
        break;

    case 'desativar':
        // Exige um código válido do app para desativar (prova de posse do dispositivo)
        if (empty($func['tfa_enabled']) || empty($func['tfa_secret'])) {
            echo json_encode(['ok' => true]); // já está desativado
            break;
        }
        $code = $_POST['code'] ?? '';
        if (!TOTP::verify($func['tfa_secret'], $code, 1)) {
            echo json_encode(['ok' => false, 'erro' => 'Código inválido. Não foi possível desativar a verificação.']);
            break;
        }
        $st = $conn->prepare('UPDATE funcionarios SET tfa_enabled = 0, tfa_secret = NULL WHERE usuario = ?');
        $st->bind_param('s', $usuario);
        $st->execute();
        $st->close();
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'erro' => 'Ação inválida.']);
}
$conn->close();
