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
$pdf->SetCompression(true); // Comprimir para melhorar performance  
$pdf->SetAutoPageBreak(true, 25);  
$pdf->AddPage();  
$pdf->SetFont('helvetica', 'B', 12);  
$pdf->Cell(0, 10, 'Livro de Depósito Prévio', 0, 1, 'C');  
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

// Consulta principal otimizada - usando DISTINCT e índices  
$os_query = $conn->query("  
    SELECT DISTINCT os.id, os.cliente, os.cpf_cliente, os.total_os, os.data_criacao   
    FROM ordens_de_servico os  
    INNER JOIN pagamento_os po ON os.id = po.ordem_de_servico_id  
    ORDER BY os.id  
");  

// Iniciar buffer para controle de memória  
ob_start();  

// Contador para gerenciamento de memória  
$contador = 0;  

while ($os = $os_query->fetch_assoc()) {  
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
    $pdf->Cell(15, 6, 'Nº OS', 1, 0, 'C', true);  
    $pdf->Cell(95, 6, 'APRESENTANTE', 1, 0, 'C', true);  
    $pdf->Cell(30, 6, 'CPF/CNPJ', 1, 0, 'C', true);  
    $pdf->Cell(30, 6, 'TOTAL OS (R$)', 1, 0, 'C', true);  
    $pdf->Cell(20, 6, 'DATA OS', 1, 1, 'C', true);  

    $pdf->SetFont('helvetica', '', 8);  
    $pdf->Cell(15, 6, $os_id, 1);  
    $pdf->Cell(95, 6, $cliente, 1);  
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
}  

// Fechar statements  
$pagamento_stmt->close();  
$atos_stmt->close();  
$atos_manuais_stmt->close();  
$devolucao_stmt->close();  

// Limpar buffer antes de gerar o PDF  
ob_end_clean();  

// Nome do arquivo incluindo o número do livro atual  
$nomeArquivo = 'Livro_Deposito_Previo_Livro_' . $pdf->getLivroAtual() . '.pdf';  

// Gerar o PDF  
$pdf->Output($nomeArquivo, 'I');  
?>