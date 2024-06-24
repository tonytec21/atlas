<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskFileName = $_POST['taskFile'];
    $taskFilePath = __DIR__ . "/meta-dados/$taskFileName";
    $commentDate = $_POST['commentDate'];

    if (file_exists($taskFilePath)) {
        $taskData = json_decode(file_get_contents($taskFilePath), true);

        if ($taskData) {
            foreach ($taskData['comments'] as &$comment) {
                if ($comment['date'] === $commentDate) {
                    $comment['description'] = $_POST['editCommentDescription'];
                    if (isset($_FILES['editCommentAttachments']['name'][0]) && $_FILES['editCommentAttachments']['name'][0] !== '') {
                        $uploadDir = __DIR__ . '/arquivos/';
                        foreach ($_FILES['editCommentAttachments']['name'] as $key => $name) {
                            $tmpName = $_FILES['editCommentAttachments']['tmp_name'][$key];
                            $filePath = $uploadDir . basename($name);
                            if (move_uploaded_file($tmpName, $filePath)) {
                                $comment['attachments'][] = 'arquivos/' . basename($name);
                            }
                        }
                    }
                    break;
                }
            }

            file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "Comentário atualizado com sucesso.";
        } else {
            http_response_code(500);
            echo "Erro ao carregar os dados da tarefa.";
        }
    } else {
        http_response_code(404);
        echo "Arquivo de tarefa não encontrado.";
    }
} else {
    http_response_code(405);
    echo "Método não permitido.";
}
