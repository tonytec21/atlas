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
    forja_prog(15, 'Abrindo no LibreOffice…');
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
    forja_prog(35, 'Convertendo o documento…');
    $r = forja_exec($cmd);
    forja_rrmdir($profile);
    forja_prog(85, 'Finalizando…');
    $files = glob($outdir . '/*.' . $outExt);
    if (!$files) {
        $msg = trim($r['out']);
        throw new RuntimeException('Falha na conversão' . ($msg ? ' (' . mb_substr($msg, 0, 200) . ')' : '') . '. Verifique o LibreOffice em "Configurar".');
    }
    /* Move o resultado para a raiz de saida/ e descarta a pasta do LibreOffice. */
    $destino = forja_dir_out() . '/lo_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.' . $outExt;
    if (@rename($files[0], $destino) || (@copy($files[0], $destino) && @unlink($files[0]))) {
        forja_rrmdir($outdir);
        $files[0] = $destino;
    }
    forja_prog(95, 'Concluído');
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
    $txt = forja_tmp_registrar(forja_dir_tmp() . '/pdftxt_' . bin2hex(random_bytes(4)) . '.txt');
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

/* =====================================================================
 * COMPRESSÃO DE PDF — motor v2
 * ---------------------------------------------------------------------
 * Por que o motor antigo falhava:
 *   1) usava apenas os presets do Ghostscript (-dPDFSETTINGS). O preset
 *      /ebook só reamostra imagens acima de 1,5x o alvo (>225 dpi) e, por
 *      padrão, o pdfwrite REPASSA os JPEGs originais sem recomprimir
 *      (-dPassThroughJPEGImages=true). Resultado: digitalização de 200 dpi
 *      saía do mesmo tamanho -> "já estava otimizado".
 *   2) o preset /screen reamostra para 72 dpi usando /Average. Em documento
 *      digitalizado isso destrói o texto -> ilegível.
 *
 * O que o motor novo faz:
 *   - controla explicitamente dpi, filtro de reamostragem (/Bicubic),
 *     limiar (=1.0, sempre reamostra) e qualidade JPEG (QFactor);
 *   - desliga o repasse de JPEG/JPX, forçando a recompressão;
 *   - usa CCITT G4 (sem perda) em imagens 1 bit;
 *   - deduplica imagens, faz subset de fontes e grava em PDF 1.7
 *     (object streams = estrutura bem menor);
 *   - escada progressiva: se a 1ª tentativa não reduzir o suficiente, tenta
 *     a próxima do MESMO nível (nunca abaixo do piso de legibilidade);
 *   - opção "tons de cinza" (detecção automática de páginas neutras);
 *   - nunca devolve arquivo maior que o original.
 * ===================================================================== */

/** Versão do Ghostscript (float). 0.0 se não identificada. */
function forja_gs_versao()
{
    static $v = null;
    if ($v !== null) return $v;
    $gs = forja_gs_bin();
    if (!$gs) return $v = 0.0;
    $r = forja_exec(forja_arg($gs) . ' --version');
    $v = (float)trim(strtok($r['out'], "\n"));
    return $v;
}

/** Escreve o arquivo .ps com os dicionários de qualidade JPEG (setdistillerparams). */
function forja_gs_qualidade_ps($qfactor, $subamostrar = false)
{
    $q  = number_format((float)$qfactor, 2, '.', '');
    $sc = $subamostrar ? '[2 1 1 2]' : '[1 1 1 1]';   // croma (só faz sentido em cor)
    $dc = '<< /QFactor ' . $q . ' /Blend 1 /HSamples ' . $sc . ' /VSamples ' . $sc . ' >>';
    $dg = '<< /QFactor ' . $q . ' /Blend 1 /HSamples [1 1 1 1] /VSamples [1 1 1 1] >>';
    $ps = "<<\n"
        . "  /ColorACSImageDict " . $dc . "\n"
        . "  /ColorImageDict    " . $dc . "\n"
        . "  /GrayACSImageDict  " . $dg . "\n"
        . "  /GrayImageDict     " . $dg . "\n"
        . ">> setdistillerparams\n";
    $f = forja_tmp_registrar(forja_dir_tmp() . '/gsq_' . bin2hex(random_bytes(4)) . '.ps');
    file_put_contents($f, $ps);
    return $f;
}

/** Heurística: o PDF é predominantemente digitalizado (imagens) ou vetorial/texto? */
function forja_pdf_tem_imagens($src)
{
    $achou = false;
    if ($fp = @fopen($src, 'rb')) {
        $cauda = '';
        while (!feof($fp)) {
            $buf = $cauda . (string)fread($fp, 1048576);
            if (strpos($buf, '/DCTDecode') !== false || strpos($buf, '/JPXDecode') !== false
             || strpos($buf, '/Subtype/Image') !== false || strpos($buf, '/Subtype /Image') !== false) { $achou = true; break; }
            $cauda = substr($buf, -32);
        }
        fclose($fp);
    }
    if ($achou) return true;
    // PDFs 1.5+ escondem os dicionários em object streams: usa o peso por página.
    $pg = forja_pdf_num_paginas($src);
    return $pg > 0 && (filesize($src) / $pg) > 120 * 1024;
}

/**
 * Detecta se as páginas são cromaticamente neutras (C≈M≈Y) — nesse caso a
 * conversão para tons de cinza é segura e reduz bastante o tamanho.
 */
function forja_pdf_neutro($src, $maxPaginas = 4, $tolerancia = 0.02)
{
    $gs = forja_gs_bin();
    if (!$gs) return false;
    $cmd = forja_arg($gs) . ' -q -o - -sDEVICE=inkcov -dNOPAUSE -dBATCH'
         . ' -dFirstPage=1 -dLastPage=' . (int)$maxPaginas . ' ' . forja_arg($src);
    $r = forja_exec($cmd);
    if ($r['rc'] !== 0) return false;
    $n = 0;
    foreach (explode("\n", $r['out']) as $ln) {
        if (!preg_match('~([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+CMYK~', $ln, $m)) continue;
        $c = (float)$m[1]; $mg = (float)$m[2]; $y = (float)$m[3];
        if (max(abs($c - $mg), abs($mg - $y), abs($c - $y)) > $tolerancia) return false;
        $n++;
    }
    return $n > 0;
}

/** Uma tentativa de compressão com parâmetros explícitos. Devolve o caminho ou null. */
function forja_gs_tentativa($src, $dpi, $qfactor, $subamostrar, $cinza, $monoDpi = 300)
{
    $gs = forja_gs_bin();
    if (!$gs) return null;
    $ps  = forja_gs_qualidade_ps($qfactor, $subamostrar);
    $out = forja_tmp_registrar(forja_dir_tmp() . '/cmp_' . bin2hex(random_bytes(5)) . '.pdf');
    $dpi = max(50, (int)$dpi);

    $o = [
        '-sDEVICE=pdfwrite',
        '-dCompatibilityLevel=1.7',
        '-dNOPAUSE', '-dBATCH', '-dQUIET', '-dSAFER',
        '-dAutoRotatePages=/None',
        '-dDetectDuplicateImages=true',
        '-dCompressFonts=true', '-dSubsetFonts=true', '-dCompressStreams=true',
        // >>> a chave do problema: sem isso o Ghostscript não recomprime JPEG algum
        '-dPassThroughJPEGImages=false',
        '-dPassThroughJPXImages=false',
        // imagens coloridas
        '-dDownsampleColorImages=true', '-dColorImageDownsampleType=/Bicubic',
        '-dColorImageResolution=' . $dpi, '-dColorImageDownsampleThreshold=1.0',
        '-dEncodeColorImages=true', '-dAutoFilterColorImages=false', '-dColorImageFilter=/DCTEncode',
        // imagens em tons de cinza
        '-dDownsampleGrayImages=true', '-dGrayImageDownsampleType=/Bicubic',
        '-dGrayImageResolution=' . $dpi, '-dGrayImageDownsampleThreshold=1.0',
        '-dEncodeGrayImages=true', '-dAutoFilterGrayImages=false', '-dGrayImageFilter=/DCTEncode',
        // imagens 1 bit: CCITT G4 é SEM PERDA e comprime muito; não reamostra à toa
        '-dDownsampleMonoImages=true', '-dMonoImageDownsampleType=/Subsample',
        '-dMonoImageResolution=' . (int)$monoDpi, '-dMonoImageDownsampleThreshold=1.5',
        '-dEncodeMonoImages=true', '-dMonoImageFilter=/CCITTFaxEncode',
    ];
    if ($cinza) {
        $o[] = '-sColorConversionStrategy=Gray';
        $o[] = '-dProcessColorModel=/DeviceGray';
    } else {
        $o[] = '-dConvertCMYKImagesToRGB=true';
    }

    $cmd = forja_arg($gs) . ' ' . implode(' ', $o)
         . ' -sOutputFile=' . forja_arg($out) . ' ' . forja_arg($ps) . ' ' . forja_arg($src);
    $r = forja_exec($cmd);
    @unlink($ps);

    if ($r['rc'] !== 0 || !is_file($out) || filesize($out) < 1024) { @unlink($out); return null; }
    return $out;
}

