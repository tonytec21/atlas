<?php
require_once __DIR__ . '/db_connection.php';

// CNS da serventia
$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";
$resultCNS = $conn->query($queryCNS);
$rowCNS = $resultCNS ? $resultCNS->fetch_assoc() : null;
$numeroCNS = $rowCNS['cns'] ?? '';

/** dd/mm/yyyy a partir de DATE (YYYY-mm-dd) — retorna "" se vazio/nulo */
function toBR(?string $iso): string {
    if (!$iso) return '';
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : '';
}

/** Adiciona elemento (sempre presente), mesmo quando value === "" */
function addTextEl(DOMDocument $doc, DOMElement $parent, string $name, string $value = ''): DOMElement {
    $el = $doc->createElement($name);
    $el->appendChild($doc->createTextNode($value));
    $parent->appendChild($el);
    return $el;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
    // Normaliza IDs
    $ids = array_map('intval', $_POST['selected_ids']);
    $ids = array_filter($ids, fn($v) => $v > 0);

    if (empty($ids)) {
        echo "<script>alert('Nenhum registro selecionado.'); window.history.back();</script>";
        exit;
    }

    // Busca registros selecionados e ativos
    $idList = implode(',', $ids);
    $sql = "SELECT id, termo, livro, folha, tipo_casamento, data_registro,
                   conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo,
                   conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo,
                   regime_bens, data_casamento, matricula
            FROM indexador_casamento
            WHERE status='ativo' AND id IN ($idList)
            ORDER BY id ASC";
    $res = $conn->query($sql);

    if (!$res || $res->num_rows === 0) {
        echo "<script>alert('Nenhum registro encontrado para gerar a carga.'); window.history.back();</script>";
        exit;
    }

    // Monta DOM
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;

    $root = $doc->createElement('CARGAREGISTROS');
    $doc->appendChild($root);

    // Cabeçalho
    addTextEl($doc, $root, 'VERSAO', '2.7');   
    addTextEl($doc, $root, 'ACAO',   'CARGA'); 
    addTextEl($doc, $root, 'CNS',    (string)$numeroCNS);

    // Container de movimento de casamento
    $mov = $doc->createElement('MOVIMENTOCASAMENTOTC');
    $root->appendChild($mov);

    while ($row = $res->fetch_assoc()) {
        $reg = $doc->createElement('REGISTROCASAMENTOINCLUSAO');
        $mov->appendChild($reg);

        // ================== ORDEM EXATA DO MANUAL (TODAS AS TAGS) ==================
        // Índice do registro (use o id da linha)
        addTextEl($doc, $reg, 'INDICEREGISTRO', (string)$row['id']);

        // ---------- Cônjuge 1 ----------
        addTextEl($doc, $reg, 'NOMECONJUGE1',        (string)($row['conjuge1_nome'] ?? ''));
        addTextEl($doc, $reg, 'NOVONOMECONJUGE1',    (string)($row['conjuge1_nome_casado'] ?? '')); 
        addTextEl($doc, $reg, 'CPFCONJUGE1',         '');  
        addTextEl($doc, $reg, 'SEXOCONJUGE1',        (string)($row['conjuge1_sexo'] ?? '')); // M/F/I
        addTextEl($doc, $reg, 'DATANASCIMENTOCONJUGE1', '');
        addTextEl($doc, $reg, 'NOMEPAICONJUGE1',     '');
        addTextEl($doc, $reg, 'SEXOPAICONJUGE1',     '');
        addTextEl($doc, $reg, 'NOMEMAECONJUGE1',     '');
        addTextEl($doc, $reg, 'SEXOMAECONJUGE1',     '');
        addTextEl($doc, $reg, 'CODIGOOCUPACAOSDCCONJUGE1', '');
        addTextEl($doc, $reg, 'PAISNASCIMENTOCONJUGE1',    '');
        addTextEl($doc, $reg, 'NACIONALIDADECONJUGE1',     '');
        addTextEl($doc, $reg, 'CODIGOIBGEMUNNATCONJUGE1',  '');
        addTextEl($doc, $reg, 'TEXTOLIVREMUNNATCONJUGE1',  '');
        addTextEl($doc, $reg, 'CODIGOIBGEMUNLOGRADOURO1',  '');
        addTextEl($doc, $reg, 'DOMICILIOESTRANGEIRO1',     '');

        // ---------- Cônjuge 2 ----------
        addTextEl($doc, $reg, 'NOMECONJUGE2',        (string)($row['conjuge2_nome'] ?? ''));
        addTextEl($doc, $reg, 'NOVONOMECONJUGE2',    (string)($row['conjuge2_nome_casado'] ?? '')); 
        addTextEl($doc, $reg, 'CPFCONJUGE2',         '');
        addTextEl($doc, $reg, 'SEXOCONJUGE2',        (string)($row['conjuge2_sexo'] ?? '')); // M/F/I
        addTextEl($doc, $reg, 'DATANASCIMENTOCONJUGE2', '');
        addTextEl($doc, $reg, 'NOMEPAICONJUGE2',     '');
        addTextEl($doc, $reg, 'SEXOPAICONJUGE2',     '');
        addTextEl($doc, $reg, 'NOMEMAECONJUGE2',     '');
        addTextEl($doc, $reg, 'SEXOMAECONJUGE2',     '');
        addTextEl($doc, $reg, 'CODIGOOCUPACAOSDCCONJUGE2', '');
        addTextEl($doc, $reg, 'PAISNASCIMENTOCONJUGE2',    '');
        addTextEl($doc, $reg, 'NACIONALIDADECONJUGE2',     '');
        addTextEl($doc, $reg, 'CODIGOIBGEMUNNATCONJUGE2',  '');
        addTextEl($doc, $reg, 'TEXTOLIVREMUNNATCONJUGE2',  '');
        addTextEl($doc, $reg, 'CODIGOIBGEMUNLOGRADOURO2',  '');
        addTextEl($doc, $reg, 'DOMICILIOESTRANGEIRO2',     '');

        // ---------- Dados do registro ----------
        addTextEl($doc, $reg, 'MATRICULA',     (string)($row['matricula'] ?? ''));
        addTextEl($doc, $reg, 'DATAREGISTRO',  toBR($row['data_registro'] ?? null));
        addTextEl($doc, $reg, 'DATACASAMENTO', toBR($row['data_casamento'] ?? null));
        addTextEl($doc, $reg, 'REGIMECASAMENTO', (string)($row['regime_bens'] ?? ''));

        // ---------- Campos adicionais do XSD (mantidos vazios quando não se aplica) ----------
        addTextEl($doc, $reg, 'ORGAOEMISSOREXTERIOR',   ''); 
        addTextEl($doc, $reg, 'INFORMACOESCONSULADO',   ''); 
        addTextEl($doc, $reg, 'OBSERVACOES',            '');
        // ================================================================================ //
    }

    // Salva conteúdo em arquivo temporário para download
    $nomeArquivo = 'carga_casamento.xml';
    $doc->save($nomeArquivo);

    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$nomeArquivo.'"');
    readfile($nomeArquivo);
    @unlink($nomeArquivo);
    exit;

} else {
    echo "<script>alert('Nenhum registro selecionado.'); window.history.back();</script>";
}
