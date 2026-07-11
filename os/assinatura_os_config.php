<?php
/**
 * assinatura_os_config.php — Infra de assinatura PAdES/SERPRO para documentos da O.S.
 * Cobre os tipos: 'recibo_a4' (recibo_a4.php) e 'os' (imprimir_os.php).
 * Reaproveita a biblioteca PAdES, o PDF.js e o cliente SERPRO do módulo de ofícios.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('America/Fortaleza');

require_once __DIR__ . '/../oficios/assin_pades.php'; // AtlasDer, AtlasPadesInjector, atlas_openssl_conf, AtlasPadesSigner

/* Tipos de documento suportados e o gerador de cada um. */
function os_tipos()
{
    return [
        'recibo_a4' => ['arquivo' => 'recibo_a4.php',   'titulo' => 'Recibo A4'],
        'os'        => ['arquivo' => 'imprimir_os.php', 'titulo' => 'Ordem de Serviço / Orçamento'],
    ];
}
function os_tipo_valido($t) { return array_key_exists($t, os_tipos()); }

/* ------------------------------------------------------------------ DB */
function os_db()
{
    static $conn = null;
    if ($conn instanceof mysqli && @$conn->ping()) return $conn;
    $conn = new mysqli('localhost', 'root', '', 'atlas');
    if ($conn->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* --------------------------------------------------------------- schema */
function os_ensure_schema()
{
    os_db()->query("CREATE TABLE IF NOT EXISTS os_documentos_assinados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(20) NOT NULL,
        os_id INT NOT NULL,
        assinado TINYINT(1) NOT NULL DEFAULT 0,
        assinatura_arquivo VARCHAR(255) NULL,
        assinado_por VARCHAR(120) NULL,
        assinante_cert VARCHAR(255) NULL,
        assinado_em DATETIME NULL,
        assinatura_codigo VARCHAR(64) NULL,
        assinatura_meta TEXT NULL,
        UNIQUE KEY uniq_doc (tipo, os_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Registro de assinatura (ou null) de um documento. */
function os_doc_info($tipo, $osId)
{
    os_ensure_schema();
    $conn = os_db();
    $stmt = $conn->prepare("SELECT * FROM os_documentos_assinados WHERE tipo=? AND os_id=? LIMIT 1");
    $stmt->bind_param('si', $tipo, $osId); $stmt->execute();
    $r = $stmt->get_result(); $row = $r ? $r->fetch_assoc() : null; $stmt->close();
    return $row;
}

/* ------------------------------------------------- assinante (do login) */
function os_signer_info()
{
    $username = $_SESSION['username'] ?? '';
    $nome = $username; $cargo = '';
    try {
        $conn = os_db();
        $stmt = $conn->prepare("SELECT * FROM funcionarios WHERE usuario=? LIMIT 1");
        $stmt->bind_param('s', $username); $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($u) {
            foreach (['nome','nome_completo','nome_funcionario'] as $k) if (!empty($u[$k])) { $nome = $u[$k]; break; }
            foreach (['cargo','funcao','função','cargo_funcionario'] as $k) if (!empty($u[$k])) { $cargo = $u[$k]; break; }
        }
    } catch (Throwable $e) {}
    return ['nome' => $nome, 'cargo' => $cargo];
}

/* ---------------------------------------------------------------- CSRF */
function os_csrf_token()
{
    if (empty($_SESSION['os_sig_csrf'])) $_SESSION['os_sig_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['os_sig_csrf'];
}
function os_csrf_check($t)
{
    return is_string($t) && !empty($_SESSION['os_sig_csrf']) && hash_equals($_SESSION['os_sig_csrf'], $t);
}

/* ------------------------------------------------------------ diretórios */
function os_dir_assinados()
{
    $d = __DIR__ . '/assinados';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d.'/.htaccess')) @file_put_contents($d.'/.htaccess', "php_flag engine off\nOptions -Indexes\n");
    return $d;
}
function os_pades_dir() { $d = os_dir_assinados() . '/.pades'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function os_log($m)
{
    $dir = __DIR__ . '/logs_assinatura';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir.'/assinatura_'.date('Y-m').'.log', '['.date('Y-m-d H:i:s').'] '.$m.PHP_EOL, FILE_APPEND);
}
function os_safe($v) { return preg_replace('~[^0-9A-Za-z_\-]~', '_', (string)$v); }
function os_public_url($relative)
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/os/x')), '/');
    return $scheme . '://' . $host . $webDir . '/' . ltrim($relative, '/');
}

/* --------------------------------------------- geração do PDF (captura) */
/**
 * Gera os bytes do PDF do documento (recibo A4 ou O.S.) reaproveitando o
 * gerador original em "modo captura" (constante OS_PDF_CAPTURE).
 */
function os_generate_pdf_bytes($tipo, $osId)
{
    if (!os_tipo_valido($tipo)) throw new RuntimeException('Tipo de documento inválido.');
    $arquivo = __DIR__ . '/' . os_tipos()[$tipo]['arquivo'];
    if (!is_file($arquivo)) throw new RuntimeException('Gerador não encontrado: ' . os_tipos()[$tipo]['arquivo']);

    if (!defined('OS_PDF_CAPTURE')) define('OS_PDF_CAPTURE', true);
    $GLOBALS['__CAP_PDF_BYTES__'] = null;
    $_GET['id'] = (int)$osId;               // os geradores usam $_GET['id']
    $_GET['__capture'] = '1';

    (function($__f){ include $__f; })($arquivo);   // roda em escopo isolado

    $bytes = $GLOBALS['__CAP_PDF_BYTES__'] ?? null;
    if (!$bytes || strncmp(ltrim($bytes), '%PDF', 4) !== 0) {
        throw new RuntimeException('Falha ao gerar o PDF do documento (O.S. inexistente ou sem dados?).');
    }
    return $bytes;
}

/* ----------------------------------------------------------- selo (nosso) */
function os_stamp_seal($pdfBytes, $opts)
{
    require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';
    require_once __DIR__ . '/../oficios/src/autoload.php';

    $page = max(1, (int)($opts['page'] ?? 1));
    $xn = min(1, max(0, (float)($opts['xn'] ?? 0.55)));
    $yn = min(1, max(0, (float)($opts['yn'] ?? 0.80)));
    $wn = min(1, max(0.15, (float)($opts['wn'] ?? 0.24)));

    $tmp = tempnam(sys_get_temp_dir(), 'os_') . '.pdf';
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
                os_draw_seal_box($pdf, [
                    'pw'=>$pw,'ph'=>$ph,'xn'=>$xn,'yn'=>$yn,'wn'=>$wn,
                    'nome'=>trim((string)($opts['nome'] ?? '')),
                    'cargo'=>trim((string)($opts['cargo'] ?? '')),
                    'codigo'=>trim((string)($opts['codigo'] ?? '')),
                    'quando'=>trim((string)($opts['quando'] ?? date('d/m/Y H:i:s'))),
                ]);
            }
        }
        return $pdf->Output('', 'S');
    } finally { @unlink($tmp); }
}

