<?php
if (isset($_POST['fileName']) && isset($_POST['commentDescription']) && isset($_POST['employee']) && isset($_POST['date'])) {
    $fileName = $_POST['fileName'];
    $commentDescription = $_POST['commentDescription'];
    $employee = $_POST['employee'];
    $date = $_POST['date'];

    $taskData = json_decode(file_get_contents($fileName), true);

    $comment = [
        'description' => $commentDescription,
        'employee' => $employee,
        'date' => $date,
        'attachments' => []
    ];

    // Adicionar anexos ao comentário
    if (!empty($_FILES['commentAttachments']['name'][0])) {
        $uploadDir = 'arquivos/' . uniqid() . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['commentAttachments']['tmp_name'] as $key => $tmpName) {
            $fileName = basename($_FILES['commentAttachments']['name'][$key]);
            $filePath = $uploadDir . $fileName;
            move_uploaded_file($tmpName, $filePath);
            $comment['attachments'][] = $filePath;
        }
    }

    $taskData['comments'][] = $comment;

    // Salvar o arquivo JSON atualizado
    file_put_contents($fileName, json_encode($taskData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
}
?>
