<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $deadline = $_POST['deadline'];
    $employee = $_POST['employee'];
    $description = $_POST['description'];
    $attachments = $_FILES['attachments'];
    $createdBy = $_SESSION['username'];
    $createdAt = date('Y-m-d H:i:s'); // Salva a data no formato americano

    $taskData = [
        'title' => $title,
        'category' => $category,
        'deadline' => $deadline,
        'employee' => $employee,
        'description' => $description,
        'status' => 'Iniciada',
        'createdBy' => $createdBy,
        'createdAt' => $createdAt,
        'attachments' => []
    ];

    $metaDir = 'meta-dados/';
    if (!file_exists($metaDir)) {
        mkdir($metaDir, 0777, true);
    }

    $taskFileName = uniqid() . '.json';
    $taskFilePath = $metaDir . $taskFileName;

    // Criar subdiretório para os anexos
    $uploadDir = 'arquivos/' . pathinfo($taskFileName, PATHINFO_FILENAME) . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    for ($i = 0; $i < count($attachments['name']); $i++) {
        $tmpName = $attachments['tmp_name'][$i];
        $fileName = basename($attachments['name'][$i]);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $filePath)) {
            $taskData['attachments'][] = $filePath;
        }
    }

    file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT));

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
