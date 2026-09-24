<?php
/**
 * config_assinatura.php — Núcleo do Atlas Signum (Assinatura Eletrônica)
 * Assina PDFs (PAdES / CAdES-detached) com certificado A1 configurável (.pfx),
 * aplicando um carimbo visível personalizável (logomarca + dados do assinante).
 * Usa TCPDF + FPDI (reaproveitados de ../oficios/).
 *
 * A3 (token): dois assinadores à escolha do usuário (Configurar):
 *  - TCloud Assinador (padrão desde a v1.4.0): o app da estação (2.4.0+) assina o arquivo inteiro;
 *    nada do TCloud na VM (link tcloudsign://local).
 *  - Assinador SERPRO (roda no navegador/estação).
 */
if (!defined('ASG_VERSAO')) define('ASG_VERSAO', '1.7.2');   // versão do módulo Atlas Signum
// Nível da assinatura no TCloud Assinador: 'basico' (sem carimbo de tempo), 'carimbo' ou 'ltv'.
// Por enquanto só o básico — a ACT ainda não está configurada no servidor.json.
if (!defined('ASG_TC_NIVEL')) define('ASG_TC_NIVEL', 'basico');
// Onde a estação baixa o TCloud Assinador (mostrado quando o link tcloudsign:// não abre).
if (!defined('ASG_TC_URL_INSTALACAO')) define('ASG_TC_URL_INSTALACAO', 'https://tcloudsoft.app/download');
// Versão mínima do serviço com o modo link (tcloudsign://), sem pareamento.
if (!defined('ASG_TC_VERSAO_MIN')) define('ASG_TC_VERSAO_MIN', '2.0.0');
// Comando de instalação da estação (Windows PowerShell 5.1 precisa ligar o TLS 1.2 para o HTTPS).
if (!defined('ASG_TC_CMD_WINDOWS')) define('ASG_TC_CMD_WINDOWS', '[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor 3072; irm ' . ASG_TC_URL_INSTALACAO . '/instalar.ps1 | iex');
if (!defined('ASG_TC_CMD_MAC')) define('ASG_TC_CMD_MAC', 'curl -fsSL ' . ASG_TC_URL_INSTALACAO . '/instalar-mac.sh | bash');
if (!defined('ASG_TC_CMD_LINUX')) define('ASG_TC_CMD_LINUX', 'wget -qO- ' . ASG_TC_URL_INSTALACAO . '/instalar-linux.sh | bash');
// Comando para a caixa Executar (Win + R). Abre o instalador num PowerShell COMO ADMINISTRADOR
// (o Windows pede permissão; o usuário clica em "Sim") — necessário quando falta o .NET Desktop
// Runtime 8, que só instala com administrador. -NoExit mantém a janela aberta para ver o resultado.
// A caixa Executar aceita até 259 caracteres (este fica em 239).
if (!defined('ASG_TC_CMD_EXECUTAR')) define('ASG_TC_CMD_EXECUTAR', 'powershell -NoProfile -Command "Start-Process powershell -Verb RunAs -ArgumentList '
    . "'-NoExit -NoProfile -ExecutionPolicy Bypass -Command [Net.ServicePointManager]::SecurityProtocol=3072;irm " . ASG_TC_URL_INSTALACAO . "/instalar.ps1|iex'" . '"');
