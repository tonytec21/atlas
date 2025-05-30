<?php
include(__DIR__ . '/db_connection.php');
require('../oficios/tcpdf/tcpdf.php');

// Carregar o número do ID da guia de recebimento
$guia_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); // Define a codificação do charset para utf8

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados da guia de recebimento
$stmt = $conn->prepare("SELECT * FROM guia_de_recebimento WHERE id = ?");
$stmt->bind_param("i", $guia_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Guia de recebimento não encontrada.");
}

$guiaData = $result->fetch_assoc();
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
    private $criado_por;

    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/timbrado.png'; // Verifique se o caminho está correto
        
        // Salva as margens atuais
        $currentMargins = $this->getMargins();
        
        // Desativa temporariamente as margens e AutoPageBreak
        $this->SetAutoPageBreak(false, 0); // Desativa o AutoPageBreak para permitir a imagem cobrir toda a página
        $this->SetMargins(0, 0, 0);

        // Inserir a imagem ocupando toda a página
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Restaura o AutoPageBreak e as margens para o conteúdo subsequente
        $this->SetAutoPageBreak(true, 10); // Ativa novamente o AutoPageBreak com a margem inferior padrão
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
        $this->SetY(25); // Define o ponto Y após a imagem para o conteúdo
    }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Guia de Recebimento');
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 10); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 8 * 0.5;

// Cabeçalho da Guia
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'GUIA DE RECEBIMENTO DE DOCUMENTOS', 0, 1, 'C');
$pdf->Ln(3);

// Conteúdo da Guia
$pdf->SetFont('helvetica', '', 10);
$html = '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="13%">Nº Guia:</td> <!-- Alterado para Nº Guia -->
        <td class="header-cell" width="25%">Data de Recebimento:</td>
        <td class="header-cell" width="37%">Apresentante:</td>
        <td class="header-cell" width="25%">CPF/CNPJ:</td>
    </tr>
    <tr>
        <td class="data-cell" width="13%">' . convertToUtf8($guiaData['id']) . '</td> <!-- Exibindo o id da guia -->
        <td class="data-cell" width="25%">' . convertToUtf8(formatDateToBrazilian($guiaData['data_recebimento'])) . '</td>
        <td class="data-cell" width="37%">' . convertToUtf8($guiaData['cliente']) . '</td>
        <td class="data-cell" width="25%">' . (!empty($guiaData['documento_apresentante']) ? convertToUtf8($guiaData['documento_apresentante']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="48%">Funcionário:</td>
        <td class="header-cell" width="52%">Observações:</td>
    </tr>
    <tr>
        <td class="data-cell" width="48%">' . convertToUtf8($guiaData['funcionario']) . '</td>
        <td class="data-cell" width="52%" style="text-align:justify;">' . (!empty($guiaData['observacoes']) ? convertToUtf8($guiaData['observacoes']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="100%">Documento(s) Recebido(s):</td>
    </tr>
    <tr>
        <td class="data-cell" width="100%" style="text-align:justify;">' . convertToUtf8($guiaData['documentos_recebidos']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Campos de assinatura
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Apresentante', 0, 1, 'L');

// Linha de corte
$pdf->Ln(15);
$pdf->Cell(0, $lineHeight, '----------------------------------------------------- Corte Aqui -----------------------------------------------------', 0, 1, 'C');

// Cabeçalho do Canhoto
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'COMPROVANTE', 0, 1, 'C');
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
        <td class="header-cell" width="13%">Nº Guia:</td> <!-- Alterado para Nº Guia -->
        <td class="header-cell" width="25%">Data de Recebimento:</td>
        <td class="header-cell" width="37%">Apresentante:</td>
        <td class="header-cell" width="25%">CPF/CNPJ:</td>
    </tr>
    <tr>
        <td class="data-cell" width="13%">' . convertToUtf8($guiaData['id']) . '</td> <!-- Exibindo o id da guia -->
        <td class="data-cell" width="25%">' . convertToUtf8(formatDateToBrazilian($guiaData['data_recebimento'])) . '</td>
        <td class="data-cell" width="37%">' . convertToUtf8($guiaData['cliente']) . '</td>
        <td class="data-cell" width="25%">' . (!empty($guiaData['documento_apresentante']) ? convertToUtf8($guiaData['documento_apresentante']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="48%">Funcionário:</td>
        <td class="header-cell" width="52%">Observações:</td>
    </tr>
    <tr>
        <td class="data-cell" width="48%">' . convertToUtf8($guiaData['funcionario']) . '</td>
        <td class="data-cell" width="52%" style="text-align:justify;">' . (!empty($guiaData['observacoes']) ? convertToUtf8($guiaData['observacoes']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="100%">Documento(s) Recebido(s):</td>
    </tr>
    <tr>
        <td class="data-cell" width="100%" style="text-align:justify;">' . convertToUtf8($guiaData['documentos_recebidos']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Campos de assinatura no canhoto
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Funcionário', 0, 1, 'L');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Guia_Recebimento_' . $guiaData['id'] . '.pdf', 'I');
?>
