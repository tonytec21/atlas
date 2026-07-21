<?php
/**
 * Atlas Forja — Ferramentas de PDF (comprimir, PDF↔imagens, juntar).
 * Núcleo: config (JSON), detecção de ferramentas (Ghostscript/ImageMagick),
 * CSRF, admin e as operações de conversão.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Fortaleza');

function forja_is_win() { return stripos(PHP_OS, 'WIN') === 0; }

/* ============================ Conexão (só p/ checar admin) ============================ */
function forja_db()
{
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;
    require __DIR__ . '/db_connection.php';
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* ============================ Perfil / Administrador ============================ */
function forja_nivel_acesso()
{
    $u = $_SESSION['username'] ?? '';
    if ($u === '') return '';
    try {
        $st = forja_db()->prepare("SELECT nivel_de_acesso FROM funcionarios WHERE usuario=? LIMIT 1");
        $st->bind_param('s', $u); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        return $r['nivel_de_acesso'] ?? '';
    } catch (Throwable $e) { return ''; }
}
function forja_is_admin()
{
    $n = mb_strtolower(trim(forja_nivel_acesso()));
    return in_array($n, ['administrador', 'admin', 'adm', 'administrator', 'master', 'root'], true);
}
function forja_require_admin()
{
    if (!forja_is_admin()) throw new RuntimeException('Acesso restrito ao administrador.');
}

/* ============================ CSRF ============================ */
function forja_csrf()
{
    if (empty($_SESSION['forja_csrf'])) $_SESSION['forja_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['forja_csrf'];
}
function forja_csrf_check($t) { return is_string($t) && !empty($_SESSION['forja_csrf']) && hash_equals($_SESSION['forja_csrf'], $t); }

/* ============================ Config (JSON) ============================ */
function forja_config_path() { return __DIR__ . '/config_forja.json'; }
function forja_config()
{
    $p = forja_config_path();
    $base = ['forja_ativo' => 'S', 'gs_path' => '', 'magick_path' => '', 'lo_path' => ''];
    if (is_file($p)) {
        $j = json_decode(file_get_contents($p), true);
        if (is_array($j)) return array_merge($base, $j);
    }
    return $base;
}
function forja_config_set($campos)
{
    $cfg = array_merge(forja_config(), $campos);
    file_put_contents(forja_config_path(), json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $cfg;
}

/* ============================ Diretórios ============================ */
function forja_dir_tmp()
{
    $d = __DIR__ . '/tmp';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    return $d;
}
function forja_dir_out()
{
    $d = __DIR__ . '/saida';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    return $d;
}

/* ============================ Detecção de ferramentas ============================ */
function forja_which($bin)
{
    $cmd = forja_is_win() ? 'where ' . $bin . ' 2>NUL' : 'command -v ' . escapeshellarg($bin) . ' 2>/dev/null';
    $out = @shell_exec($cmd);
    if ($out) { $line = trim(strtok($out, "\n")); if ($line !== '') return $line; }
    return null;
}
function forja_gs_bin()
{
    $cfg = forja_config();
    if (!empty($cfg['gs_path']) && @is_file($cfg['gs_path'])) return $cfg['gs_path'];
    foreach (['C:/Program Files/gs/gs*/bin/gswin64c.exe',
              'C:/Program Files/gs/gs*/bin/gswin32c.exe',
              'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe'] as $pat) {
        $g = glob($pat); if ($g) return $g[0];
    }
    foreach (['gswin64c', 'gswin32c', 'gs'] as $b) { $w = forja_which($b); if ($w) return $w; }
    return null;
}
function forja_magick_bin()
{
    $cfg = forja_config();
    if (!empty($cfg['magick_path']) && @is_file($cfg['magick_path'])) return $cfg['magick_path'];
    foreach (['C:/Program Files/ImageMagick-*/magick.exe',
              'C:/Program Files (x86)/ImageMagick-*/magick.exe'] as $pat) {
        $g = glob($pat); if ($g) return $g[0];
    }
    foreach (['magick', 'convert'] as $b) { $w = forja_which($b); if ($w) return $w; }
    return null;
}
function forja_tem_pdf_engine() { return forja_gs_bin() || forja_magick_bin(); }

/** Procura um LibreOffice embutido em forja/libreoffice/ (portátil, sem instalação). */
function forja_bundled_soffice()
{
    $base = __DIR__ . '/libreoffice';
    if (!is_dir($base)) return null;
    $alvos = forja_is_win() ? ['soffice.exe'] : ['soffice', 'soffice.bin'];
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && in_array(strtolower($f->getFilename()), $alvos, true)) return $f->getPathname();
        }
    } catch (Throwable $e) {}
    return null;
}
function forja_libreoffice_bin()
{
    $cfg = forja_config();
    if (!empty($cfg['lo_path']) && @is_file($cfg['lo_path'])) return $cfg['lo_path'];
    $bundled = forja_bundled_soffice(); if ($bundled) return $bundled;
    foreach (['C:/Program Files/LibreOffice/program/soffice.exe',
              'C:/Program Files (x86)/LibreOffice/program/soffice.exe'] as $p) if (@is_file($p)) return $p;
    foreach (['soffice', 'libreoffice'] as $b) { $w = forja_which($b); if ($w) return $w; }
    return null;
}