// O link tcloudsign:// aponta para o mesmo endereço pelo qual o navegador abriu o Atlas
// (rede local, VPN/Tailscale…), para a estação chegar ao serviço pelo mesmo caminho e com o mesmo IP.
if (!defined('ASG_TC_LINK_PELO_NAVEGADOR')) define('ASG_TC_LINK_PELO_NAVEGADOR', true);
if (!defined('ASG_SEM_SESSAO') && session_status() === PHP_SESSION_NONE) session_start();   // tcloud_estacao.php não usa sessão
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

    // v1.1.0 — TCloud Assinador como alternativa ao Assinador SERPRO
    asg_add_coluna('assinatura_config_usuario', 'a3_assinador', "VARCHAR(10) NOT NULL DEFAULT 'tcloud' AFTER metodo");
    // v1.4.0 — TCloud Assinador passa a ser o padrão. Uma única vez: troca o padrão da coluna e leva para o
    // TCloud quem estava no SERPRO só por ser o padrão antigo (depois disso, a escolha de cada um é respeitada).
    $rc = $conn->query("SHOW COLUMNS FROM assinatura_config_usuario LIKE 'a3_assinador'");
    $col = $rc ? $rc->fetch_assoc() : null;
    if ($col && trim((string)($col['Default'] ?? ''), "'") === 'serpro') {   // MariaDB pode mostrar com aspas
        try {
            $conn->query("ALTER TABLE assinatura_config_usuario ALTER a3_assinador SET DEFAULT 'tcloud'");
            $conn->query("UPDATE assinatura_config_usuario SET a3_assinador='tcloud' WHERE a3_assinador='serpro'");
        } catch (Throwable $e) {}
    }
    asg_add_coluna('assinatura_config', 'tcloud_url', "VARCHAR(255) NULL");
    asg_add_coluna('assinatura_config', 'tcloud_token_enc', "TEXT NULL");
    // v1.3.0 — modo do TCloud Assinador: 'local' (estação assina, sem serviço na VM) ou 'servidor'
    asg_add_coluna('assinatura_config', 'tcloud_modo', "VARCHAR(10) NOT NULL DEFAULT 'local'");
    asg_add_coluna('assinatura_documentos', 'provedor', "VARCHAR(10) NULL AFTER metodo");
}

