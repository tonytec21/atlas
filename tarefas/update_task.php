<?php
include(__DIR__ . '/session_check.php');
checkSession();

$taskFile = $_POST['taskFile'];
$taskData = json_decode(file_get_contents($taskFile), true);

// Atualizar os dados da tarefa
$taskData['title'] = $_POST['title'];
$taskData['category'] = $_POST['category'];
$taskData['deadline'] = $_POST['deadline'];
$taskData['employee'] = $_POST['employee'];
$taskData['description'] = $_POST['description'];

// Adicionar novos anexos, se houver
if (!empty($_FILES['attachments']['name'][0])) {
    $uploadDir = 'arquivos/' . uniqid() . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
        $fileName = basename($_FILES['attachments']['name'][$key]);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $filePath)) {
            $taskData['attachments'][] = $filePath;
        }
    }
}

// Salvar o arquivo JSON atualizado
file_put_contents($taskFile, json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'success';
