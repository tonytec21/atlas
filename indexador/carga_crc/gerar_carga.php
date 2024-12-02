<?php
require_once __DIR__ . '/db_connection.php';

// Obtenção do CNS
$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";
$resultCNS = $conn->query($queryCNS);
$rowCNS = $resultCNS->fetch_assoc();
$numeroCNS = $rowCNS['cns'] ?? 'NAO INFORMADO';

// Verifica se os IDs selecionados foram enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    // Obtém os IDs selecionados
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));

    // Query para buscar os registros selecionados
    $query = "SELECT * FROM indexador_nascimento WHERE id IN ($ids) AND status = 'ativo'";
    $result = $conn->query($query);

    // Verificação se há registros
    if ($result->num_rows > 0) {
        // Inicia o XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><CARGAREGISTROS></CARGAREGISTROS>');
        $xml->addChild('VERSAO', '2.7');
        $xml->addChild('ACAO', 'CARGA');
        $xml->addChild('CNS', $numeroCNS);

        $movimento = $xml->addChild('MOVIMENTONASCIMENTOTN');

        while ($row = $result->fetch_assoc()) {
            // Cria o registro de nascimento
            $registro = $movimento->addChild('REGISTRONASCIMENTOINCLUSAO');

            // Ordem das tags conforme o manual
            $registro->addChild('INDICEREGISTRO', $row['id'] ?? 'NAO INFORMADO');
            $registro->addChild('NOMEREGISTRADO', htmlspecialchars($row['nome_registrado'] ?? 'NAO INFORMADO'));
            $registro->addChild('CPFREGISTRADO', $row['cpf_registrado'] ?? '');
            $registro->addChild('MATRICULA', htmlspecialchars($row['matricula'] ?? 'NAO INFORMADO'));
            $registro->addChild('DATAREGISTRO', date('d/m/Y', strtotime($row['data_registro'] ?? 'NAO INFORMADO')));
            $registro->addChild('DNV', $row['dnv'] ?? '');
            $registro->addChild('DATANASCIMENTO', date('d/m/Y', strtotime($row['data_nascimento'] ?? 'NAO INFORMADO')));
            $registro->addChild('HORANASCIMENTO', $row['hora_nascimento'] ?? '');
            $registro->addChild('LOCALNASCIMENTO', htmlspecialchars($row['local_nascimento'] ?? 'NAO INFORMADO'));
            $registro->addChild('SEXO', htmlspecialchars($row['sexo'] ?? 'NAO INFORMADO'));
            $registro->addChild('POSSUIGEMEOS', $row['possui_gemeos'] ?? '');
            $registro->addChild('NUMEROGEMEOS', $row['numero_gemeos'] ?? '');
            $registro->addChild('CODIGOIBGEMUNNASCIMENTO', htmlspecialchars($row['codigo_ibge_mun_nascimento'] ?? 'NAO INFORMADO'));
            $registro->addChild('PAISNASCIMENTO', $row['pais_nascimento'] ?? '');
            $registro->addChild('NACIONALIDADE', $row['nacionalidade'] ?? '');
            $registro->addChild('TEXTONACIONALIDADEESTRANGEIRO', $row['texto_nacionalidade_estrangeiro'] ?? '');

            // Adiciona os dados de filiação
            $filiacao = $registro->addChild('FILIACAONASCIMENTO');

            // Dados do pai (apenas se existir nome do pai)
            if (!empty($row['nome_pai'])) {
                $pai = $filiacao->addChild('FILIACAO');
                $pai->addChild('INDICEREGISTRO', $row['id'] ?? 'NAO INFORMADO');
                $pai->addChild('INDICEFILIACAO', '1');
                $pai->addChild('NOME', htmlspecialchars($row['nome_pai']));
                $pai->addChild('SEXO', 'M');
                $pai->addChild('CPF', $row['cpf_pai'] ?? '');
                $pai->addChild('DATANASCIMENTO', $row['data_nascimento_pai'] ?? '');
                $pai->addChild('IDADE', $row['idade_pai'] ?? '');
                $pai->addChild('IDADE_DIAS_MESES_ANOS', $row['idade_dias_meses_anos_pai'] ?? '');
                $pai->addChild('CODIGOIBGEMUNLOGRADOURO', $row['codigo_ibge_mun_logradouro_pai'] ?? '');
                $pai->addChild('LOGRADOURO', $row['logradouro_pai'] ?? '');
                $pai->addChild('NUMEROLOGRADOURO', $row['numero_logradouro_pai'] ?? '');
                $pai->addChild('COMPLEMENTOLOGRADOURO', $row['complemento_logradouro_pai'] ?? '');
                $pai->addChild('BAIRRO', $row['bairro_pai'] ?? '');
                $pai->addChild('NACIONALIDADE', $row['nacionalidade_pai'] ?? '');
                $pai->addChild('DOMICILIOESTRANGEIRO', $row['domicilio_estrangeiro_pai'] ?? '');
                $pai->addChild('CODIGOIBGEMUNNATURALIDADE', htmlspecialchars($row['codigo_ibge_mun_naturalidade_pai'] ?? ''));
                $pai->addChild('TEXTOLIVREMUNICIPIONAT', $row['texto_livre_municipio_nat_pai'] ?? 'NAO INFORMADO');
                $pai->addChild('CODIGOOCUPACAOSDC', $row['codigo_ocupacao_sdc_pai'] ?? '');
            }

            // Dados da mãe (sempre adicionar, pois é obrigatório)
            $mae = $filiacao->addChild('FILIACAO');
            $mae->addChild('INDICEREGISTRO', $row['id'] ?? 'NAO INFORMADO');
            $mae->addChild('INDICEFILIACAO', '2');
            $mae->addChild('NOME', htmlspecialchars($row['nome_mae'] ?? 'NAO INFORMADO'));
            $mae->addChild('SEXO', 'F');
            $mae->addChild('CPF', $row['cpf_mae'] ?? '');
            $mae->addChild('DATANASCIMENTO', $row['data_nascimento_mae'] ?? '');
            $mae->addChild('IDADE', $row['idade_mae'] ?? '');
            $mae->addChild('IDADE_DIAS_MESES_ANOS', $row['idade_dias_meses_anos_mae'] ?? '');
            $mae->addChild('CODIGOIBGEMUNLOGRADOURO', $row['codigo_ibge_mun_logradouro_mae'] ?? '');
            $mae->addChild('LOGRADOURO', $row['logradouro_mae'] ?? '');
            $mae->addChild('NUMEROLOGRADOURO', $row['numero_logradouro_mae'] ?? '');
            $mae->addChild('COMPLEMENTOLOGRADOURO', $row['complemento_logradouro_mae'] ?? '');
            $mae->addChild('BAIRRO', $row['bairro_mae'] ?? '');
            $mae->addChild('NACIONALIDADE', $row['nacionalidade_mae'] ?? '');
            $mae->addChild('DOMICILIOESTRANGEIRO', $row['domicilio_estrangeiro_mae'] ?? '');
            $mae->addChild('CODIGOIBGEMUNNATURALIDADE', htmlspecialchars($row['codigo_ibge_mun_naturalidade_mae'] ?? ''));
            $mae->addChild('TEXTOLIVREMUNICIPIONAT', $row['texto_livre_municipio_nat_mae'] ?? 'NAO INFORMADO');
            $mae->addChild('CODIGOOCUPACAOSDC', $row['codigo_ocupacao_sdc_mae'] ?? '');

            // Adiciona as tags opcionais vazias
            $registro->addChild('DOCUMENTOS');
            $registro->addChild('ORGAOEMISSOREXTERIOR');
            $registro->addChild('INFORMACOESCONSULADO');
            $registro->addChild('OBSERVACOES');
        }

        // Gera o XML formatado
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        $nomeArquivo = 'carga_nascimento.xml';
        $dom->save($nomeArquivo);

        // Configurações para download
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        readfile($nomeArquivo);

        // Exclui o arquivo do servidor após download
        unlink($nomeArquivo);
        exit;
    } else {
        echo "<script>alert('Nenhum registro selecionado foi encontrado para gerar a carga.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Nenhum registro selecionado.'); window.history.back();</script>";
}

$conn->close();