/** Passe apenas estrutural (sem mexer em imagem) — bom para PDF de texto/vetor. */
function forja_gs_estrutural($src)
{
    $gs = forja_gs_bin();
    if (!$gs) return null;
    $out = forja_tmp_registrar(forja_dir_tmp() . '/cmp_' . bin2hex(random_bytes(5)) . '.pdf');
    $cmd = forja_arg($gs) . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.7 -dNOPAUSE -dBATCH -dQUIET -dSAFER'
         . ' -dAutoRotatePages=/None -dDetectDuplicateImages=true -dCompressFonts=true -dSubsetFonts=true'
         . ' -dCompressStreams=true -dEncodeColorImages=true -dEncodeGrayImages=true'
         . ' -sOutputFile=' . forja_arg($out) . ' ' . forja_arg($src);
    $r = forja_exec($cmd);
    if ($r['rc'] !== 0 || !is_file($out) || filesize($out) < 1024) { @unlink($out); return null; }
    return $out;
}

/** Perfis de compressão. Cada passo = [dpi, QFactor, subamostrar croma]. */
function forja_perfis_compressao()
{
    return [
        'alta' => [
            'rotulo' => 'Alta qualidade',
            'meta'   => 0.10,
            'passos' => [[300, 0.25, false], [300, 0.45, false], [250, 0.55, false]],
        ],
        'recomendado' => [
            'rotulo' => 'Recomendada (equilíbrio)',
            'meta'   => 0.35,
            'passos' => [[200, 0.45, false], [180, 0.65, false], [150, 0.80, true]],
        ],
        'forte' => [
            'rotulo' => 'Máxima compressão legível',
            'meta'   => 0.65,
            'passos' => [[150, 0.80, true], [132, 1.00, true], [120, 1.20, true]],
        ],
        'extrema' => [
            'rotulo' => 'Compressão extrema',
            'meta'   => 0.85,
            'passos' => [[120, 1.10, true], [110, 1.40, true], [100, 1.70, true]],
        ],
    ];
}

/** Compatibilidade com os nomes antigos usados pela tela. */
function forja_normaliza_nivel($n)
{
    $n = strtolower(trim((string)$n));
    $mapa = [
        'tela' => 'forte', 'screen' => 'forte', 'maxima' => 'extrema', 'máxima' => 'extrema',
        'ebook' => 'recomendado', 'equilibrio' => 'recomendado', 'equilíbrio' => 'recomendado',
        'printer' => 'alta', 'prepress' => 'alta', 'qualidade' => 'alta',
    ];
    if (isset($mapa[$n])) $n = $mapa[$n];
    return isset(forja_perfis_compressao()[$n]) ? $n : 'recomendado';
}

/** Limpeza dos temporários/saídas antigos (evita o módulo crescer sem limite). */
/* =====================================================================
 * LIMPEZA DE TEMPORÁRIOS
 * ---------------------------------------------------------------------
 * Tudo que é criado em forja/tmp durante um processamento (uploads,
 * imagens preparadas, PDFs normalizados, amostras, tentativas de
 * compressão) é registrado e apagado no fim do request — exceto os
 * arquivos que viraram download (registrados via forja_registrar_saida).
 * Além disso, forja_gc() varre tmp/ e saida/ removendo o que passou da
 * retenção, inclusive PASTAS (imgs_*, split_*, multi_*, lo_*).
 * ===================================================================== */

/* ============================ Limites de envio ============================ */

/**
 * Teto do módulo, em MB (padrão 2048 = 2 GB). O PHP de 32 bits não consegue
 * tratar arquivos acima de 2 GB (filesize/ftell estouram), então o valor é
 * reduzido nesse caso.
 */
function forja_limite_upload_mb()
{
    $c  = forja_config();
    $mb = (int)($c['limite_upload_mb'] ?? 2048);
    if ($mb < 10) $mb = 2048;
    $mb = min(4096, $mb);
    if (PHP_INT_SIZE < 8) $mb = min($mb, 1900);
    return $mb;
}

/** Limites reais: o menor entre a configuração do módulo e o php.ini. */
function forja_limites_php()
{
    $u = forja_ini_bytes(@ini_get('upload_max_filesize'));
    $p = forja_ini_bytes(@ini_get('post_max_size'));
    $cfg = forja_limite_upload_mb() * 1048576;
    $ef  = $cfg;
    foreach ([$u, $p] as $v) if ($v > 0 && $v < $ef) $ef = $v;
    return ['upload' => $u, 'post' => $p, 'config' => $cfg, 'efetivo' => $ef,
            'x64' => (PHP_INT_SIZE >= 8), 'tempo' => (int)@ini_get('max_execution_time')];
}

/**
 * Quando o corpo do POST passa do post_max_size, o PHP descarta TUDO — $_POST e
 * $_FILES chegam vazios e a validação de CSRF acusaria "sessão expirada", que é
 * enganoso. Aqui o motivo real é identificado antes.
 */
function forja_checar_post()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (!empty($_POST) || !empty($_FILES)) return;
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len <= 0) return;
    $p = forja_ini_bytes(@ini_get('post_max_size'));
    if ($p > 0 && $len > $p) {
        throw new RuntimeException('O envio de ' . forja_human($len) . ' passou do limite do servidor ('
            . 'post_max_size = ' . forja_human($p) . '). Ajuste upload_max_filesize e post_max_size no '
            . 'php.ini do XAMPP, reinicie o Apache e tente de novo.');
    }
    throw new RuntimeException('O servidor descartou o envio de ' . forja_human($len)
        . '. Verifique upload_max_filesize, post_max_size, max_input_time e o espaço livre em disco.');
}

/** Espaço livre no disco das pastas de trabalho (0 se não der para medir). */
function forja_disco_livre()
{
    $v = @disk_free_space(forja_dir_tmp());
    return ($v === false) ? 0 : (float)$v;
}

function forja_retencao_horas()
{
    $c = forja_config();
    $h = (int)($c['retencao_horas'] ?? 3);
    return max(1, min(72, $h));
}

/** Marca um arquivo/pasta de tmp para exclusão automática no fim do request. */
function forja_tmp_registrar($path)
{
    if (empty($GLOBALS['forja_tmp_hook'])) {
        $GLOBALS['forja_tmp_hook'] = true;
        register_shutdown_function('forja_tmp_limpar');
    }
    $GLOBALS['forja_tmp_files'][$path] = true;
    return $path;
}

/** Protege um caminho da limpeza automática (usado pelos downloads). */
function forja_tmp_manter($path)
{
    $GLOBALS['forja_tmp_keep'][$path] = true;
    return $path;
}

function forja_tmp_limpar()
{
    $keep = (array)($GLOBALS['forja_tmp_keep'] ?? []);
    foreach (array_keys((array)($GLOBALS['forja_tmp_files'] ?? [])) as $f) {
        if (isset($keep[$f])) continue;
        if (is_dir($f)) forja_rrmdir($f);
        elseif (is_file($f)) @unlink($f);
    }
    $GLOBALS['forja_tmp_files'] = [];
}

function forja_tamanho_caminho($f)
{
    if (is_file($f)) return (int)@filesize($f);
    $s = 0;
    foreach ((array)glob(rtrim($f, '/\\') . '/*') as $x) $s += forja_tamanho_caminho($x);
    return $s;
}

/** Espaço ocupado hoje pelas pastas de trabalho. */
function forja_uso_disco()
{
    $tmp = forja_tamanho_caminho(forja_dir_tmp());
    $out = forja_tamanho_caminho(forja_dir_out());
    return ['tmp' => $tmp, 'saida' => $out, 'total' => $tmp + $out];
}

/**
 * Remove o que passou da retenção. Com $horas = 0 limpa tudo (menos o
 * .htaccess e o log de erros). Devolve ['arquivos' => n, 'bytes' => n].
 */
function forja_gc($horas = null)
{
    if ($horas === null) $horas = forja_retencao_horas();
    $horas = max(0, (int)$horas);
    $lim   = time() - $horas * 3600;
    $n = 0; $bytes = 0;

    foreach ([forja_dir_tmp(), forja_dir_out()] as $d) {
        foreach ((array)glob($d . '/*') as $f) {
            $base = basename($f);
            if (in_array($base, ['.htaccess', 'forja_erros.log'], true)) continue;
            if ($horas > 0 && @filemtime($f) >= $lim) continue;
            $bytes += forja_tamanho_caminho($f);
            if (is_dir($f)) { forja_rrmdir($f); $n++; }
            elseif (@unlink($f)) { $n++; }
        }
    }
    return ['arquivos' => $n, 'bytes' => $bytes];
}

