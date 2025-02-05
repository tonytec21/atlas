<?php
require_once 'db_connection_kml.php';

header('Content-Type: application/json');

try {
    $query = "SELECT name, coordinates FROM memorial_data";
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
