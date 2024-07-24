<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);

// Função para definir o fuso horário corretamente como sendo brasileiro
date_default_timezone_set('America/Sao_Paulo');

// Configurar a classe PDF
class PDF extends TCPDF
{
    private $criado_por;

    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/logo.png'; // Verifique se o caminho está correto
        @$this->Image($image_file, 20, 8, 170, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetY(25);
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('arial', 'I', 8);
        // Adiciona o nome do usuário que criou a OS
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Criado por: ' . $this->criado_por, 0, false, 'R', 0, '', 0, false, 'T', 'M');

    }

    public function setCriadoPor($criado_por)
    {
        $this->criado_por = $criado_por;
    }
}

if (isset($_GET['id'])) {
    $os_id = $_GET['id'];

    // Obter dados da SO
    $os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $os_query->bind_param("i", $os_id);
    $os_query->execute();
    $os_result = $os_query->get_result();
    $ordem_servico = $os_result->fetch_assoc();

    // Obter dados de itens da SO
    $os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
    $os_items_query->bind_param("i", $os_id);
    $os_items_query->execute();
    $os_items_result = $os_items_query->get_result();
    $ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

    // Obter informações do criador
    $criado_por = $ordem_servico['criado_por'];
    $user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $user_query->bind_param("s", $criado_por);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user_info = $user_result->fetch_assoc();
    $criado_por_nome = $user_info['nome_completo'];
    $criado_por_cargo = $user_info['cargo'];

    // Obter informações do usuário logado
    $logged_in_user = $_SESSION['username'];
    $logged_in_user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $logged_in_user_query->bind_param("s", $logged_in_user);
    $logged_in_user_query->execute();
    $logged_in_user_result = $logged_in_user_query->get_result();
    $logged_in_user_info = $logged_in_user_result->fetch_assoc();
    $logged_in_user_nome = $logged_in_user_info['nome_completo'];
    $logged_in_user_cargo = $logged_in_user_info['cargo'];

    $pdf = new PDF();
    $pdf->SetMargins(10, 45, 10);
    $pdf->setCriadoPor($criado_por_nome); // Define o nome do usuário que criou a OS
    $pdf->AddPage();
    $pdf->SetFont('arial', '', 10);

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->writeHTML('<div style="text-align: center;">ORDEM DE SERVIÇO Nº: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div style="text-align: left;">'.'<b>CLIENTE: ' . $ordem_servico['cliente'] .'</b></div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div style="text-align: left;">'.'<b>CPF/CNPJ: '. $ordem_servico['cpf_cliente'] .'</b></div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    $data_criacao = date('d/m/Y - H:i', strtotime($ordem_servico['data_criacao']));
    $pdf->writeHTML('<div style="text-align: left;">'.'<b>DATA: '. $data_criacao .'</b></div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div style="text-align: justify;">'.'<b>OBS:</b> '. $ordem_servico['observacoes'] .'</div>', true, false, true, false, '');
    $pdf->Ln(5);

    $html = '
    <h3>Itens da Ordem de Serviço</h3>
    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th style="width: 8%; text-align: center; font-size: 8px;"><b>ATO</b></th>
                <th style="width: 5%; text-align: center; font-size: 8px;"><b>QTD</b></th>
                <th style="width: 8%; text-align: center; font-size: 8px;"><b>DESC. LEGAL (%)</b></th>
                <th style="width: 24%; text-align: center; font-size: 8px;"><b>DESCRIÇÃO</b></th>
                <th style="width: 12%; text-align: center; font-size: 8px;"><b>EMOL</b></th>
                <th style="width: 10%; text-align: center; font-size: 8px;"><b>FERC</b></th>
                <th style="width: 10%; text-align: center; font-size: 8px;"><b>FADEP</b></th>
                <th style="width: 10%; text-align: center; font-size: 8px;"><b>FEMP</b></th>
                <th style="width: 12%; text-align: center; font-size: 8px;"><b>TOTAL</b></th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($ordem_servico_itens as $item) {
        $html .= '<tr>
            <td style="width: 8%; font-size: 8px;">' . $item['ato'] . '</td>
            <td style="width: 5%; font-size: 8px;">' . $item['quantidade'] . '</td>
            <td style="width: 8%; font-size: 8px;">' . $item['desconto_legal'] . '</td>
            <td style="width: 24%; font-size: 8px;">' . $item['descricao'] . '</td>
            <td style="width: 12%; font-size: 8px;">R$ ' . number_format($item['emolumentos'], 2, ',', '.') . '</td>
            <td style="width: 10%; font-size: 8px;">R$ ' . number_format($item['ferc'], 2, ',', '.') . '</td>
            <td style="width: 10%; font-size: 8px;">R$ ' . number_format($item['fadep'], 2, ',', '.') . '</td>
            <td style="width: 10%; font-size: 8px;">R$ ' . number_format($item['femp'], 2, ',', '.') . '</td>
            <td style="width: 12%; font-size: 8px;">R$ ' . number_format($item['total'], 2, ',', '.') . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>';

    // Adicionar a tabela e os valores totais abaixo dela
    $pdf->writeHTML($html, true, false, true, false, '');

    // Adicionar os valores totais abaixo da tabela
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 20px;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor Pago em Dep. Prévio: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(10);

    // Adicionar a linha de assinatura
    $pdf->Cell(0, 5, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, $logged_in_user_cargo, 0, 1, 'C');

    $pdf->Output('ordem_de_servico.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>
