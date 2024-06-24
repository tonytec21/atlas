<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskFileName = $_POST['taskFile'];
    $filePath = $_POST['file'];
    $taskFilePath = __DIR__ . "/meta-dados/$taskFileName";

    if (file_exists($taskFilePath)) {
        $taskData = json_decode(file_get_contents($taskFilePath), true);

        if ($taskData) {
            // Remove o anexo da lista de anexos
            $taskData['attachments'] = array_filter($taskData['attachments'], function ($attachment) use ($filePath) {
                return $attachment !== $filePath;
            });

            // Remove o anexo da lista de anexos de comentários, se aplicável
            foreach ($taskData['comments'] as &$comment) {
                if (isset($comment['attachments'])) {
                    $comment['attachments'] = array_filter($comment['attachments'], function ($attachment) use ($filePath) {
                        return $attachment !== $filePath;
                    });
                }
            }

            file_put_contents($taskFilePath, json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "Anexo excluído com sucesso.";
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
