<?php
require_once(__DIR__ . '/../../PhpSpreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Criar nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Olá, mundo!');

// Salvar arquivo
$writer = new Xlsx($spreadsheet);
$writer->save('teste.xlsx');

echo "Planilha criada com sucesso!";
?>
