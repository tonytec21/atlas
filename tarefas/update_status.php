<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = $_POST['fileName'];
    $status = $_POST['status'];

    $metaDir = 'meta-dados/';
    $taskFilePath = $metaDir . $fileName;

    if (file_exists($taskFilePath)) {
        $taskData = json_decode(file_get_contents($taskFilePath), true);
        $taskData['status'] = $status;

        file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
