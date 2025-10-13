<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig('credentials.json');
$client->setRedirectUri('http://localhost/atlas/oauth2callback.php');
$client->addScope([
    Google_Service_Drive::DRIVE_METADATA_READONLY,
    Google_Service_Docs::DOCUMENTS_READONLY
]);
$client->setAccessType('offline');

if (!isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit;
} else {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    file_put_contents('token.json', json_encode($token));
    echo "Token salvo com sucesso!";
}
