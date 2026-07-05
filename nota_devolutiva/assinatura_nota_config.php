<?php
/**
 * assinatura_nota_config.php — Infraestrutura de assinatura PAdES + anexos
 * para o módulo de Nota Devolutiva. Reaproveita a biblioteca PAdES, o PDF.js
 * e o cliente SERPRO do módulo de ofícios (../oficios).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('America/Fortaleza'); // Maranhão (UTC−3)

require_once __DIR__ . '/../oficios/assin_pades.php'; // AtlasDer, AtlasPadesInjector, atlas_openssl_conf

/* ------------------------------------------------------------------ DB */
function nd_db()
{
    static $conn = null;
    if ($conn instanceof mysqli && @$conn->ping()) return $conn;
    $conn = new mysqli('localhost', 'root', '', 'atlas');
    if ($conn->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* --------------------------------------------------------------- schema */
function nd_ensure_schema()
{
    $conn = nd_db();

    // Colunas de assinatura em notas_devolutivas
    $cols = [
        'assinado'          => "TINYINT(1) NOT NULL DEFAULT 0",
        'assinatura_arquivo'=> "VARCHAR(255) NULL",
        'assinado_por'      => "VARCHAR(120) NULL",
        'assinante_cert'    => "VARCHAR(255) NULL",
        'assinado_em'       => "DATETIME NULL",
        'assinatura_pagina' => "INT NULL",
        'assinatura_codigo' => "VARCHAR(64) NULL",
        'assinatura_meta'   => "TEXT NULL",
    ];
    foreach ($cols as $name => $def) {
        $r = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE '" . $conn->real_escape_string($name) . "'");
        if ($r && $r->num_rows === 0) { @$conn->query("ALTER TABLE notas_devolutivas ADD COLUMN `$name` $def"); }
    }

    // Tabela de anexos
    @$conn->query("CREATE TABLE IF NOT EXISTS nota_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nota_numero VARCHAR(60) NOT NULL,
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NULL,
        tamanho INT NULL,
        descricao VARCHAR(255) NULL,
        enviado_por VARCHAR(120) NULL,
        enviado_em DATETIME NOT NULL,
        INDEX idx_nota (nota_numero)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------------------------------------------------------------- CSRF */
function nd_csrf_token()
{
    if (empty($_SESSION['nd_csrf'])) $_SESSION['nd_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['nd_csrf'];
}
function nd_csrf_check($token)
{
    return is_string($token) && !empty($_SESSION['nd_csrf']) && hash_equals($_SESSION['nd_csrf'], $token);
}

/* ------------------------------------------------------------ diretórios */
function nd_dir_assinados()
{
    $d = __DIR__ . '/assinados';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d.'/.htaccess')) @file_put_contents($d.'/.htaccess',"php_flag engine off
Options -Indexes
");
    return $d;
}
function nd_dir_anexos()
{
    $d = __DIR__ . '/anexos';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d.'/.htaccess')) @file_put_contents($d.'/.htaccess',"php_flag engine off
Options -Indexes
");
    return $d;
}
function nd_log($msg)
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/assinatura_' . date('Y-m') . '.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

/** Nome seguro para compor caminhos a partir do número da nota. */
function nd_safe($numero)
{
    return preg_replace('~[^0-9A-Za-z_\-]~', '_', (string)$numero);
}

/** URL pública de um arquivo relativo à pasta do módulo. */
function nd_public_url($relative)
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/nota_devolutiva/x')), '/');
    return $scheme . '://' . $host . $webDir . '/' . ltrim($relative, '/');
}

/* ------------------------------------------------------- geração + selo */
function nd_generate_pdf_bytes($numero)
{
    require_once __DIR__ . '/nota_pdf_lib.php';
    $bytes = nd_build_nota_pdf($numero, nd_db());
    if ($bytes === false || strncmp(ltrim($bytes), '%PDF', 4) !== 0) {
        throw new RuntimeException('Falha ao gerar o PDF da nota.');
    }
    return $bytes;
}

/** Carimba o NOSSO selo de assinatura na posição escolhida (reusa FPDI/TCPDF de ../oficios). */
function nd_stamp_seal($pdfBytes, $opts)
{
    require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';
    require_once __DIR__ . '/../oficios/src/autoload.php';

    $page = max(1, (int)($opts['page'] ?? 1));
    $xn = min(1, max(0, (float)($opts['xn'] ?? 0.55)));
    $yn = min(1, max(0, (float)($opts['yn'] ?? 0.80)));
    $wn = min(1, max(0.15, (float)($opts['wn'] ?? 0.38)));

    $tmp = tempnam(sys_get_temp_dir(), 'nota_') . '.pdf';
    file_put_contents($tmp, $pdfBytes);
    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0); $pdf->setMargins(0, 0, 0);
        $pageCount = $pdf->setSourceFile($tmp);
        if ($page > $pageCount) $page = $pageCount;
        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl = $pdf->importPage($p); $size = $pdf->getTemplateSize($tpl);
            $pw = $size['width']; $ph = $size['height'];
            $pdf->AddPage($size['orientation'], [$pw, $ph]);
            $pdf->useTemplate($tpl, 0, 0, $pw, $ph);
            if ($p === $page) {
                nd_draw_seal_box($pdf, [
                    'pw' => $pw, 'ph' => $ph, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
                    'nome' => trim((string)($opts['nome'] ?? '')),
                    'cargo' => trim((string)($opts['cargo'] ?? '')),
                    'codigo' => trim((string)($opts['codigo'] ?? '')),
                    'quando' => trim((string)($opts['quando'] ?? date('d/m/Y H:i:s'))),
                ]);
            }
        }
        return $pdf->Output('', 'S');
    } finally { @unlink($tmp); }
}

