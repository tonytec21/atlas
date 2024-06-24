<?php
require('fpdf.php');

$oficiosDir = __DIR__ . '/meta-dados';
$numero = isset($_GET['numero']) ? $_GET['numero'] : '';

$oficioPath = "$oficiosDir/$numero.json";
if (!file_exists($oficioPath)) {
    die("Ofício não encontrado.");
}

$oficio = json_decode(file_get_contents($oficioPath), true);

class PDF extends FPDF
{
    function Header()
    {
        // Arial bold 12
        $this->SetFont('Arial', 'B', 12);
        // Title
        $this->Cell(0, 10, 'Ofício ' . $_GET['numero'], 0, 1, 'C');
    }

    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function ChapterTitle($num, $label)
    {
        // Arial 12
        $this->SetFont('Arial', '', 12);
        // Background color
        $this->SetFillColor(200, 220, 255);
        // Title
        $this->Cell(0, 10, "Chapter $num : $label", 0, 1, 'L', true);
        // Line break
        $this->Ln(4);
    }

    function ChapterBody($body)
    {
        // Read text file
        $txt = $body;
        // Times 12
        $this->SetFont('Times', '', 12);
        // Output justified text
        $this->MultiCell(0, 10, $txt);
        // Line break
        $this->Ln();
        // Mention in italics
        $this->SetFont('', 'I');
        $this->Cell(0, 10, '(end of excerpt)');
    }

    function PrintChapter($num, $title, $body)
    {
        $this->AddPage();
        $this->ChapterTitle($num, $title);
        $this->ChapterBody($body);
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Oficio data
$pdf->Cell(0, 10, 'A Ilma. Sra.', 0, 1);
$pdf->Cell(0, 10, $oficio['destinatario'], 0, 1);
$pdf->Cell(0, 10, 'Ref.: ' . $oficio['assunto'], 0, 1);

$pdf->Ln(10);
$pdf->MultiCell(0, 10, $oficio['corpo']);

$pdf->Ln(20);
$pdf->Cell(0, 10, 'Atenciosamente,', 0, 1);
$pdf->Cell(0, 10, $oficio['assinante'], 0, 1);

$pdfOutputPath = $oficiosDir . "/oficio_$numero.pdf";
$pdf->Output('F', $pdfOutputPath);

echo "/meta-dados/oficio_$numero.pdf";
?>