/** Baixa um arquivo (stream para disco, sem carregar na memória). */
function forja_baixar_arquivo($url, $destino)
{
    $fp = fopen($destino, 'wb'); if (!$fp) throw new RuntimeException('Não foi possível gravar o download.');
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 1800,
            CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'AtlasForja']);
        $ok = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
        curl_close($ch); fclose($fp);
        if (!$ok || $code >= 400) { @unlink($destino); throw new RuntimeException('Falha no download (HTTP ' . $code . ') ' . $err); }
    } else {
        $src = @fopen($url, 'rb'); if (!$src) { fclose($fp); @unlink($destino); throw new RuntimeException('Não foi possível baixar (allow_url_fopen desativado?).'); }
        stream_copy_to_stream($src, $fp); fclose($src); fclose($fp);
    }
    if (filesize($destino) < 1024) { @unlink($destino); throw new RuntimeException('Download vazio/incompleto.'); }
}

/** Baixa e extrai um LibreOffice portátil (.zip) ou .msi (Windows) para forja/libreoffice/. */
function forja_instalar_libreoffice($url)
{
    if (!preg_match('~^https?://~i', $url)) throw new RuntimeException('Informe uma URL http(s) para o pacote (.zip ou .msi).');
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    $arq = forja_dir_tmp() . '/lodl_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'bin');
    forja_baixar_arquivo($url, $arq);

    $destino = __DIR__ . '/libreoffice';
    forja_rrmdir($destino); @mkdir($destino, 0775, true);

    if ($ext === 'zip') {
        if (!class_exists('ZipArchive')) throw new RuntimeException('Extensão ZIP do PHP ausente.');
        $za = new ZipArchive();
        if ($za->open($arq) !== true) throw new RuntimeException('Arquivo ZIP inválido.');
        $za->extractTo($destino); $za->close();
    } elseif ($ext === 'msi') {
        if (!forja_is_win()) throw new RuntimeException('Instalação por .msi só é suportada no Windows. Use um .zip.');
        $tgt = str_replace('/', '\\', $destino);
        forja_exec('msiexec /a ' . escapeshellarg($arq) . ' /qn TARGETDIR=' . escapeshellarg($tgt));
    } else {
        @unlink($arq);
        throw new RuntimeException('Formato não suportado (' . ($ext ?: '?') . '). Use um .zip (portátil) ou .msi (Windows).');
    }
    @unlink($arq);

    $so = forja_bundled_soffice();
    if (!$so) throw new RuntimeException('Pacote extraído, mas o soffice não foi localizado dentro dele.');
    forja_config_set(['lo_path' => $so]);
    return $so;
}
function forja_tem_libreoffice() { return forja_libreoffice_bin() !== null; }

function forja_rrmdir($dir)
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) { if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f; is_dir($p) ? forja_rrmdir($p) : @unlink($p); }
    @rmdir($dir);
}

