<?php
function searchTasks($queryParams) {
    $metaDir = 'meta-dados/';
    $taskFiles = glob($metaDir . '*.json');
    $results = [];

    foreach ($taskFiles as $taskFile) {
        $taskData = json_decode(file_get_contents($taskFile), true);
        $taskData['fileName'] = basename($taskFile);

        $match = true;

        foreach ($queryParams as $key => $value) {
            if (!empty($value) && strpos(strtolower($taskData[$key]), strtolower($value)) === false) {
                $match = false;
                break;
            }
        }

        if ($match) {
            $results[] = $taskData;
        }
    }

    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $queryParams = [
        'title' => $_GET['title'] ?? '',
        'category' => $_GET['category'] ?? '',
        'deadline' => $_GET['deadline'] ?? '',
        'employee' => $_GET['employee'] ?? '',
        'status' => $_GET['status'] ?? '',
        'description' => $_GET['description'] ?? ''
    ];

    $tasks = searchTasks($queryParams);

    echo json_encode($tasks);
}
?>