/* =====================================================================
 * PROGRESSO (job)
 * ---------------------------------------------------------------------
 * O navegador gera um "job" aleatório, envia junto do POST e consulta
 * progresso.php a cada ~700 ms. O processamento grava o percentual em
 * tmp/prog_<job>.json. Para que a consulta não fique presa esperando o
 * lock do arquivo de sessão, os endpoints chamam session_write_close()
 * logo após validar o CSRF.
 * ===================================================================== */

function forja_job_sanitize($id) { return substr(preg_replace('~[^A-Za-z0-9]~', '', (string)$id), 0, 48); }

function forja_job_arquivo($id) { return forja_dir_tmp() . '/prog_' . forja_job_sanitize($id) . '.json'; }

/** Ativa o acompanhamento para este request. Devolve o job (ou '' se não houver). */
function forja_job_iniciar($id)
{
    $id = forja_job_sanitize($id);
    $GLOBALS['forja_job'] = $id;
    if ($id === '') return '';
    forja_prog(1, 'Preparando…');
    register_shutdown_function(function () use ($id) { @unlink(forja_job_arquivo($id)); });
    return $id;
}

/** Grava o andamento. Só faz algo se houver job ativo — seguro chamar sempre. */
function forja_prog($pct, $texto = '', $extra = [])
{
    $id = $GLOBALS['forja_job'] ?? '';
    if ($id === '') return;
    $d = array_merge([
        'pct'   => max(0, min(100, (int)round($pct))),
        'texto' => (string)$texto,
        'em'    => time(),
    ], $extra);
    @file_put_contents(forja_job_arquivo($id), json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Lê o andamento. Quando o job informou uma pasta de saída ("dir"), conta os
 * arquivos já gerados — assim o Ghostscript, que renderiza tudo numa única
 * chamada, também mostra progresso real.
 */
function forja_prog_ler($id)
{
    $f = forja_job_arquivo($id);
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d)) return null;

    if (!empty($d['dir']) && !empty($d['total']) && !empty($d['formato'])) {
        $feitas = count(forja_imgs_validas($d['dir'], $d['formato']));
        $base   = isset($d['base']) ? (float)$d['base'] : 15;
        $teto   = isset($d['teto']) ? (float)$d['teto'] : 85;
        $p      = $base + ($teto - $base) * min(1, $feitas / max(1, (int)$d['total']));
        if ($p > $d['pct']) {
            $d['pct']   = (int)round($p);
            $d['texto'] = 'Renderizando página ' . min((int)$d['total'], $feitas + 1) . ' de ' . (int)$d['total'] . '…';
        }
    }
    return ['pct' => (int)$d['pct'], 'texto' => (string)($d['texto'] ?? '')];
}

function forja_pdf_amostra($src, $paginas = 4)
{
    $gs = forja_gs_bin();
    if (!$gs) return null;
    $out = forja_tmp_registrar(forja_dir_tmp() . '/amo_' . bin2hex(random_bytes(5)) . '.pdf');
    $cmd = forja_arg($gs) . ' -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dQUIET -dSAFER'
         . ' -dFirstPage=1 -dLastPage=' . max(1, (int)$paginas)
         . ' -dPassThroughJPEGImages=true -dAutoRotatePages=/None'
         . ' -sOutputFile=' . forja_arg($out) . ' ' . forja_arg($src);
    $r = forja_exec($cmd);
    if ($r['rc'] !== 0 || !is_file($out) || filesize($out) < 1024) { @unlink($out); return null; }
    return $out;
}

/**
 * Comprime um PDF.
 *
 * @param string $src    caminho do PDF de origem
 * @param string $nivel  alta | recomendado | forte | extrema (aceita os nomes antigos)
 * @param array  $opc    ['cinza' => 'auto'|'sim'|'nao']
 * @param array  $info   (por referência) detalhes do que foi feito
 * @return string caminho do PDF resultante (em forja/saida)
 */
function forja_comprimir_pdf($src, $nivel = 'recomendado', $opc = [], &$info = null)
{
    if (!forja_gs_bin()) throw new RuntimeException('Ghostscript não encontrado. Configure o caminho em "Configurar".');

    $nivel   = forja_normaliza_nivel($nivel);
    $perfis  = forja_perfis_compressao();
    $perfil  = $perfis[$nivel];
    $orig    = (int)filesize($src);
    forja_prog(5, 'Analisando o PDF…');
    $paginas = forja_pdf_num_paginas($src);

    $info = [
        'nivel' => $nivel, 'rotulo' => $perfil['rotulo'], 'orig' => $orig, 'paginas' => $paginas,
        'cinza' => false, 'cinza_auto' => false, 'tentativas' => 0, 'dpi' => null, 'qualidade' => null,
        'estrategia' => '', 'calibrado' => false, 'ja_otimizado' => false, 'avisos' => [],
    ];

    /* ---- PDF vetorial/texto: reamostrar não adianta, só otimização estrutural ---- */
    if (!forja_pdf_tem_imagens($src)) {
        $info['estrategia'] = 'estrutural';
        $info['tentativas'] = 1;
        forja_prog(40, 'PDF de texto — otimizando a estrutura…');
        $o = forja_gs_estrutural($src);
        $fim = forja_finalizar_compressao($src, $o, $orig, $info);
        forja_prog(100, 'Concluído');
        return $fim;
    }
    $info['estrategia'] = 'imagem';

    /* ---- Arquivos grandes: calibra a escada numa amostra e só depois processa tudo ---- */
    $amostra = null;
    if ($paginas > 6 && ($orig > 25 * 1024 * 1024 || $paginas > 40)) {
        forja_prog(12, 'Arquivo grande — calibrando numa amostra…');
        $amostra = forja_pdf_amostra($src, 4);
    }
    $ref     = $amostra ?: $src;
    $refTam  = (int)filesize($ref);
    $info['calibrado'] = (bool)$amostra;

    /* ---- Tons de cinza: automático (páginas neutras), forçado ou desligado ---- */
    $modoCinza = strtolower((string)($opc['cinza'] ?? 'auto'));
    $cinza = false; $cinzaAuto = false;
    if ($modoCinza === 'sim') $cinza = true;
    elseif ($modoCinza !== 'nao') { $cinza = $cinzaAuto = forja_pdf_neutro($ref); }

    /* ---- Escada progressiva sobre a referência ---- */
    $melhor = null; $melhorTam = $refTam; $passoOk = null;
    $nPassos = max(1, count($perfil['passos']));
    foreach ($perfil['passos'] as $i => $p) {
        forja_prog(18 + 55 * $i / $nPassos, 'Comprimindo — tentativa ' . ($i + 1) . ' de ' . $nPassos . ' (' . $p[0] . ' dpi)…');
        $o = forja_gs_tentativa($ref, $p[0], $p[1], $p[2], $cinza);
        $info['tentativas'] = $i + 1;
        if (!$o) continue;
        $tam = filesize($o);
        if ($tam < $melhorTam) {
            if ($melhor) @unlink($melhor);
            $melhor = $o; $melhorTam = $tam; $passoOk = $p;
        } else { @unlink($o); }
        if ($melhorTam <= $refTam * (1 - $perfil['meta'])) break;
    }

    /* ---- Último recurso: tons de cinza (só no modo automático) ---- */
    if (!$cinza && $modoCinza === 'auto' && $melhorTam > $refTam * 0.90) {
        $p = end($perfil['passos']);
        $o = forja_gs_tentativa($ref, $p[0], $p[1], $p[2], true);
        if ($o) {
            if (filesize($o) < $melhorTam * 0.92) {
                if ($melhor) @unlink($melhor);
                $melhor = $o; $melhorTam = filesize($o); $passoOk = $p;
                $cinza = $cinzaAuto = true;
                $info['avisos'][] = 'Convertido para tons de cinza: era a única forma de reduzir o arquivo sem perder nitidez.';
            } else { @unlink($o); }
        }
    }

    /* ---- Nada funcionou na escada: tenta o ganho estrutural ---- */
    if (!$passoOk) {
        if ($melhor) { @unlink($melhor); $melhor = null; }
        $o = forja_gs_estrutural($ref);
        if ($o) { if (filesize($o) < $refTam) { $melhor = $o; $info['estrategia'] = 'estrutural'; } else @unlink($o); }
    }

    $info['cinza'] = $cinza && $info['estrategia'] === 'imagem';
    $info['cinza_auto'] = $cinzaAuto && $info['cinza'];
    if ($passoOk) { $info['dpi'] = $passoOk[0]; $info['qualidade'] = $passoOk[1]; }

    /* ---- Se trabalhamos numa amostra, aplica agora o passo escolhido no arquivo inteiro ---- */
    if ($amostra) {
        forja_prog(80, 'Aplicando o melhor ajuste no arquivo inteiro…');
        if ($melhor) @unlink($melhor);
        @unlink($amostra);
        $melhor = $passoOk
            ? forja_gs_tentativa($src, $passoOk[0], $passoOk[1], $passoOk[2], $info['cinza'])
            : forja_gs_estrutural($src);
    }

    forja_prog(95, 'Finalizando…');
    $fim = forja_finalizar_compressao($src, $melhor, $orig, $info);
    forja_prog(100, 'Concluído');
    return $fim;
}

