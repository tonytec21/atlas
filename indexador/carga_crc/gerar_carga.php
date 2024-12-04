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
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><CARGAREGISTROS></CARGAREGISTROS>');
        $xml->addChild('VERSAO', '2.6');
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
            $registro->addChild('LOCALNASCIMENTO', htmlspecialchars($row['local_nascimento'] ?? 'IGNORADO'));
            $registro->addChild('SEXO', htmlspecialchars($row['sexo'] ?? 'NAO INFORMADO'));
            $registro->addChild('POSSUIGEMEOS', $row['possui_gemeos'] ?? 'N');
            $registro->addChild('NUMEROGEMEOS', $row['numero_gemeos'] ?? '');
            $registro->addChild('CODIGOIBGEMUNNASCIMENTO', htmlspecialchars($row['ibge_naturalidade'] ?? 'NAO INFORMADO'));
            $registro->addChild('PAISNASCIMENTO', $row['pais_nascimento'] ?? '');
            $registro->addChild('NACIONALIDADE', $row['nacionalidade'] ?? '');
            $registro->addChild('TEXTONACIONALIDADEESTRANGEIRO', $row['texto_nacionalidade_estrangeiro'] ?? '');

            // Dados do pai (apenas se existir nome do pai)
            if (!empty($row['nome_pai'])) {
                $filiacaoPai = $registro->addChild('FILIACAONASCIMENTO');
                $filiacaoPai->addChild('INDICEREGISTRO', $row['id'] ?? 'NAO INFORMADO');
                $filiacaoPai->addChild('INDICEFILIACAO', '1');
                $filiacaoPai->addChild('NOME', htmlspecialchars($row['nome_pai']));
                $filiacaoPai->addChild('SEXO', 'M');
                $filiacaoPai->addChild('CPF', $row['cpf_pai'] ?? '');
                $filiacaoPai->addChild('DATANASCIMENTO', $row['data_nascimento_pai'] ?? '');
                $filiacaoPai->addChild('IDADE', $row['idade_pai'] ?? '');
                $filiacaoPai->addChild('IDADE_DIAS_MESES_ANOS', $row['idade_dias_meses_anos_pai'] ?? '');
                $filiacaoPai->addChild('CODIGOIBGEMUNLOGRADOURO', $row['codigo_ibge_mun_logradouro_pai'] ?? '');
                $filiacaoPai->addChild('LOGRADOURO', $row['logradouro_pai'] ?? '');
                $filiacaoPai->addChild('NUMEROLOGRADOURO', $row['numero_logradouro_pai'] ?? '');
                $filiacaoPai->addChild('COMPLEMENTOLOGRADOURO', $row['complemento_logradouro_pai'] ?? '');
                $filiacaoPai->addChild('BAIRRO', $row['bairro_pai'] ?? '');
                $filiacaoPai->addChild('NACIONALIDADE', $row['nacionalidade_pai'] ?? '');
                $filiacaoPai->addChild('DOMICILIOESTRANGEIRO', $row['domicilio_estrangeiro_pai'] ?? '');
                $filiacaoPai->addChild('CODIGOIBGEMUNNATURALIDADE', htmlspecialchars($row['codigo_ibge_mun_naturalidade_pai'] ?? ''));
                $filiacaoPai->addChild('TEXTOLIVREMUNICIPIONAT', $row['texto_livre_municipio_nat_pai'] ?? 'NAO INFORMADO');
                $filiacaoPai->addChild('CODIGOOCUPACAOSDC', $row['codigo_ocupacao_sdc_pai'] ?? '');
            }

            // Dados da mãe (sempre adicionar, pois é obrigatório)
            $filiacaoMae = $registro->addChild('FILIACAONASCIMENTO');
            $filiacaoMae->addChild('INDICEREGISTRO', $row['id'] ?? 'NAO INFORMADO');
            $filiacaoMae->addChild('INDICEFILIACAO', '2');
            $filiacaoMae->addChild('NOME', htmlspecialchars($row['nome_mae'] ?? 'NAO INFORMADO'));
            $filiacaoMae->addChild('SEXO', 'F');
            $filiacaoMae->addChild('CPF', $row['cpf_mae'] ?? '');
            $filiacaoMae->addChild('DATANASCIMENTO', $row['data_nascimento_mae'] ?? '');
            $filiacaoMae->addChild('IDADE', $row['idade_mae'] ?? '');
            $filiacaoMae->addChild('IDADE_DIAS_MESES_ANOS', $row['idade_dias_meses_anos_mae'] ?? '');
            $filiacaoMae->addChild('CODIGOIBGEMUNLOGRADOURO', $row['codigo_ibge_mun_logradouro_mae'] ?? '');
            $filiacaoMae->addChild('LOGRADOURO', $row['logradouro_mae'] ?? '');
            $filiacaoMae->addChild('NUMEROLOGRADOURO', $row['numero_logradouro_mae'] ?? '');
            $filiacaoMae->addChild('COMPLEMENTOLOGRADOURO', $row['complemento_logradouro_mae'] ?? '');
            $filiacaoMae->addChild('BAIRRO', $row['bairro_mae'] ?? '');
            $filiacaoMae->addChild('NACIONALIDADE', $row['nacionalidade_mae'] ?? '');
            $filiacaoMae->addChild('DOMICILIOESTRANGEIRO', $row['domicilio_estrangeiro_mae'] ?? '');
            $filiacaoMae->addChild('CODIGOIBGEMUNNATURALIDADE', htmlspecialchars($row['codigo_ibge_mun_naturalidade_mae'] ?? ''));
            $filiacaoMae->addChild('TEXTOLIVREMUNICIPIONAT', $row['texto_livre_municipio_nat_mae'] ?? 'NAO INFORMADO');
            $filiacaoMae->addChild('CODIGOOCUPACAOSDC', $row['codigo_ocupacao_sdc_mae'] ?? '');

            // Adiciona as tags opcionais vazias
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
