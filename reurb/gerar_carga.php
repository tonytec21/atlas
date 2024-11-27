<?php
include(__DIR__ . '/db_connection.php');

try {
    // Recebe o processo_adm enviado pelo formulário
    $processoAdm = $_POST['processo_adm'] ?? null;

    if (!$processoAdm) {
        throw new Exception("O parâmetro 'processo_adm' é obrigatório.");
    }

    // Consulta os imóveis com o processo_adm especificado
    $query = $conn->prepare("
        SELECT 
            i.tipo_logradouro, i.logradouro, i.quadra, i.numero, i.bairro, i.cidade, i.cep, i.memorial_descritivo,
            i.area_do_lote, i.perimetro, i.area_construida, i.proprietario_nome, i.proprietario_cpf, 
            i.conjuge, i.nome_conjuge, i.cpf_conjuge, i.processo_adm,
            p.responsavel_tecnico, p.qualificacao_responsavel_tecnico, p.municipio, p.qualificacao_municipio, 
            p.representante, p.matricula_mae, p.classificacao_individual, p.edital, p.data_edital,
            p.oficial_do_registro, p.cargo_oficial
        FROM cadastro_de_imoveis i
        LEFT JOIN cadastro_de_processo_adm p ON i.processo_adm = p.processo_adm
        WHERE i.processo_adm = ?
    ");
    $query->bind_param('s', $processoAdm);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Nenhum imóvel encontrado para o processo administrativo '{$processoAdm}'.");
    }

    // Monta o JSON
    $imoveis = [];
    while ($imovel = $result->fetch_assoc()) {
        // Verifica se a chave `processo_adm` está definida
        $processoAdmValue = $imovel['processo_adm'] ?? 'N/D';
        $classificacaoIndividual = $imovel['classificacao_individual'] ?? 'N/D';
        $edital = $imovel['edital'] ?? 'N/D';

        // Formata a data para o formato brasileiro
        $data_edital = isset($imovel['data_edital']) ? date('d/m/Y', strtotime($imovel['data_edital'])) : 'N/D';
        $data_hoje = date('d/m/Y');

        // Gera o texto da matrícula
        $texto_matricula = "
<b>IMÓVEL URBANO.</b> Imóvel constituído de um terreno urbano localizado na {$imovel['logradouro']}, quadra {$imovel['quadra']}, nº {$imovel['numero']}, Bairro: {$imovel['bairro']}, {$imovel['cidade']}, contendo as seguintes descrições, limites e área: Com área total de {$imovel['area_do_lote']}m², Perímetro de {$imovel['perimetro']}m e área construída de {$imovel['area_construida']}m² – {$imovel['memorial_descritivo']}. 
<b>ORIGEM:</b> Matrícula aberta em razão do registro do procedimento de Regularização Fundiária de Interesse Social, através da CRF e PRF nos termos do Art. 11, III e VI, 13, II, da Lei Federal nº 13.465/2017, promovida pelo Poder Público Municipal, através da Comissão Municipal de Regularização Fundiária, conforme Processo Administrativo nº {$processoAdmValue} - {$classificacaoIndividual} - Bairro {$imovel['bairro']}, instaurado pelo edital nº {$edital}, publicado em {$data_edital} no diário oficial dos municípios.";

        // Prepara a chave `proprietarios`
        $proprietarios = [];
        $proprietarios[] = [
            'nome' => $imovel['proprietario_nome'],
            'cpf' => $imovel['proprietario_cpf']
        ];

        if (!empty($imovel['nome_conjuge']) && !empty($imovel['cpf_conjuge'])) {
            $proprietarios[] = [
                'nome' => $imovel['nome_conjuge'],
                'cpf' => $imovel['cpf_conjuge']
            ];
        }

        // Adiciona o imóvel ao array
        $imoveis[] = [
            'registro_anterior' => $imovel['matricula_mae'],
            'imovel_localizacao' => 1,
            'tipo_imovel' => 5,
            'tipo_logradouro' => $imovel['tipo_logradouro'],
            'logradouro' => $imovel['logradouro'],
            'quadra' => $imovel['quadra'],
            'numero' => $imovel['numero'],
            'bairro' => $imovel['bairro'],
            'cidade' => $imovel['cidade'],
            'cep' => $imovel['cep'],
            'memorial_descritivo' => $imovel['memorial_descritivo'],
            'area_do_lote' => $imovel['area_do_lote'],
            'tipo_area' => "m²",
            'perimetro' => $imovel['perimetro'],
            'area_construida' => $imovel['area_construida'],
            'proprietarios' => $proprietarios,
            'texto_da_matricula' => trim($texto_matricula)
        ];
    }

    
    // Garante que o diretório completo existe
    $diretorioBase = __DIR__ . "/cargas/" . date('Y') . "/" . date('m');
    if (!is_dir($diretorioBase)) {
        mkdir($diretorioBase, 0777, true);
    }
    
    // Sanitiza o nome do processo administrativo para evitar caracteres inválidos
    $processoAdmSanitizado = preg_replace('/[^\w\s-]/', '', $processoAdm);
    $processoAdmSanitizado = str_replace(' ', '_', $processoAdmSanitizado);

    // Define o caminho final do arquivo
    $arquivoJson = "{$diretorioBase}/carga_{$processoAdmSanitizado}.json";


    // Salva o arquivo JSON
    file_put_contents($arquivoJson, json_encode($imoveis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Redireciona para download
    header('Content-Type: application/json');
    header("Content-Disposition: attachment; filename=carga_{$processoAdm}.json");
    readfile($arquivoJson);

    exit;
} catch (Exception $e) {
    header('Location: exportar_carga.php?error=' . urlencode($e->getMessage()));
}
