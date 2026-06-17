<?php
// os/entregar_os.php
// Marca a O.S. (totalmente liquidada) como ENTREGUE ao cliente, registra quem
// recebeu e atualiza o status do rastreio na API para 'entregue'.

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php'); // getDatabaseConnection() -> PDO
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');
if (function_exists('ob_start')) { ob_start(); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// ===== Validadores de CPF/CNPJ =====
function validarCPF_BR($cpf) {
    $cpf = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += (int)$cpf[$i] * (($t + 1) - $i);
        $d = ((10 * $soma) % 11) % 10;
        if ((int)$cpf[$t] !== $d) return false;
    }
    return true;
}
function validarCNPJ_BR($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string)$cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    $calc = function ($base) {
        $mult = strlen($base) - 7; $soma = 0;
        for ($i = 0; $i < strlen($base); $i++) {
            $soma += (int)$base[$i] * $mult;
            $mult = ($mult - 1 < 2) ? 9 : $mult - 1;
        }
        $r = $soma % 11;
        return ($r < 2) ? 0 : 11 - $r;
    };
    $d1 = $calc(substr($cnpj, 0, 12));
    if ((int)$cnpj[12] !== $d1) return false;
    $d2 = $calc(substr($cnpj, 0, 13));
    return (int)$cnpj[13] === $d2;
}
function validarDocumentoBR($doc) {
    $doc = preg_replace('/\D/', '', (string)$doc);
    if (strlen($doc) === 11) return validarCPF_BR($doc);
    if (strlen($doc) === 14) return validarCNPJ_BR($doc);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Método inválido']); exit;
}
// CSRF (mesmo token usado nas chamadas ao módulo de pedidos)
if (empty($_POST['csrf']) || empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $_POST['csrf'])) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Falha de CSRF.']); exit;
}

$os_id        = isset($_POST['os_id']) ? (int)$_POST['os_id'] : 0;
$recebido_por = trim((string)($_POST['recebido_por'] ?? ''));
$recebido_doc = trim((string)($_POST['recebido_doc'] ?? ''));
$observacoes  = trim((string)($_POST['observacoes'] ?? ''));
$usuario      = $_SESSION['username'] ?? 'sistema';

if ($os_id <= 0) { if (ob_get_length()) ob_clean(); echo json_encode(['error' => 'O.S. inválida.']); exit; }
if ($recebido_por === '') { if (ob_get_length()) ob_clean(); echo json_encode(['error' => 'Informe o nome de quem recebeu.']); exit; }

// Validação do CPF/CNPJ do receptor (opcional; se informado, precisa ser válido)
$recebido_doc = preg_replace('/\D/', '', $recebido_doc);
if ($recebido_doc !== '') {
    if (!validarDocumentoBR($recebido_doc)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['error' => 'CPF/CNPJ do receptor é inválido.']); exit;
    }
}

try {
    $pdo = getDatabaseConnection();

    // 1) Confere se a O.S. existe e está TOTALMENTE liquidada
    //    Usa o MESMO critério da tela (visualizar_os.php): item liquidado = status 'liquidado'.
    //    (Antes usava quantidade_liquidada >= quantidade, que diverge quando o status é
    //     'liquidado' mas quantidade_liquidada está NULL, bloqueando indevidamente a entrega.)
    $st = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) AS concluidos
          FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
    $st->execute([$os_id]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'concluidos' => 0];
    $total = (int)$r['total']; $concl = (int)$r['concluidos'];

    if ($total === 0) { if (ob_get_length()) ob_clean(); echo json_encode(['error' => 'A O.S. não possui itens.']); exit; }
    if ($concl < $total) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['error' => 'A O.S. ainda não está totalmente liquidada.']); exit;
    }

    // 2) Registra a entrega (tabela própria)
    $pdo->exec("CREATE TABLE IF NOT EXISTS os_entregas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ordem_servico_id INT NOT NULL,
        recebido_por VARCHAR(255) NOT NULL,
        recebido_doc VARCHAR(40) NULL,
        observacoes VARCHAR(1000) NULL,
        entregue_por VARCHAR(120) NOT NULL,
        data_entrega DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_os (ordem_servico_id),
        INDEX idx_os (ordem_servico_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // upsert (uma entrega por O.S.; nova marcação atualiza os dados)
    $up = $pdo->prepare("INSERT INTO os_entregas (ordem_servico_id, recebido_por, recebido_doc, observacoes, entregue_por)
                         VALUES (:os,:rp,:rd,:obs,:user)
                         ON DUPLICATE KEY UPDATE
                            recebido_por=VALUES(recebido_por),
                            recebido_doc=VALUES(recebido_doc),
                            observacoes=VALUES(observacoes),
                            entregue_por=VALUES(entregue_por),
                            data_entrega=NOW()");
    $up->execute([
        ':os' => $os_id, ':rp' => $recebido_por,
        ':rd' => ($recebido_doc !== '' ? $recebido_doc : null),
        ':obs' => ($observacoes !== '' ? $observacoes : null),
        ':user' => $usuario
    ]);

    // 3) Rastreio: garante 'emitida' e marca 'entregue' (envia à API)
    require_once(__DIR__ . '/../pedidos_certidao/os_rastreio_lib.php');
    os_rastreio_sync_liquidacao($pdo, $os_id, $usuario);   // -> emitida
    $resEntrega = os_rastreio_entregar($pdo, $os_id, $recebido_por, $usuario, $observacoes); // -> entregue

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success'      => true,
        'status'       => 'entregue',
        'recebido_por' => $recebido_por,
        'protocolo'    => $resEntrega['protocolo'] ?? null
    ]);
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Erro ao registrar entrega: ' . $e->getMessage()]);
}
