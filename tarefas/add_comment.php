<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = $_POST['fileName'];
    $description = $_POST['commentDescription'];
    $attachments = $_FILES['commentAttachments'];

    $metaDir = 'meta-dados/';
    $taskFilePath = $metaDir . $fileName;

    if (file_exists($taskFilePath)) {
        $taskData = json_decode(file_get_contents($taskFilePath), true);

        $commentData = [
            'employee' => $_SESSION['username'],
            'date' => date('Y-m-d H:i:s'), // Salva a data no formato americano
            'description' => $description,
            'attachments' => []
        ];

        $uploadDir = 'arquivos/' . pathinfo($fileName, PATHINFO_FILENAME) . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        for ($i = 0; $i < count($attachments['name']); $i++) {
            $tmpName = $attachments['tmp_name'][$i];
            $attachmentName = basename($attachments['name'][$i]);
            $filePath = $uploadDir . $attachmentName;

            if (move_uploaded_file($tmpName, $filePath)) {
                $commentData['attachments'][] = $filePath;
            }
        }

        if (!isset($taskData['comments'])) {
            $taskData['comments'] = [];
        }

        $taskData['comments'][] = $commentData;

        file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
