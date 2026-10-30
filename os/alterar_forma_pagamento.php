<?php
/**
 * alterar_forma_pagamento.php
 * Troca a forma de pagamento de um pagamento já lançado na O.S.
 * Regra (pagamento_exclusao_lib.php · os_pagto_alteracao): somente o Oficial, no dia do
 * pagamento e com o caixa do colaborador que lançou ainda aberto. O valor não muda.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
require_once __DIR__ . '/pagamento_exclusao_lib.php';
/* AUDITORIA O.S. — registra o antes/depois desta operação */
require_once __DIR__ . '/auditoria_os_lib.php';
osaud_monitorar('pagamento_forma_alterada', ['pagamento_id' => $_POST['pagamento_id'] ?? 0]);
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

function afp_resposta(array $r): void
{
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    afp_resposta(['error' => 'Método inválido']);
}
if (!isset($conn)) {
    afp_resposta(['error' => 'Erro ao conectar ao banco de dados']);
}

$pagamento_id = (int) ($_POST['pagamento_id'] ?? 0);
$nova_forma   = trim((string) ($_POST['nova_forma'] ?? ''));

if (!in_array($nova_forma, os_pagto_formas_troca(), true)) {
    afp_resposta(['error' => 'Escolha uma forma de pagamento válida.']);
}

$st = $conn->prepare('SELECT id, ordem_de_servico_id, funcionario, data_pagamento, forma_de_pagamento, total_pagamento FROM pagamento_os WHERE id = ?');
$st->bind_param('i', $pagamento_id);
$st->execute();
$pag = $st->get_result()->fetch_assoc();
$st->close();
if (!$pag) {
    afp_resposta(['error' => 'Pagamento não encontrado.']);
}

$regra = os_pagto_alteracao($conn, $pag, (string) $_SESSION['username']);
if (!$regra['pode']) {
    afp_resposta(['error' => $regra['motivo'], 'bloqueado' => true]);
}
if ($nova_forma === trim((string) $pag['forma_de_pagamento'])) {
    afp_resposta(['error' => 'O pagamento já está como ' . $nova_forma . '.']);
}

$up = $conn->prepare('UPDATE pagamento_os SET forma_de_pagamento = ? WHERE id = ?');
$up->bind_param('si', $nova_forma, $pagamento_id);
if (!$up->execute()) {
    afp_resposta(['error' => 'Erro ao alterar a forma de pagamento: ' . $conn->error]);
}
$up->close();

afp_resposta([
    'success'    => true,
    'forma'      => $nova_forma,
    'anterior'   => (string) $pag['forma_de_pagamento'],
    'mensagem'   => 'Forma de pagamento alterada de ' . $pag['forma_de_pagamento'] . ' para ' . $nova_forma . '.',
]);