/** Move o melhor resultado para forja/saida — e nunca devolve arquivo maior que o original. */
function forja_finalizar_compressao($src, $candidato, $orig, &$info)
{
    if (!$candidato || !is_file($candidato) || filesize($candidato) >= $orig) {
        if ($candidato) @unlink($candidato);
        $info['ja_otimizado'] = true;
        $info['dpi'] = null; $info['qualidade'] = null; $info['cinza'] = false;
        $candidato = forja_tmp_registrar(forja_dir_tmp() . '/cmp_' . bin2hex(random_bytes(5)) . '.pdf');
        if (!@copy($src, $candidato)) throw new RuntimeException('Falha ao preparar o arquivo de saída.');
    }
    $final = forja_dir_out() . '/comprimido_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
    if (!@rename($candidato, $final)) { @copy($candidato, $final); @unlink($candidato); }
    if (!is_file($final) || filesize($final) < 100) throw new RuntimeException('Falha ao comprimir o PDF.');

    $info['novo']    = filesize($final);
    $info['reducao'] = $orig > 0 ? round((1 - $info['novo'] / $orig) * 100, 1) : 0;
    return $final;
}

/** Renderiza uma página como JPEG (usado na prévia de qualidade). */
function forja_pdf_previa_jpeg($pdf, $pagina = 1, $dpi = 110)
{
    $gs = forja_gs_bin();
    if (!$gs) throw new RuntimeException('Ghostscript não configurado.');
    $pagina = max(1, (int)$pagina);
    $out = forja_tmp_registrar(forja_dir_tmp() . '/prev_' . bin2hex(random_bytes(5)) . '.jpg');
    $cmd = forja_arg($gs) . ' -sDEVICE=jpeg -dJPEGQ=92 -dNOPAUSE -dBATCH -dQUIET -dSAFER'
         . ' -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -r' . (int)$dpi
         . ' -dFirstPage=' . $pagina . ' -dLastPage=' . $pagina
         . ' -sOutputFile=' . forja_arg($out) . ' ' . forja_arg($pdf);
    $r = forja_exec($cmd);
    if ($r['rc'] !== 0 || !is_file($out) || filesize($out) < 100) { @unlink($out); throw new RuntimeException('Não foi possível gerar a prévia.'); }
    return $out;
}

/**
 * Aspas seguras para argumentos de linha de comando.
 *
 * IMPORTANTE: no Windows o escapeshellarg() do PHP substitui '%' por espaço
 * (proteção contra expansão de variáveis do cmd.exe). Isso destrói padrões como
 * "pagina-%03d.png", que viram "pagina- 03d.png" — e o Ghostscript, sem o %d,
 * grava apenas a 1ª página e aborta nas demais. Por isso, para caminhos gerados
 * internamente (que nunca contêm aspas), montamos as aspas manualmente.
 */
function forja_arg($s)
{
    if (!forja_is_win()) return escapeshellarg($s);
    return '"' . str_replace('"', '', $s) . '"';
}

/** Lista apenas as imagens com nome válido (pagina-001.png, pagina-002.png, ...). */
function forja_imgs_validas($dir, $formato)
{
    $out = [];
    foreach ((array)glob($dir . '/pagina-*.' . $formato) as $f) {
        if (preg_match('~^pagina-\d+\.' . preg_quote($formato, '~') . '$~i', basename($f))) $out[] = $f;
    }
    natsort($out);
    return array_values($out);
}

/** Remove tudo que estiver na pasta de imagens (usado antes do fallback). */
function forja_limpar_imgs($dir, $formato)
{
    foreach ((array)glob($dir . '/pagina-*.' . $formato) as $f) @unlink($f);
}

/** Quantidade de páginas do PDF. Retorna 0 se não for possível determinar. */
function forja_pdf_num_paginas($src)
{
    $tam = (int)@filesize($src);

    /* 1) FPDI — rápido, sem processo externo. Acima de 200 MB o parser fica caro
          demais: vai direto para o Ghostscript, que lê o arquivo em streaming. */
    if ($tam > 0 && $tam <= 200 * 1048576) {
      try {
        forja_load_libs();
        $cls = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
        $t = new $cls();
        $n = (int)$t->setSourceFile($src);
        if ($n > 0) return $n;
      } catch (Throwable $e) { /* PDF 1.5+ com object streams: tenta o Ghostscript */ }
    }

    /* 2) Ghostscript. */
    if ($gs = forja_gs_bin()) {
        $ps  = str_replace('\\', '/', $src);
        $cmd = forja_arg($gs) . ' -q -dNODISPLAY -dNOSAFER -dBATCH -c '
             . forja_arg('(' . $ps . ') (r) file runpdfbegin pdfpagecount = quit');
        $r = forja_exec($cmd);
        if (preg_match('~(\d+)~', $r['out'], $m) && (int)$m[1] > 0) return (int)$m[1];
    }

    /* 3) Heurística no conteúdo bruto, lida em blocos de 1 MB — nunca carrega o
          arquivo inteiro na memória (um PDF de 2 GB derrubaria o PHP). */
    if ($fp = @fopen($src, 'rb')) {
        $n = 0; $cauda = '';
        while (!feof($fp)) {
            $buf   = $cauda . (string)fread($fp, 1048576);
            $corte = strlen($cauda);             /* o que veio antes já foi contado */
            $m = [];
            if (preg_match_all('~/Type\s*/Page\b~', $buf, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) if ($hit[1] + strlen($hit[0]) > $corte) $n++;
            }
            $cauda = substr($buf, -32);          /* evita cortar o padrão na borda */
        }
        fclose($fp);
        if ($n > 0) return $n;
    }

    return 0;
}

function forja_pdf_para_imagens($src, $formato = 'png', $dpi = 150)
{
    $dpi     = max(72, min(400, (int)$dpi));
    $formato = $formato === 'jpg' ? 'jpg' : 'png';
    $dir     = forja_dir_out() . '/imgs_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
    @mkdir($dir, 0775, true);
    $pattern = $dir . '/pagina-%03d.' . $formato;

    $total = forja_pdf_num_paginas($src);   /* 0 = desconhecido */
    $erro  = '';
    /* "dir/total" faz o progresso.php contar os arquivos já renderizados. */
    forja_prog(8, $total > 0 ? 'Renderizando ' . $total . ' página(s)…' : 'Renderizando páginas…',
        $total > 0 ? ['dir' => $dir, 'formato' => $formato, 'total' => $total, 'base' => 8, 'teto' => 88] : []);

    $gs = forja_gs_bin();
    if ($gs) {
        $device = $formato === 'jpg' ? 'jpeg' : 'png16m';
        $extra  = $formato === 'jpg' ? ' -dJPEGQ=90' : '';
        $comuns = ' -dNOPAUSE -dQUIET -dBATCH -dTextAlphaBits=4 -dGraphicsAlphaBits=4';

        /* Tentativa 1: uma única chamada com o padrão %03d. */
        $cmd = forja_arg($gs) . ' -sDEVICE=' . $device . ' -r' . $dpi . $extra . $comuns
             . ' -sOutputFile=' . forja_arg($pattern) . ' ' . forja_arg($src);
        $r = forja_exec($cmd);
        $erro = $r['out'];

        $feitas = count(forja_imgs_validas($dir, $formato));
        $faltou = $total > 0 ? ($feitas < $total) : ($feitas === 0);

        /* Tentativa 2: renderiza página a página. Sempre confiável, pois não
           depende do padrão %d sobreviver ao shell. */
        if ($faltou) {
            forja_limpar_imgs($dir, $formato);
            $limite = $total > 0 ? $total : 2000;
            for ($p = 1; $p <= $limite; $p++) {
                forja_prog($total > 0 ? 8 + 80 * ($p - 1) / $total : 20,
                    'Renderizando página ' . $p . ($total > 0 ? ' de ' . $total : '') . '…',
                    $total > 0 ? ['dir' => $dir, 'formato' => $formato, 'total' => $total, 'base' => 8, 'teto' => 88] : []);
                $alvo = $dir . '/pagina-' . sprintf('%03d', $p) . '.' . $formato;
                $cmd  = forja_arg($gs) . ' -sDEVICE=' . $device . ' -r' . $dpi . $extra . $comuns
                      . ' -dFirstPage=' . $p . ' -dLastPage=' . $p
                      . ' -sOutputFile=' . forja_arg($alvo) . ' ' . forja_arg($src);
                $r = forja_exec($cmd);
                if ($r['rc'] !== 0 || !is_file($alvo) || filesize($alvo) < 100) { @unlink($alvo); break; }
            }
        }
    }

    /* Fallback: ImageMagick (também com aspas seguras + numeração a partir de 1). */
    if (!forja_imgs_validas($dir, $formato) && ($mk = forja_magick_bin())) {
        forja_limpar_imgs($dir, $formato);
        $cmd = forja_arg($mk) . ' -density ' . $dpi . ' ' . forja_arg($src)
             . ($formato === 'jpg' ? ' -quality 90' : '') . ' -scene 1 ' . forja_arg($pattern);
        $r = forja_exec($cmd);
        if (!$erro) $erro = $r['out'];
    }

    $files = forja_imgs_validas($dir, $formato);
    if (!$files) throw new RuntimeException('Nenhuma imagem gerada. Verifique se o Ghostscript/ImageMagick está configurado. ' . mb_substr($erro, 0, 300));

    forja_prog(92, 'Compactando as imagens…');
    $zip = forja_dir_out() . '/imagens_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.zip';
    $za  = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');
    foreach ($files as $f) $za->addFile($f, basename($f));
    $za->close();
    forja_rrmdir($dir);          /* o ZIP já tem tudo — libera o espaço na hora */

    forja_prog(100, 'Concluído');
    return ['zip' => $zip, 'paginas' => count($files)];
}

