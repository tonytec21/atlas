<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
require_once('../oficios/tcpdf/tcpdf.php');  
include(__DIR__ . '/db_connection2.php');  

error_reporting(E_ERROR | E_PARSE);  
date_default_timezone_set('America/Sao_Paulo');  

// Aumentar limites de memória e tempo de execução  
ini_set('memory_limit', '2048M');  
set_time_limit(600); // 10 minutos  


/* =====================================================================
   MODO "LIQUIDAÇÃO EM DATA DIVERSA DO DEPÓSITO" (ativado por JSON)
   ---------------------------------------------------------------------
   Arquivo: config_livro_dep_previo.json (mesma pasta deste script).
   Quando ativo, o livro carrega SOMENTE as O.S. em que pelo menos um ato
   foi liquidado em data diferente da data do pagamento. As O.S. cujos
   atos foram todos liquidados no mesmo dia do pagamento ficam de fora.
   Se o arquivo não existir, o comportamento é o original (sem filtro).
   ===================================================================== */
$LIVRO_CFG = array(
    'ativo'                     => false,
    'base_comparacao'           => 'primeiro_pagamento', // ou 'qualquer_pagamento'
    'incluir_os_sem_liquidacao' => true,
    'exibir_subtitulo'          => false,
    'sufixo_arquivo'            => '_Divergentes',
    'permitir_override_url'     => false
);

$LIVRO_CFG_FILE = __DIR__ . '/config_livro_dep_previo.json';
if (is_file($LIVRO_CFG_FILE)) {
    $cfg_raw = json_decode(file_get_contents($LIVRO_CFG_FILE), true);
    if (is_array($cfg_raw) && isset($cfg_raw['modo_liquidacao_em_data_diversa'])
        && is_array($cfg_raw['modo_liquidacao_em_data_diversa'])) {
        $LIVRO_CFG = array_merge($LIVRO_CFG, $cfg_raw['modo_liquidacao_em_data_diversa']);
    }
}

$filtro_divergentes = filter_var($LIVRO_CFG['ativo'], FILTER_VALIDATE_BOOLEAN);

// Override opcional por URL (?divergentes=1 / ?divergentes=0), só se liberado no JSON
if (filter_var($LIVRO_CFG['permitir_override_url'], FILTER_VALIDATE_BOOLEAN) && isset($_GET['divergentes'])) {
    $filtro_divergentes = filter_var($_GET['divergentes'], FILTER_VALIDATE_BOOLEAN);
}

/**
 * Pré-calcula, em duas varreduras agregadas, quais O.S. têm liquidação em data
 * diferente da data do pagamento. Devolve um array [id_da_os => true].
 *
 * O filtro NÃO vai para a consulta principal de propósito: EXISTS correlacionado
 * com DATE() não usa índice e faz o MySQL varrer atos_liquidados uma vez por O.S.
 * Aqui são 3 GROUP BY simples e a decisão acontece em memória.
 */
