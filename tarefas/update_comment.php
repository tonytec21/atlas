<?php
include(__DIR__ . '/session_check.php');
checkSession();

$taskFile = $_POST['taskFile'];
$index = $_POST['index'];
$description = $_POST['description'];

$taskData = json_decode(file_get_contents($taskFile), true);

// Atualizar o comentário
$taskData['comments'][$index]['description'] = $description;

// Adicionar novos anexos, se houver
if (!empty($_FILES['editCommentAttachments']['name'][0])) {
    $uploadDir = 'arquivos/' . uniqid() . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($_FILES['editCommentAttachments']['tmp_name'] as $key => $tmpName) {
        $fileName = basename($_FILES['editCommentAttachments']['name'][$key]);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $filePath)) {
            $taskData['comments'][$index]['attachments'][] = $filePath;
        }
    }
}

// Salvar o arquivo JSON atualizado
file_put_contents($taskFile, json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'success';
