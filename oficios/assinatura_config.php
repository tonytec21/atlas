<?php
/**
 * oficios/assinatura_config.php
 * ---------------------------------------------------------------------------
 * Núcleo do módulo de Assinatura Eletrônica (ICP-Brasil / Assinador SERPRO).
 *
 * Reúne, em um único ponto:
 *   - Conexão com o banco (oficios_db);
 *   - Migração defensiva das colunas de assinatura na tabela `oficios`;
 *   - Diretórios de trabalho (assinados / cache);
 *   - Geração dos BYTES do PDF-base do ofício (reaproveitando os geradores
 *     view_oficio.php / view-oficio.php, exatamente como o cache_pdf_oficio.php);
 *   - Carimbo (selo visual) da assinatura no PDF usando FPDI + TCPDF, na
 *     posição escolhida pelo usuário (clique) na tela de assinatura.
 *
 * A assinatura CRIPTOGRÁFICA (PAdES/ICP-Brasil) é feita pelo Assinador SERPRO
 * no desktop, via WebSocket, no navegador. Este arquivo cuida apenas do selo
 * VISUAL e da persistência. Assim garantimos que o selo apareça EXATAMENTE
 * onde o usuário clicou dentro do próprio Atlas.
 * ---------------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

/* Fuso horário do Maranhão (UTC−3, sem horário de verão). Corrige carimbos
   quando o php.ini do XAMPP vem com um fuso estrangeiro (ex.: Europe/Berlin). */
date_default_timezone_set('America/Fortaleza');

/* ===========================================================================
   1. Conexão com o banco
   =========================================================================== */
function assin_db()
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $conn = new mysqli('localhost', 'root', '', 'oficios_db');
    if ($conn->connect_error) {
        throw new RuntimeException('Falha na conexão com o banco: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8');
    return $conn;
}

/* ===========================================================================
   2. Migração defensiva (adiciona colunas somente se ainda não existirem)
   =========================================================================== */
function assin_ensure_schema()
{
    $conn = assin_db();

    // Colunas necessárias na tabela oficios
    $cols = [
        'assinado'            => "TINYINT(1) NOT NULL DEFAULT 0",
        'assinatura_arquivo'  => "VARCHAR(255) NULL",
        'assinado_por'        => "VARCHAR(255) NULL",
        'assinante_cert'      => "VARCHAR(255) NULL",
        'assinado_em'         => "DATETIME NULL",
        'assinatura_pagina'   => "INT NULL",
        'assinatura_codigo'   => "VARCHAR(64) NULL",
        'assinatura_meta'     => "TEXT NULL",
    ];

    $existing = [];
    if ($res = $conn->query("SHOW COLUMNS FROM `oficios`")) {
        while ($row = $res->fetch_assoc()) {
            $existing[strtolower($row['Field'])] = true;
        }
        $res->free();
    }

    foreach ($cols as $name => $def) {
        if (!isset($existing[strtolower($name)])) {
            @$conn->query("ALTER TABLE `oficios` ADD COLUMN `{$name}` {$def}");
        }
    }
}

/* ===========================================================================
   3. Diretórios de trabalho
   =========================================================================== */
function assin_dir_assinados()
{
    $dir = __DIR__ . '/assinados';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function assin_log($msg)
{
    try {
        $dir = assin_dir_assinados() . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)) . "\n";
        @file_put_contents($dir . '/' . date('Ymd') . '.log', $line, FILE_APPEND);
    } catch (Throwable $e) {
    }
}

/* ===========================================================================
   4. Descobre o flag "timbrado" (S/N) — mesmo critério do front-end
   =========================================================================== */
function assin_timbrado_flag($override = null)
{
    if ($override !== null) {
        return strtoupper((string)$override) === 'S' ? 'S' : 'N';
    }
    // O front lê ../style/configuracao.json {"timbrado":"S|N"}
    $candidates = [
        __DIR__ . '/../style/configuracao.json',
        __DIR__ . '/configuracao_timbrado.json',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $cfg = json_decode(@file_get_contents($file), true);
            if (isset($cfg['timbrado'])) {
                return strtoupper((string)$cfg['timbrado']) === 'S' ? 'S' : 'N';
            }
        }
    }
    return 'N';
}

/* ===========================================================================
   5. Gera os BYTES do PDF-base do ofício
   Reaproveita os geradores existentes (view_oficio.php / view-oficio.php)
   fazendo uma requisição interna autenticada (mesma técnica do
   cache_pdf_oficio.php), garantindo layout idêntico ao visualizado.
   =========================================================================== */