/** Conversão via LibreOffice headless. Devolve o caminho do arquivo gerado. */
function forja_lo_convert($src, $convertArg, $infilter, $outExt)
{
    $so = forja_libreoffice_bin();
    if (!$so) throw new RuntimeException('LibreOffice não encontrado. Instale o LibreOffice e informe o caminho do soffice.exe em "Configurar".');
    $outdir = forja_dir_out() . '/lo_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    @mkdir($outdir, 0775, true);
    $profile = forja_dir_tmp() . '/loprof_' . bin2hex(random_bytes(5));
    $pp = str_replace('\\', '/', $profile);
    $profileUrl = (substr($pp, 0, 1) === '/') ? 'file://' . $pp : 'file:///' . $pp;
    $inf = ($infilter !== '') ? ' --infilter=' . escapeshellarg($infilter) : '';
    $cmd = escapeshellarg($so) . ' --headless --norestore --nolockcheck -env:UserInstallation=' . escapeshellarg($profileUrl)
         . $inf . ' --convert-to ' . escapeshellarg($convertArg)
         . ' --outdir ' . escapeshellarg($outdir) . ' ' . escapeshellarg($src);
    $r = forja_exec($cmd);
    forja_rrmdir($profile);
    $files = glob($outdir . '/*.' . $outExt);
    if (!$files) {
        $msg = trim($r['out']);
        throw new RuntimeException('Falha na conversão' . ($msg ? ' (' . mb_substr($msg, 0, 200) . ')' : '') . '. Verifique o LibreOffice em "Configurar".');
    }
    return $files[0];
}
function forja_word_para_pdf($src) { return forja_lo_convert($src, 'pdf', '', 'pdf'); }

/** Achata as caixas de texto de um DOCX (importado de PDF) em parágrafos fluidos,
 *  preservando a formatação (negrito, itálico, fonte) e removendo as formas vazias.
 *  Retorna o nº de parágrafos aproveitados (0 se não havia caixas de texto). */
function forja_flatten_docx($docx)
{
    if (!class_exists('ZipArchive')) return 0;
    $zip = new ZipArchive();
    if ($zip->open($docx) !== true) return 0;
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false || !preg_match('~<w:body>(.*)</w:body>~s', $xml, $mb)) { $zip->close(); return 0; }
    $body = $mb[1];
    $sectPr = '';
    if (preg_match('~<w:sectPr[ >].*?</w:sectPr>~s', $body, $ms)) $sectPr = $ms[0];
    elseif (preg_match('~<w:sectPr[^>]*/>~', $body, $ms2)) $sectPr = $ms2[0];
    preg_match_all('~<w:txbxContent>(.*?)</w:txbxContent>~s', $body, $mt);
    $plist = [];
    foreach ($mt[1] as $inner)
        if (preg_match_all('~<w:p[ >].*?</w:p>~s', $inner, $mp)) foreach ($mp[0] as $p) $plist[] = $p;
    if (!$plist) { $zip->close(); return 0; }
    // remove duplicatas consecutivas (o PDF import costuma emitir cada caixa 2x)
    $outp = []; $prev = null;
    foreach ($plist as $p) {
        $t = trim(html_entity_decode(strip_tags($p)));
        if ($t !== '' && $t === $prev) continue;
        $outp[] = $p; $prev = $t;
    }
    $novoBody = '<w:body>' . implode('', $outp) . $sectPr . '</w:body>';
    $novoXml = preg_replace('~<w:body>.*</w:body>~s', $novoBody, $xml, 1);
    $zip->addFromString('word/document.xml', $novoXml);
    $zip->close();
    return count($outp);
}

/** Extrai o texto do PDF (Ghostscript) e monta um DOCX simples, sem formatação. */
function forja_pdf_texto_simples($src)
{
    $gs = forja_gs_bin();
    if (!$gs) throw new RuntimeException('Para o modo "texto simples" é necessário o Ghostscript. Configure-o ou use outro modo.');
    $txt = forja_dir_tmp() . '/pdftxt_' . bin2hex(random_bytes(4)) . '.txt';
    forja_exec(escapeshellarg($gs) . ' -sDEVICE=txtwrite -dNOPAUSE -dBATCH -dQUIET -sOutputFile=' . escapeshellarg($txt) . ' ' . escapeshellarg($src));
    if (!is_file($txt)) throw new RuntimeException('Falha ao extrair o texto do PDF.');
    $c = file_get_contents($txt);
    $c = str_replace("\r\n", "\n", $c);
    $c = preg_replace('~^[ \t]+~m', '', $c);
    $c = preg_replace('~[ \t]+$~m', '', $c);
    $c = preg_replace('~\n{3,}~', "\n\n", $c);
    $c = trim($c);
    if (mb_strlen(preg_replace('~\s~u', '', $c)) < 5) {
        @unlink($txt);
        throw new RuntimeException('Não há texto extraível neste PDF (provavelmente é digitalizado/imagem). Para PDFs escaneados, use o módulo Atlas Iris (OCR).');
    }
    file_put_contents($txt, $c);
    $out = forja_lo_convert($txt, 'docx:MS Word 2007 XML', 'Text (encoded):UTF8', 'docx');
    @unlink($txt);
    return $out;
}

