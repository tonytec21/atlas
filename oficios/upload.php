<?php
$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["upload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Verifique se o arquivo é uma imagem real ou uma imagem falsa
$check = getimagesize($_FILES["upload"]["tmp_name"]);
if ($check !== false) {
    $uploadOk = 1;
} else {
    echo "File is not an image.";
    $uploadOk = 0;
}

// Verifique se o arquivo já existe
if (file_exists($target_file)) {
    echo "Sorry, file already exists.";
    $uploadOk = 0;
}

// Verifique o tamanho do arquivo
if ($_FILES["upload"]["size"] > 500000) {
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
}

// Permitir certos formatos de arquivo
if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    $uploadOk = 0;
}

// Verifique se $uploadOk é 0 por causa de um erro
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
// Se tudo estiver ok, tente fazer o upload do arquivo
} else {
    if (move_uploaded_file($_FILES["upload"]["tmp_name"], $target_file)) {
        $funcNum = $_GET['CKEditorFuncNum'];
        $url = "/uploads/" . basename($_FILES["upload"]["name"]);
        $message = 'Image uploaded successfully';
        echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction($funcNum, '$url', '$message');</script>";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}
?>
