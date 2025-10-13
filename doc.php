<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google_Service_Drive::DRIVE_METADATA_READONLY);
$client->setAccessType('offline');

// Usa token salvo (você já deve ter o processo OAuth funcionando)
$client->setAccessToken(json_decode(file_get_contents('token.json'), true));

$service = new Google_Service_Drive($client);

// Lista apenas documentos Google Docs
$results = $service->files->listFiles([
    'q' => "mimeType = 'application/vnd.google-apps.document' and trashed = false",
    'fields' => 'files(id, name)',
    'pageSize' => 50,
]);

echo "<h2>Documentos no Google Drive</h2><ul>";

foreach ($results->getFiles() as $file) {
    $id = $file->getId();
    $name = htmlspecialchars($file->getName());
    $url_preview = "https://docs.google.com/document/d/$id/preview";
    $url_edit = "https://docs.google.com/document/d/$id/edit";

    echo "<li>$name 
        [<a href='$url_preview' target='_blank'>Visualizar</a>] 
        [<a href='$url_edit' target='_blank'>Editar</a>]
        [<a href='ver.php?id=$id' target='_self'>Abrir no sistema</a>]
    </li>";
}

echo "</ul>";
?>