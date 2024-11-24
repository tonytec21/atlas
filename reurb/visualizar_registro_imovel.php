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

    // Busca informações do imóvel e processo administrativo
    $queryImovel = $conn->prepare("
        SELECT 
            i.proprietario_cpf, i.cpf_conjuge, i.logradouro, i.quadra, i.numero, i.bairro, i.cidade,
            p.processo_adm, p.data_de_publicacao, p.representante, p.oficial_do_registro, p.cargo_oficial
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

    // Busca informações do proprietário
    $queryProprietario = $conn->prepare("
        SELECT 
            p.nome, p.nacionalidade, p.naturalidade, p.estado_civil, p.profissao, p.rg, p.orgao_emissor_rg, p.data_emissao_rg, 
            p.cpf, p.filiacao, p.logradouro, p.quadra, p.numero, p.bairro, p.cidade
        FROM cadastro_de_pessoas p
        WHERE p.cpf = ?
    ");
    $queryProprietario->bind_param('s', $imovel['proprietario_cpf']);
    $queryProprietario->execute();
    $resultProprietario = $queryProprietario->get_result();

    if ($resultProprietario->num_rows === 0) {
        throw new Exception('Proprietário não encontrado.');
    }

    $proprietario = $resultProprietario->fetch_assoc();

    // Busca informações do cônjuge
    $conjuge = null;
    if ($imovel['cpf_conjuge']) {
        $queryConjuge = $conn->prepare("
            SELECT 
                p.nome, p.nacionalidade, p.naturalidade, p.estado_civil, p.profissao, p.rg, p.orgao_emissor_rg, p.data_emissao_rg, 
                p.cpf, p.filiacao
            FROM cadastro_de_pessoas p
            WHERE p.cpf = ?
        ");
        $queryConjuge->bind_param('s', $imovel['cpf_conjuge']);
        $queryConjuge->execute();
        $resultConjuge = $queryConjuge->get_result();

        if ($resultConjuge->num_rows > 0) {
            $conjuge = $resultConjuge->fetch_assoc();
        }
    }

    // Trata o campo de filiação, substituindo ";" por "e"
    $filiacao_proprietario = str_replace(';', ' e ', $proprietario['filiacao']);
    $filiacao_conjuge = $conjuge ? str_replace(';', ' e ', $conjuge['filiacao']) : null;

    // Formata as datas para o formato brasileiro
    $data_de_publicacao = date('d/m/Y', strtotime($imovel['data_de_publicacao']));
    $data_emissao_rg_proprietario = date('d/m/Y', strtotime($proprietario['data_emissao_rg']));
    $data_emissao_rg_conjuge = $conjuge ? date('d/m/Y', strtotime($conjuge['data_emissao_rg'])) : null;
    $data_hoje = date('d/m/Y');

    // Monta o endereço omitindo campos ausentes
    $endereco = array_filter([
        $proprietario['logradouro'] ? "na {$proprietario['logradouro']}" : null,
        $proprietario['quadra'] ? "quadra {$proprietario['quadra']}" : null,
        $proprietario['numero'] ? "nº {$proprietario['numero']}" : null,
        $proprietario['bairro'] ? $proprietario['bairro'] : null,
        "{$proprietario['cidade']}/MA"
    ]);
    $endereco_formatado = implode(', ', $endereco);

    // Gera o texto do registro com base no estado civil
    if ($proprietario['estado_civil'] === 'casado' && $conjuge) {
        $registro = "
        <b>R.01 - Mat. XX.</b> Feito em XX/XX/20XX. Prenotação nº XXXXX, Livro 1, datada de XX/XX/20XX. <b>LEGITIMAÇÃO FUNDIÁRIA:</b> Nos termos da CRF conforme Listagem dos Ocupantes e o Instituto da Regularização Fundiária de Interesse Social nº {$imovel['processo_adm']} expedido em {$data_de_publicacao}, pela Comissão Municipal de Regularização Fundiária de {$imovel['cidade']}, assinado pelo {$imovel['representante']} prefeito municipal, com fundamento nos artigos 13, I e 23 da Lei nº 13.465/2017, foi atribuído por Legitimação Fundiária em favor do(a) beneficiário(a) <b>{$proprietario['nome']}</b>, {$proprietario['nacionalidade']}, {$proprietario['estado_civil']}(a), {$proprietario['profissao']}, natural de {$proprietario['naturalidade']}, portador(a) do RG N.º {$proprietario['rg']}, {$proprietario['orgao_emissor_rg']}, expedido em {$data_emissao_rg_proprietario}, inscrito no CPF n.º {$proprietario['cpf']}, filho(a) de {$filiacao_proprietario}, e seu cônjuge <b>{$conjuge['nome']}</b>, {$conjuge['nacionalidade']}, {$conjuge['estado_civil']}, {$conjuge['profissao']}, natural de {$conjuge['naturalidade']}, portador(a) do RG N.º {$conjuge['rg']}, {$conjuge['orgao_emissor_rg']}, expedido em {$data_emissao_rg_conjuge}, inscrito no CPF n.º {$conjuge['cpf']}, filho(a) de {$filiacao_conjuge}, residentes e domiciliados {$endereco_formatado}. EMOLUMENTOS: Isento de emolumentos na forma do artigo 13, § 1º, inciso I, da lei 13.465/2017 de Regularização Fundiária na modalidade REURB-S. Certifico também, que foi emitida a Declaração sobre Operações Imobiliárias - DOI, nos termos do artigo 8º da Lei nº 10.426, de 24/04/2002 e da Instrução Normativa vigente da Secretaria da Receita Federal do Brasil e Nos termos do § III, do artigo 638 do Código de Normas da Corregedoria Geral de Justiça do Maranhão. O referido é verdade e dou fé. {$proprietario['cidade']}, {$data_hoje}. Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
        ";
    } else {
        $registro = "
        <b>R.01 - Mat. XX.</b> Feito em XX/XX/20XX. Prenotação nº XXXXX, Livro 1, datada de XX/XX/20XX. <b>LEGITIMAÇÃO FUNDIÁRIA:</b> Nos termos da CRF conforme Listagem dos Ocupantes e o Instituto da Regularização Fundiária de Interesse Social nº {$imovel['processo_adm']} expedido em {$data_de_publicacao}, pela Comissão Municipal de Regularização Fundiária de {$imovel['cidade']}, assinado pelo {$imovel['representante']} prefeito municipal, com fundamento nos artigos 13, I e 23 da Lei nº 13.465/2017, foi atribuído por Legitimação Fundiária em favor do(a) beneficiário(a) <b>{$proprietario['nome']}</b>, {$proprietario['nacionalidade']}, {$proprietario['estado_civil']}, {$proprietario['profissao']}, natural de {$proprietario['naturalidade']}, portador(a) do RG N.º {$proprietario['rg']}, {$proprietario['orgao_emissor_rg']}, expedido em {$data_emissao_rg_proprietario}, inscrito no CPF n.º {$proprietario['cpf']}, filho(a) de {$filiacao_proprietario}, residente e domiciliado {$endereco_formatado}. EMOLUMENTOS: Isento de emolumentos na forma do artigo 13, § 1º, inciso I, da lei 13.465/2017 de Regularização Fundiária na modalidade REURB-S. Certifico também, que foi emitida a Declaração sobre Operações Imobiliárias - DOI, nos termos do artigo 8º da Lei nº 10.426, de 24/04/2002 e da Instrução Normativa vigente da Secretaria da Receita Federal do Brasil e Nos termos do § III, do artigo 638 do Código de Normas da Corregedoria Geral de Justiça do Maranhão. O referido é verdade e dou fé. {$proprietario['cidade']}, {$data_hoje}. Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
        ";
    }

    $response['success'] = true;
    $response['data'] = nl2br(trim($registro)); // Formata o texto com quebras de linha
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