// Proporção do selo (altura = largura * OS_SEAL_RATIO). Menor = mais baixo/largo.
if (!defined('OS_SEAL_RATIO')) define('OS_SEAL_RATIO', 0.22);

function os_draw_seal_box($pdf, $a)
{
    $pw = $a['pw']; $ph = $a['ph'];
    $w = $a['wn'] * $pw; $w = max(52, min($w, $pw - 8)); $h = $w * OS_SEAL_RATIO;
    $x = $a['xn'] * $pw; $y = $a['yn'] * $ph;
    if ($x + $w > $pw - 3) $x = $pw - 3 - $w;
    if ($y + $h > $ph - 3) $y = $ph - 3 - $h;
    if ($x < 3) $x = 3; if ($y < 3) $y = 3;

    $pdf->SetAlpha(0.92);
    $pdf->SetFillColor(255,255,255); $pdf->SetDrawColor(37,99,235); $pdf->SetLineWidth(0.35);
    $pdf->RoundedRect($x, $y, $w, $h, 1.2, '1111', 'DF');
    $pdf->SetAlpha(1);
    $pdf->SetFillColor(37,99,235); $pdf->Rect($x, $y, 1.6, $h, 'F');

    // Layout em duas colunas para caber num selo baixo: esquerda = identidade, direita = validação.
    $padL   = $x + 3.6;
    $colGap = 3.0;
    $leftW  = ($w - 5.0) * 0.56;
    $rightX = $padL + $leftW + $colGap;
    $rightW = $x + $w - 2.6 - $rightX;
    $top    = $y + 1.8;

    // Coluna esquerda
    $pdf->SetTextColor(37,99,235); $pdf->SetFont('helvetica','B',5.6);
    $pdf->SetXY($padL, $top); $pdf->Cell($leftW, 2.5, 'ASSINADO DIGITALMENTE', 0, 2, 'L');
    $pdf->SetTextColor(17,24,39); $pdf->SetFont('helvetica','B',7.6); $pdf->SetX($padL);
    $pdf->Cell($leftW, 3.3, os_fit($pdf, $a['nome'], $leftW, 7.6, 'B'), 0, 2, 'L');
    if ($a['cargo'] !== '') {
        $pdf->SetTextColor(55,65,81); $pdf->SetFont('helvetica','',5.8); $pdf->SetX($padL);
        $pdf->Cell($leftW, 2.6, os_fit($pdf, $a['cargo'], $leftW, 5.8, ''), 0, 2, 'L');
    }

    // Coluna direita
    if ($rightW > 18) {
        $pdf->SetTextColor(55,65,81); $pdf->SetFont('helvetica','',5.6);
        $pdf->SetXY($rightX, $top); $pdf->Cell($rightW, 2.4, os_fit($pdf,'ICP-Brasil · PAdES',$rightW,5.6,''), 0, 2, 'R');
        $pdf->SetX($rightX); $pdf->Cell($rightW, 2.4, os_fit($pdf,'Assinador SERPRO',$rightW,5.6,''), 0, 2, 'R');
        $pdf->SetTextColor(17,24,39); $pdf->SetFont('helvetica','',5.6); $pdf->SetX($rightX);
        $pdf->Cell($rightW, 2.6, $a['quando'], 0, 2, 'R');
        if ($a['codigo'] !== '') {
            $pdf->SetTextColor(107,114,128); $pdf->SetFont('helvetica','',5.0); $pdf->SetX($rightX);
            $pdf->Cell($rightW, 2.3, os_fit($pdf, $a['codigo'], $rightW, 5.0, ''), 0, 2, 'R');
        }
    } else {
        // selo estreito: validação abaixo, em uma linha
        $pdf->SetTextColor(107,114,128); $pdf->SetFont('helvetica','',5.0); $pdf->SetX($padL);
        $pdf->Cell($leftW, 2.3, $a['quando'] . '  ICP-Brasil/PAdES', 0, 2, 'L');
    }
    $pdf->SetTextColor(0,0,0);
}
function os_fit($pdf, $text, $maxW, $size, $style)
{
    $text = (string)$text; $pdf->SetFont('helvetica', $style, $size);
    if ($pdf->GetStringWidth($text) <= $maxW) return $text;
    while (mb_strlen($text) > 1 && $pdf->GetStringWidth($text.'…') > $maxW) $text = mb_substr($text, 0, mb_strlen($text)-1, 'UTF-8');
    return $text . '…';
}