/* =====================================================================
 * IMAGENS → PDF  (motor seguro)
 * ---------------------------------------------------------------------
 * Causa clássica do HTTP 500 aqui: o TCPDF, ao receber a imagem original
 * de um celular/scanner (12–50 MP) com $resize=true, descomprime tudo em
 * memória via GD (largura × altura × 4 bytes, mais as cópias internas).
 * Uma foto de 4000×3000 já custa ~48 MB só para abrir; três delas estouram
 * o memory_limit. O "Allowed memory size exhausted" é um erro FATAL: não é
 * pego por try/catch, o script morre e o Apache devolve 500 com corpo vazio.
 *
 * Solução: reduzir/normalizar cada imagem ANTES do TCPDF (com controle de
 * memória e liberação imediata), achatar transparência de PNG (evita o
 * caminho ImagePngAlpha, que exige K_PATH_CACHE gravável) e corrigir a
 * orientação EXIF das fotos. O tamanho físico da página continua sendo
 * calculado pelas dimensões ORIGINAIS — só a resolução interna cai.
 * ===================================================================== */

/** Log simples do módulo (forja/tmp/forja_erros.log). */
function forja_log($msg)
{
    @file_put_contents(forja_dir_tmp() . '/forja_erros.log',
        date('Y-m-d H:i:s') . ' | ' . str_replace(["\r", "\n"], ' ', $msg) . "\r\n", FILE_APPEND);
}

/** Traduz mensagens de erro fatal do PHP para algo acionável em pt-BR. */
function forja_msg_fatal($msg)
{
    if (stripos($msg, 'Allowed memory size') !== false || stripos($msg, 'memory_limit') !== false)
        return 'A conversão esgotou a memória do PHP. Aumente memory_limit no php.ini (sugerido: 1024M), reinicie o Apache e tente novamente — ou envie menos imagens por vez.';
    if (stripos($msg, 'Maximum execution time') !== false)
        return 'O tempo máximo de execução do PHP foi atingido. Aumente max_execution_time no php.ini e reinicie o Apache.';
    if (stripos($msg, 'imagecreatefrom') !== false || stripos($msg, 'undefined function image') !== false)
        return 'A extensão GD do PHP não está habilitada. Ative extension=gd no php.ini do XAMPP e reinicie o Apache.';
    if (stripos($msg, 'TCPDF') !== false)
        return 'Falha na biblioteca TCPDF ao montar o PDF: ' . $msg;
    return 'Erro interno durante a conversão: ' . $msg;
}

/** Converte "512M", "1G", "-1" em bytes. */
function forja_ini_bytes($v)
{
    $v = trim((string)$v);
    if ($v === '')   return 0;
    if ($v === '-1') return -1;
    $u = strtolower(substr($v, -1));
    $n = (float)$v;
    if     ($u === 'g') $n *= 1073741824;
    elseif ($u === 'm') $n *= 1048576;
    elseif ($u === 'k') $n *= 1024;
    return (int)$n;
}

/** Tenta garantir pelo menos $mb MB de memory_limit. Devolve true se conseguiu. */
function forja_memoria_minima($mb)
{
    $atual = forja_ini_bytes(@ini_get('memory_limit'));
    if ($atual === -1) return true;
    $alvo = (int)$mb * 1048576;
    if ($atual >= $alvo) return true;
    @ini_set('memory_limit', (int)$mb . 'M');
    $novo = forja_ini_bytes(@ini_get('memory_limit'));
    return ($novo === -1 || $novo >= $alvo);
}

/** Cor de tipo PNG (4 e 6 = com canal alfa; 3 = paleta, pode ter tRNS). */
function forja_png_tem_alfa($f)
{
    $fh = @fopen($f, 'rb');
    if (!$fh) return false;
    $h = fread($fh, 26);
    fclose($fh);
    if (strlen($h) < 26) return false;
    $ct = ord($h[25]);
    return in_array($ct, [3, 4, 6], true);
}

/**
 * Reduz/normaliza a imagem com o ImageMagick. Usado quando o GD não cabe na
 * memória (imagens muito grandes). O "-define jpeg:size" com o tamanho ALVO faz
 * a libjpeg decodificar já em escala reduzida (DCT scaling) — medido aqui, a
 * diferença é de 1,3 s contra 10 s quando o valor é maior que o necessário.
 */
function forja_img_reduzir_magick($src, $maxLado, $destino, $ehJpeg = false)
{
    $mk = forja_magick_bin();
    if (!$mk) return false;
    $hint = $ehJpeg ? ' -define ' . forja_arg('jpeg:size=' . (int)$maxLado . 'x' . (int)$maxLado) : '';
    $cmd  = forja_arg($mk) . $hint . ' ' . forja_arg($src)
          . ' -auto-orient -background white -flatten'
          . ' -thumbnail ' . forja_arg((int)$maxLado . 'x' . (int)$maxLado . '>')
          . ' -strip -quality 88 ' . forja_arg($destino);
    $r = forja_exec($cmd);
    clearstatcache();
    return ($r['rc'] === 0 && is_file($destino) && filesize($destino) > 100);
}

/**
 * Reduz/normaliza com o GD. Devolve o caminho gerado ou null.
 * Reduções até 2× usam imagescale (3× mais rápido que imagecopyresampled e
 * visualmente equivalente); reduções maiores usam a reamostragem completa,
 * que preserva melhor texto fino.
 */
function forja_img2pdf_max_px()
{
    $cfg = forja_config();
    $v = (int)($cfg['img2pdf_max_px'] ?? 2600);
    return ($v < 600) ? 2600 : min(6000, $v);
}