function assin_generate_pdf_bytes($numero, $timbrado)
{
    $numero = preg_replace('~[^0-9A-Za-z_\-/]~', '', (string)$numero);
    if ($numero === '') {
        throw new InvalidArgumentException('Número do ofício inválido.');
    }
    $timbrado = strtoupper($timbrado) === 'S' ? 'S' : 'N';

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (strpos($webDir, '/oficios') === false) {
        $webDir = '/oficios';
    }
    $generator = ($timbrado === 'S') ? 'view_oficio.php' : 'view-oficio.php';
    $url = $scheme . '://' . $host . $webDir . '/' . $generator . '?numero=' . rawurlencode($numero);

    assin_log("Gerando PDF-base: {$url}");

    $body = assin_http_get_pdf($url);
    if ($body === false || strlen($body) < 100) {
        throw new RuntimeException('O gerador retornou conteúdo vazio para o ofício.');
    }
    $probe = ltrim($body);
    if (strncmp($probe, '%PDF', 4) !== 0) {
        $sample = preg_replace('~[\r\n\t]+~', ' ', substr($probe, 0, 160));
        throw new RuntimeException('O gerador não retornou um PDF válido. Trecho: ' . $sample);
    }
    return $probe;
}

/** Download interno reaproveitando o cookie de sessão (cURL -> fallback FGC) */
function assin_http_get_pdf($url)
{
    $cookieHeader = null;
    if (isset($_COOKIE[session_name()])) {
        $cookieHeader = session_name() . '=' . $_COOKIE[session_name()];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        $headers = ['Accept: application/pdf'];
        if ($cookieHeader) {
            $headers[] = 'Cookie: ' . $cookieHeader;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === 0 && $body !== false) {
            return $body;
        }
    }

    // Fallback file_get_contents
    $opts = ['http' => ['method' => 'GET', 'header' => "Accept: application/pdf\r\n", 'ignore_errors' => true, 'timeout' => 90]];
    if ($cookieHeader) {
        $opts['http']['header'] .= 'Cookie: ' . $cookieHeader . "\r\n";
    }
    $opts['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];
    $ctx = stream_context_create($opts);
    return @file_get_contents($url, false, $ctx);
}

/* ===========================================================================
   6. Carimba (estampa) o selo VISUAL da assinatura no PDF-base
   Recebe posição normalizada (0..1, origem no canto superior esquerdo).
   Retorna os bytes do PDF já com o selo desenhado na página escolhida.
   =========================================================================== */
function assin_stamp_seal($pdfBytes, $opts)
{
    require_once __DIR__ . '/tcpdf/tcpdf.php';
    require_once __DIR__ . '/src/autoload.php';

    $page = max(1, (int)($opts['page'] ?? 1));
    $xn   = min(1, max(0, (float)($opts['xn'] ?? 0.55)));  // canto sup-esq do selo (fração largura)
    $yn   = min(1, max(0, (float)($opts['yn'] ?? 0.80)));  // canto sup-esq do selo (fração altura)
    $wn   = min(1, max(0.15, (float)($opts['wn'] ?? 0.38))); // largura do selo (fração largura)

    $nome    = trim((string)($opts['nome'] ?? ''));
    $cargo   = trim((string)($opts['cargo'] ?? ''));
    $numero  = trim((string)($opts['numero'] ?? ''));
    $codigo  = trim((string)($opts['codigo'] ?? ''));
    $quando  = trim((string)($opts['quando'] ?? date('d/m/Y H:i:s')));

    // Salva o PDF-base em arquivo temporário (FPDI lê de arquivo)
    $tmp = tempnam(sys_get_temp_dir(), 'ofc_') . '.pdf';
    file_put_contents($tmp, $pdfBytes);

    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);

        $pageCount = $pdf->setSourceFile($tmp);
        if ($page > $pageCount) {
            $page = $pageCount;
        }

        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl); // ['width'=>mm,'height'=>mm,'orientation'=>...]
            $pw = $size['width'];
            $ph = $size['height'];

            $pdf->AddPage($size['orientation'], [$pw, $ph]);
            $pdf->useTemplate($tpl, 0, 0, $pw, $ph);

            if ($p === $page) {
                assin_draw_seal_box($pdf, [
                    'pw' => $pw, 'ph' => $ph,
                    'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
                    'nome' => $nome, 'cargo' => $cargo,
                    'numero' => $numero, 'codigo' => $codigo, 'quando' => $quando,
                ]);
            }
        }

        $out = $pdf->Output('', 'S'); // string
        return $out;
    } finally {
        @unlink($tmp);
    }
}

