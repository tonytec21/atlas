<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

$response = ['success' => false];

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('ID do imóvel não fornecido.');
    }

    // Corrige o SQL para buscar matricula_mae da tabela cadastro_de_processo_adm
    $queryImovel = $conn->prepare("
        SELECT 
            i.tipo_logradouro, i.logradouro, i.quadra, i.numero, i.bairro, i.cidade, i.cep,
            i.memorial_descritivo, i.area_do_lote, i.perimetro, i.area_construida, i.processo_adm,
            i.proprietario_nome, i.proprietario_cpf, i.conjuge, i.nome_conjuge, i.cpf_conjuge,
            p.matricula_mae, p.data_de_publicacao, p.classificacao_individual, p.direito_real_outorgado, p.municipio,
            p.qualificacao_municipio, p.representante, p.qualificacao_representante, p.edital, p.data_edital,
            p.responsavel_tecnico, p.qualificacao_responsavel_tecnico, p.oficial_do_registro, p.cargo_oficial
        FROM cadastro_de_imoveis i
        LEFT JOIN cadastro_de_processo_adm p ON i.processo_adm = p.processo_adm
        WHERE i.id = ?
    ");
    $queryImovel->bind_param('i', $id);
    $queryImovel->execute();
    $resultImovel = $queryImovel->get_result();

    if ($resultImovel->num_rows === 0) {
        throw new Exception('Imóvel não encontrado.');
    }

    $imovel = $resultImovel->fetch_assoc();

    // Formata o texto da matrícula
    $matricula = "
        <b>IMÓVEL URBANO.</b> Imóvel constituído de um terreno urbano localizado na {$imovel['logradouro']}, quadra {$imovel['quadra']}, nº {$imovel['numero']}, Bairro: {$imovel['bairro']}, {$imovel['cidade']}, contendo as seguintes descrições, limites e área: Com área total de {$imovel['area_do_lote']}m², Perímetro de {$imovel['perimetro']}m e área construída de {$imovel['area_construida']}m² – {$imovel['memorial_descritivo']}. Responsável Técnico {$imovel['responsavel_tecnico']}, {$imovel['qualificacao_responsavel_tecnico']}.
        
        <b>PROPRIETÁRIO:</b> MUNICÍPIO DE {$imovel['municipio']}, ESTADO DO MARANHÃO, {$imovel['qualificacao_municipio']}, neste ato representado pelo Prefeito Municipal {$imovel['representante']} em pleno exercício do mandato. <b>REGISTRO ANTERIOR:</b> Sob a matrícula nº {$imovel['matricula_mae']}, do Livro 2 de Registro Geral desta Serventia Extrajudicial. 
        
        <b>ORIGEM:</b> Matrícula aberta em razão do registro do procedimento de Regularização Fundiária de Interesse Social, através da CRF e PRF nos termos do Art. 11, III e VI, 13, II, da Lei Federal nº 13.465/2017, promovida pelo Poder Público Municipal, através da Comissão Municipal de Regularização Fundiária, conforme Processo Administrativo nº {$imovel['processo_adm']} - {$imovel['classificacao_individual']} - Bairro {$imovel['bairro']}, instaurado pelo edital nº {$imovel['edital']}, publicado em {$imovel['data_edital']} no diário oficial dos municípios. Esta matrícula é forma originária de aquisição do direito real de propriedade. O referido é verdade e dou fé. {$imovel['cidade']}, " . date('d/m/Y') . ". Emolumentos isentos amparado pelo art. 13 da Lei 13.465/2017. Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
        ";

    $response['success'] = true;
    $response['data'] = nl2br(trim($matricula));
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