/** Adiciona uma coluna se ainda não existir (guarda com SHOW COLUMNS, compatível com MySQL/MariaDB antigos). */
function asg_add_coluna($tabela, $coluna, $ddl)
{
    $conn = asg_db();
    $rc = $conn->query("SHOW COLUMNS FROM `$tabela` LIKE '" . $conn->real_escape_string($coluna) . "'");
    if ($rc && $rc->num_rows === 0) { try { $conn->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $ddl"); } catch (Throwable $e) {} }
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
        return ['usuario' => $usuario, 'metodo' => 'a3', 'a3_assinador' => 'tcloud', 'cert_arquivo' => null, 'cert_senha_enc' => null,
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
function asg_registrar($origName, $outPath, $titular, $metodo, $usuario, $codigo, $nPag, $provedor = null)
{
    $bin = file_get_contents($outPath);
    $hash = hash('sha256', $bin);
    $conn = asg_db();
    $tam = strlen($bin); $agora = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO assinatura_documentos
        (nome_original, arquivo, hash_sha256, codigo, titular, metodo, provedor, assinado_por, assinado_em, tamanho, paginas, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'ativo')");
    $arq = basename($outPath);
    $st->bind_param('sssssssssii', $origName, $arq, $hash, $codigo, $titular, $metodo, $provedor, $usuario, $agora, $tam, $nPag);
    $st->execute(); $id = $st->insert_id; $st->close();
    return ['id' => $id, 'arquivo' => $arq, 'nome_original' => $origName, 'codigo' => $codigo,
            'hash' => $hash, 'titular' => $titular, 'metodo' => $metodo, 'provedor' => $provedor, 'assinado_em' => $agora, 'tamanho' => $tam, 'paginas' => $nPag];
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
    return asg_registrar($origName, $final, $titular, 'a1', $usuario, $meta['codigo'], $meta['nPag'], 'arquivo');
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

    return asg_registrar($st['orig'], $final, $st['titular'], 'a3', $usuario, $st['codigo'], $st['nPag'], 'agente');
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
    return asg_registrar($st['orig'], $final, $st['titular'], 'a3', $usuario, $st['codigo'], $st['nPag'], 'serpro');
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

    return asg_registrar($sess['orig'], asg_dir_sig() . '/' . $finalName, $titular, 'a3', $usuario, $sess['codigo'], $sess['nPag'], 'serpro');
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

/* ============================ A3 via TCloud Assinador ============================ */
/*  O PDF nunca sai do servidor: o serviço "TCloud Assinador - Servidor" (porta 9480, na VM)
 *  recebe o pedido e devolve um link tcloudsign:// que o navegador de quem clicou abre; o
 *  TCloud Assinador daquele computador pega só o hash pelo ticket (token + PIN), sem
 *  pareamento, e o serviço devolve o PDF PAdES pronto (nível em ASG_TC_NIVEL). A chancela é desenhada pelo
 *  próprio assinador, com nome/CPF lidos do certificado usado.                              */

function asg_tc_lib() { require_once __DIR__ . '/lib/TCloudAssinador.php'; require_once __DIR__ . '/lib/tcloud_local.php'; }

/**
 * Modo do TCloud Assinador. Desde a v1.6.0 é sempre 'local': o app da estação assina e nada do TCloud
 * roda na VM (o serviço da VM foi descontinuado no TCloud Assinador 2.5.0). O código do modo via
 * serviço continua no módulo só por compatibilidade e não é mais usado.
 */
function asg_tc_modo()
{
    return 'local';
}

/** Qual assinador A3 o usuário escolheu: 'tcloud' (padrão) ou 'serpro'. */
function asg_a3_assinador($ucfg)
{
    return (isset($ucfg['a3_assinador']) && $ucfg['a3_assinador'] === 'serpro') ? 'serpro' : 'tcloud';
}

/** Conexão configurada (global). URL vazia = serviço nesta VM; token vazio = lido do servidor.json. */
function asg_tc_conexao()
{
    $cfg = asg_config();
    return ['url'   => trim((string)($cfg['tcloud_url'] ?? '')),
            'token' => !empty($cfg['tcloud_token_enc']) ? asg_dec($cfg['tcloud_token_enc']) : ''];
}

/** Cria o cliente do TCloud Assinador (lança TCloudAssinadorException 'sem_token'). */
function asg_tc_cliente($timeout = null)
{
    asg_tc_lib();
    $c = asg_tc_conexao();
    $op = ['sistema' => 'Atlas'];
    if ($c['url'] !== '')   $op['url'] = $c['url'];
    if ($c['token'] !== '') $op['token'] = $c['token'];
    if ($timeout)           $op['timeout'] = (int)$timeout;
    return new TCloudAssinador($op);
}

/** Estações do usuário, só com os campos que a tela usa. */
/** Normaliza IP (IPv4 mapeado em IPv6, loopback IPv6). */
function asg_tc_ip($ip)
{
    $ip = strtolower(trim((string)$ip));
    if (strpos($ip, '::ffff:') === 0) $ip = substr($ip, 7);
    if ($ip === '::1') $ip = '127.0.0.1';
    return $ip;
}

/** Host (sem porta) pelo qual o navegador abriu o Atlas; '' se não servir para o link. */
function asg_tc_host_navegador($httpHost)
{
    $h = strtolower(trim((string)$httpHost));
    if ($h === '' || $h[0] === '[') return '';                 // IPv6 literal: mantém o link do serviço
    $h = preg_replace('~:\d+$~', '', $h);
    if (!preg_match('~^[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?$~', $h)) return '';
    if ($h === 'localhost') $h = '127.0.0.1';
    return $h;
}

/** O serviço roda nesta mesma VM (conexão padrão ou apontando para loopback)? */
function asg_tc_servico_local()
{
    $c = asg_tc_conexao();
    if ($c['url'] === '') return true;
    $host = strtolower((string)parse_url(preg_match('~^https?://~i', $c['url']) ? $c['url'] : 'http://' . $c['url'], PHP_URL_HOST));
    return $host === '127.0.0.1' || $host === 'localhost' || $host === '::1' || $host === '[::1]';
}

/**
 * Troca o servidor do link (s=) pelo host que o navegador usou, mantendo esquema e porta do serviço.
 * Só quando o serviço está nesta VM — aí esse host, por definição, é alcançável pela estação.
 */
function asg_tc_ajustar_link($link, $hostNavegador)
{
    if (!ASG_TC_LINK_PELO_NAVEGADOR || $hostNavegador === '' || !asg_tc_servico_local()) return $link;
    $q = (string)parse_url($link, PHP_URL_QUERY);
    if ($q === '') return $link;
    parse_str($q, $p);
    if (empty($p['s']) || empty($p['t'])) return $link;
    $orig = (string)$p['s'];
    $u = parse_url(preg_match('~^https?://~i', $orig) ? $orig : 'http://' . $orig);
    if (!$u || empty($u['host'])) return $link;
    $esq   = !empty($u['scheme']) ? $u['scheme'] : 'http';
    $porta = !empty($u['port']) ? (int)$u['port'] : 9480;
    $p['s'] = $esq . '://' . $hostNavegador . ':' . $porta;
    $base = substr($link, 0, strpos($link, '?'));
    return $base . '?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986);
}

/** Compara a versão do serviço com a mínima exigida (modo link). */
function asg_tc_versao_ok($v)
{
    $v = preg_replace('~[^0-9.].*$~', '', (string)$v);
    return $v !== '' && version_compare($v, ASG_TC_VERSAO_MIN, '>=');
}

/** Situação do serviço (no modo link não há computadores pareados a listar). */
function asg_tc_situacao($modo = null)
{
    if (($modo ?: asg_tc_modo()) === 'local') {
        // nada a consultar na VM: o app da estação faz tudo
        return ['modo' => 'local', 'instalado' => true, 'disponivel' => true, 'token_ok' => true, 'versao' => '',
                'versao_ok' => true, 'url' => '', 'mensagem' => '', 'codigo' => '', 'url_instalacao' => ASG_TC_URL_INSTALACAO];
    }
    $out = ['modo' => 'servidor', 'instalado' => true, 'disponivel' => false, 'token_ok' => false, 'versao' => '', 'versao_ok' => false,
            'url' => '', 'mensagem' => '', 'codigo' => '', 'url_instalacao' => ASG_TC_URL_INSTALACAO];
    try { $tc = asg_tc_cliente(15); }
    catch (TCloudAssinadorException $e) { $out['instalado'] = false; $out['mensagem'] = $e->getMessage(); $out['codigo'] = $e->codigo; return $out; }
    $out['url'] = $tc->url();
    try { $v = $tc->versao(); $out['disponivel'] = true; $out['versao'] = (string)($v['versao'] ?? ''); }
    catch (TCloudAssinadorException $e) { $out['mensagem'] = $e->getMessage(); $out['codigo'] = $e->codigo; return $out; }
    $out['versao_ok'] = asg_tc_versao_ok($out['versao']);
    try {
        $tc->estacoes();   // só valida o token (rota autenticada e leve)
        $out['token_ok'] = true;
    } catch (TCloudAssinadorException $e) { $out['mensagem'] = $e->getMessage(); $out['codigo'] = $e->codigo; }
    if ($out['token_ok'] && !$out['versao_ok']) {
        $out['mensagem'] = 'O servidor de assinatura está na versão ' . ($out['versao'] ?: '?') . '. Atualize-o para a '
                         . ASG_TC_VERSAO_MIN . ' ou mais nova (assinatura pelo link, sem pareamento).';
    }
    return $out;
}

/** Página alvo, tamanho dela em pontos e total de páginas do PDF. */
function asg_tc_geometria_pagina($src, $pagina)
{
    $W = 595.28; $H = 841.89; $nPag = 0; $alvo = (int)$pagina;
    try {
        asg_load_libs();
        $Fpdi = 'setasign\\Fpdi\\Tcpdf\\Fpdi';
        $pdf = new $Fpdi('P', 'pt');
        $nPag = (int)$pdf->setSourceFile($src);
        $alvo = max(1, min($nPag, $alvo > 0 ? $alvo : $nPag));
        $tpl = $pdf->importPage($alvo);
        $s = $pdf->getTemplateSize($tpl);
        if (!empty($s['width']) && !empty($s['height'])) { $W = (float)$s['width']; $H = (float)$s['height']; }
    } catch (Throwable $e) {
        // sem FPDI (ou PDF que ele não abre): usa A4 e conta as páginas pelo dicionário
        $bin = (string)@file_get_contents($src);
        $nPag = max(1, (int)preg_match_all('~/Type\s*/Page(?!s)\b~', $bin));
        $alvo = max(1, min($nPag, $alvo > 0 ? $alvo : 1));
    }
    return ['pagina' => $alvo, 'W' => $W, 'H' => $H, 'nPag' => max(1, $nPag)];
}

/** Proporção altura/largura da chancela do TCloud (tem mais linhas que o carimbo do Atlas). */
function asg_tc_proporcao() { return 0.48; }

/** Converte a posição escolhida na tela (frações da página) em chancela do TCloud (pontos, canto superior esquerdo). */
function asg_tc_aparencia($geo, $pos, $titulo, $extras, $logo)
{
    $mm = 72 / 25.4;
    $W = $geo['W']; $H = $geo['H'];
    $cw = max(52 * $mm, min(90 * $mm, $W * (float)($pos['w'] ?? 0.30)));   // mesmos limites do carimbo A1/SERPRO
    $ch = $cw * asg_tc_proporcao();
    $cx = isset($pos['x']) && $pos['x'] !== null ? ($pos['x'] * $W) : ($W - $cw - 10 * $mm);
    $cy = isset($pos['y']) && $pos['y'] !== null ? ($pos['y'] * $H) : ($H - $ch - 10 * $mm);
    $cx = max(2 * $mm, min($W - $cw - 2 * $mm, $cx));
    $cy = max(2 * $mm, min($H - $ch - 2 * $mm, $cy));

    $ap = ['visivel' => true, 'pagina' => (int)$geo['pagina'],
           'x' => round($cx, 2), 'y' => round($cy, 2),
           'largura' => round($cw, 2), 'altura' => round($ch, 2),
           'ancorar_direita' => false, 'ancorar_topo' => true,
           'titulo' => function_exists('mb_strtoupper') ? mb_strtoupper($titulo, 'UTF-8') : strtoupper($titulo), 'borda' => true, 'fonte' => 0];
    if ($extras) $ap['texto_adicional'] = implode("\n", $extras);
    if ($logo) { $ap['logo_base64'] = $logo; $ap['logo_posicao'] = 'esquerda'; }
    return $ap;
}

/** Logomarca do cartório em base64 (cache por requisição). */
function asg_tc_logo_b64()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $p = asg_logo_path();
    $cache = ($p && is_file($p)) ? base64_encode(file_get_contents($p)) : '';
    return $cache;
}

function asg_tc_id($id) { return preg_replace('~[^A-Za-z0-9_\-]~', '', (string)$id); }
function asg_tc_estado_arquivo($id) { return asg_dir_tmp() . '/tc_' . asg_tc_id($id) . '.json'; }

/** Cria o pedido de assinatura no TCloud Assinador (modo local ou via serviço) para o PDF enviado. */
function asg_tc_iniciar($usuario, $src, $origName, $pos, $uploadToken, $ipNavegador = '', $httpHost = '')
{
    $ipNavegador = asg_tc_ip($ipNavegador);
    $hostNav = asg_tc_host_navegador($httpHost);
    asg_ensure_schema();
    // limpeza de pedidos antigos (> 6 h)
    foreach ((array)@glob(asg_dir_tmp() . '/tc_*.json') as $f) if (is_file($f) && filemtime($f) < time() - 6 * 3600) @unlink($f);

    $u = asg_ucfg($usuario);
    $cfg = asg_config();
    $titulo = $cfg['carimbo_titulo'] ?: 'Assinado digitalmente';
    $motivo = $cfg['motivo'] ?: 'Assinatura eletrônica de documento';
    $codigo = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));

    $geo = asg_tc_geometria_pagina($src, (int)($pos['pagina'] ?? 1));
    $extras = [];
    if (trim((string)($u['assinante_cargo'] ?? '')) !== '') $extras[] = trim($u['assinante_cargo']);
    $extras[] = 'Código: ' . $codigo;
    $ap = asg_tc_aparencia($geo, $pos, $titulo, $extras, asg_tc_logo_b64());

    $op = ['titulo'    => $origName,       // a janela do assinador mostra o nome do documento
           'motivo'    => $motivo,
           'aparencia' => $ap,
           'nivel'     => ASG_TC_NIVEL,    // básico: não usa a ACT (sem carimbo de tempo / LTV)
           'modo'      => 'link'];         // sempre pelo link: abre só no computador de quem clicou
    if (trim((string)($u['assinante_local'] ?? '')) !== '') $op['local'] = trim($u['assinante_local']);
    if ($ipNavegador !== '') $op['ip_estacao'] = $ipNavegador;   // o ticket só abre neste IP (LinkExigirMesmoIp)

    if (asg_tc_modo() === 'local') {
        asg_tc_lib();
        // o pedido fica no próprio Signum; vão ao app só as opções de assinatura (contrato 3.6)
        $opL = array_diff_key($op, ['modo' => 1, 'ip_estacao' => 1, 'titulo' => 1]);
        $nome = trim((string)($u['assinante_nome'] ?? '')) ?: $usuario;
        $r = asg_tcl_iniciar($usuario, $nome, $src, $origName, $opL,
                             ['token' => $uploadToken, 'codigo' => $codigo, 'nPag' => $geo['nPag']],
                             $ipNavegador, $httpHost);
        return $r + ['codigo' => $codigo, 'url_instalacao' => ASG_TC_URL_INSTALACAO];
    }

    $tc = asg_tc_cliente();
    $r = $tc->iniciar($usuario, [['nome' => $origName, 'arquivo' => $src]], $op);
    $id = asg_tc_id($r['id'] ?? '');
    if ($id === '') throw new RuntimeException('O TCloud Assinador não devolveu o número do pedido.');

    $link = (string)($r['link'] ?? '');
    if (strpos($link, 'tcloudsign://') === 0) $link = asg_tc_ajustar_link($link, $hostNav);
    if (strpos($link, 'tcloudsign://') !== 0) {
        // serviço antigo (sem modo link): não segue pelo pareamento
        try { $tc->cancelar($id); } catch (Exception $e) {}
        throw new TCloudAssinadorException('O servidor de assinatura não devolveu o link tcloudsign://. Atualize-o para a versão '
            . ASG_TC_VERSAO_MIN . ' ou mais nova.', 'sem_link', 0);
    }

    $state = ['usuario' => $usuario, 'orig' => $origName, 'codigo' => $codigo, 'nPag' => $geo['nPag'],
              'token' => $uploadToken, 'em' => time(), 'doc' => null];
    file_put_contents(asg_tc_estado_arquivo($id), json_encode($state, JSON_UNESCAPED_UNICODE));

    return ['id' => $id, 'estado' => (string)($r['estado'] ?? 'aguardando_estacao'),
            'link' => $link, 'codigo' => $codigo, 'url_instalacao' => ASG_TC_URL_INSTALACAO];
}

/** Carrega o estado local do pedido, conferindo o dono. */
function asg_tc_estado($usuario, $id)
{
    $sf = asg_tc_estado_arquivo($id);
    if (!is_file($sf)) throw new RuntimeException('Pedido de assinatura não encontrado. Refaça.');
    $st = json_decode((string)file_get_contents($sf), true);
    if (!is_array($st) || ($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Pedido inválido.');
    return $st;
}

/** Acompanha o pedido; ao concluir, grava o PDF assinado e registra o documento (uma única vez). */
function asg_tc_acompanhar($usuario, $id)
{
    $id = asg_tc_id($id);
    asg_tc_lib();
    if (asg_tcl_existe($id)) return asg_tcl_status($usuario, $id);   // modo local
    asg_tc_estado($usuario, $id);                       // valida dono antes de travar
    $sf = asg_tc_estado_arquivo($id);
    $fh = fopen($sf, 'c+');
    if (!$fh) throw new RuntimeException('Falha ao abrir o pedido.');
    flock($fh, LOCK_EX);
    try {
        $st = json_decode((string)stream_get_contents($fh), true);
        if (!is_array($st)) throw new RuntimeException('Pedido inválido.');
        if (!empty($st['doc'])) return ['estado' => 'concluido', 'finalizado' => true, 'mensagem' => 'Documento assinado.', 'doc' => $st['doc']];

        $tc = asg_tc_cliente(60);
        try { $s = $tc->status($id); }
        catch (TCloudAssinadorException $e) {
            if ($e->status === 404) throw new RuntimeException('O pedido não existe mais no TCloud Assinador (expirou ou o serviço foi reiniciado). Assine de novo.');
            throw $e;
        }
        $out = ['estado' => (string)($s['estado'] ?? ''), 'finalizado' => !empty($s['finalizado']),
                'mensagem' => (string)($s['mensagem'] ?? ''), 'estacao' => (string)($s['estacao'] ?? ''),
                'assinante' => (string)($s['assinante'] ?? '')];

        if ($out['estado'] === 'concluido') {
            $res = $tc->resultado($id);
            $d = $res['documentos'][0] ?? null;
            if (!$d || empty($d['assinado']) || empty($d['pdf'])) {
                throw new RuntimeException('O documento não foi assinado: ' . (($d['erro'] ?? '') ?: 'motivo não informado.'));
            }
            if (strncmp($d['pdf'], '%PDF', 4) !== 0 || strpos($d['pdf'], 'ByteRange') === false)
                throw new RuntimeException('O TCloud Assinador devolveu um PDF inválido.');

            $titular = trim((string)($res['assinante'] ?? '')) ?: ($out['assinante'] ?: 'Titular do certificado');
            $titular = preg_replace('~:\d{11,14}\b.*$~', '', $titular);   // remove ":CPF" do CN, se vier

            $final = asg_dir_sig() . '/' . asg_nome_saida($st['orig']);
            file_put_contents($final, $d['pdf']);
            $rec = asg_registrar($st['orig'], $final, $titular, 'a3', $usuario, $st['codigo'], (int)$st['nPag'], 'tcloud');
            $rec['carimbo'] = !empty($d['carimbo']);
            $rec['act']     = (string)($d['act'] ?? '');
            $rec['ltv']     = !empty($d['ltv']);
            $rec['avisos']  = array_values(array_filter((array)($d['avisos'] ?? [])));

            $st['doc'] = $rec;
            ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($st, JSON_UNESCAPED_UNICODE)); fflush($fh);

            $tk = preg_replace('~[^a-f0-9]~', '', (string)($st['token'] ?? ''));
            if ($tk !== '') { @unlink(asg_dir_tmp() . '/' . $tk . '.pdf'); @unlink(asg_dir_tmp() . '/' . $tk . '.nome'); }
            $out['finalizado'] = true;
            $out['doc'] = $rec;
        } elseif ($out['finalizado']) {
            // recusado / expirado / erro / cancelado: descarta o pedido; o upload fica para nova tentativa
            flock($fh, LOCK_UN); fclose($fh); $fh = null;
            @unlink($sf);
        }
        return $out;
    } finally {
        if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
    }
}

/** Cancela o pedido no serviço e descarta o estado local. */
function asg_tc_cancelar($usuario, $id)
{
    $id = asg_tc_id($id);
    asg_tc_lib();
    if (asg_tcl_existe($id)) return asg_tcl_cancelar($usuario, $id);   // modo local
    $st = asg_tc_estado($usuario, $id);
    if (!empty($st['doc'])) return ['cancelado' => false, 'doc' => $st['doc']];
    try { asg_tc_cliente(20)->cancelar($id); } catch (TCloudAssinadorException $e) { if ($e->status !== 404) throw $e; }
    @unlink(asg_tc_estado_arquivo($id));
    return ['cancelado' => true];
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
/** Rótulo do método para a lista: "A1", "A3 · SERPRO", "A3 · TCloud". */
function asg_metodo_rotulo($d)
{
    $m = strtoupper((string)($d['metodo'] ?? ''));
    $p = (string)($d['provedor'] ?? '');
    if ($p === 'tcloud') return $m . ' · TCloud';
    if ($p === 'serpro') return $m . ' · SERPRO';
    return $m;
}
function asg_human($n) { $n = (int)$n; if ($n < 1024) return $n . ' B'; if ($n < 1048576) return round($n / 1024, 1) . ' KB'; return round($n / 1048576, 1) . ' MB'; }