/**
 * PDF -> Word. $modo:
 *   'formatado' (padrão) — mantém a formatação e a estrutura, sem as caixas brancas sobrepostas;
 *   'simples'            — só o texto corrido, sem formatação;
 *   'layout'             — preserva o visual exato (mas gera molduras/caixas).
 */
function forja_pdf_para_word($src, $modo = 'formatado')
{
    if ($modo === 'layout')  return forja_lo_convert($src, 'docx:MS Word 2007 XML', 'writer_pdf_import', 'docx');
    if ($modo === 'simples') return forja_pdf_texto_simples($src);
    // 'formatado': converte preservando o visual e depois achata as caixas em texto fluido.
    $docx = forja_lo_convert($src, 'docx:MS Word 2007 XML', 'writer_pdf_import', 'docx');
    $n = forja_flatten_docx($docx);
    if ($n > 0) return $docx;
    @unlink($docx);
    return forja_pdf_texto_simples($src);   // fallback se não houver caixas de texto
}

function forja_load_libs()
{
    if (class_exists('TCPDF') && class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) return;
    $bases = [__DIR__ . '/../oficios', __DIR__ . '/../signum', __DIR__];
    foreach ($bases as $b) {
        foreach (['/tcpdf/tcpdf.php', '/TCPDF/tcpdf.php', '/vendor/tecnickcom/tcpdf/tcpdf.php'] as $t)
            if (!class_exists('TCPDF') && is_file($b . $t)) require_once $b . $t;
        foreach (['/vendor/autoload.php'] as $a)
            if (is_file($b . $a)) require_once $b . $a;
        foreach (['/src/autoload.php', '/fpdi/src/autoload.php', '/FPDI/src/autoload.php'] as $f)
            if (!class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi') && is_file($b . $f)) require_once $b . $f;
    }
    if (!class_exists('TCPDF')) throw new RuntimeException('Biblioteca TCPDF não encontrada (esperada em ../oficios).');
    if (!class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) throw new RuntimeException('Biblioteca FPDI não encontrada (esperada em ../oficios).');
}

function forja_exec($cmd)
{
    $out = []; $rc = 1;
    @exec($cmd . ' 2>&1', $out, $rc);
    return ['rc' => $rc, 'out' => implode("\n", $out)];
}

function forja_comprimir_pdf($src, $nivel = 'recomendado')
{
    $gs = forja_gs_bin();
    if (!$gs) throw new RuntimeException('Ghostscript não encontrado. Configure o caminho em "Configurar".');
    $mapa = ['tela' => '/screen', 'recomendado' => '/ebook', 'alta' => '/printer', 'maxima' => '/prepress'];
    $set = $mapa[$nivel] ?? '/ebook';
    $out = forja_dir_out() . '/comprimido_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
    $cmd = escapeshellarg($gs)
         . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.5 -dPDFSETTINGS=' . $set
         . ' -dNOPAUSE -dQUIET -dBATCH -dDetectDuplicateImages=true -dCompressFonts=true'
         . ' -sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($src);
    $r = forja_exec($cmd);
    if ($r['rc'] !== 0 || !is_file($out) || filesize($out) < 100)
        throw new RuntimeException('Falha ao comprimir o PDF. ' . mb_substr($r['out'], 0, 300));
    return $out;
}

function forja_pdf_para_imagens($src, $formato = 'png', $dpi = 150)
{
    $dpi = max(72, min(400, (int)$dpi));
    $formato = $formato === 'jpg' ? 'jpg' : 'png';
    $dir = forja_dir_out() . '/imgs_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    @mkdir($dir, 0775, true);
    $pattern = $dir . '/pagina-%03d.' . $formato;

    $gs = forja_gs_bin();
    $ok = false;
    if ($gs) {
        $device = $formato === 'jpg' ? 'jpeg' : 'png16m';
        $extra  = $formato === 'jpg' ? ' -dJPEGQ=90' : '';
        $cmd = escapeshellarg($gs) . ' -sDEVICE=' . $device . ' -r' . $dpi . $extra
             . ' -dNOPAUSE -dQUIET -dBATCH -dTextAlphaBits=4 -dGraphicsAlphaBits=4'
             . ' -sOutputFile=' . escapeshellarg($pattern) . ' ' . escapeshellarg($src);
        $r = forja_exec($cmd);
        $ok = ($r['rc'] === 0);
    }
    if (!$ok && ($mk = forja_magick_bin())) {
        $cmd = escapeshellarg($mk) . ' -density ' . $dpi . ' ' . escapeshellarg($src)
             . ($formato === 'jpg' ? ' -quality 90' : '') . ' ' . escapeshellarg($pattern);
        $r = forja_exec($cmd);
    }
    $files = glob($dir . '/pagina-*.' . $formato);
    natsort($files); $files = array_values($files);
    if (!$files) throw new RuntimeException('Nenhuma imagem gerada. Verifique se o Ghostscript/ImageMagick está configurado.');

    $zip = forja_dir_out() . '/imagens_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.zip';
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');
    foreach ($files as $f) $za->addFile($f, basename($f));
    $za->close();

    return ['zip' => $zip, 'paginas' => count($files)];
}

function forja_imagens_para_pdf($imagens, $modo = 'imagem')
{
    forja_load_libs();
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false); $pdf->SetMargins(0, 0, 0);
    $pdf->SetCreator('Atlas Forja');

    $qtd = 0;
    foreach ($imagens as $img) {
        $info = @getimagesize($img);
        if (!$info) continue;
        $wpx = $info[0]; $hpx = $info[1];
        if ($modo === 'a4') {
            $orient = $wpx > $hpx ? 'L' : 'P';
            $pdf->AddPage($orient, 'A4');
            $pw = $pdf->getPageWidth(); $ph = $pdf->getPageHeight(); $m = 8;
            $maxW = $pw - 2 * $m; $maxH = $ph - 2 * $m;
            $ratio = min($maxW / $wpx, $maxH / $hpx);
            $w = $wpx * $ratio; $h = $hpx * $ratio;
            $x = ($pw - $w) / 2; $y = ($ph - $h) / 2;
            $pdf->Image($img, $x, $y, $w, $h, '', '', '', true, 300);
        } else {
            $mmW = $wpx * 25.4 / 96; $mmH = $hpx * 25.4 / 96;
            $pdf->AddPage($mmW > $mmH ? 'L' : 'P', [$mmW, $mmH]);
            $pdf->Image($img, 0, 0, $mmW, $mmH, '', '', '', false, 96);
        }
        $qtd++;
    }
    if ($qtd === 0) throw new RuntimeException('Nenhuma imagem válida (use PNG ou JPG).');
    $out = forja_dir_out() . '/imagens_para_pdf_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
    $pdf->Output($out, 'F');
    return ['path' => $out, 'paginas' => $qtd];
}