function carregarOsElegiveis($conn, $cfg)
{
    $incluir_sem_liq = filter_var($cfg['incluir_os_sem_liquidacao'], FILTER_VALIDATE_BOOLEAN);
    $por_qualquer    = ($cfg['base_comparacao'] === 'qualquer_pagamento');

    // 1) Datas de pagamento por O.S.
    $sql = $por_qualquer
        ? "SELECT DISTINCT ordem_de_servico_id, DATE(data_pagamento) FROM pagamento_os"
        : "SELECT ordem_de_servico_id, MIN(DATE(data_pagamento)) FROM pagamento_os GROUP BY ordem_de_servico_id";

    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }

    $pag = array();
    while ($r = $res->fetch_row()) {
        $id = (int) $r[0];
        if ($por_qualquer) {
            $pag[$id][(string) $r[1]] = true;
        } else {
            $pag[$id] = $r[1];
        }
    }
    $res->free();

    // 2) Datas de liquidação por O.S. (atos + atos manuais)
    $liq = array(); // modo qualquer_pagamento: id => [datas]
    $agg = array(); // modo primeiro_pagamento: id => menor/maior data e nulos

    foreach (array('atos_liquidados', 'atos_manuais_liquidados') as $tabela) {
        $sql = $por_qualquer
            ? "SELECT DISTINCT ordem_servico_id, DATE(data) FROM $tabela"
            : "SELECT ordem_servico_id, MIN(DATE(data)), MAX(DATE(data)),
                      SUM(CASE WHEN data IS NULL THEN 1 ELSE 0 END)
               FROM $tabela GROUP BY ordem_servico_id";

        $res = $conn->query($sql);
        if (!$res) {
            return false;
        }

        while ($r = $res->fetch_row()) {
            $id = (int) $r[0];

            if ($por_qualquer) {
                $liq[$id][(string) $r[1]] = true;
                continue;
            }

            if (!isset($agg[$id])) {
                $agg[$id] = array('menor' => null, 'maior' => null, 'nulos' => 0);
            }
            if ($r[1] !== null && ($agg[$id]['menor'] === null || $r[1] < $agg[$id]['menor'])) {
                $agg[$id]['menor'] = $r[1];
            }
            if ($r[2] !== null && ($agg[$id]['maior'] === null || $r[2] > $agg[$id]['maior'])) {
                $agg[$id]['maior'] = $r[2];
            }
            $agg[$id]['nulos'] += (int) $r[3];
        }
        $res->free();
    }

    // 3) Decide O.S. por O.S.
    $elegiveis = array();

    foreach ($pag as $id => $info) {
        $tem_liquidacao = $por_qualquer ? isset($liq[$id]) : isset($agg[$id]);

        // O.S. paga e ainda sem nenhum ato liquidado: depósito em aberto
        if (!$tem_liquidacao) {
            if ($incluir_sem_liq) {
                $elegiveis[$id] = true;
            }
            continue;
        }

        if ($por_qualquer) {
            // Basta um ato liquidado em dia sem pagamento na O.S.
            foreach ($liq[$id] as $data_ato => $ignora) {
                if ($data_ato === '' || !isset($info[$data_ato])) {
                    $elegiveis[$id] = true;
                    break;
                }
            }
            continue;
        }

        // Padrão: compara com a data do primeiro pagamento
        $a = $agg[$id];
        if ($a['nulos'] > 0 || $a['menor'] === null
            || $a['menor'] !== $info || $a['maior'] !== $info) {
            $elegiveis[$id] = true;
        }
    }

    unset($pag, $liq, $agg);

    return $elegiveis;
}

// Configuração do PDF  
class LivroDepositoPDF extends TCPDF  
{  
    protected $folhaAtual = 0;  
    protected $livroAtual = 1;  
    protected $folhasPorLivro = 300;  
    protected $isFront = true;   

    public function Header()  
    {  
        $image_file = '../style/img/timbrado.png';  
        $this->SetAutoPageBreak(false, 0);  
        $this->SetMargins(0, 0, 0);  
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);  
        $this->SetAutoPageBreak(true, 25);  
        $this->SetMargins(12, 40, 10);  
        
        // AJUSTE: Posicionamento do número do livro e página para ficar na altura correta  
        $this->SetY(30); // Ajuste aqui para posicionar na mesma altura do título  
        $this->SetFont('helvetica', 'B', 8);  
        
        // Número do livro à esquerda  
        $this->Cell(50, 10, 'LIVRO ' . $this->livroAtual, 0, 0, 'L');  
        
        // Espaço central  
        $this->Cell(100, 10, '', 0, 0, 'C');  
        
        // Número da folha à direita com frente/verso  
        $folhaFormatada = sprintf('%03d', $this->folhaAtual); // Formata com zeros à esquerda  
        $lado = $this->isFront ? 'V' : 'F';  
        $this->Cell(35, 10, 'FOLHA ' . $folhaFormatada . $lado, 0, 1, 'R');  
        
