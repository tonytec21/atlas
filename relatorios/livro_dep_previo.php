<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Sao_Paulo');

// Configuração do PDF
class LivroDepositoPDF extends TCPDF
{
    public function Header()
    {
        $image_file = '../style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(12, 40, 10);
        $this->SetY(25);
    }
}

// Instanciar o PDF
$pdf = new LivroDepositoPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Livro de Depósito Prévio', 0, 1, 'C');
$pdf->Ln(10);

// Cabeçalho da tabela
$pdf->SetFont('helvetica', '', 8);
$conn->set_charset("utf8");

// Início da Tabela com Cabeçalho e Corpo Integrado
$html = '
    <table border="1" cellpadding="4" cellspacing="0" width="100%">
        <tr>
            <th style="width: 6%; text-align: center;"><b>Nº OS</b></th>
            <th style="width: 15%; text-align: center;"><b>Apresentante</b></th>
            <th style="width: 15%; text-align: center;"><b>CPF/CNPJ</b></th>
            <th style="width: 11%; text-align: center;"><b>Total OS (R$)</b></th>
            <th style="width: 9%; text-align: center;"><b>Data OS</b></th>
            <th style="width: 14%; text-align: center;"><b>Depósito Prévio</b></th>
            <th style="width: 30%; text-align: center;"><b>Atos Praticados</b></th>
        </tr>
';

// Consulta das Ordens de Serviço
$os_query = $conn->query("SELECT id, cliente, cpf_cliente, total_os, data_criacao FROM ordens_de_servico");

while ($os = $os_query->fetch_assoc()) {
    $os_id = $os['id'];
    $cliente = $os['cliente'];
    $cpf_cnpj = $os['cpf_cliente'] ?: '---';  // Exibir '---' se CPF/CNPJ não estiver preenchido
    $total_os = 'R$ ' . number_format($os['total_os'], 2, ',', '.');
    $data_os = date('d/m/Y', strtotime($os['data_criacao']));

    // Consulta dos Pagamentos
    $pagamento_query = $conn->prepare("SELECT total_pagamento, forma_de_pagamento, data_pagamento FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamento_query->bind_param("i", $os_id);
    $pagamento_query->execute();
    $pagamento_result = $pagamento_query->get_result();
    $pagamento_info = '';
    while ($pagamento = $pagamento_result->fetch_assoc()) {
        $valor = 'R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.');
        $forma = $pagamento['forma_de_pagamento'];
        $data_pagamento = date('d/m/Y', strtotime($pagamento['data_pagamento']));
        $pagamento_info .= "$valor - $forma - $data_pagamento<br/>";
    }

    // Consulta dos Atos Praticados
    $atos_query = $conn->prepare("SELECT ato, quantidade_liquidada, total, data FROM atos_liquidados WHERE ordem_servico_id = ?");
    $atos_query->bind_param("i", $os_id);
    $atos_query->execute();
    $atos_result = $atos_query->get_result();
    $atos_info = '<b>Atos Liquidados:</b><br/>';
    while ($ato = $atos_result->fetch_assoc()) {
        $descricao_ato = $ato['ato'];
        $quantidade = $ato['quantidade_liquidada'];
        $total = 'R$ ' . number_format($ato['total'], 2, ',', '.');
        $data_ato = date('d/m/Y', strtotime($ato['data']));
        $atos_info .= "$descricao_ato - $quantidade - $total - $data_ato<br/>";
    }

    // Consulta das Devoluções
    $devolucao_query = $conn->prepare("SELECT total_devolucao, forma_devolucao, data_devolucao FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucao_query->bind_param("i", $os_id);
    $devolucao_query->execute();
    $devolucao_result = $devolucao_query->get_result();
    
    if ($devolucao_result->num_rows > 0) {
        $atos_info .= '<b>Devoluções:</b><br/>';
        while ($devolucao = $devolucao_result->fetch_assoc()) {
            $valor_devolucao = 'R$ ' . number_format($devolucao['total_devolucao'], 2, ',', '.');
            $forma_devolucao = $devolucao['forma_devolucao'];
            $data_devolucao = date('d/m/Y', strtotime($devolucao['data_devolucao']));
            $atos_info .= "$valor_devolucao - $forma_devolucao - $data_devolucao<br/>";
        }
    }

    // Adicionar Linha da OS na Tabela
    $html .= "
        <tr>
            <td style='width: 6%; text-align: center;'>{$os_id}</td>
            <td style='width: 15%; text-align: left;'>{$cliente}</td>
            <td style='width: 15%; text-align: center;'>{$cpf_cnpj}</td>
            <td style='width: 11%; text-align: center;'>{$total_os}</td>
            <td style='width: 9%; text-align: center;'>{$data_os}</td>
            <td style='width: 14%; text-align: left;'>{$pagamento_info}</td>
            <td style='width: 30%; text-align: left;'>{$atos_info}</td>
        </tr>
    ";
}

$html .= '</table>';

// Adicionar a Tabela ao PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Gerar o PDF
$pdf->Output('Livro_Deposito_Previo.pdf', 'I');

?>