/**
 * Garante que o PDF seja legível pelo parser gratuito do FPDI. PDFs salvos com
 * object streams / xref comprimido (PDF 1.5+) não são suportados; nesse caso
 * normaliza via Ghostscript (reescreve como PDF 1.4) e devolve o novo caminho.
 */
function forja_pdf_compativel_fpdi($src)
{
    forja_load_libs();
    $fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
    try { $t = new $fpdi(); $t->setSourceFile($src); return $src; }
    catch (Throwable $e) { /* provável compressão não suportada — normaliza abaixo */ }
    $gs = forja_gs_bin();
    if (!$gs) throw new RuntimeException('Este PDF usa uma compressão (object streams, PDF 1.5+) que o leitor interno não abre, e o Ghostscript — necessário para convertê-lo — não está configurado.');
    $out = forja_dir_tmp() . '/norm_' . bin2hex(random_bytes(5)) . '.pdf';
    forja_exec(escapeshellarg($gs) . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($src));
    if (!is_file($out) || filesize($out) < 100) throw new RuntimeException('Não foi possível normalizar este PDF para leitura.');
    try { $t = new $fpdi(); $t->setSourceFile($out); }
    catch (Throwable $e) { @unlink($out); throw new RuntimeException('Este PDF não pôde ser processado, mesmo após a normalização.'); }
    return $out;
}

