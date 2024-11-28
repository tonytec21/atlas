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

    // Função para obter os dados de matrícula
    function getMatriculaTexto($id, $conn) {
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
            throw new Exception('Imóvel não encontrado para gerar a matrícula.');
        }

        $imovel = $resultImovel->fetch_assoc();

        setlocale(LC_TIME, 'pt_BR.UTF-8', 'Portuguese_Brazil', 'ptb');
        return "
            IMÓVEL URBANO. Imóvel constituído de um terreno urbano localizado na {$imovel['logradouro']}, quadra {$imovel['quadra']}, nº {$imovel['numero']}, Bairro: {$imovel['bairro']}, {$imovel['cidade']}, contendo as seguintes descrições, limites e área: Área total de {$imovel['area_do_lote']}m², Perímetro de {$imovel['perimetro']}m e área construída de {$imovel['area_construida']}m² – {$imovel['memorial_descritivo']}. Responsável Técnico {$imovel['responsavel_tecnico']}, {$imovel['qualificacao_responsavel_tecnico']}. PROPRIETÁRIO: {$imovel['municipio']}, {$imovel['qualificacao_municipio']}. REGISTRO ANTERIOR: Sob a matrícula nº {$imovel['matricula_mae']}, do Livro 2 de Registro Geral desta Serventia Extrajudicial. ORIGEM: Matrícula aberta em razão do registro do procedimento de regularização fundiária de interesse Social, através da cooperação técnica entre a União, por intermédio da Superintendência do Patrimônio da União no Maranhão (SPU-MA), o Tribunal de Justiça do Estado do Maranhão, por intermédio do Núcleo de Governança Fundiária (NGF), e o Estado do Maranhão, por intermédio do Instituto de Colonização e Terras do Maranhão (ITERMA), conforme Acordo de Cooperação Técnica MGI-SPU-MA nº {$imovel['processo_adm']} - {$imovel['classificacao_individual']} - Bairro {$imovel['bairro']}, instaurado pelo Processo Nº 19739.016494/2024-23. Esta matrícula é forma originária de aquisição do direito real de propriedade. O referido é verdade e dou fé. {$imovel['cidade']}, " . mb_strtolower(strftime('%d de %B de %Y', strtotime('now')), 'UTF-8') . ". Emolumentos isentos amparado pelo art. 13 da Lei 13.465/2017. Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
        ";
    }

    function getRegistroTexto($id, $conn) {
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
            throw new Exception('Imóvel não encontrado para gerar o registro.');
        }
    
        $imovel = $resultImovel->fetch_assoc();
    
        // Buscar informações do proprietário
        $queryProprietario = $conn->prepare("
            SELECT 
                p.nome, p.nacionalidade, p.naturalidade, p.estado_civil, p.profissao, p.rg, p.orgao_emissor_rg, 
                p.data_emissao_rg, p.cpf, p.filiacao, p.logradouro, p.quadra, p.numero, p.bairro, p.cidade
            FROM cadastro_de_pessoas p
            WHERE p.cpf = ?
        ");
        $queryProprietario->bind_param('s', $imovel['proprietario_cpf']);
        $queryProprietario->execute();
        $resultProprietario = $queryProprietario->get_result();
    
        if ($resultProprietario->num_rows === 0) {
            throw new Exception('Proprietário não encontrado para gerar o registro.');
        }
    
        $proprietario = $resultProprietario->fetch_assoc();
    
        // Buscar informações do cônjuge (se existir)
        $conjuge = null;
        if ($imovel['cpf_conjuge']) {
            $queryConjuge = $conn->prepare("
                SELECT 
                    p.nome, p.nacionalidade, p.naturalidade, p.estado_civil, p.profissao, p.rg, p.orgao_emissor_rg, 
                    p.data_emissao_rg, p.cpf, p.filiacao
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
        $data_emissao_rg_proprietario = date('d/m/Y', strtotime($proprietario['data_emissao_rg']));
        $data_emissao_rg_conjuge = $conjuge ? date('d/m/Y', strtotime($conjuge['data_emissao_rg'])) : null;
    
        // Monta o endereço omitindo campos ausentes
        $endereco = array_filter([
            $proprietario['logradouro'] ? "na {$proprietario['logradouro']}" : null,
            $proprietario['quadra'] ? "quadra {$proprietario['quadra']}" : null,
            $proprietario['numero'] ? "nº {$proprietario['numero']}" : null,
            $proprietario['bairro'] ? $proprietario['bairro'] : null,
            "{$proprietario['cidade']}"
        ]);
        $endereco_formatado = implode(', ', $endereco);
    
        // Gera o texto do registro com base no estado civil
        setlocale(LC_TIME, 'pt_BR.UTF-8', 'Portuguese_Brazil', 'ptb');
        if ($proprietario['estado_civil'] === 'casado' && $conjuge) {
            return "
            R.01 - Mat. XX. Feito em XX/XX/20XX. Prenotação nº XXXXX, Livro 1, datada de XX/XX/20XX. LEGITIMAÇÃO FUNDIÁRIA: Nos termos do Acordo de Cooperação Técnica MGI-SPU-MA nº 02/2024 (SEI Nº 45962890) - REURB-S - Bairro Tamancão, instaurado pelo Processo Nº 19739.016494/2024-23 entre a União, por intermédio da Superintendência do Patrimônio da União no Maranhão (SPU-MA), o Tribunal de Justiça do Estado do Maranhão, por intermédio do Núcleo de Governança Fundiária (NGF), e o Estado do Maranhão, por intermédio do Instituto de Colonização e Terras do Maranhão (ITERMA), foi atribuído por Legitimação Fundiária em favor do(a) beneficiário(a) {$proprietario['nome']}, {$proprietario['nacionalidade']}" . (!empty($proprietario['estado_civil']) ? ", {$proprietario['estado_civil']}(a)" : "") . (!empty($proprietario['profissao']) ? ", {$proprietario['profissao']}" : "") . (!empty($proprietario['naturalidade']) ? ", natural de {$proprietario['naturalidade']}" : "") . (!empty($proprietario['rg']) ? ", portador(a) do RG N.º {$proprietario['rg']}" : "") . (!empty($proprietario['orgao_emissor_rg']) ? ", {$proprietario['orgao_emissor_rg']}" : "") . (!empty($data_emissao_rg_proprietario) ? ", expedido em {$data_emissao_rg_proprietario}" : "") . ", inscrito no CPF n.º {$proprietario['cpf']}" . (!empty($filiacao_proprietario) ? ", filho(a) de {$filiacao_proprietario}" : "") . ", e seu cônjuge {$conjuge['nome']}, {$conjuge['nacionalidade']}" . (!empty($conjuge['estado_civil']) ? ", {$conjuge['estado_civil']}" : "") . (!empty($conjuge['profissao']) ? ", {$conjuge['profissao']}" : "") . (!empty($conjuge['naturalidade']) ? ", natural de {$conjuge['naturalidade']}" : "") . (!empty($conjuge['rg']) ? ", portador(a) do RG N.º {$conjuge['rg']}" : "") . (!empty($conjuge['orgao_emissor_rg']) ? ", {$conjuge['orgao_emissor_rg']}" : "") . (!empty($data_emissao_rg_conjuge) ? ", expedido em {$data_emissao_rg_conjuge}" : "") . ", inscrito no CPF n.º {$conjuge['cpf']}" . (!empty($filiacao_conjuge) ? ", filho(a) de {$filiacao_conjuge}" : "") . ", residentes e domiciliados {$endereco_formatado}. EMOLUMENTOS: Isento de emolumentos na forma do artigo 13, § 1º, inciso I, da lei 13.465/2017 de Regularização Fundiária na modalidade REURB-S. Certifico também, que foi emitida a Declaração sobre Operações Imobiliárias - DOI, nos termos do artigo 8º da Lei nº 10.426, de 24/04/2002 e da Instrução Normativa vigente da Secretaria da Receita Federal do Brasil e Nos termos do § III, do artigo 638 do Código de Normas da Corregedoria Geral de Justiça do Maranhão. O referido é verdade e dou fé. {$proprietario['cidade']}, " . mb_strtolower(strftime('%d de %B de %Y', strtotime('now')), 'UTF-8') . ". Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
            ";
        } else {
            return "
            R.01 - Mat. XX. Feito em XX/XX/20XX. Prenotação nº XXXXX, Livro 1, datada de XX/XX/20XX. LEGITIMAÇÃO FUNDIÁRIA: Nos termos do Acordo de Cooperação Técnica MGI-SPU-MA nº 02/2024 (SEI Nº 45962890) - REURB-S - Bairro Tamancão, instaurado pelo Processo Nº 19739.016494/2024-23 entre a União, por intermédio da Superintendência do Patrimônio da União no Maranhão (SPU-MA), o Tribunal de Justiça do Estado do Maranhão, por intermédio do Núcleo de Governança Fundiária (NGF), e o Estado do Maranhão, por intermédio do Instituto de Colonização e Terras do Maranhão (ITERMA), foi atribuído por Legitimação Fundiária em favor do(a) beneficiário(a) {$proprietario['nome']}, {$proprietario['nacionalidade']}" . (!empty($proprietario['estado_civil']) ? ", {$proprietario['estado_civil']}" : "") . (!empty($proprietario['profissao']) ? ", {$proprietario['profissao']}" : "") . (!empty($proprietario['naturalidade']) ? ", natural de {$proprietario['naturalidade']}" : "") . (!empty($proprietario['rg']) ? ", portador(a) do RG N.º {$proprietario['rg']}" : "") . (!empty($proprietario['orgao_emissor_rg']) ? ", {$proprietario['orgao_emissor_rg']}" : "") . (!empty($data_emissao_rg_proprietario) ? ", expedido em {$data_emissao_rg_proprietario}" : "") . ", inscrito no CPF n.º {$proprietario['cpf']}" . (!empty($filiacao_proprietario) ? ", filho(a) de {$filiacao_proprietario}" : "") . ", residente e domiciliado {$endereco_formatado}. EMOLUMENTOS: Isento de emolumentos na forma do artigo 13, § 1º, inciso I, da lei 13.465/2017 de Regularização Fundiária na modalidade REURB-S. Certifico também, que foi emitida a Declaração sobre Operações Imobiliárias - DOI, nos termos do artigo 8º da Lei nº 10.426, de 24/04/2002 e da Instrução Normativa vigente da Secretaria da Receita Federal do Brasil e Nos termos do § III, do artigo 638 do Código de Normas da Corregedoria Geral de Justiça do Maranhão. O referido é verdade e dou fé. {$proprietario['cidade']}, " . mb_strtolower(strftime('%d de %B de %Y', strtotime('now')), 'UTF-8') . ". Selo de fiscalização vide abaixo. (a.a.) {$imovel['oficial_do_registro']}, {$imovel['cargo_oficial']}, que confiro, subscrevo, dato e assino em público e raso.
            ";
        }        
    }
    

    // Obter textos combinados
    $matriculaTexto = getMatriculaTexto($id, $conn);
    $registroTexto = getRegistroTexto($id, $conn);

    // Substituir entidades HTML por aspas reais
    $matriculaTexto = str_replace(['&apos;', '&quot;'], ["'", '"'], $matriculaTexto);
    $registroTexto = str_replace(['&apos;', '&quot;'], ["'", '"'], $registroTexto);

    // Combina os textos
    $textoFinal = $matriculaTexto . "\n\n" . $registroTexto;


// Consulta para obter o nome e CPF do proprietário
$queryProprietario = $conn->prepare("
    SELECT 
        i.proprietario_nome, i.proprietario_cpf
    FROM cadastro_de_imoveis i
    WHERE i.id = ?
");
$queryProprietario->bind_param('i', $id);
$queryProprietario->execute();
$resultProprietario = $queryProprietario->get_result();

if ($resultProprietario->num_rows === 0) {
    throw new Exception('Proprietário não encontrado.');
}

$proprietario = $resultProprietario->fetch_assoc();

// Inclui os dados no retorno
$response['success'] = true;
$response['texto'] = $textoFinal;
$response['nome_proprietario'] = $proprietario['proprietario_nome'];
$response['cpf_proprietario'] = $proprietario['proprietario_cpf'];


} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
