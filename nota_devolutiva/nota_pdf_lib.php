<?php
/**
 * nota_pdf_lib.php — Geração do PDF da Nota Devolutiva como bytes (string).
 * Reaproveitado por gerar_pdf_nota.php (saída inline) e pelo fluxo de
 * assinatura (pré-visualização / preparo PAdES).
 */
require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';

if (!class_exists('NotaDevolutivaPDF')) {
    class NotaDevolutivaPDF extends TCPDF
    {
        public function Header()
        {
            $image_file = __DIR__ . '/../style/img/timbrado.png';
            $this->SetAutoPageBreak(false, 0);
            $this->SetMargins(0, 0, 0);
            if (file_exists($image_file)) {
                $this->Image($image_file, 0, 0, 210, 297, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetAutoPageBreak(true, 25);
            $this->SetMargins(15, 45, 15);
            $this->SetY(35);
        }
        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
        }
    }
}

if (!function_exists('nd_format_date_br')) {
    function nd_format_date_br($date)
    {
        $dt = new DateTime($date);
        $mes = ['January'=>'janeiro','February'=>'fevereiro','March'=>'março','April'=>'abril','May'=>'maio','June'=>'junho','July'=>'julho','August'=>'agosto','September'=>'setembro','October'=>'outubro','November'=>'novembro','December'=>'dezembro'];
        return $dt->format('d') . ' de ' . $mes[$dt->format('F')] . ' de ' . $dt->format('Y');
    }
}

/**
 * Constrói o PDF da nota devolutiva e devolve os bytes (string).
 * @param string        $numero  número da nota
 * @param mysqli|null   $conn    conexão com o DB `atlas` (abre uma própria se null)
 * @return string bytes do PDF
 */
function nd_build_nota_pdf($numero, $conn = null)
{
    $ownConn = false;
    if (!($conn instanceof mysqli)) {
        $conn = new mysqli('localhost', 'root', '', 'atlas');
        if ($conn->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
        $conn->set_charset('utf8');
        $ownConn = true;
    }

    // Garante colunas usadas
    foreach ([
        "prazo_cumprimento TEXT AFTER corpo",
        "cpf_cnpj VARCHAR(20) AFTER apresentante",
        "origem_titulo VARCHAR(200) AFTER titulo",
    ] as $def) {
        $col = strtok($def, ' ');
        $r = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE '$col'");
        if ($r && $r->num_rows === 0) { @$conn->query("ALTER TABLE notas_devolutivas ADD COLUMN $def"); }
    }

    $stmt = $conn->prepare("SELECT * FROM notas_devolutivas WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) { $stmt->close(); if ($ownConn) $conn->close(); throw new RuntimeException('Nota devolutiva não encontrada.'); }
    $n = $res->fetch_assoc();
    $stmt->close();

    $cidade = 'Sua Cidade';
    if ($r = $conn->query("SELECT cidade FROM cadastro_serventia WHERE id = 1")) {
        if ($r->num_rows > 0) { $cidade = $r->fetch_assoc()['cidade']; }
    }

    // Assinatura manuscrita (imagem) — mantida se existir
    $signatureImage = '';
    $dataJson = __DIR__ . '/../oficios/assinaturas/data.json';
    if (file_exists($dataJson)) {
        $signatures = json_decode(file_get_contents($dataJson), true) ?: [];
        foreach ($signatures as $s) {
            if (($s['fullName'] ?? '') === ($n['assinante'] ?? '')) { $signatureImage = $s['assinatura'] ?? ''; break; }
        }
    }
    if ($ownConn) $conn->close();

    $pdf = new NotaDevolutivaPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Nota Devolutiva ' . $n['numero']);
    $pdf->SetMargins(15, 45, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $lineHeight = 5;

    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, $lineHeight, $cidade . ', ' . nd_format_date_br($n['data']) . '.', 0, 1, 'R');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->writeHTML('<div style="text-align: justify;">Protocolo: ' . htmlspecialchars($n['protocolo']) . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    if (!empty($n['data_protocolo'])) {
        $pdf->SetFont('helvetica', '', 11);
        $dp = new DateTime($n['data_protocolo']);
        $pdf->writeHTML('<div style="text-align: justify;">Data do Protocolo: ' . $dp->format('d/m/Y') . '</div>', true, false, true, false, '');
        $pdf->Ln(1);
    }

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->writeHTML('<div style="text-align: justify;">Apresentante: ' . htmlspecialchars($n['apresentante']) . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    if (!empty($n['cpf_cnpj'])) {
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML('<div style="text-align: justify;">CPF/CNPJ: ' . htmlspecialchars($n['cpf_cnpj']) . '</div>', true, false, true, false, '');
        $pdf->Ln(1);
    }

    if (!empty($n['titulo'])) {
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML('<div style="text-align: justify;">Título Apresentado: ' . htmlspecialchars($n['titulo']) . '</div>', true, false, true, false, '');
        if (!empty($n['origem_titulo'])) {
            $pdf->Ln(2);
            $pdf->writeHTML('<div style="text-align: justify;">Origem do Título: ' . htmlspecialchars($n['origem_titulo']) . '</div>', true, false, true, false, '');
        }
        $pdf->Ln(8);
    }

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->writeHTML('<div style="text-align: center;">NOTA DEVOLUTIVA Nº.: ' . htmlspecialchars($n['numero']) . '</div>', true, false, true, false, '');
    $pdf->Ln(5);

    // Corpo (com blockquote/table)
    $pdf->SetFont('helvetica', '', 11);
    nd_render_rich($pdf, html_entity_decode($n['corpo'] ?? ''));

    if (!empty($n['prazo_cumprimento'])) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->writeHTML('<div style="text-align: justify;">PRAZO PARA CUMPRIMENTO:</div>', true, false, true, false, '');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 11);
        nd_render_rich($pdf, html_entity_decode($n['prazo_cumprimento']));
    }

    $pdf->SetFont('helvetica', '', 11);
    $pdf->writeHTML('<p style="text-indent: 20mm; text-align: justify;">Atenciosamente,</p>', true, false, true, false);
    $pdf->Ln(15);

    if ($signatureImage) {
        $sigPath = __DIR__ . '/../oficios/assinaturas/' . $signatureImage;
        if (file_exists($sigPath)) {
            list($iw) = getimagesize($sigPath);
            $sw = 80;
            $centerX = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - $sw) / 2 + $pdf->getMargins()['left'];
            $pdf->Image($sigPath, $centerX, $pdf->GetY() - 10, $sw, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $pdf->Ln($iw < 2000 ? 15 : 4);
        }
    }

    $pdf->Cell(0, $lineHeight, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, $lineHeight, htmlspecialchars($n['assinante'] ?? ''), 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, $lineHeight, htmlspecialchars($n['cargo_assinante'] ?? ''), 0, 1, 'C');

    return $pdf->Output('', 'S');
}

/** Renderiza conteúdo rico (parágrafos, blockquote, table) como no gerador original. */
function nd_render_rich($pdf, $conteudo)
{
    $partes = preg_split('/(<blockquote>.*?<\/blockquote>|<table.*?<\/table>)/is', $conteudo, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($partes as $parte) {
        if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $m)) {
            $pdf->Ln(-6);
            $pdf->SetX(60);
            $bw = $pdf->getPageWidth() - 60 - $pdf->getMargins()['right'] - 1;
            $pdf->SetFont('helvetica', 'I', 12);
            $pdf->MultiCell($bw, 5, strip_tags($m[1]), 0, 'J', false, 1);
            $pdf->SetY($pdf->GetY() + 3);
        } elseif (preg_match('/<table.*?<\/table>/is', $parte)) {
            $pdf->SetFont('helvetica', '', 11);
            $pdf->writeHTML($parte, true, false, true, false, '');
            $pdf->Ln(5);
        } else {
            if (preg_match_all('/<p>(.*?)<\/p>/is', $parte, $mm)) {
                foreach ($mm[1] as $txt) {
                    $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $txt . '</div>', true, false, true, false);
                    $pdf->Ln(5);
                }
            } else {
                $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);
                $pdf->Ln(5);
            }
        }
    }
}
