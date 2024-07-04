<?php
include(__DIR__ . '/db_connection.php');
require('../oficios/tcpdf/tcpdf.php');

// Carregar o número do protocolo
$task_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); // Define a codificação do charset para utf8

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados do recibo de entrega
$stmt = $conn->prepare("SELECT * FROM recibos_de_entrega WHERE task_id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Recibo de entrega não encontrado.");
}

$reciboData = $result->fetch_assoc();
$stmt->close();

// Buscar dados da tarefa
$stmt = $conn->prepare("SELECT * FROM tarefas WHERE id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tarefa não encontrada.");
}

$tarefaData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Converter data para o formato brasileiro
function formatDateToBrazilian($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y H:i:s');
}

// Função para converter a codificação para UTF-8
function convertToUtf8($data)
{
    return mb_convert_encoding($data, 'UTF-8', 'auto');
}

// Configurar a classe PDF
class PDF extends TCPDF
{
    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/logo.png'; // Verifique se o caminho está correto
        $this->Image($image_file, 30, 10, 150, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetY(35); // Ajuste para garantir que o conteúdo não sobreponha a imagem
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
    }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Recibo de Entrega - Protocolo Geral nº ' . $tarefaData['id']);
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 10); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 8 * 0.5;

// Cabeçalho do Recibo
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'RECIBO DE ENTREGA DE DOCUMENTOS', 0, 1, 'C');
$pdf->Ln(3);

// Conteúdo do Recibo
$pdf->SetFont('helvetica', '', 10);
$html = '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="33%">Protocolo Geral:</td>
        <td class="header-cell" width="33%">Data da Entrega:</td>
        <td class="header-cell" width="33%">Receptor:</td>
    </tr>
    <tr>
        <td class="data-cell" width="33%">' . convertToUtf8($tarefaData['id']) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8(formatDateToBrazilian($reciboData['data_entrega'])) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8($reciboData['receptor']) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="49.5%">Entregador:</td>
        <td class="header-cell" width="49.5%">Observações:</td>
    </tr>
    <tr>
        <td class="data-cell" width="49.5%">' . convertToUtf8($reciboData['entregador']) . '</td>
        <td class="data-cell" width="49.5%" style="text-align:justify;">' . (!empty($reciboData['observacoes']) ? convertToUtf8($reciboData['observacoes']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="99%">Documento(s) Entregue(s):</td>
    </tr>
    <tr>
        <td class="data-cell" width="99%" style="text-align:justify;">' . convertToUtf8($reciboData['documentos']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Campos de assinatura
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Receptor', 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Entregador', 0, 1, 'L');

// Linha de corte
$pdf->Ln(15);
$pdf->Cell(0, $lineHeight, '----------------------------------------------------- Corte Aqui -----------------------------------------------------', 0, 1, 'C');

// Cabeçalho do Canhoto
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'CANHOTO DE COMPROVANTE', 0, 1, 'C');
$pdf->Ln(3);

// Conteúdo do Canhoto
$pdf->SetFont('helvetica', '', 10);
$html = '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="33%">Protocolo Geral:</td>
        <td class="header-cell" width="33%">Data da Entrega:</td>
        <td class="header-cell" width="33%">Receptor:</td>
    </tr>
    <tr>
        <td class="data-cell" width="33%">' . convertToUtf8($tarefaData['id']) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8(formatDateToBrazilian($reciboData['data_entrega'])) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8($reciboData['receptor']) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="49.5%">Entregador:</td>
        <td class="header-cell" width="49.5%">Observações:</td>
    </tr>
    <tr>
        <td class="data-cell" width="49.5%">' . convertToUtf8($reciboData['entregador']) . '</td>
        <td class="data-cell" width="49.5%" style="text-align:justify;">' . (!empty($reciboData['observacoes']) ? convertToUtf8($reciboData['observacoes']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="99%">Documento(s) Entregue(s):</td>
    </tr>
    <tr>
        <td class="data-cell" width="99%" style="text-align:justify;">' . convertToUtf8($reciboData['documentos']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Campos de assinatura no canhoto
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Receptor', 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Entregador', 0, 1, 'L');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Recibo_Entrega_Protocolo_Geral_' . $tarefaData['id'] . '.pdf', 'I');
?>
