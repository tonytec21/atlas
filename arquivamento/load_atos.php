<?php
// Pega os filtros da requisição
$categoria = isset($_GET['categoria']) ? normalizeText($_GET['categoria']) : '';
$cpf = isset($_GET['cpf']) ? normalizeText($_GET['cpf']) : '';
$nome = isset($_GET['nome']) ? normalizeText($_GET['nome']) : '';
$livro = isset($_GET['livro']) ? normalizeText($_GET['livro']) : '';
$folha = isset($_GET['folha']) ? normalizeText($_GET['folha']) : '';
$termo = isset($_GET['termo']) ? normalizeText($_GET['termo']) : '';
$protocolo = isset($_GET['protocolo']) ? normalizeText($_GET['protocolo']) : '';
$matricula = isset($_GET['matricula']) ? normalizeText($_GET['matricula']) : '';

// Função para normalizar texto
function normalizeText($text) {
    return strtolower(preg_replace('/[\x{0300}-\x{036f}]/u', '', 
        iconv('UTF-8', 'ASCII//TRANSLIT', $text)));
}

// Caminho da pasta onde os arquivos JSON estão armazenados
$path = 'meta-dados/';
$files = glob($path . '*.json');
$results = [];

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);

    $dataCategoria = normalizeText($data['categoria']);
    $dataCpf = normalizeText(implode(', ', array_column($data['partes_envolvidas'], 'cpf')));
    $dataNome = normalizeText(implode(', ', array_column($data['partes_envolvidas'], 'nome')));
    $dataLivro = normalizeText($data['livro']);
    $dataFolha = normalizeText($data['folha']);
    $dataTermo = normalizeText($data['termo']);
    $dataProtocolo = normalizeText($data['protocolo']);
    $dataMatricula = normalizeText($data['matricula']);

    if (
        ($categoria === '' || strpos($dataCategoria, $categoria) !== false) &&
        ($cpf === '' || strpos($dataCpf, $cpf) !== false) &&
        ($nome === '' || strpos($dataNome, $nome) !== false) &&
        ($livro === '' || strpos($dataLivro, $livro) !== false) &&
        ($folha === '' || strpos($dataFolha, $folha) !== false) &&
        ($termo === '' || strpos($dataTermo, $termo) !== false) &&
        ($protocolo === '' || strpos($dataProtocolo, $protocolo) !== false) &&
        ($matricula === '' || strpos($dataMatricula, $matricula) !== false)
    ) {
        $results[] = $data;
    }
}

echo json_encode($results);
?>