        $this->SetY(25);  
    }  

    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false)  
    {  
        // Alterna entre frente e verso  
        if ($this->isFront) {  
            $this->folhaAtual++;  
        }  
        $this->isFront = !$this->isFront;  

        // Verifica se atingiu o limite de folhas do livro  
        if ($this->folhaAtual > $this->folhasPorLivro) {  
            $this->livroAtual++;  
            $this->folhaAtual = 1;  
            $this->isFront = false;   
        }  

        parent::AddPage($orientation, $format, $keepmargins, $tocpage);  
    }  

    public function getLivroAtual() {  
        return $this->livroAtual;  
    }  

    public function getFolhaAtual() {  
        return sprintf('%03d%s', $this->folhaAtual, $this->isFront ? 'F' : 'V');  
    }  
}  

// Instanciar o PDF com otimizações  
$pdf = new LivroDepositoPDF();  
$pdf->SetCompression(true);  
$pdf->SetAutoPageBreak(true, 25);  
$pdf->AddPage();  
$pdf->SetFont('helvetica', 'B', 12);  
$pdf->Cell(0, 10, 'Livro de Depósito Prévio', 0, 1, 'C');
if ($filtro_divergentes && filter_var($LIVRO_CFG['exibir_subtitulo'], FILTER_VALIDATE_BOOLEAN)) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Somente O.S. com liquidação em data diversa da do pagamento', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
}
$pdf->Ln(10);

// Preparar conexão com otimizações  
$conn->set_charset("utf8");  
$conn->query("SET SESSION sql_big_selects=1");  
$conn->query("SET SESSION wait_timeout=300");  

// Preparar statements reutilizáveis no início  
$pagamento_stmt = $conn->prepare("SELECT total_pagamento, forma_de_pagamento, data_pagamento FROM pagamento_os WHERE ordem_de_servico_id = ?");  
$atos_stmt = $conn->prepare("SELECT ato, quantidade_liquidada, total, data FROM atos_liquidados WHERE ordem_servico_id = ?");  
$atos_manuais_stmt = $conn->prepare("SELECT ato, quantidade_liquidada, total, data FROM atos_manuais_liquidados WHERE ordem_servico_id = ?");  
$devolucao_stmt = $conn->prepare("SELECT total_devolucao, forma_devolucao, data_devolucao FROM devolucao_os WHERE ordem_de_servico_id = ?");  

// Conjunto de O.S. elegíveis quando o filtro está ligado
$os_elegiveis = array();
if ($filtro_divergentes) {
    $os_elegiveis = carregarOsElegiveis($conn, $LIVRO_CFG);
    if ($os_elegiveis === false) {
        die('Erro ao apurar as liquidações: ' . $conn->error);
    }
}

