<?php
require('tcpdf/tcpdf.php');

// Carregar o número do ofício
$numero = isset($_GET['numero']) ? $_GET['numero'] : 0;

// Conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados do ofício
$stmt = $conn->prepare("SELECT * FROM oficios WHERE numero = ?");
$stmt->bind_param("s", $numero); // "s" to accept the format "NUMERO/ANO"
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ofício não encontrado.");
}

$oficioData = $result->fetch_assoc();
$stmt->close();

// Buscar dados da serventia
$stmt = $conn->prepare("SELECT cidade FROM cadastro_serventia WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Dados da serventia não encontrados.");
}

$serventiaData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Obter NUMERO_SEQUENCIAL e ANO_VIGENTE
list($numeroSequencial, $anoVigente) = explode('/', $oficioData['numero']);

// Converter data para o formato brasileiro
function formatDateToBrazilian($date)
{
    $dateTime = new DateTime($date);
    $mes = array(
        'January' => 'janeiro',
        'February' => 'fevereiro',
        'March' => 'março',
        'April' => 'abril',
        'May' => 'maio',
        'June' => 'junho',
        'July' => 'julho',
        'August' => 'agosto',
        'September' => 'setembro',
        'October' => 'outubro',
        'November' => 'novembro',
        'December' => 'dezembro'
    );
    return $dateTime->format('d') . ' de ' . $mes[$dateTime->format('F')] . ' de ' . $dateTime->format('Y');
}

// Carregar assinaturas
$signatures = json_decode(file_get_contents(__DIR__ . '/assinaturas/data.json'), true);
$signatureImage = '';
foreach ($signatures as $signature) {
    if ($signature['fullName'] == $oficioData['assinante']) {
        $signatureImage = $signature['assinatura'];
        break;
    }
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

    // Adicionar parágrafo com recuo na primeira linha
    public function AddParagraph($text, $lineHeight)
    {
        $this->SetX(25); // Recuo da margem esquerda
        $paragraphs = explode("\n", $text);
        foreach ($paragraphs as $paragraph) {
            $this->SetX(25);
            $paragraph = ltrim($paragraph, "\t"); // Remove o tab no início do parágrafo
            $this->WriteHTML('<p style="text-align:justify; text-indent:2cm;">' . htmlspecialchars_decode($paragraph) . '</p>');
        }
    }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Ofício ' . $oficioData['numero']);
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 25); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 10 * 0.5;

// Cidade e data
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, $lineHeight, $serventiaData['cidade'] . ', ' . formatDateToBrazilian($oficioData['data']), 0, 1, 'R');
$pdf->Ln(3);

// Número do ofício
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, $lineHeight, 'Ofício n° ' . $oficioData['numero'], 0, 1, 'L');
$pdf->Ln(5);

// Forma de Tratamento e Destinatário
if (!empty($oficioData['tratamento'])) {
    $pdf->SetFont('helvetica', '0', 12);
    $pdf->Cell(0, $lineHeight, $oficioData['tratamento'], 0, 1, 'L');
}

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, $lineHeight, ($oficioData['destinatario']), 0, 1, 'L');

// Cargo
if (!empty($oficioData['cargo'])) {
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, $lineHeight, ($oficioData['cargo']), 0, 1, 'L');
}
$pdf->Ln(5);

// Assunto
$pdf->SetFont('helvetica', 'B', 12);
$pdf->writeHTML('<div style="text-align: justify;">' . ('Assunto: ' . $oficioData['assunto']) . '</div>', true, false, true, false, '');
$pdf->Ln(0);

// Corpo do ofício com recuo na primeira linha de cada parágrafo e texto justificado
$pdf->SetFont('helvetica', '', 12);
$pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . ($oficioData['corpo']) . '</div>', true, false, true, false, '');
$pdf->Ln(0);

// Assinatura
$pdf->SetFont('helvetica', '', 12);
$pdf->writeHTML('<p style="text-indent: 20mm; text-align: justify;">Atenciosamente,</p>', true, false, true, false, '');
$pdf->Ln(15);

// Adicionar imagem da assinatura, se disponível
if ($signatureImage) {
    $signatureImagePath = __DIR__ . '/assinaturas/' . $signatureImage;
    if (file_exists($signatureImagePath)) {
        // Obter dimensões da imagem
        list($imageWidth, $imageHeight) = getimagesize($signatureImagePath);

        $signatureWidth = 80; // largura da imagem da assinatura
        $pageWidth = $pdf->getPageWidth();
        $marginLeft = $pdf->getMargins()['left'];
        $marginRight = $pdf->getMargins()['right'];
        $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;

        $pdf->Image($signatureImagePath, $centerX, $pdf->GetY() - 10, $signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Ajustar o espaço vertical com base na largura da imagem
        if ($imageWidth < 2000) {
            $pdf->Ln(15);
        } else {
            $pdf->Ln(2);
        }
    } else {
        // Debug: show the path of the signature image if it doesn't exist
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, $lineHeight, 'Assinatura não encontrada: ' . $signatureImagePath, 0, 1, 'C');
        $pdf->Ln(5);
    }
}

$pdf->Cell(0, $lineHeight, '__________________________________', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, $lineHeight, ($oficioData['assinante']), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, $lineHeight, ($oficioData['cargo_assinante']), 0, 1, 'C');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Oficio_' . $numeroSequencial . '_' . $anoVigente . '.pdf', 'I');
?>
