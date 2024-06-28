<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$metaDataDir = __DIR__ . '/arquivamento/meta-dados';
$categoriesFile = __DIR__ . '/arquivamento/categorias/categorias.json';

$files = glob($metaDataDir . '/*.json');
$totalAtos = count($files);

$dailyAtos = array_fill(0, 7, 0); // 7 days of the week
$weeklyAtos = array_fill(0, 6, 0); // Up to 6 weeks in a month (in some cases)
$monthlyAtos = array_fill(0, 12, 0); // 12 months in a year

$atosByCategory = [];
$atosByUser = [];

if (file_exists($categoriesFile)) {
    $categories = json_decode(file_get_contents($categoriesFile), true);
    foreach ($categories as $category) {
        $atosByCategory[$category] = 0;
    }
}

$now = new DateTime();
$startOfWeek = (clone $now)->modify('last sunday')->setTime(0, 0); // Start of the current week (Sunday)
$endOfWeek = (clone $startOfWeek)->modify('next saturday')->setTime(23, 59, 59); // End of the current week (Saturday)
$twoDaysAgo = (clone $now)->modify('-2 days')->setTime(0, 0); // Start time two days ago

$novosCadastros = 0;

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    
    if (isset($data['data_cadastro'])) {
        // Extract date from 'data_cadastro' field
        $fileDate = DateTime::createFromFormat('Y-m-d H:i:s', $data['data_cadastro']);

        if ($fileDate) {
            $dayOfWeek = $fileDate->format('w'); // 0 (for Sunday) through 6 (for Saturday)
            $firstDayOfMonth = (clone $fileDate)->modify('first day of this month')->format('N'); // 1 (for Monday) through 7 (for Sunday)
            $weekOfMonth = ceil(($fileDate->format('j') + $firstDayOfMonth - 1) / 7); // Week of the month (1-6)
            $monthOfYear = $fileDate->format('n') - 1; // 0 (for January) through 11 (for December)

            if ($fileDate >= $startOfWeek && $fileDate <= $endOfWeek) {
                $dailyAtos[$dayOfWeek]++;
            }
            $weeklyAtos[$weekOfMonth - 1]++;
            $monthlyAtos[$monthOfYear]++;
            
            if ($fileDate >= $twoDaysAgo) {
                $novosCadastros++;
            }
        }
    }

    if (isset($atosByCategory[$data['categoria']])) {
        $atosByCategory[$data['categoria']]++;
    }

    if (isset($data['cadastrado_por'])) {
        $user = $data['cadastrado_por'];
        if (!isset($atosByUser[$user])) {
            $atosByUser[$user] = 0;
        }
        $atosByUser[$user]++;
    }
}

// Processar dados das tarefas
$tarefasStatus = [
    'pendente' => 0,
    'em andamento' => 0,
    'concluída' => 0,
    'cancelada' => 0,
    'iniciada' => 0, // Add 'iniciada' status
];

$sqlTarefas = "SELECT status, COUNT(*) as count FROM tarefas GROUP BY status";
$resultTarefas = $conn->query($sqlTarefas);
while ($row = $resultTarefas->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (isset($tarefasStatus[$status])) {
        $tarefasStatus[$status] = $row['count'];
    }
}

// Buscar tarefas com data limite ultrapassada e prestes a vencer
$overdueTasks = 0;
$upcomingTasks = 0;
$twoDaysLater = (clone $now)->modify('+2 days')->setTime(23, 59, 59);

$sqlOverdueTasks = "SELECT COUNT(*) as count FROM tarefas WHERE data_limite < NOW() AND status != 'Concluída'";
$resultOverdueTasks = $conn->query($sqlOverdueTasks);
if ($resultOverdueTasks) {
    $overdueTasks = $resultOverdueTasks->fetch_assoc()['count'];
}

$sqlUpcomingTasks = "SELECT COUNT(*) as count FROM tarefas WHERE data_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY) AND status != 'Concluída'";
$resultUpcomingTasks = $conn->query($sqlUpcomingTasks);
if ($resultUpcomingTasks) {
    $upcomingTasks = $resultUpcomingTasks->fetch_assoc()['count'];
}

echo json_encode([
    'totalAtos' => $totalAtos,
    'dailyAtos' => $dailyAtos,
    'weeklyAtos' => $weeklyAtos,
    'monthlyAtos' => $monthlyAtos,
    'atosByCategory' => $atosByCategory,
    'atosByUser' => $atosByUser,
    'novosCadastros' => $novosCadastros,
    'tarefasStatus' => $tarefasStatus,
    'overdueTasks' => $overdueTasks,
    'upcomingTasks' => $upcomingTasks
]);
?>