function nd_draw_seal_box($pdf, $a)
{
    $pw = $a['pw']; $ph = $a['ph'];
    $w = $a['wn'] * $pw; $w = max(40, min($w, $pw - 8)); $h = $w * 0.42;
    $x = $a['xn'] * $pw; $y = $a['yn'] * $ph;
    if ($x + $w > $pw - 3) $x = $pw - 3 - $w;
    if ($y + $h > $ph - 3) $y = $ph - 3 - $h;
    if ($x < 3) $x = 3; if ($y < 3) $y = 3;

    $pdf->SetAlpha(0.92);
    $pdf->SetFillColor(255, 255, 255); $pdf->SetDrawColor(37, 99, 235); $pdf->SetLineWidth(0.4);
    $pdf->RoundedRect($x, $y, $w, $h, 1.6, '1111', 'DF');
    $pdf->SetAlpha(1);
    $pdf->SetFillColor(37, 99, 235); $pdf->Rect($x, $y, 2.0, $h, 'F');

    $padL = $x + 4.5; $innerW = $w - 6.5; $cy = $y + 2.4;
    $pdf->SetTextColor(37, 99, 235); $pdf->SetFont('helvetica', 'B', 6.8);
    $pdf->SetXY($padL, $cy); $pdf->Cell($innerW, 3, 'ASSINADO DIGITALMENTE', 0, 2, 'L');
    $pdf->SetTextColor(17, 24, 39); $pdf->SetFont('helvetica', 'B', 8.2); $pdf->SetX($padL);
    $pdf->Cell($innerW, 3.8, nd_fit($pdf, $a['nome'], $innerW, 8.2, 'B'), 0, 2, 'L');
    if ($a['cargo'] !== '') {
        $pdf->SetTextColor(55, 65, 81); $pdf->SetFont('helvetica', '', 6.6); $pdf->SetX($padL);
        $pdf->Cell($innerW, 3.1, nd_fit($pdf, $a['cargo'], $innerW, 6.6, ''), 0, 2, 'L');
    }
    $pdf->SetTextColor(55, 65, 81); $pdf->SetFont('helvetica', '', 6.4); $pdf->SetX($padL);
    $pdf->Cell($innerW, 3.0, 'Data: ' . $a['quando'], 0, 2, 'L');
    $pdf->SetTextColor(107, 114, 128); $pdf->SetFont('helvetica', '', 5.6); $pdf->SetX($padL);
    $pdf->Cell($innerW, 2.6, 'ICP-Brasil - PAdES - Assinador SERPRO', 0, 2, 'L');
    if ($a['codigo'] !== '') { $pdf->SetX($padL); $pdf->Cell($innerW, 2.6, 'Verificacao: ' . $a['codigo'], 0, 2, 'L'); }
    $pdf->SetTextColor(0, 0, 0);
}

function nd_fit($pdf, $text, $maxW, $size, $style)
{
    $text = (string)$text; $pdf->SetFont('helvetica', $style, $size);
    if ($pdf->GetStringWidth($text) <= $maxW) return $text;
    while (mb_strlen($text) > 1 && $pdf->GetStringWidth($text . '…') > $maxW) {
        $text = mb_substr($text, 0, mb_strlen($text) - 1, 'UTF-8');
    }
    return $text . '…';
}