/** Desenha a caixinha do selo na posição/página corrente do FPDI */
function assin_draw_seal_box($pdf, $a)
{
    $pw = $a['pw'];
    $ph = $a['ph'];

    // Dimensões do selo (mm)
    $w = $a['wn'] * $pw;
    $w = max(40, min($w, $pw - 8));           // limites de sanidade
    $h = $w * 0.42;                            // proporção agradável
    $x = $a['xn'] * $pw;
    $y = $a['yn'] * $ph;

    // Impede que o selo saia da página
    if ($x + $w > $pw - 3) { $x = $pw - 3 - $w; }
    if ($y + $h > $ph - 3) { $y = $ph - 3 - $h; }
    if ($x < 3) { $x = 3; }
    if ($y < 3) { $y = 3; }

    // Caixa com fundo branco semitransparente e borda azul
    $pdf->SetAlpha(0.92);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(37, 99, 235); // azul
    $pdf->SetLineWidth(0.4);
    $pdf->RoundedRect($x, $y, $w, $h, 1.6, '1111', 'DF');
    $pdf->SetAlpha(1);

    // Faixa lateral (destaque)
    $pdf->SetFillColor(37, 99, 235);
    $pdf->Rect($x, $y, 2.0, $h, 'F');

    $padL = $x + 4.5;
    $innerW = $w - 6.5;
    $cy = $y + 2.4;

    // Título
    $pdf->SetTextColor(37, 99, 235);
    $pdf->SetFont('helvetica', 'B', 6.8);
    $pdf->SetXY($padL, $cy);
    $pdf->Cell($innerW, 3, 'ASSINADO DIGITALMENTE', 0, 2, 'L');

    // Nome do assinante
    $pdf->SetTextColor(17, 24, 39);
    $pdf->SetFont('helvetica', 'B', 8.2);
    $pdf->SetX($padL);
    $pdf->Cell($innerW, 3.8, assin_fit($pdf, $a['nome'], $innerW, 8.2, 'B'), 0, 2, 'L');

    // Cargo
    if ($a['cargo'] !== '') {
        $pdf->SetTextColor(55, 65, 81);
        $pdf->SetFont('helvetica', '', 6.6);
        $pdf->SetX($padL);
        $pdf->Cell($innerW, 3.1, assin_fit($pdf, $a['cargo'], $innerW, 6.6, ''), 0, 2, 'L');
    }

    // Data/hora
    $pdf->SetTextColor(55, 65, 81);
    $pdf->SetFont('helvetica', '', 6.4);
    $pdf->SetX($padL);
    $pdf->Cell($innerW, 3.0, 'Data: ' . $a['quando'], 0, 2, 'L');

    // Rodapé: padrão + código de verificação
    $pdf->SetTextColor(107, 114, 128);
    $pdf->SetFont('helvetica', '', 5.6);
    $pdf->SetX($padL);
    $linha = 'ICP-Brasil - PAdES - Assinador SERPRO';
    $pdf->Cell($innerW, 2.6, $linha, 0, 2, 'L');
    if ($a['codigo'] !== '') {
        $pdf->SetX($padL);
        $pdf->Cell($innerW, 2.6, 'Verificacao: ' . $a['codigo'], 0, 2, 'L');
    }

    $pdf->SetTextColor(0, 0, 0);
}

/** Trunca texto para caber na largura, adicionando reticências */
function assin_fit($pdf, $text, $maxW, $size, $style)
{
    $text = (string)$text;
    $pdf->SetFont('helvetica', $style, $size);
    if ($pdf->GetStringWidth($text) <= $maxW) {
        return $text;
    }
    while (strlen($text) > 1 && $pdf->GetStringWidth($text . '…') > $maxW) {
        $text = mb_substr($text, 0, mb_strlen($text) - 1, 'UTF-8');
    }
    return $text . '…';
}

/* ===========================================================================
   7. URL pública de um arquivo dentro de /oficios
   =========================================================================== */
function assin_public_url($relativePathFromOficios)
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (strpos($webDir, '/oficios') === false) {
        $webDir = '/oficios';
    }
    return $scheme . '://' . $host . $webDir . '/' . ltrim($relativePathFromOficios, '/');
}