function forja_img_reduzir_gd($src, $tipo, $w, $h, $rot, $maxLado, $bits, $ehPng)
{
    if ($w < 1 || $h < 1 || $tipo < 1) {
        $i = @getimagesize($src);
        if (!$i) return null;
        $w = (int)$i[0]; $h = (int)$i[1]; $tipo = (int)$i[2];
    }

    $leitores = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
    ];
    if (defined('IMAGETYPE_WEBP')) $leitores[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    if (defined('IMAGETYPE_BMP'))  $leitores[IMAGETYPE_BMP]  = 'imagecreatefrombmp';
    $fn = $leitores[$tipo] ?? null;
    if (!$fn || !function_exists($fn)) return null;

    $im = @$fn($src);
    if (!$im) return null;

    if ($rot !== 0) {
        $r = @imagerotate($im, $rot, imagecolorallocate($im, 255, 255, 255));
        if ($r) { imagedestroy($im); $im = $r; $w = imagesx($im); $h = imagesy($im); }
    }

    $escala = 1.0;
    if (max($w, $h) > $maxLado) $escala = $maxLado / max($w, $h);
    $nw = max(1, (int)round($w * $escala));
    $nh = max(1, (int)round($h * $escala));

    $red = null;
    if ($escala >= 0.5 && function_exists('imagescale')) {
        $red = @imagescale($im, $nw, $nh, IMG_BILINEAR_FIXED);
    }
    if (!$red) {
        $red = @imagecreatetruecolor($nw, $nh);
        if (!$red) { imagedestroy($im); return null; }
        @imagecopyresampled($red, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    }
    imagedestroy($im);
    $im = null;

    /* Achata transparência sobre branco — já no tamanho final, custo mínimo. */
    $dst = @imagecreatetruecolor($nw, $nh);
    if (!$dst) { imagedestroy($red); return null; }
    imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocate($dst, 255, 255, 255));
    imagecopy($dst, $red, 0, 0, 0, 0, $nw, $nh);
    imagedestroy($red);

    /* Digitalização em traço (1–4 bits) continua em PNG; o resto vira JPEG. */
    $comoPng = ($ehPng && $escala >= 1.0 && (int)$bits <= 4);
    $tmp = forja_dir_tmp() . '/prep_' . bin2hex(random_bytes(6)) . ($comoPng ? '.png' : '.jpg');
    $ok  = $comoPng ? @imagepng($dst, $tmp, 6) : @imagejpeg($dst, $tmp, 88);
    imagedestroy($dst);
    if (function_exists('gc_collect_cycles')) gc_collect_cycles();

    clearstatcache();
    if (!$ok || !is_file($tmp)) { @unlink($tmp); return null; }
    return ['path' => forja_tmp_registrar($tmp), 'w' => $w, 'h' => $h];
}

/**
 * Normaliza a imagem para virar página de PDF.
 * Devolve ['path','w','h','temp'] onde w/h são as dimensões ORIGINAIS
 * (já corrigidas pela orientação EXIF) — usadas para o tamanho da página.
 * Devolve null se o arquivo não for imagem legível.
 */
function forja_img_preparar($src, $nome = '', $maxLado = 0)
{
    if ($nome === '') $nome = basename($src);
    if ($maxLado <= 0) $maxLado = forja_img2pdf_max_px();

    $info = @getimagesize($src);
    if (!$info || empty($info[0]) || empty($info[1])) return null;

    $w = (int)$info[0]; $h = (int)$info[1]; $tipo = (int)$info[2];
    $mime = $info['mime'] ?? '';
    $mp   = ($w * $h) / 1048576;

    $ehPng  = ($tipo === IMAGETYPE_PNG);
    $ehJpeg = ($tipo === IMAGETYPE_JPEG);
    $rot    = 0;

    /* Orientação EXIF (fotos de celular chegam "deitadas"). */
    if ($ehJpeg && function_exists('exif_read_data')) {
        $ex = @exif_read_data($src);
        $o  = isset($ex['Orientation']) ? (int)$ex['Orientation'] : 1;
        if ($o === 3) $rot = 180; elseif ($o === 6) $rot = -90; elseif ($o === 8) $rot = 90;
    }

    /* Tolerância de 15%: não compensa reprocessar uma imagem só um pouco maior. */
    $precisaEscala = (max($w, $h) > $maxLado * 1.15);
    $precisaAlfa   = ($ehPng && forja_png_tem_alfa($src));

    /* Caminho rápido: nada a fazer, usa o arquivo original (sem perda, custo zero). */
    if (!$precisaEscala && !$precisaAlfa && $rot === 0 && ($ehJpeg || $ehPng)) {
        return ['path' => $src, 'w' => $w, 'h' => $h, 'temp' => false];
    }

    /* GD é o caminho preferido: sem processo externo, ~3× mais rápido que o
       ImageMagick nas medições. Só cede a vez quando a imagem não cabe na memória. */
    $temGd  = function_exists('imagecreatetruecolor');
    $precisa = (int)($w * $h * 4 * 2.3) + 16777216;
    $cabeGd = $temGd && forja_memoria_minima((int)ceil((memory_get_usage(true) + $precisa) / 1048576) + 96);

    if ($cabeGd) {
        $r = forja_img_reduzir_gd($src, $tipo, $w, $h, $rot, $maxLado, $info['bits'] ?? 8, $ehPng);
        if ($r) return ['path' => $r['path'], 'w' => $r['w'], 'h' => $r['h'], 'temp' => true];
    }

    /* Sem memória (ou o GD falhou): ImageMagick, que não usa memória do PHP. */
    $tmpJpg = forja_dir_tmp() . '/prep_' . bin2hex(random_bytes(6)) . '.jpg';
    if (forja_img_reduzir_magick($src, $maxLado, $tmpJpg, $ehJpeg)) {
        forja_tmp_registrar($tmpJpg);
        $ni = @getimagesize($tmpJpg);
        if ($ni && $ni[0] > 0 && $ni[1] > 0) {
            /* -auto-orient pode ter girado: mantém a proporção física correta. */
            if (($w > $h) !== ($ni[0] > $ni[1])) { $t = $w; $w = $h; $h = $t; }
            return ['path' => $tmpJpg, 'w' => $w, 'h' => $h, 'temp' => true];
        }
    }
    @unlink($tmpJpg);

    if (!$temGd)
        throw new RuntimeException('A extensão GD do PHP não está habilitada (necessária para tratar imagens). Ative extension=gd no php.ini e reinicie o Apache.');
    if (!$cabeGd)
        throw new RuntimeException('Imagem "' . $nome . '" (' . round($mp, 1)
            . ' MP) exige mais memória do que o PHP permite. Aumente memory_limit no php.ini (ex.: 1024M) e reinicie o Apache, ou configure o ImageMagick em "Configurar".');
    throw new RuntimeException('Não foi possível preparar a imagem "' . $nome . '" (formato ' . ($mime ?: 'desconhecido') . ' ou arquivo corrompido).');
}

/* =====================================================================
 * MOTOR RÁPIDO: imagens → PDF sem TCPDF
 * ---------------------------------------------------------------------
 * O TCPDF é lento aqui porque carrega fontes, mantém o documento inteiro
 * em memória e reprocessa cada imagem. Para "imagem em página" nada disso
 * é necessário: o PDF só precisa de um XObject por página apontando para
 * os bytes que já estão no arquivo.
 *   - JPEG  → /DCTDecode  (os bytes do .jpg entram no PDF sem recompressão)
 *   - PNG   → /FlateDecode com /Predictor 15 (o IDAT entra como está)
 * O arquivo é gravado em streaming, então o consumo de memória é constante
 * mesmo com 200 imagens.
 * ===================================================================== */

/** Lê o cabeçalho do JPEG: dimensões, componentes e se é baseline. */
function forja_jpeg_scan($f)
{
    $fh = @fopen($f, 'rb');
    if (!$fh) return null;
    if (fread($fh, 2) !== "\xFF\xD8") { fclose($fh); return null; }
    $r = null;
    while (!feof($fh)) {
        $b = fread($fh, 1);
        if ($b === '' || $b === false) break;
        if ($b !== "\xFF") continue;
        do { $m = fread($fh, 1); } while ($m === "\xFF");
        if ($m === '' || $m === false) break;
        $m = ord($m);
        if ($m === 0xD8 || $m === 0x01 || ($m >= 0xD0 && $m <= 0xD7)) continue;
        if ($m === 0xD9 || $m === 0xDA) break;              /* SOS: fim do cabeçalho */
        $l = fread($fh, 2);
        if (strlen($l) < 2) break;
        $l = unpack('n', $l)[1];
        if ($l < 2) break;
        if (in_array($m, [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF], true)) {
            $d = fread($fh, 6);
            if (strlen($d) < 6) break;
            $p = unpack('Cprec/nh/nw/Ccomp', $d);
            $r = ['w' => $p['w'], 'h' => $p['h'], 'comp' => $p['comp'], 'prec' => $p['prec'],
                  'baseline' => in_array($m, [0xC0, 0xC1], true)];
            break;
        }
        fseek($fh, $l - 2, SEEK_CUR);
    }
    fclose($fh);
    return $r;
}

