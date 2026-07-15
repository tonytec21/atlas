<?php
/**
 * config_assinatura.php — Núcleo do Atlas Signum (Assinatura Eletrônica)
 * Assina PDFs (PAdES / CAdES-detached) com certificado A1 configurável (.pfx),
 * aplicando um carimbo visível personalizável (logomarca + dados do assinante).
 * Usa TCPDF + FPDI (reaproveitados de ../oficios/).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
date_default_timezone_set('America/Fortaleza');

/* ============================ Conexão ============================ */
function asg_db()
{
    static $c = null;
    if ($c instanceof mysqli && @$c->ping()) return $c;
    $c = new mysqli('localhost', 'root', '', 'atlas');
    if ($c->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $c->set_charset('utf8mb4');
    return $c;
}

/* ============================ Caminhos ============================ */
function asg_base()      { return __DIR__; }
function asg_dir_tmp()   { return asg_ensure(__DIR__ . '/uploads_tmp'); }
function asg_dir_sig()   { return asg_ensure(__DIR__ . '/assinados'); }
function asg_dir_cert()  { return asg_ensure_protegido(__DIR__ . '/certificado'); }
function asg_dir_logo()  { return asg_ensure(__DIR__ . '/logo'); }
function asg_ensure($d)  { if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function asg_ensure_protegido($d)
{
    asg_ensure($d);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $d;
}

/* ============================ Bibliotecas (TCPDF/FPDI) ============================ */
function asg_load_libs()
{
    static $ok = false;
    if ($ok) return;
    // TCPDF
    $tcpdf = null;
    foreach (['/../oficios/tcpdf/tcpdf.php', '/tcpdf/tcpdf.php', '/../oficios/vendor/tecnickcom/tcpdf/tcpdf.php'] as $c)
        if (is_file(__DIR__ . $c)) { $tcpdf = __DIR__ . $c; break; }
    if (!$tcpdf && is_file(__DIR__ . '/../oficios/vendor/autoload.php')) { require_once __DIR__ . '/../oficios/vendor/autoload.php'; }
    elseif ($tcpdf) { require_once $tcpdf; }
    else throw new RuntimeException('TCPDF não encontrado. Esperado em ../oficios/tcpdf/tcpdf.php');
    // FPDI
    if (!class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
        $cands = ['/../oficios/src/autoload.php', '/../oficios/src/src/autoload.php',
                  '/../oficios/fpdi/src/autoload.php', '/fpdi/src/autoload.php', '/../oficios/vendor/autoload.php'];
        $fpdi = null;
        foreach ($cands as $c) if (is_file(__DIR__ . $c)) { $fpdi = __DIR__ . $c; break; }
        if (!$fpdi) { // procura um autoload.php ao lado de um Fpdi.php dentro de ../oficios
            foreach ((array)@glob(__DIR__ . '/../oficios/*/autoload.php') as $g) if (is_file($g)) { $fpdi = $g; break; }
            if (!$fpdi) foreach ((array)@glob(__DIR__ . '/../oficios/*/src/autoload.php') as $g) if (is_file($g)) { $fpdi = $g; break; }
        }
        if ($fpdi) require_once $fpdi;
        else throw new RuntimeException('FPDI não encontrado. Esperado em ../oficios/src/autoload.php');
    }
    $ok = true;
}

/* ============================ Schema ============================ */
function asg_ensure_schema()
{
    $conn = asg_db();
    $conn->query("CREATE TABLE IF NOT EXISTS assinatura_documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        hash_sha256 CHAR(64) NULL,
        codigo VARCHAR(16) NULL,
        titular VARCHAR(255) NULL,
        metodo VARCHAR(4) NULL,
        assinado_por VARCHAR(120) NULL,
        assinado_em DATETIME NULL,
        tamanho INT NULL,
        paginas INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'ativo',
        INDEX idx_status (status), INDEX idx_data (assinado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // garante coluna metodo em bases antigas
    $rc = $conn->query("SHOW COLUMNS FROM assinatura_documentos LIKE 'metodo'");
    if ($rc && $rc->num_rows === 0) { try { $conn->query("ALTER TABLE assinatura_documentos ADD COLUMN metodo VARCHAR(4) NULL AFTER titular"); } catch (Throwable $e) {} }

    // Configuração GLOBAL (aparência do carimbo do cartório)
    $conn->query("CREATE TABLE IF NOT EXISTS assinatura_config (
        id INT PRIMARY KEY,
        logo_arquivo VARCHAR(255) NULL,
        carimbo_titulo VARCHAR(120) NULL,
        motivo VARCHAR(160) NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("INSERT IGNORE INTO assinatura_config (id, carimbo_titulo, motivo) VALUES (1, 'Assinado digitalmente', 'Assinatura eletrônica de documento')");

    // Configuração POR USUÁRIO (método + certificado próprio)
    $conn->query("CREATE TABLE IF NOT EXISTS assinatura_config_usuario (
        usuario VARCHAR(120) PRIMARY KEY,
        metodo VARCHAR(4) NOT NULL DEFAULT 'a3',
        cert_arquivo VARCHAR(255) NULL,
        cert_senha_enc TEXT NULL,
        a3_agente_url VARCHAR(255) NULL,
        assinante_nome VARCHAR(160) NULL,
        assinante_cargo VARCHAR(160) NULL,
        assinante_local VARCHAR(160) NULL,
        usar_cn_titular TINYINT(1) NOT NULL DEFAULT 1,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rc = $conn->query("SHOW COLUMNS FROM assinatura_config_usuario LIKE 'assinante_cpf'");
    if ($rc && $rc->num_rows === 0) { try { $conn->query("ALTER TABLE assinatura_config_usuario ADD COLUMN assinante_cpf VARCHAR(20) NULL AFTER assinante_nome"); } catch (Throwable $e) {} }
}

/* ============================ CSRF ============================ */
function asg_csrf()
{
    if (empty($_SESSION['asg_csrf'])) $_SESSION['asg_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['asg_csrf'];
}
function asg_csrf_check($t) { return is_string($t) && !empty($_SESSION['asg_csrf']) && hash_equals($_SESSION['asg_csrf'], $t); }

/* ============================ Config GLOBAL (carimbo do cartório) ============================ */
function asg_config()
{
    asg_ensure_schema();
    $r = asg_db()->query("SELECT * FROM assinatura_config WHERE id=1 LIMIT 1");
    return $r ? $r->fetch_assoc() : [];
}
function asg_config_set($campos)
{
    asg_ensure_schema();
    $conn = asg_db();
    $sets = []; $vals = []; $types = '';
    foreach ($campos as $k => $v) { $sets[] = "`$k`=?"; $vals[] = $v; $types .= 's'; }
    $sets[] = "atualizado_em=?"; $vals[] = date('Y-m-d H:i:s'); $types .= 's';
    $st = $conn->prepare("UPDATE assinatura_config SET " . implode(',', $sets) . " WHERE id=1");
    $st->bind_param($types, ...$vals); $st->execute(); $st->close();
}

/* ============================ Config POR USUÁRIO ============================ */
function asg_ucfg($usuario)
{
    asg_ensure_schema();
    $conn = asg_db();
    $st = $conn->prepare("SELECT * FROM assinatura_config_usuario WHERE usuario=? LIMIT 1");
    $st->bind_param('s', $usuario); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) {
        $ins = $conn->prepare("INSERT INTO assinatura_config_usuario (usuario, metodo, usar_cn_titular) VALUES (?, 'a3', 1)");
        $ins->bind_param('s', $usuario); $ins->execute(); $ins->close();
        return ['usuario' => $usuario, 'metodo' => 'a3', 'cert_arquivo' => null, 'cert_senha_enc' => null,
                'a3_agente_url' => null, 'assinante_nome' => '', 'assinante_cpf' => '', 'assinante_cargo' => '', 'assinante_local' => '', 'usar_cn_titular' => 1];
    }
    return $row;
}
function asg_ucfg_set($usuario, $campos)
{
    asg_ucfg($usuario); // garante existência
    $conn = asg_db();
    $sets = []; $vals = []; $types = '';
    foreach ($campos as $k => $v) { $sets[] = "`$k`=?"; $vals[] = $v; $types .= 's'; }
    $sets[] = "atualizado_em=?"; $vals[] = date('Y-m-d H:i:s'); $types .= 's';
    $vals[] = $usuario; $types .= 's';
    $st = $conn->prepare("UPDATE assinatura_config_usuario SET " . implode(',', $sets) . " WHERE usuario=?");
    $st->bind_param($types, ...$vals); $st->execute(); $st->close();
}

/* ============================ Senha do certificado (AES-256-GCM) ============================ */
function asg_key()
{
    $f = asg_dir_cert() . '/.masterkey';
    if (!is_file($f)) @file_put_contents($f, base64_encode(random_bytes(32)));
    return base64_decode(trim(file_get_contents($f)));
}
function asg_enc($plain)
{
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', asg_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function asg_dec($enc)
{
    $raw = base64_decode($enc); if ($raw === false || strlen($raw) < 28) return '';
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
    $p = openssl_decrypt($ct, 'aes-256-gcm', asg_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $p === false ? '' : $p;
}

/* ============================ Certificado A1 (por usuário) ============================ */
function asg_salvar_certificado($usuario, $pfxTmpPath, $senha)
{
    $bin = file_get_contents($pfxTmpPath);
    if ($bin === false || $bin === '') throw new RuntimeException('Arquivo do certificado vazio.');
    $certs = [];
    if (!openssl_pkcs12_read($bin, $certs, $senha)) throw new RuntimeException('Não foi possível abrir o certificado — verifique a senha e se é um .pfx/.p12 válido.');
    if (empty($certs['pkey'])) throw new RuntimeException('O certificado não contém chave privada.');
    $nome = 'cert_' . preg_replace('~[^A-Za-z0-9]~', '', $usuario) . '_' . date('YmdHis') . '.pfx';
    file_put_contents(asg_dir_cert() . '/' . $nome, $bin);
    asg_ucfg_set($usuario, ['cert_arquivo' => $nome, 'cert_senha_enc' => asg_enc($senha)]);
    return asg_cert_info($usuario);
}
/** Lê o A1 do usuário -> ['cert','pkey',...] ou null. */
function asg_cert_load($usuario)
{
    $u = asg_ucfg($usuario);
    if (empty($u['cert_arquivo'])) return null;
    $path = asg_dir_cert() . '/' . basename($u['cert_arquivo']);
    if (!is_file($path)) return null;
    $senha = asg_dec($u['cert_senha_enc'] ?? '');
    $certs = [];
    if (!openssl_pkcs12_read(file_get_contents($path), $certs, $senha)) return null;
    return $certs;
}
/** Info amigável do A1 do usuário. */
function asg_cert_info($usuario)
{
    $certs = asg_cert_load($usuario);
    if (!$certs) return null;
    $x = openssl_x509_parse($certs['cert']);
    return [
        'cn'      => $x['subject']['CN'] ?? '(sem CN)',
        'emissor' => $x['issuer']['O'] ?? ($x['issuer']['CN'] ?? '?'),
        'de'      => date('d/m/Y', $x['validFrom_time_t'] ?? time()),
        'ate'     => date('d/m/Y', $x['validTo_time_t'] ?? time()),
        'expirado'=> (($x['validTo_time_t'] ?? 0) < time()),
    ];
}

/** Formata CPF (11 dígitos) → 000.000.000-00. Devolve como veio se não for CPF. */
function asg_cpf_fmt($cpf)
{
    $d = preg_replace('~\D~', '', (string)$cpf);
    if (strlen($d) !== 11) return trim((string)$cpf);
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

/**
 * Extrai NOME e CPF do titular a partir do certificado ICP-Brasil.
 * - Nome: CN (removendo ":CPF" se presente).
 * - CPF: do CN "NOME:CPF" ou do SubjectAltName otherName (OID 2.16.76.1.3.1 = dados e-CPF).
 * @param string|array $certPemOrParsed  PEM do certificado, ou já um array do openssl_x509_parse
 * @return array ['nome'=>..., 'cpf'=>...] (cpf pode vir vazio)
 */
function asg_cert_pessoa($certPemOrParsed)
{
    $nome = ''; $cpf = '';
    $x = is_array($certPemOrParsed) ? $certPemOrParsed : @openssl_x509_parse($certPemOrParsed);
    if (!$x) return ['nome' => $nome, 'cpf' => $cpf];

    $cn = $x['subject']['CN'] ?? '';
    if ($cn !== '') {
        if (preg_match('~^(.*?):(\d{11})\b~', $cn, $m)) { $nome = trim($m[1]); $cpf = $m[2]; }
        else $nome = trim($cn);
    }
    // Fallback: SAN otherName com os dados do e-CPF (nascimento[8]+CPF[11]+...)
    if ($cpf === '' && !empty($x['extensions']['subjectAltName'])) {
        if (preg_match_all('~\d{19,}~', $x['extensions']['subjectAltName'], $mm)) {
            foreach ($mm[0] as $blob) {
                // após 8 dígitos de data de nascimento, os 11 seguintes são o CPF
                if (strlen($blob) >= 19) { $cand = substr($blob, 8, 11); if (ctype_digit($cand)) { $cpf = $cand; break; } }
            }
        }
    }
    return ['nome' => $nome, 'cpf' => $cpf];
}

/* ============================ Certificado DUMMY (placeholder p/ A3) ============================ */
/** Gera (uma vez) um par autoassinado usado só para reservar o espaço da assinatura. */
function asg_dummy_cert()
{
    $dir = asg_dir_cert();
    $crt = $dir . '/pades_dummy.crt';
    $key = $dir . '/pades_dummy.key';
    if (is_file($crt) && is_file($key)) return ['cert' => 'file://' . $crt, 'key' => 'file://' . $key];
    // reaproveita o do ../oficios se existir
    foreach (['/../oficios/pades_dummy.crt' => '/../oficios/pades_dummy.key'] as $c => $k) {
        if (is_file(__DIR__ . $c) && is_file(__DIR__ . $k)) return ['cert' => 'file://' . __DIR__ . $c, 'key' => 'file://' . __DIR__ . $k];
    }
    $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'ATLAS PADES DUMMY', 'organizationName' => 'Atlas'], $pk, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $pk, 3650, ['digest_alg' => 'sha256']);
    openssl_x509_export($x509, $certOut);
    openssl_pkey_export($pk, $keyOut);
    file_put_contents($crt, $certOut);
    file_put_contents($key, $keyOut);
    return ['cert' => 'file://' . $crt, 'key' => 'file://' . $key];
}

/* ============================ Logo ============================ */
function asg_salvar_logo($tmpPath, $origName)
{
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) throw new RuntimeException('Use uma imagem PNG ou JPG para a logomarca.');
    $nome = 'logo.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    // limpa logos antigas
    foreach (glob(asg_dir_logo() . '/logo.*') as $f) @unlink($f);
    if (!move_uploaded_file($tmpPath, asg_dir_logo() . '/' . $nome)) {
        if (!@copy($tmpPath, asg_dir_logo() . '/' . $nome)) throw new RuntimeException('Falha ao salvar a logomarca.');
    }
    asg_config_set(['logo_arquivo' => $nome]);
    return $nome;
}
function asg_logo_path()
{
    $cfg = asg_config();
    if (empty($cfg['logo_arquivo'])) return null;
    $p = asg_dir_logo() . '/' . basename($cfg['logo_arquivo']);
    return is_file($p) ? $p : null;
}

/* ============================ Assinatura ============================ */

/** Constrói o PDF com o carimbo e assina com o "signer" informado (A1 real ou dummy).
 *  Retorna o caminho do PDF gerado; preenche $meta com nPag, codigo e coords do carimbo. */
function asg_gerar_pdf($src, $pos, $titular, $signer, &$meta)
{
    asg_load_libs();
    $cfg = asg_config();
    $titulo = $cfg['carimbo_titulo'] ?: 'Assinado digitalmente';
    $motivo = $cfg['motivo'] ?: 'Assinatura eletrônica de documento';
    $logo = asg_logo_path();

    $Fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
    $pdf = new $Fpdi();
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(false);
    $pdf->SetCreator('Atlas Signum'); $pdf->SetAuthor($titular);

    $nPag = $pdf->setSourceFile($src);
    $alvo = max(1, min($nPag, (int)($pos['pagina'] ?? $nPag)));
    $codigo = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $sigX = $sigY = $sigW = $sigH = 0;
    $local = $meta['local'] ?? '';
    $cargo = $meta['cargo'] ?? '';
    $cpf = $meta['cpf'] ?? '';

    for ($i = 1; $i <= $nPag; $i++) {
        $tpl = $pdf->importPage($i);
        $s = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($s['orientation'], [$s['width'], $s['height']]);
        $pdf->useTemplate($tpl, 0, 0, $s['width'], $s['height'], true);
        if ($i === $alvo) {
            $W = $s['width']; $H = $s['height'];
            $cw = max(52, min(90, $W * (float)($pos['w'] ?? 0.30)));
            $ch = $cw * 0.40;
            $cx = isset($pos['x']) ? ($pos['x'] * $W) : ($W - $cw - 10);
            $cy = isset($pos['y']) ? ($pos['y'] * $H) : ($H - $ch - 10);
            $cx = max(2, min($W - $cw - 2, $cx)); $cy = max(2, min($H - $ch - 2, $cy));
            asg_desenhar_carimbo($pdf, $cx, $cy, $cw, $ch, $logo, $titulo, $titular, $cargo, $local, $codigo, $cpf);
            $sigX = $cx; $sigY = $cy; $sigW = $cw; $sigH = $ch;
        }
    }

    $sigInfo = ['Name' => $titular, 'Location' => $local, 'Reason' => $motivo, 'ContactInfo' => ''];
    if ($signer !== null) {
        $pdf->setSignature($signer['cert'], $signer['key'], $signer['pass'] ?? '', '', 2, $sigInfo);
        if ($sigW > 0) $pdf->setSignatureAppearance($sigX, $sigY, $sigW, $sigH);
    }

    $out = asg_dir_tmp() . '/gen_' . bin2hex(random_bytes(6)) . '.pdf';
    $pdf->Output($out, 'F');
    $meta['nPag'] = $nPag; $meta['codigo'] = $codigo;
    return $out;
}

/** Registra o documento assinado no banco. */
function asg_registrar($origName, $outPath, $titular, $metodo, $usuario, $codigo, $nPag)
{
    $bin = file_get_contents($outPath);
    $hash = hash('sha256', $bin);
    $conn = asg_db();
    $tam = strlen($bin); $agora = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO assinatura_documentos
        (nome_original, arquivo, hash_sha256, codigo, titular, metodo, assinado_por, assinado_em, tamanho, paginas, status)
        VALUES (?,?,?,?,?,?,?,?,?,?, 'ativo')");
    $arq = basename($outPath);
    $st->bind_param('ssssssssii', $origName, $arq, $hash, $codigo, $titular, $metodo, $usuario, $agora, $tam, $nPag);
    $st->execute(); $id = $st->insert_id; $st->close();
    return ['id' => $id, 'arquivo' => $arq, 'nome_original' => $origName, 'codigo' => $codigo,
            'hash' => $hash, 'titular' => $titular, 'metodo' => $metodo, 'assinado_em' => $agora, 'tamanho' => $tam, 'paginas' => $nPag];
}

/* -------- A1: assinatura direta (chave no arquivo do usuário) -------- */
function asg_assinar_a1($usuario, $src, $origName, $pos)
{
    asg_ensure_schema();
    $certs = asg_cert_load($usuario);
    if (!$certs) throw new RuntimeException('Configure um certificado A1 válido (menu Configurar).');
    $u = asg_ucfg($usuario); $info = asg_cert_info($usuario);
    $pessoa = asg_cert_pessoa($certs['cert']);           // nome + CPF do próprio certificado
    $titular = (!empty($u['usar_cn_titular']) && $pessoa['nome']) ? $pessoa['nome']
             : ($u['assinante_nome'] ?: ($pessoa['nome'] ?: ($info['cn'] ?? '')));
    $cpf = $pessoa['cpf'] ?: ($u['assinante_cpf'] ?? '');
    $meta = ['local' => $u['assinante_local'] ?? '', 'cargo' => $u['assinante_cargo'] ?? '', 'cpf' => $cpf];
    $signer = ['cert' => $certs['cert'], 'key' => $certs['pkey'], 'pass' => ''];
    $out = asg_gerar_pdf($src, $pos, $titular, $signer, $meta);
    $final = asg_dir_sig() . '/' . asg_nome_saida($origName);
    rename($out, $final);
    return asg_registrar($origName, $final, $titular, 'a1', $usuario, $meta['codigo'], $meta['nPag']);
}

/* -------- A3: assinatura diferida (token assina o hash) -------- */
/** Passo 1: prepara o PDF carimbado com placeholder (cert dummy) e devolve os bytes a assinar. */
function asg_preparar_a3($usuario, $src, $origName, $pos, $titular)
{
    asg_ensure_schema();
    $u = asg_ucfg($usuario);
    if ($titular === '') $titular = $u['assinante_nome'] ?: 'Assinante';
    $meta = ['local' => $u['assinante_local'] ?? '', 'cargo' => $u['assinante_cargo'] ?? ''];
    $out = asg_gerar_pdf($src, $pos, $titular, asg_dummy_cert(), $meta);

    $pdf = file_get_contents($out);
    if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m))
        throw new RuntimeException('Falha ao preparar a assinatura (ByteRange).');
    [, $a, $b, $c, $d] = $m;
    $toSign = substr($pdf, (int)$a, (int)$b) . substr($pdf, (int)$c, (int)$d);
    $phStart = (int)$b + 1;                 // logo após "<"
    $phLen   = ((int)$c - (int)$b) - 2;     // espaço hex entre < e >

    $sign_token = bin2hex(random_bytes(12));
    $state = ['pdf' => $out, 'phStart' => $phStart, 'phLen' => $phLen,
              'titular' => $titular, 'codigo' => $meta['codigo'], 'nPag' => $meta['nPag'],
              'orig' => $origName, 'usuario' => $usuario, 'em' => time()];
    file_put_contents(asg_dir_tmp() . '/sig_' . $sign_token . '.json', json_encode($state));

    return ['sign_token' => $sign_token, 'to_sign_b64' => base64_encode($toSign),
            'hash_sha256' => hash('sha256', $toSign)];
}
/** Passo 2: injeta o CMS (DER) devolvido pelo token e salva o documento assinado. */
function asg_finalizar_a3($usuario, $sign_token, $cmsDer)
{
    $sf = asg_dir_tmp() . '/sig_' . preg_replace('~[^a-f0-9]~', '', $sign_token) . '.json';
    if (!is_file($sf)) throw new RuntimeException('Sessão de assinatura expirada. Refaça.');
    $st = json_decode(file_get_contents($sf), true);
    if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Sessão inválida.');
    if (!is_file($st['pdf'])) throw new RuntimeException('PDF preparado não encontrado.');

    $hex = strtoupper(bin2hex($cmsDer));
    if (strlen($hex) > $st['phLen'])
        throw new RuntimeException('A assinatura do token é maior que o espaço reservado. Contate o suporte.');
    $hex = str_pad($hex, $st['phLen'], '0');

    $pdf = file_get_contents($st['pdf']);
    $pdf = substr($pdf, 0, $st['phStart']) . $hex . substr($pdf, $st['phStart'] + $st['phLen']);

    $final = asg_dir_sig() . '/' . asg_nome_saida($st['orig']);
    file_put_contents($final, $pdf);
    @unlink($st['pdf']); @unlink($sf);

    return asg_registrar($st['orig'], $final, $st['titular'], 'a3', $usuario, $st['codigo'], $st['nPag']);
}

/* -------- A3 via Assinador SERPRO (o assinador assina o PDF inteiro) -------- */
/** Monta o PDF só com o carimbo (sem assinatura) e devolve caminho + metadados,
 *  para o Assinador SERPRO assinar no cliente (PBAD-PAdES). */
function asg_montar_para_serpro($usuario, $src, $origName, $pos, $titular)
{
    asg_ensure_schema();
    $u = asg_ucfg($usuario);
    if ($titular === '') $titular = $u['assinante_nome'] ?: 'Assinante';
    $meta = ['local' => $u['assinante_local'] ?? '', 'cargo' => $u['assinante_cargo'] ?? ''];
    $out = asg_gerar_pdf($src, $pos, $titular, null, $meta);   // signer null = só carimbo

    $prep_token = bin2hex(random_bytes(12));
    $state = ['pdf' => $out, 'titular' => $titular, 'codigo' => $meta['codigo'],
              'nPag' => $meta['nPag'], 'orig' => $origName, 'usuario' => $usuario, 'em' => time()];
    file_put_contents(asg_dir_tmp() . '/prep_' . $prep_token . '.json', json_encode($state));
    return ['prep_token' => $prep_token, 'pdf_b64' => base64_encode(file_get_contents($out)),
            'nome' => $origName];
}
/** Recebe o PDF já assinado pelo Assinador SERPRO e registra. */
function asg_salvar_assinado_serpro($usuario, $prep_token, $pdfAssinado)
{
    $pf = asg_dir_tmp() . '/prep_' . preg_replace('~[^a-f0-9]~', '', $prep_token) . '.json';
    if (!is_file($pf)) throw new RuntimeException('Sessão de assinatura expirada. Refaça.');
    $st = json_decode(file_get_contents($pf), true);
    if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Sessão inválida.');
    if (strncmp($pdfAssinado, '%PDF', 4) !== 0) throw new RuntimeException('Retorno do assinador não é um PDF válido.');
    if (strpos($pdfAssinado, 'ByteRange') === false)
        throw new RuntimeException('O PDF retornado não contém assinatura. Verifique o Assinador SERPRO.');

    $final = asg_dir_sig() . '/' . asg_nome_saida($st['orig']);
    file_put_contents($final, $pdfAssinado);
    @unlink($st['pdf']); @unlink($pf);
    return asg_registrar($st['orig'], $final, $st['titular'], 'a3', $usuario, $st['codigo'], $st['nPag']);
}

/** Nome do arquivo de saída assinado. */
function asg_nome_saida($origName)
{
    $safe = preg_replace('~[^A-Za-z0-9_\-\.]~', '_', pathinfo($origName, PATHINFO_FILENAME));
    return $safe . '_assinado_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.pdf';
}

/* ============================ PAdES / Injeção do CMS do Assinador SERPRO ============================ */
if (!class_exists('AtlasPadesInjector')) {
    final class AtlasPadesInjector
    {
        public static function readByteRange($pdf)
        {
            if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m))
                throw new RuntimeException('/ByteRange não encontrado.');
            $a = (int)$m[1]; $len1 = (int)$m[2]; $b = (int)$m[3]; $len2 = (int)$m[4];
            $digest = hash('sha256', substr($pdf, $a, $len1) . substr($pdf, $b, $len2), true);
            return ['a' => $a, 'len1' => $len1, 'b' => $b, 'len2' => $len2,
                    'digest' => $digest, 'holeStart' => $a + $len1, 'holeEnd' => $b];
        }
        public static function inject($pdf, $holeStart, $holeEnd, $cmsDer)
        {
            if ($pdf[$holeStart] !== '<' || $pdf[$holeEnd - 1] !== '>')
                throw new RuntimeException('Delimitadores do /Contents não conferem.');
            $hexLen = ($holeEnd - 1) - ($holeStart + 1);
            $hex = bin2hex($cmsDer);
            if (strlen($hex) > $hexLen) throw new RuntimeException('CMS maior que o placeholder.');
            $hex = str_pad($hex, $hexLen, '0');
            return substr($pdf, 0, $holeStart + 1) . $hex . substr($pdf, $holeEnd - 1);
        }
        public static function toEtsiCades($pdf)
        {
            return str_replace('/SubFilter /adbe.pkcs7.detached', '/SubFilter /ETSI.CAdES.detached', $pdf);
        }
    }
}
function asg_pades_message_digest($der)
{
    $pat = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04";
    $len = strlen($der); $plen = strlen($pat); $from = 0;
    while (($i = strpos($der, $pat, $from)) !== false) {
        for ($m = $i + $plen; $m < $i + $plen + 8 && $m + 2 + 32 <= $len; $m++)
            if ($der[$m] === "\x04" && $der[$m + 1] === "\x20") return substr($der, $m + 2, 32);
        $from = $i + $plen;
    }
    return null;
}

/** A3/SERPRO — Fase 1: carimba o PDF enviado, cria placeholder (dummy),
 *  troca p/ ETSI.CAdES.detached e devolve o digest do ByteRange (base64). */
function asg_preparar_serpro($usuario, $src, $origName, $pos, $titular, $cpfOverride = '')
{
    asg_load_libs();
    require_once __DIR__ . '/fpdi_sig.php';
    asg_ensure_schema();
    $u = asg_ucfg($usuario);
    if ($titular === '') $titular = $u['assinante_nome'] ?: 'Assinante';
    $cfg = asg_config();
    $titulo = $cfg['carimbo_titulo'] ?: 'Assinado digitalmente';
    $logo = asg_logo_path();
    $cargo = $u['assinante_cargo'] ?? ''; $local = $u['assinante_local'] ?? '';
    $cpf = ($cpfOverride !== '') ? $cpfOverride : ($u['assinante_cpf'] ?? '');

    $pdf = new AtlasFpdiSig('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(false);
    $pdf->SetCreator('Atlas Signum'); $pdf->SetAuthor($titular);

    $nPag = $pdf->setSourceFile($src);
    $alvo = max(1, min($nPag, (int)($pos['pagina'] ?? $nPag)));
    $codigo = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    for ($i = 1; $i <= $nPag; $i++) {
        $tpl = $pdf->importPage($i); $s = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($s['orientation'], [$s['width'], $s['height']]);
        $pdf->useTemplate($tpl, 0, 0, $s['width'], $s['height'], true);
        if ($i === $alvo) {
            $W = $s['width']; $H = $s['height'];
            $cw = max(52, min(90, $W * (float)($pos['w'] ?? 0.30))); $ch = $cw * 0.40;
            $cx = isset($pos['x']) ? ($pos['x'] * $W) : ($W - $cw - 10);
            $cy = isset($pos['y']) ? ($pos['y'] * $H) : ($H - $ch - 10);
            $cx = max(2, min($W - $cw - 2, $cx)); $cy = max(2, min($H - $ch - 2, $cy));
            asg_desenhar_carimbo($pdf, $cx, $cy, $cw, $ch, $logo, $titulo, $titular, $cargo, $local, $codigo, $cpf);
        }
    }
    $pdf->setSigMaxLength(16000);
    $pdf->setSignatureAppearance(0, 0, 0, 0, 1);
    $dummy = asg_dummy_cert();
    $pdf->setSignature($dummy['cert'], $dummy['key'], '', '', 2, [], false);
    $prepared = $pdf->Output('prep.pdf', 'S');

    $prepared = AtlasPadesInjector::toEtsiCades($prepared);
    $br = AtlasPadesInjector::readByteRange($prepared);

    $session = bin2hex(random_bytes(16));
    $ppath = asg_dir_tmp() . '/prep_' . $session . '.pdf';
    file_put_contents($ppath, $prepared);
    $meta = ['orig' => $origName, 'prepared' => basename($ppath),
             'holeStart' => $br['holeStart'], 'holeEnd' => $br['holeEnd'],
             'brDigestHex' => bin2hex($br['digest']), 'codigo' => $codigo,
             'titular' => $titular, 'nPag' => $nPag, 'usuario' => $usuario, 'em' => time()];
    file_put_contents(asg_dir_tmp() . '/sess_' . $session . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE));

    return ['session' => $session, 'to_sign' => base64_encode($br['digest']), 'codigo' => $codigo];
}

/** A3/SERPRO — Fase 2: injeta o CMS devolvido e salva. */
function asg_finalizar_serpro($usuario, $session, $cmsB64, $certSubject = '')
{
    $session = preg_replace('~[^0-9a-f]~', '', (string)$session);
    $sessFile = asg_dir_tmp() . '/sess_' . $session . '.json';
    if (!is_file($sessFile)) throw new RuntimeException('Sessão de assinatura expirada. Refaça.');
    $sess = json_decode(file_get_contents($sessFile), true);
    if (!is_array($sess) || ($sess['usuario'] ?? '') !== $usuario) throw new RuntimeException('Sessão inválida.');
    $ppath = asg_dir_tmp() . '/' . basename($sess['prepared']);
    if (!is_file($ppath)) throw new RuntimeException('PDF preparado não encontrado.');

    $cms = base64_decode(preg_replace('~\s+~', '', (string)$cmsB64), true);
    if ($cms === false || strlen($cms) < 100) throw new RuntimeException('Assinatura (CMS) inválida.');
    if (strncmp($cms, '-----', 5) === 0) $cms = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', (string)$cmsB64), true);

    $md = asg_pades_message_digest($cms);
    $brDigest = hex2bin($sess['brDigestHex']);
    if ($md !== null && !hash_equals($brDigest, $md))
        throw new RuntimeException('A assinatura não corresponde a este documento.');

    $prepared = file_get_contents($ppath);
    $final = AtlasPadesInjector::inject($prepared, (int)$sess['holeStart'], (int)$sess['holeEnd'], $cms);
    $br2 = AtlasPadesInjector::readByteRange($final);
    if (!hash_equals($brDigest, $br2['digest'])) throw new RuntimeException('Falha de integridade após injeção.');
    if (strncmp($final, '%PDF', 4) !== 0) throw new RuntimeException('PDF final inválido.');

    $titular = $sess['titular'];
    if ($certSubject && preg_match('~CN\s*=\s*([^,/]+)~i', $certSubject, $mm)) {
        $cn = trim($mm[1]);
        $titular = preg_replace('~:\d{11}\b.*$~', '', $cn);   // remove ":CPF" do CN, se houver
    }

    $finalName = asg_nome_saida($sess['orig']);
    file_put_contents(asg_dir_sig() . '/' . $finalName, $final);
    @unlink($ppath); @unlink($sessFile);

    return asg_registrar($sess['orig'], asg_dir_sig() . '/' . $finalName, $titular, 'a3', $usuario, $sess['codigo'], $sess['nPag']);
}

/** Desenha o carimbo (logo + textos) numa caixa. */
function asg_desenhar_carimbo($pdf, $x, $y, $w, $h, $logo, $titulo, $nome, $cargo, $local, $codigo, $cpf = '')
{
    // fundo translúcido + borda
    $pdf->SetAlpha(0.92);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(37, 99, 235);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect($x, $y, $w, $h, 1.4, '1111', 'DF');
    $pdf->SetAlpha(1);

    $pad = 2.2;
    $logoW = 0;
    if ($logo && is_file($logo)) {
        $ls = $h - $pad * 2;
        $ext = strtolower(pathinfo($logo, PATHINFO_EXTENSION)) === 'png' ? 'PNG' : 'JPG';
        @$pdf->Image($logo, $x + $pad, $y + $pad, $ls, $ls, $ext, '', '', true, 300, '', false, false, 0, 'CM');
        $logoW = $ls + 1.5;
    }
    $tx = $x + $pad + $logoW;
    $tw = $w - $pad * 2 - $logoW;
    $ty = $y + $pad;

    $pdf->SetTextColor(37, 99, 235);
    $pdf->SetFont('helvetica', 'B', 6.2);
    $pdf->SetXY($tx, $ty);
    $pdf->Cell($tw, 2.6, mb_strtoupper($titulo, 'UTF-8'), 0, 2, 'L');

    $pdf->SetTextColor(20, 25, 40);
    $pdf->SetFont('helvetica', 'B', 6.6);
    $pdf->SetXY($tx, $pdf->GetY() + 0.2);
    $pdf->MultiCell($tw, 2.6, $nome, 0, 'L');

    $linhas = [];
    $cpfFmt = asg_cpf_fmt($cpf);
    if ($cpfFmt !== '') $linhas[] = 'CPF: ' . $cpfFmt;
    if ($cargo !== '') $linhas[] = $cargo;
    if ($local !== '') $linhas[] = $local;
    $linhas[] = 'Data: ' . date('d/m/Y H:i');
    $linhas[] = 'Código: ' . $codigo;
    $pdf->SetTextColor(70, 80, 100);
    $pdf->SetFont('helvetica', '', 5.4);
    $pdf->SetXY($tx, $pdf->GetY() + 0.1);
    $pdf->MultiCell($tw, 2.1, implode("\n", $linhas), 0, 'L');
}

/* ============================ Listagem ============================ */
function asg_listar($limit = 200)
{
    asg_ensure_schema();
    $out = [];
    $r = asg_db()->query("SELECT * FROM assinatura_documentos WHERE status='ativo' ORDER BY assinado_em DESC, id DESC LIMIT " . (int)$limit);
    while ($r && $row = $r->fetch_assoc()) $out[] = $row;
    return $out;
}

/** Monta o WHERE + binds para os filtros da lista. */
function asg_filtros_sql($f)
{
    $where = ["status='ativo'"]; $types = ''; $vals = [];
    $q = trim($f['q'] ?? '');
    if ($q !== '') {
        $where[] = "(nome_original LIKE ? OR titular LIKE ? OR codigo LIKE ?)";
        $like = '%' . $q . '%'; $types .= 'sss'; array_push($vals, $like, $like, $like);
    }
    if (!empty($f['metodo']) && in_array($f['metodo'], ['a1', 'a3'], true)) { $where[] = "metodo=?"; $types .= 's'; $vals[] = $f['metodo']; }
    if (!empty($f['de']))  { $where[] = "assinado_em >= ?"; $types .= 's'; $vals[] = $f['de'] . ' 00:00:00'; }
    if (!empty($f['ate'])) { $where[] = "assinado_em <= ?"; $types .= 's'; $vals[] = $f['ate'] . ' 23:59:59'; }
    return ['where' => implode(' AND ', $where), 'types' => $types, 'vals' => $vals];
}

/** Lista paginada + filtrada. Retorna ['rows'=>[], 'total'=>N, 'pages'=>N, 'page'=>N]. */
function asg_listar_filtrado($f = [])
{
    asg_ensure_schema();
    $conn = asg_db();
    $page = max(1, (int)($f['page'] ?? 1));
    $per  = min(100, max(5, (int)($f['per'] ?? 20)));
    $off  = ($page - 1) * $per;
    $flt  = asg_filtros_sql($f);

    // total
    $total = 0;
    $sqlC = "SELECT COUNT(*) AS c FROM assinatura_documentos WHERE " . $flt['where'];
    $st = $conn->prepare($sqlC);
    if ($flt['types'] !== '') $st->bind_param($flt['types'], ...$flt['vals']);
    $st->execute(); $total = (int)($st->get_result()->fetch_assoc()['c'] ?? 0); $st->close();

    // linhas
    $rows = [];
    $sql = "SELECT * FROM assinatura_documentos WHERE " . $flt['where'] . " ORDER BY assinado_em DESC, id DESC LIMIT ? OFFSET ?";
    $types = $flt['types'] . 'ii'; $vals = $flt['vals']; $vals[] = $per; $vals[] = $off;
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute(); $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();

    return ['rows' => $rows, 'total' => $total, 'per' => $per,
            'page' => $page, 'pages' => max(1, (int)ceil($total / $per))];
}
function asg_doc($id)
{
    $conn = asg_db();
    $st = $conn->prepare("SELECT * FROM assinatura_documentos WHERE id=? AND status='ativo' LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $d = $st->get_result()->fetch_assoc(); $st->close();
    return $d ?: null;
}
function asg_human($n) { $n = (int)$n; if ($n < 1024) return $n . ' B'; if ($n < 1048576) return round($n / 1024, 1) . ' KB'; return round($n / 1048576, 1) . ' MB'; }
