<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = $_GET['file'];

    $metaDir = 'meta-dados/';
    $filePath = $metaDir . $file;

    if (file_exists($filePath)) {
        $taskData = json_decode(file_get_contents($filePath), true);
        echo json_encode($taskData);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