/** Lê um PNG simples (sem entrelaçamento, sem alfa) para embutir direto. */
function forja_png_scan($f)
{
    $d = @file_get_contents($f);
    if ($d === false || substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;
    $len = strlen($d); $pos = 8;
    $idat = ''; $plte = ''; $w = $h = $bd = $ct = 0; $inter = 1; $trns = false;
    while ($pos + 8 <= $len) {
        $n    = unpack('N', substr($d, $pos, 4))[1];
        $tipo = substr($d, $pos + 4, 4);
        if ($n < 0 || $pos + 12 + $n > $len) break;
        $body = substr($d, $pos + 8, $n);
        $pos += 12 + $n;
        if ($tipo === 'IHDR') {
            $v = unpack('Nw/Nh/Cbd/Cct/Ccomp/Cfilt/Cinter', $body);
            $w = $v['w']; $h = $v['h']; $bd = $v['bd']; $ct = $v['ct']; $inter = $v['inter'];
        } elseif ($tipo === 'PLTE') { $plte .= $body; }
        elseif ($tipo === 'IDAT')   { $idat .= $body; }
        elseif ($tipo === 'tRNS')   { $trns = true; }
        elseif ($tipo === 'IEND')   { break; }
    }
    if ($inter !== 0 || $trns || $bd > 8 || $idat === '' || !in_array($ct, [0, 2, 3], true)) return null;
    if ($ct === 3 && $plte === '') return null;
    return ['w' => $w, 'h' => $h, 'bd' => $bd, 'ct' => $ct, 'idat' => $idat, 'plte' => $plte,
            'cores' => ($ct === 2 ? 3 : 1)];
}

/** Descreve a imagem para o motor rápido, ou null se o formato não servir. */
function forja_pdf_img_fonte($path)
{
    $t = @exif_imagetype($path);
    if ($t === IMAGETYPE_JPEG) {
        $j = forja_jpeg_scan($path);
        /* Progressivo e CMYK não são seguros em /DCTDecode: caem para o TCPDF. */
        if (!$j || !$j['baseline'] || $j['prec'] != 8 || !in_array($j['comp'], [1, 3], true)) return null;
        return ['modo' => 'jpg', 'w' => $j['w'], 'h' => $j['h'], 'bpc' => 8,
                'cs' => ($j['comp'] === 1 ? '/DeviceGray' : '/DeviceRGB'),
                'filtro' => '/DCTDecode', 'arquivo' => $path, 'tam' => (int)filesize($path)];
    }
    if ($t === IMAGETYPE_PNG) {
        $p = forja_png_scan($path);
        if (!$p) return null;
        return ['modo' => 'png', 'w' => $p['w'], 'h' => $p['h'], 'bpc' => $p['bd'],
                'ct' => $p['ct'], 'plte' => $p['plte'], 'cores' => $p['cores'],
                'filtro' => '/FlateDecode', 'dados' => $p['idat'], 'tam' => strlen($p['idat'])];
    }
    return null;
}

/** Tamanho da página (em pontos) para a imagem. */
function forja_pdf_pagina_pt($w, $h, $modo)
{
    if ($modo === 'a4') {
        $pw = ($w > $h) ? 841.89 : 595.28;
        $ph = ($w > $h) ? 595.28 : 841.89;
        $m  = 22.68;                                  /* 8 mm */
        $r  = min(($pw - 2 * $m) / $w, ($ph - 2 * $m) / $h);
        $dw = $w * $r; $dh = $h * $r;
        return [$pw, $ph, $dw, $dh, ($pw - $dw) / 2, ($ph - $dh) / 2];
    }
    $pw = $w * 72.0 / 96; $ph = $h * 72.0 / 96;       /* mesma regra de 96 dpi */
    if ($pw > 14000 || $ph > 14000) {                 /* limite do formato PDF */
        $k = min(14000 / $pw, 14000 / $ph); $pw *= $k; $ph *= $k;
    }
    return [$pw, $ph, $pw, $ph, 0, 0];
}

/**
 * Grava o PDF em streaming. Devolve o número de páginas, ou 0 se alguma
 * imagem não for compatível (nesse caso o chamador usa o TCPDF).
 */
function forja_pdf_imagens_rapido($fontes, $modo, $out)
{
    if (!$fontes) return 0;

    $fh = @fopen($out, 'wb');
    if (!$fh) throw new RuntimeException('Não foi possível gravar em forja/saida (verifique permissões).');

    $offs = [];
    $esc = function ($num, $dic, $stream = null, $arquivo = null) use ($fh, &$offs) {
        $offs[$num] = ftell($fh);
        fwrite($fh, $num . " 0 obj\n" . $dic);
        if ($stream !== null || $arquivo !== null) {
            fwrite($fh, "\nstream\n");
            if ($arquivo !== null) {
                $in = fopen($arquivo, 'rb');
                if ($in) { while (!feof($in)) { $c = fread($in, 1048576); if ($c === '' || $c === false) break; fwrite($fh, $c); } fclose($in); }
            } else { fwrite($fh, $stream); }
            fwrite($fh, "\nendstream");
        }
        fwrite($fh, "\nendobj\n");
    };

    fwrite($fh, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");

    $prox = 3; $kids = [];                            /* 1 = Catalog, 2 = Pages */
    $total = count($fontes); $i = 0;
    foreach ($fontes as $f) {
        $i++;
        forja_prog(70 + 25 * $i / max(1, $total), 'Gravando página ' . $i . ' de ' . $total . '…');
        list($pw, $ph, $dw, $dh, $x, $y) = forja_pdf_pagina_pt($f['pw'], $f['ph'], $modo);

        $cnt = sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /I0 Do Q\n", $dw, $dh, $x, $y);
        $cid = $prox++;
        $esc($cid, '<< /Length ' . strlen($cnt) . ' >>', $cnt);

        $palId = 0;
        if ($f['modo'] === 'png' && $f['ct'] === 3) {
            $palId = $prox++;
            $esc($palId, '<< /Length ' . strlen($f['plte']) . ' >>', $f['plte']);
        }

        $dic = '<< /Type /XObject /Subtype /Image /Width ' . $f['w'] . ' /Height ' . $f['h']
             . ' /BitsPerComponent ' . $f['bpc'];
        if ($f['modo'] === 'png') {
            $dic .= ($f['ct'] === 3)
                ? ' /ColorSpace [/Indexed /DeviceRGB ' . (intdiv(strlen($f['plte']), 3) - 1) . ' ' . $palId . ' 0 R]'
                : ' /ColorSpace ' . ($f['ct'] === 2 ? '/DeviceRGB' : '/DeviceGray');
            $dic .= ' /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors ' . $f['cores']
                  . ' /BitsPerComponent ' . $f['bpc'] . ' /Columns ' . $f['w'] . ' >>';
        } else {
            $dic .= ' /ColorSpace ' . $f['cs'] . ' /Filter /DCTDecode';
        }
        $dic .= ' /Length ' . $f['tam'] . ' >>';

        $xid = $prox++;
        if ($f['modo'] === 'png') $esc($xid, $dic, $f['dados']);
        else                      $esc($xid, $dic, null, $f['arquivo']);

        $pid = $prox++;
        $esc($pid, sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
            . '/Resources << /XObject << /I0 %d 0 R >> >> /Contents %d 0 R >>', $pw, $ph, $xid, $cid));
        $kids[] = $pid;
    }

    $infoId = $prox++;
    $esc($infoId, '<< /Producer (Atlas Forja) /CreationDate (D:' . date('YmdHis') . ') >>');

    $refs = [];
    foreach ($kids as $k) $refs[] = $k . ' 0 R';
    $esc(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $esc(2, '<< /Type /Pages /Count ' . count($kids) . ' /Kids [' . implode(' ', $refs) . '] >>');

    $maxObj = $prox - 1;
    $xref = ftell($fh);
    fwrite($fh, "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n");
    for ($n = 1; $n <= $maxObj; $n++) fwrite($fh, sprintf("%010d 00000 n \n", (int)($offs[$n] ?? 0)));
    fwrite($fh, "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R /Info " . $infoId . " 0 R >>\n"
              . "startxref\n" . $xref . "\n%%EOF\n");
    fclose($fh);

    clearstatcache();
    return count($kids);
}

/** Caminho antigo (TCPDF) — usado quando alguma imagem não serve ao motor rápido. */
function forja_pdf_imagens_tcpdf($itens, $modo, $out)
{
    forja_load_libs();
    forja_memoria_minima(768);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false); $pdf->SetMargins(0, 0, 0);
    $pdf->SetCreator('Atlas Forja');
    $pdf->SetCompression(true);

    $qtd = 0; $total = count($itens);
    foreach ($itens as $i => $it) {
        forja_prog(70 + 25 * ($i + 1) / max(1, $total), 'Gravando página ' . ($i + 1) . ' de ' . $total . '…');
        $wpx = $it['w']; $hpx = $it['h'];
        if ($modo === 'a4') {
            $pdf->AddPage($wpx > $hpx ? 'L' : 'P', 'A4');
            $pw = $pdf->getPageWidth(); $ph = $pdf->getPageHeight(); $m = 8;
            $ratio = min(($pw - 2 * $m) / $wpx, ($ph - 2 * $m) / $hpx);
            $w = $wpx * $ratio; $h = $hpx * $ratio;
            $pdf->Image($it['path'], ($pw - $w) / 2, ($ph - $h) / 2, $w, $h, '', '', '', false, 300);
        } else {
            $mmW = $wpx * 25.4 / 96; $mmH = $hpx * 25.4 / 96;
            if ($mmW > 5000 || $mmH > 5000) { $k = min(5000 / $mmW, 5000 / $mmH); $mmW *= $k; $mmH *= $k; }
            $pdf->AddPage($mmW > $mmH ? 'L' : 'P', [$mmW, $mmH]);
            $pdf->Image($it['path'], 0, 0, $mmW, $mmH, '', '', '', false, 96);
        }
        $qtd++;
        if (function_exists('gc_collect_cycles')) gc_collect_cycles();
    }
    $pdf->Output($out, 'F');
    $pdf = null;
    clearstatcache();
    return $qtd;
}

function forja_imagens_para_pdf($imagens, $modo = 'imagem')
{
    $itens = []; $ignoradas = [];
    $totalImgs = max(1, count($imagens)); $idxImg = 0;

    foreach ($imagens as $item) {
        $img  = is_array($item) ? ($item['path'] ?? '') : $item;
        $nome = is_array($item) ? ($item['nome'] ?? basename($img)) : basename($img);
        $idxImg++;
        forja_prog(3 + 65 * ($idxImg - 1) / $totalImgs, 'Preparando imagem ' . $idxImg . ' de ' . $totalImgs . '…');

        if ($img === '' || !is_file($img)) { $ignoradas[] = $nome . ' — arquivo não encontrado.'; continue; }
        try {
            $p = forja_img_preparar($img, $nome);
        } catch (Throwable $e) {
            $ignoradas[] = $nome . ' — ' . $e->getMessage();
            forja_log('IMG2PDF preparar: ' . $e->getMessage());
            continue;
        }
        if (!$p) { $ignoradas[] = $nome . ' — não é uma imagem válida (PNG/JPG).'; continue; }
        $itens[] = $p;
    }

    if (!$itens) throw new RuntimeException('Nenhuma imagem válida (use PNG ou JPG).'
        . ($ignoradas ? ' Detalhes: ' . implode(' | ', $ignoradas) : ''));

    forja_prog(70, 'Montando o PDF…');
    $out = forja_dir_out() . '/imagens_para_pdf_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';

    /* Formatos que o PDF não embute direto (JPEG progressivo/CMYK, PNG
       entrelaçado ou com tRNS) são reconvertidos uma única vez para JPEG
       baseline — evita cair no caminho lento do TCPDF. */
    $fontes = []; $completo = true;
    foreach ($itens as $it) {
        $f = forja_pdf_img_fonte($it['path']);
        if (!$f) {
            $re = forja_img_reduzir_gd($it['path'], @exif_imagetype($it['path']) ?: 0,
                    0, 0, 0, forja_img2pdf_max_px(), 8, false);
            if ($re) $f = forja_pdf_img_fonte($re['path']);
        }
        if (!$f) { $completo = false; break; }
        $f['pw'] = $it['w']; $f['ph'] = $it['h'];   /* dimensões físicas da página */
        $fontes[] = $f;
    }

    $motor = 'rapido';
    $qtd = 0;
    if ($completo) {
        try {
            $qtd = forja_pdf_imagens_rapido($fontes, $modo, $out);
        } catch (Throwable $e) {
            forja_log('IMG2PDF motor rápido: ' . $e->getMessage());
            $qtd = 0;
        }
    }
    if ($qtd === 0) {                       /* último recurso: TCPDF */
        @unlink($out);
        $motor = 'tcpdf';
        $qtd = forja_pdf_imagens_tcpdf($itens, $modo, $out);
    }

    if (!is_file($out) || filesize($out) < 100) throw new RuntimeException('O PDF não pôde ser gravado em forja/saida (verifique permissões).');
    forja_prog(100, 'Concluído');

    return [
        'path'      => $out,
        'paginas'   => $qtd,
        'motor'     => $motor,
        'ignoradas' => $ignoradas,
        'pico_mb'   => round(memory_get_peak_usage(true) / 1048576, 1),
    ];
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
    $out = forja_tmp_registrar(forja_dir_tmp() . '/norm_' . bin2hex(random_bytes(5)) . '.pdf');
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
    $nArq = max(1, count($pdfs)); $iArq = 0;
    foreach ($pdfs as $p) {
        $iArq++;
        forja_prog(3 + 85 * ($iArq - 1) / $nArq, 'Arquivo ' . $iArq . ' de ' . $nArq . '…');
        $p = forja_pdf_compativel_fpdi($p);
        $n = $pdf->setSourceFile($p);
        for ($i = 1; $i <= $n; $i++) {
            if ($n > 4 && $i % 5 === 0) forja_prog(3 + 85 * (($iArq - 1) + $i / $n) / $nArq,
                'Arquivo ' . $iArq . '/' . $nArq . ' — página ' . $i . ' de ' . $n . '…');
            $tpl = $pdf->importPage($i); $s = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($s['orientation'], [$s['width'], $s['height']]);
            $pdf->useTemplate($tpl, 0, 0, $s['width'], $s['height'], true);
            $total++;
        }
    }
    if ($total === 0) throw new RuntimeException('Nenhuma página encontrada nos PDFs enviados.');
    forja_prog(92, 'Gravando o PDF final…');
    $out = forja_dir_out() . '/juntados_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
    $pdf->Output($out, 'F');
    forja_prog(100, 'Concluído');
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

    $usados = []; $n = 0; $nB = max(1, count($bItens));
    foreach ($bItens as $b) {
        forja_prog(5 + 85 * $n / $nB, 'Documento ' . ($n + 1) . ' de ' . $nB . '…');
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
    forja_prog(95, 'Compactando…');
    $za->close();
    forja_rrmdir($dir);          /* o ZIP já tem tudo — libera o espaço na hora */
    forja_prog(100, 'Concluído');
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
    $arqs = []; $p = 1; $nPartes = max(1, count($ranges));
    foreach ($ranges as $r) {
        forja_prog(8 + 78 * ($p - 1) / $nPartes, 'Parte ' . $p . ' de ' . $nPartes . '…');
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

    forja_prog(90, 'Compactando as partes…');
    $zip = forja_dir_out() . '/partes_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.zip';
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');
    foreach ($arqs as $a) $za->addFile($a, basename($a));
    $za->close();
    forja_rrmdir($dir);          /* o ZIP já tem tudo — libera o espaço na hora */

    forja_prog(100, 'Concluído');
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
    $lim    = forja_limites_php();
    $LIMITE = $lim['efetivo'];
    $livre  = forja_disco_livre();
    $saved = [];
    foreach ($names as $i => $nm) {
        $err = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
            throw new RuntimeException('O arquivo "' . $nm . '" passou do upload_max_filesize do PHP ('
                . forja_human(forja_ini_bytes(@ini_get('upload_max_filesize')))
                . '). Ajuste upload_max_filesize e post_max_size no php.ini do XAMPP e reinicie o Apache.');
        if ($err !== UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($tmps[$i])) continue;
        $tam = (int)($sizes[$i] ?? 0);
        if ($tam > $LIMITE) throw new RuntimeException('O arquivo "' . $nm . '" tem ' . forja_human($tam)
            . ' e o limite atual é ' . forja_human($LIMITE)
            . ($lim['efetivo'] < $lim['config']
                ? ' (imposto pelo php.ini: upload_max_filesize=' . forja_human($lim['upload'])
                  . ', post_max_size=' . forja_human($lim['post']) . ').'
                : '.'));
        /* Compressão/conversão precisa de espaço para as cópias intermediárias. */
        if ($livre > 0 && $tam > 100 * 1048576 && $livre < $tam * 2.5)
            throw new RuntimeException('Espaço insuficiente em disco: o arquivo tem ' . forja_human($tam)
                . ' e restam ' . forja_human($livre) . ' livres. O processamento precisa de cerca de '
                . forja_human((int)($tam * 2.5)) . '.');
        if ($word) {
            $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
            if (!in_array($ext, ['docx', 'doc', 'odt', 'rtf', 'txt'], true)) throw new RuntimeException('Envie um Word (.docx/.doc), ODT, RTF ou TXT (' . $nm . ').');
        } else {
            $mime = $fi->file($tmps[$i]) ?: '';
            if ($somentePdf && $mime !== 'application/pdf') throw new RuntimeException('Apenas PDF é aceito aqui (' . $nm . ').');
            if ($somenteImg && strpos($mime, 'image/') !== 0) throw new RuntimeException('Apenas imagens PNG/JPG são aceitas (' . $nm . ').');
            $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpg' : 'bin'));
        }
        $path = forja_tmp_registrar(forja_dir_tmp() . '/up_' . bin2hex(random_bytes(6)) . '.' . $ext);
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
    forja_tmp_manter($path);            /* nunca apagar um arquivo que virou download */
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
