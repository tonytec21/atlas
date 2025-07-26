<?php
session_start();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');

function checkSession() {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['error'=>'Sessão expirada']); exit;
    }
}
checkSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error'=>'Método inválido']); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) { echo json_encode(['error'=>'ID inválido']); exit; }

// Campos
$titulo        = $_POST['titulo'] ?? '';
$descricao     = $_POST['descricao'] ?? '';
$funcionario_id = ($_POST['funcionario_id'] === '' ? null : (int)$_POST['funcionario_id']);
$rec_type      = $_POST['recurrence_type'] ?? 'diaria';
$dia_semana    = isset($_POST['dia_semana']) && $_POST['dia_semana'] !== '' ? (int)$_POST['dia_semana'] : null;
$dia_mes       = isset($_POST['dia_mes']) && $_POST['dia_mes'] !== '' ? (int)$_POST['dia_mes'] : null;
$hora_execucao = $_POST['hora_execucao'] ?? '';
$inicio        = $_POST['inicio_vigencia'] ?? '';
$fim           = $_POST['fim_vigencia'] ?: null;
$obrigatoria   = isset($_POST['obrigatoria']) ? (int)$_POST['obrigatoria'] : 0;

if ($rec_type !== 'semanal') $dia_semana = null;
if ($rec_type !== 'mensal')  $dia_mes    = null;

// Atualizar
$sql = "UPDATE tarefas_recorrentes
        SET titulo=?, descricao=?, funcionario_id=?, recurrence_type=?, dia_semana=?, dia_mes=?, 
            hora_execucao=?, inicio_vigencia=?, fim_vigencia=?, obrigatoria=?, proxima_execucao=NULL
        WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssississiii",
    $titulo, $descricao, $funcionario_id, $rec_type, $dia_semana, $dia_mes,
    $hora_execucao, $inicio, $fim, $obrigatoria, $id
);
if(!$stmt->execute()){
    echo json_encode(['error'=>'Erro ao atualizar tarefa']); exit;
}

// Se não estiver suspensa (tem funcionário), recalcular primeira ocorrência
if (!is_null($funcionario_id)) {
    // Buscar tarefa completa
    $tarefa = $conn->query("SELECT * FROM tarefas_recorrentes WHERE id=$id")->fetch_assoc();
    if ($tarefa) {
        $primeira = calcularPrimeiraExecucao($tarefa);
        if ($primeira) {
            $primeiraStr = $primeira->format('Y-m-d H:i:s');
            $upd = $conn->prepare("UPDATE tarefas_recorrentes SET proxima_execucao=? WHERE id=?");
            $upd->bind_param("si", $primeiraStr, $id);
            $upd->execute();
        }
    }
}

echo json_encode(['success'=>true, 'suspensa'=>is_null($funcionario_id)]);
exit;

/** Calcula a primeira execução */
function calcularPrimeiraExecucao(array $t){
    $inicio = new DateTime($t['inicio_vigencia'].' '.$t['hora_execucao']);
    switch ($t['recurrence_type']) {
        case 'diaria':
        case 'quinzenal':
        case 'trimestral':
            return $inicio;
        case 'semanal':
            $targetDow = (int)$t['dia_semana']; // 0=Dom..6=Sáb
            $dt = clone $inicio;
            while ((int)$dt->format('w') !== $targetDow) {
                $dt->modify('+1 day');
            }
            return $dt;
        case 'mensal':
            $diaMes = (int)$t['dia_mes'];
            $base   = clone $inicio;
            $diasNoMes = (int)$base->format('t');
            $diaAjustado = min($diaMes, $diasNoMes);
            $primeira = new DateTime($base->format('Y-m-').str_pad($diaAjustado,2,'0',STR_PAD_LEFT).' '.$t['hora_execucao']);
            if ($primeira < $base) {
                $primeira->modify('first day of next month');
                $diasNoMes = (int)$primeira->format('t');
                $diaAjustado = min($diaMes, $diasNoMes);
                $primeira->setDate($primeira->format('Y'), $primeira->format('m'), $diaAjustado);
            }
            return $primeira;
    }
    return null;
}