// Consulta principal otimizada - usando DISTINCT e índices
$os_query = $conn->query("
    SELECT DISTINCT os.id, os.cliente, os.cpf_cliente, os.total_os, os.data_criacao
    FROM ordens_de_servico os
    INNER JOIN pagamento_os po ON os.id = po.ordem_de_servico_id
    ORDER BY os.id
");

if (!$os_query) {
    die('Erro ao consultar as ordens de serviço: ' . $conn->error);
}

// Iniciar buffer para controle de memória  
ob_start();  

// Contador para gerenciamento de memória  
$contador = 0;  

$numero_ordem = 1;

while ($os = $os_query->fetch_assoc()) {
    // Filtro por data de liquidação: descarta a O.S. antes de qualquer trabalho
    if ($filtro_divergentes && !isset($os_elegiveis[(int) $os['id']])) {
        continue;
    }

    // Verificar se precisa de nova página antes de adicionar conteúdo
    if ($pdf->GetY() > 250) {  
        $pdf->AddPage();  
    }  

    $os_id = $os['id'];  
    $cliente = $os['cliente'];  
    $cpf_cnpj = $os['cpf_cliente'] ?: '---';  
    $total_os = 'R$ ' . number_format($os['total_os'], 2, ',', '.');  
    $data_os = date('d/m/Y', strtotime($os['data_criacao']));  

    // Variáveis para observações e cálculos  
    $observacoes = '';  
    $total_geral_atos = 0;  
    $total_devolucoes = 0;  
    $deposito_previo_total = 0;  

    // Pagamentos (Depósito Prévio) - usando prepared statement já preparado  
    $pagamento_stmt->bind_param("i", $os_id);  
    $pagamento_stmt->execute();  
    $pagamento_result = $pagamento_stmt->get_result();  

    $observacoes .= "<b>DEPÓSITOS PRÉVIO: </b>";  
    $pagamentos = [];   

    while ($pagamento = $pagamento_result->fetch_assoc()) {  
        $valor = $pagamento['total_pagamento'];  
        $forma = $pagamento['forma_de_pagamento'];  
        $data_pagamento = date('d/m/Y', strtotime($pagamento['data_pagamento']));  
        $deposito_previo_total += $valor;  
        $pagamentos[] = 'R$ ' . number_format($valor, 2, ',', '.') . " - $forma - $data_pagamento";  
    }  

    $observacoes .= implode(' | ', $pagamentos);  
    $observacoes .= " | <b>TOTAL EM DEP. PRÉVIO: </b> R$ " . number_format($deposito_previo_total, 2, ',', '.');  

    // Atos Praticados - atos_liquidados - usando prepared statement já preparado  
    $atos_stmt->bind_param("i", $os_id);  
    $atos_stmt->execute();  
    $atos_result = $atos_stmt->get_result();  

    $observacoes .= " | <b>ATOS PRATICADOS: </b>";  
    while ($ato = $atos_result->fetch_assoc()) {  
        $descricao_ato = $ato['ato'];  
        $quantidade = $ato['quantidade_liquidada'];  
        $total = $ato['total'];  
        $data_ato = date('d/m/Y', strtotime($ato['data']));  
        $total_geral_atos += $total;  
        $observacoes .= "$descricao_ato - Qtd: $quantidade - Total: R$ " . number_format($total, 2, ',', '.') . " - Data: $data_ato | ";  
    }  

    // Atos Praticados - atos_manuais_liquidados - usando prepared statement já preparado  
    $atos_manuais_stmt->bind_param("i", $os_id);  
    $atos_manuais_stmt->execute();  
    $atos_manuais_result = $atos_manuais_stmt->get_result();  

    if ($atos_manuais_result->num_rows > 0) {  
        $observacoes .= " | <b>ATOS MANUAIS PRATICADOS: </b>";  
        while ($ato_manual = $atos_manuais_result->fetch_assoc()) {  
            $descricao_ato_manual = $ato_manual['ato'];  
            $quantidade_manual = $ato_manual['quantidade_liquidada'];  
            $total_manual = $ato_manual['total'];  
            $data_ato_manual = date('d/m/Y', strtotime($ato_manual['data']));  
            $total_geral_atos += $total_manual;  
            $observacoes .= "$descricao_ato_manual - Qtd: $quantidade_manual - Total: R$ " . number_format($total_manual, 2, ',', '.') . " - Data: $data_ato_manual | ";  
        }  
    }  

    // Exibir o Total Geral dos Atos  
    $observacoes .= "<b>TOTAL GERAL DOS ATOS:</b> R$ " . number_format($total_geral_atos, 2, ',', '.');  

    // Devoluções - usando prepared statement já preparado  
    $devolucao_stmt->bind_param("i", $os_id);  
    $devolucao_stmt->execute();  
    $devolucao_result = $devolucao_stmt->get_result();  

    if ($devolucao_result->num_rows > 0) {  
        $observacoes .= " | <b>DEVOLUÇÕES: </b>";  
        $devolucoes = [];  
        while ($devolucao = $devolucao_result->fetch_assoc()) {  
            $valor_devolucao = $devolucao['total_devolucao'];  
            $forma_devolucao = $devolucao['forma_devolucao'];  
            $data_devolucao = date('d/m/Y', strtotime($devolucao['data_devolucao']));  
            $total_devolucoes += $valor_devolucao;  
            $devolucoes[] = 'R$ ' . number_format($valor_devolucao, 2, ',', '.') . " - $forma_devolucao - $data_devolucao";  
        }  
        $observacoes .= implode(' | ', $devolucoes);  
    }  

    // Cálculo do Saldo  
    $saldo = $deposito_previo_total - $total_geral_atos - $total_devolucoes;  
    if (round($saldo, 2) != 0) {  
        $observacoes .= " | <b>SALDO: </b> R$ " . number_format($saldo, 2, ',', '.');  
    }  

    // Tabela Principal da OS com a nova célula "OBSERVAÇÕES"  
    $pdf->SetFillColor(242, 242, 242);  
    $pdf->SetFont('helvetica', 'B', 8);  
    $pdf->Cell(15, 6, 'ORDEM', 1, 0, 'C', true); 
    $pdf->Cell(15, 6, 'Nº OS', 1, 0, 'C', true);  
    $pdf->Cell(80, 6, 'APRESENTANTE', 1, 0, 'C', true);  
    $pdf->Cell(30, 6, 'CPF/CNPJ', 1, 0, 'C', true);  
    $pdf->Cell(30, 6, 'TOTAL OS (R$)', 1, 0, 'C', true);  
    $pdf->Cell(20, 6, 'DATA OS', 1, 1, 'C', true);  

    $pdf->SetFont('helvetica', '', 8);  
    $pdf->Cell(15, 6, $numero_ordem, 1); 
    $pdf->Cell(15, 6, $os_id, 1);  
    $pdf->Cell(80, 6, $cliente, 1);  
    $pdf->Cell(30, 6, $cpf_cnpj, 1);  
    $pdf->Cell(30, 6, $total_os, 1);  
    $pdf->Cell(20, 6, $data_os, 1, 1);  
    
    $pdf->SetFont('helvetica', 'B', 8);  
    $pdf->Cell(0, 6, 'OBSERVAÇÕES', 1, 1, 'C', true);  

    $pdf->SetFont('helvetica', '', 8);  
    $pdf->writeHTMLCell(0, 0, '', '', $observacoes, 1, 1, false, true, 'J', true);  
    $pdf->Ln(5);  
    
    // Liberar resultados para economizar memória  
    $pagamento_result->free();  
    $atos_result->free();  
    $atos_manuais_result->free();  
    if ($devolucao_result) {  
        $devolucao_result->free();  
    }  
    
    // Limpar memória a cada 50 registros  
    $contador++;  
    if ($contador % 50 == 0) {  
        // Liberar memória  
        unset($observacoes);  
        unset($pagamentos);  
        unset($devolucoes);  
        gc_collect_cycles();  
    }  

    $numero_ordem++;
}  

// Nenhuma O.S. atendeu ao filtro: registra o fato em vez de entregar folha em branco
if ($contador === 0) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Nenhuma ordem de serviço atende aos critérios do livro.', 1, 1, 'C');
}

// Fechar statements
$pagamento_stmt->close();  
$atos_stmt->close();  
$atos_manuais_stmt->close();  
$devolucao_stmt->close();  

// Limpar buffer antes de gerar o PDF  
ob_end_clean();  

// Nome do arquivo incluindo o número do livro atual  
$sufixo = '';
if ($filtro_divergentes && !empty($LIVRO_CFG['sufixo_arquivo'])) {
    $sufixo = preg_replace('/[^A-Za-z0-9_\-]/', '', $LIVRO_CFG['sufixo_arquivo']);
}
$nomeArquivo = 'Livro_Deposito_Previo_Livro_' . $pdf->getLivroAtual() . $sufixo . '.pdf';

// Gerar o PDF  
$pdf->Output($nomeArquivo, 'I');  
?>