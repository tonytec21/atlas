<?php
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');
checkSession();

try {
    // Consulta todos os dados da tabela `cadastro_de_pessoas` com status ativo
    $query = $conn->query("
        SELECT 
            nome, data_de_nascimento, cpf, rg, data_emissao_rg, orgao_emissor_rg, nacionalidade, naturalidade, 
            profissao, estado_civil, regime_de_bens, filiacao, logradouro, quadra, numero, bairro, cidade, cep 
        FROM cadastro_de_pessoas 
        WHERE status = 'ativo'
    ");

    if ($query->num_rows === 0) {
        throw new Exception("Nenhum cadastro de pessoa encontrado.");
    }

    // Monta o JSON
    $pessoas = [];
    while ($pessoa = $query->fetch_assoc()) {
        // Divide a filiacao em mãe e pai com base no delimitador ";"
        $filiacao = explode(';', $pessoa['filiacao']);
        $mae = trim($filiacao[0] ?? '');
        $pai = trim($filiacao[1] ?? '');
    
        $pessoas[] = [
            'nome' => $pessoa['nome'],
            'data_de_nascimento' => $pessoa['data_de_nascimento'],
            'cpf' => $pessoa['cpf'],
            'rg' => $pessoa['rg'],
            'data_emissao_rg' => $pessoa['data_emissao_rg'],
            'orgao_emissor_rg' => $pessoa['orgao_emissor_rg'],
            'nacionalidade' => $pessoa['nacionalidade'],
            'naturalidade' => $pessoa['naturalidade'],
            'profissao' => $pessoa['profissao'],
            'estado_civil' => $pessoa['estado_civil'],
            'regime_de_bens' => $pessoa['regime_de_bens'],
            'filiacao' => [
                'mae' => $mae,
                'pai' => $pai
            ],
            'endereco' => [
                'logradouro' => $pessoa['logradouro'],
                'quadra' => $pessoa['quadra'],
                'numero' => $pessoa['numero'],
                'bairro' => $pessoa['bairro'],
                'cidade' => $pessoa['cidade'],
                'cep' => $pessoa['cep']
            ]
        ];
    }    

    // Garante que o diretório existe
    $diretorio = "cargas";
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    // Nome do arquivo JSON
    $arquivoJson = "{$diretorio}/carga_pessoas.json";

    // Salva o arquivo JSON
    file_put_contents($arquivoJson, json_encode($pessoas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Redireciona para download
    header('Content-Type: application/json');
    header("Content-Disposition: attachment; filename=carga_pessoas.json");
    readfile($arquivoJson);

    exit;
} catch (Exception $e) {
    header('Location: exportar_carga.php?error=' . urlencode($e->getMessage()));
}