function forja_juntar_pdfs($pdfs)
{
    forja_load_libs();
    $fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
    $pdf = new $fpdi('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(false);
    $total = 0;
    foreach ($pdfs as $p) {
        $p = forja_pdf_compativel_fpdi($p);
        $n = $pdf->setSourceFile($p);
        for ($i = 1; $i <= $n; $i++) {
            $tpl = $pdf->importPage($i); $s = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($s['orientation'], [$s['width'], $s['height']]);
            $pdf->useTemplate($tpl, 0, 0, $s['width'], $s['height'], true);
            $total++;
        }
    }
    if ($total === 0) throw new RuntimeException('Nenhuma página encontrada nos PDFs enviados.');
    $out = forja_dir_out() . '/juntados_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
    $pdf->Output($out, 'F');
    return ['path' => $out, 'paginas' => $total];
}

/**
 * União em lote: um Lado A comum (1+ PDFs) combinado com CADA item do Lado B,
 * gerando um PDF por item do Lado B. $posicao: 'antes' (A+B) | 'depois' (B+A).
 * $bItens: array de ['path'=>, 'nome'=>]. Devolve um ZIP.
 */
function forja_juntar_multiplo($aPaths, $bItens, $posicao = 'antes')
{
    forja_load_libs();
    if (!$aPaths)  throw new RuntimeException('Envie ao menos um PDF no Lado A.');
    if (!$bItens)  throw new RuntimeException('Envie ao menos um PDF no Lado B.');
    $fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
    $aNorm = array_map('forja_pdf_compativel_fpdi', $aPaths);   // normaliza o Lado A uma vez

    $dir = forja_dir_out() . '/multi_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    @mkdir($dir, 0775, true);
    $zip = forja_dir_out() . '/uniao_multipla_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.zip';
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');

    $usados = []; $n = 0;
    foreach ($bItens as $b) {
        $bNorm = forja_pdf_compativel_fpdi($b['path']);
        $ordem = ($posicao === 'depois') ? array_merge([$bNorm], $aNorm) : array_merge($aNorm, [$bNorm]);
        $pdf = new $fpdi('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(false);
        foreach ($ordem as $src) {
            $cnt = $pdf->setSourceFile($src);
            for ($i = 1; $i <= $cnt; $i++) {
                $tpl = $pdf->importPage($i); $sz = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($sz['orientation'], [$sz['width'], $sz['height']]);
                $pdf->useTemplate($tpl, 0, 0, $sz['width'], $sz['height'], true);
            }
        }
        $base = preg_replace('~[^A-Za-z0-9_\- ]~', '_', pathinfo($b['nome'], PATHINFO_FILENAME));
        if ($base === '') $base = 'resultado';
        $nome = $base . '.pdf'; $k = 1;
        while (isset($usados[mb_strtolower($nome)])) { $nome = $base . '_' . (++$k) . '.pdf'; }
        $usados[mb_strtolower($nome)] = 1;
        $out = $dir . '/' . $nome;
        $pdf->Output($out, 'F');
        $za->addFile($out, $nome);
        $n++;
    }
    $za->close();
    return ['zip' => $zip, 'total' => $n];
}

/** Divide um PDF em partes. $modo: partes (N partes) | paginas (N páginas por parte). Devolve um ZIP. */
function forja_dividir_pdf($src, $modo = 'partes', $valor = 2)
{
    forja_load_libs();
    $src = forja_pdf_compativel_fpdi($src);
    $fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
    $contador = new $fpdi();
    $total = $contador->setSourceFile($src);
    if ($total < 2) throw new RuntimeException('O PDF tem apenas ' . $total . ' página — não há como dividir.');

    $ranges = [];
    if ($modo === 'paginas') {
        $por = max(1, (int)$valor);
        for ($i = 1; $i <= $total; $i += $por) $ranges[] = [$i, min($total, $i + $por - 1)];
    } else {
        $n = (int)$valor;
        if ($n < 2) throw new RuntimeException('Informe ao menos 2 partes.');
        if ($n > $total) $n = $total;
        $base = intdiv($total, $n); $resto = $total % $n; $ini = 1;
        for ($k = 0; $k < $n; $k++) {
            $qtd = $base + ($k < $resto ? 1 : 0);
            $ranges[] = [$ini, $ini + $qtd - 1];
            $ini += $qtd;
        }
    }

    $dir = forja_dir_out() . '/split_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    @mkdir($dir, 0775, true);
    $arqs = []; $p = 1;
    foreach ($ranges as $r) {
        $pdf = new $fpdi('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(false);
        $pdf->setSourceFile($src);
        for ($pg = $r[0]; $pg <= $r[1]; $pg++) {
            $tpl = $pdf->importPage($pg); $sz = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($sz['orientation'], [$sz['width'], $sz['height']]);
            $pdf->useTemplate($tpl, 0, 0, $sz['width'], $sz['height'], true);
        }
        $nome = sprintf('parte-%02d_pag%d-%d.pdf', $p, $r[0], $r[1]);
        $out = $dir . '/' . $nome;
        $pdf->Output($out, 'F');
        $arqs[] = $out; $p++;
    }

    $zip = forja_dir_out() . '/partes_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.zip';
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');
    foreach ($arqs as $a) $za->addFile($a, basename($a));
    $za->close();

    return ['zip' => $zip, 'partes' => count($ranges), 'total_paginas' => $total];
}

/** Salva os uploads (arquivo[]) em tmp e devolve [['path','nome'], ...] na ordem enviada. */
function forja_salvar_uploads($somentePdf = false, $somenteImg = false, $word = false, $campo = 'arquivo')
{
    if (empty($_FILES[$campo])) throw new RuntimeException('Nenhum arquivo recebido. Verifique se o arquivo não excede o limite do servidor (php.ini).');
    $f = $_FILES[$campo];
    $names = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps  = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errs  = is_array($f['error']) ? $f['error'] : [$f['error']];
    $sizes = is_array($f['size']) ? $f['size'] : [$f['size']];
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $LIMITE = 200 * 1024 * 1024;
    $saved = [];
    foreach ($names as $i => $nm) {
        $err = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
            throw new RuntimeException('O arquivo "' . $nm . '" excede o limite do servidor. Ajuste upload_max_filesize/post_max_size (veja o .htaccess do módulo).');
        if ($err !== UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($tmps[$i])) continue;
        if (($sizes[$i] ?? 0) > $LIMITE) throw new RuntimeException('Arquivo muito grande (máx. 200 MB): ' . $nm);
        if ($word) {
            $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
            if (!in_array($ext, ['docx', 'doc', 'odt', 'rtf', 'txt'], true)) throw new RuntimeException('Envie um Word (.docx/.doc), ODT, RTF ou TXT (' . $nm . ').');
        } else {
            $mime = $fi->file($tmps[$i]) ?: '';
            if ($somentePdf && $mime !== 'application/pdf') throw new RuntimeException('Apenas PDF é aceito aqui (' . $nm . ').');
            if ($somenteImg && strpos($mime, 'image/') !== 0) throw new RuntimeException('Apenas imagens PNG/JPG são aceitas (' . $nm . ').');
            $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpg' : 'bin'));
        }
        $path = forja_dir_tmp() . '/up_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($tmps[$i], $path) && !@copy($tmps[$i], $path))
            throw new RuntimeException('Falha ao salvar o upload: ' . $nm);
        $saved[] = ['path' => $path, 'nome' => $nm];
    }
    if (!$saved) throw new RuntimeException('Nenhum arquivo válido recebido.');
    return $saved;
}

function forja_human($n) { $n = (int)$n; if ($n < 1024) return $n . ' B'; if ($n < 1048576) return round($n / 1024, 1) . ' KB'; return round($n / 1048576, 1) . ' MB'; }

/** Registra um arquivo de saída num "cofre" e devolve um token para download. */
function forja_registrar_saida($path, $nomeSugerido)
{
    $token = bin2hex(random_bytes(12));
    $meta = ['path' => $path, 'nome' => $nomeSugerido, 'em' => time()];
    file_put_contents(forja_dir_tmp() . '/dl_' . $token . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
    return $token;
}
function forja_saida($token)
{
    $token = preg_replace('~[^a-f0-9]~', '', $token);
    $f = forja_dir_tmp() . '/dl_' . $token . '.json';
    if (!is_file($f)) return null;
    $m = json_decode(file_get_contents($f), true);
    return (is_array($m) && is_file($m['path'])) ? $m : null;
}
