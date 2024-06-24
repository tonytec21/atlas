<?php
include(__DIR__ . '/session_check.php');
checkSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskFile = isset($_POST['taskFile']) ? $_POST['taskFile'] : '';
    $taskFilePath = __DIR__ . "/meta-dados/$taskFile";

    if (!file_exists($taskFilePath)) {
        http_response_code(404);
        echo "Arquivo de tarefa não encontrado.";
        exit;
    }

    $taskData = json_decode(file_get_contents($taskFilePath), true);
    if (!$taskData) {
        http_response_code(500);
        echo "Erro ao carregar os dados da tarefa.";
        exit;
    }

    $taskData['title'] = isset($_POST['title']) ? $_POST['title'] : $taskData['title'];
    $taskData['category'] = isset($_POST['category']) ? $_POST['category'] : $taskData['category'];
    $taskData['deadline'] = isset($_POST['deadline']) ? $_POST['deadline'] : $taskData['deadline'];
    $taskData['employee'] = isset($_POST['employee']) ? $_POST['employee'] : $taskData['employee'];
    $taskData['description'] = isset($_POST['description']) ? $_POST['description'] : $taskData['description'];

    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            $fileName = basename($_FILES['attachments']['name'][$key]);
            $filePath = __DIR__ . "/arquivos/$fileName";

            if (move_uploaded_file($tmp_name, $filePath)) {
                $taskData['attachments'][] = "arquivos/$fileName";
            }
        }
    }

    file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Tarefa salva com sucesso.";
} else {
    http_response_code(405);
    echo "Método não permitido.";
}
