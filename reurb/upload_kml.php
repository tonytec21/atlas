<?php
require_once 'db_connection_kml.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['kmlFile'])) {
    $file = $_FILES['kmlFile'];
    $allowedExtensions = ['kml'];

    $fileName = $file['name']; // Nome completo do arquivo
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION); // Extensão do arquivo

    // Remover a extensão .kml do nome do arquivo
    $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);

    if (!in_array(strtolower($fileExt), $allowedExtensions)) {
        http_response_code(400);
        die("Erro: Apenas arquivos KML são permitidos.");
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . basename($fileName);
    if (move_uploaded_file($fileTmpPath, $filePath)) {
        try {
            // Processar o arquivo KML
            $kmlContent = simplexml_load_file($filePath);
            if (!$kmlContent) {
                throw new Exception("Erro ao processar o arquivo KML. Formato inválido.");
            }

            foreach ($kmlContent->Document->Placemark as $placemark) {
                // Usar o nome do arquivo como nome do registro
                $name = $fileNameWithoutExtension;
                $coordinates = '';

                if (isset($placemark->Polygon->outerBoundaryIs->LinearRing->coordinates)) {
                    $coordinates = trim((string)$placemark->Polygon->outerBoundaryIs->LinearRing->coordinates);
                } elseif (isset($placemark->LineString->coordinates)) {
                    $coordinates = trim((string)$placemark->LineString->coordinates);
                }

                if (!empty($coordinates)) {
                    // Salvar no banco de dados
                    $query = "INSERT INTO kml_data (name, coordinates) VALUES (?, ?)";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$name, $coordinates]);
                } else {
                    error_log("Coordenadas ausentes ou inválidas para o arquivo: $fileName");
                }
            }
            echo "Arquivo KML processado com sucesso.";
        } catch (Exception $e) {
            error_log("Erro ao processar arquivo KML: " . $e->getMessage());
            http_response_code(500);
            die("Erro ao processar o arquivo KML.");
        }
    } else {
        http_response_code(500);
        die("Erro ao fazer o upload do arquivo.");
    }
}
?>
