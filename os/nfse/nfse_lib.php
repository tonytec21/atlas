<?php
/**
 * =====================================================================
 * ATLAS O.S. — Integração NFS-e Nacional (Emissor Nacional / SEFIN)
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-07-13s-fuso-fortaleza-alinhado
 *   (auto-reempacota .pfx RC2-40 -> AES-256 no upload/emissao)
 *
 * Base normativa adotada (ver INSTALACAO.md para o detalhamento):
 *  - LC 116/2003, item 21.01 da lista anexa  -> cTribNac 210101
 *  - LC 214/2025, art. 62, §1º, I            -> uso obrigatório do
 *    Ambiente Nacional da NFS-e a partir de 01/01/2026
 *  - Regime transitório de 2026: admite-se "Tomador não informado" e
 *    NFS-e consolidada; a partir de 01/01/2027 a emissão deve ser
 *    individualizada por ato, com identificação do tomador
 *  - Regime Especial de Tributação = 4 (Notário ou Registrador)
 *  - Fato gerador: LIQUIDAÇÃO do ato (serviço prestado), nunca o
 *    depósito prévio, que é mero adiantamento
 *
 * Requisitos: PHP >= 8.1, ext-openssl, ext-zlib, ext-curl,
 *             composer require nfse-nacional/nfse-php
 * =====================================================================
 */

if (!defined('ATLAS_NFSE_VERSAO')) {
    define('ATLAS_NFSE_VERSAO', 'ATLAS-NFSE-1.0.0');
}

define('ATLAS_NFSE_DIR', __DIR__);
define('ATLAS_NFSE_CERTS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'certs');
define('ATLAS_NFSE_KEYFILE', ATLAS_NFSE_CERTS_DIR . DIRECTORY_SEPARATOR . '.nfse.key');

date_default_timezone_set('America/Fortaleza');

/* =====================================================================
 * 1. AUTOLOAD / DISPONIBILIDADE DO SDK
 * ===================================================================== */

/**
 * Procura o autoload do Composer em locais prováveis.
 */
function nfse_autoload(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $candidatos = [
        __DIR__ . '/vendor',
        __DIR__ . '/../vendor',
        __DIR__ . '/../../vendor',
    ];

    foreach ($candidatos as $dir) {
        if (!is_file($dir . '/autoload.php')) {
            continue;
        }

        require_once $dir . '/autoload.php';

        // A raiz do Atlas costuma ter seu proprio vendor/ e carrega o Composer
        // antes deste modulo, curto-circuitando o autoloader do SDK. Para
        // garantir a resolucao de forma independente, montamos um ClassLoader a
        // partir dos mapas do Composer DESTE modulo e o registramos com prepend.
        // Auto-carregamos o ClassLoader.php do modulo caso ainda nao exista.
        $clFile = $dir . '/composer/ClassLoader.php';
        if (!class_exists('\\Composer\\Autoload\\ClassLoader', false) && is_file($clFile)) {
            require_once $clFile;
        }
        if (class_exists('\\Composer\\Autoload\\ClassLoader', false)) {
            try {
                $loader = new \Composer\Autoload\ClassLoader();

                $psr4 = $dir . '/composer/autoload_psr4.php';
                if (is_file($psr4)) {
                    foreach ((array) require $psr4 as $ns => $paths) {
                        $loader->setPsr4($ns, $paths);
                    }
                }

                $psr0 = $dir . '/composer/autoload_namespaces.php';
                if (is_file($psr0)) {
                    foreach ((array) require $psr0 as $ns => $paths) {
                        $loader->set($ns, $paths);
                    }
                }

                $cmap = $dir . '/composer/autoload_classmap.php';
                if (is_file($cmap)) {
                    $m = require $cmap;
                    if (is_array($m) && $m) {
                        $loader->addClassMap($m);
                    }
                }

                $loader->register(true); // prepend: SDK do modulo tem prioridade
            } catch (\Throwable $e) {
                error_log('[nfse_autoload] fallback ClassLoader: ' . $e->getMessage());
            }
        }

        $ok = class_exists('\\Nfse\\Nfse');
        return $ok;
    }

    $ok = class_exists('\\Nfse\\Nfse');
    return $ok;
}

/**
 * Diagnóstico do ambiente. Usado pela página de configuração.
 */
function nfse_diagnostico(): array
{
    return [
        'php_versao'   => PHP_VERSION,
        'php_ok'       => version_compare(PHP_VERSION, '8.1.0', '>='),
        'openssl'      => extension_loaded('openssl'),
        'curl'         => extension_loaded('curl'),
        'zlib'         => function_exists('gzencode'),
        'dom'          => extension_loaded('dom'),
        'sdk'          => nfse_autoload(),
        'certs_dir'    => is_dir(ATLAS_NFSE_CERTS_DIR) && is_writable(ATLAS_NFSE_CERTS_DIR),
        'chave_mestra' => is_file(ATLAS_NFSE_KEYFILE),
    ];
}

/* =====================================================================
 * 2. CONEXÃO
 * ===================================================================== */

function nfse_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    require_once __DIR__ . '/../db_connection.php';
    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Alinha o fuso do MySQL ao do PHP (America/Fortaleza = -03:00), para que
    // NOW() e a exibição com date()/strtotime() usem o mesmo horário. O formato
    // de offset não depende das tabelas de fuso nomeadas (que o XAMPP não traz).
    try {
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (\Throwable $e) {
        // Sem permissão para trocar o fuso da sessão: segue com o padrão do servidor.
    }

    return $pdo;
}

/* =====================================================================
 * 3. CRIPTOGRAFIA DO CERTIFICADO A1 (AES-256-GCM)
 * ---------------------------------------------------------------------
 * O .pfx e a senha NUNCA ficam em texto claro no banco nem no disco.
 * A chave mestra fica em certs/.nfse.key (fora do alcance HTTP via
 * .htaccess) e é gerada automaticamente na primeira execução.
 * ===================================================================== */

function nfse_chave_mestra(): string
{
    if (!is_dir(ATLAS_NFSE_CERTS_DIR)) {
        @mkdir(ATLAS_NFSE_CERTS_DIR, 0700, true);
    }

    if (!is_file(ATLAS_NFSE_KEYFILE)) {
        $key = random_bytes(32);
        file_put_contents(ATLAS_NFSE_KEYFILE, base64_encode($key), LOCK_EX);
        @chmod(ATLAS_NFSE_KEYFILE, 0600);
        return $key;
    }

    $key = base64_decode(trim((string) file_get_contents(ATLAS_NFSE_KEYFILE)), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Chave mestra da NFS-e inválida ou corrompida (certs/.nfse.key).');
    }

    return $key;
}

function nfse_encrypt(string $plain): string
{
    $key = nfse_chave_mestra();
    $iv  = random_bytes(12);
    $tag = '';

    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Falha ao cifrar dado sensível da NFS-e.');
    }

    return base64_encode($iv . $tag . $cipher);
}

function nfse_decrypt(?string $blob): ?string
{
    if ($blob === null || $blob === '') {
        return null;
    }

    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RuntimeException('Dado cifrado da NFS-e inválido.');
    }

    $key    = nfse_chave_mestra();
    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Não foi possível decifrar o certificado. A chave mestra mudou?');
    }

    return $plain;
}

/* =====================================================================
 * 4. MIGRAÇÕES IDEMPOTENTES
 * ===================================================================== */

function nfse_migrar(?PDO $pdo = null): void
{
    static $feito = false;
    if ($feito) {
        return; // uma vez por request basta
    }
    $feito = true;

    $pdo = $pdo ?: nfse_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nfse_config (
            id                  TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            ativo               TINYINT(1)      NOT NULL DEFAULT 0,
            ambiente            CHAR(1)         NOT NULL DEFAULT '2',
            emissao_automatica  TINYINT(1)      NOT NULL DEFAULT 0,
            modo_emissao        VARCHAR(20)     NOT NULL DEFAULT 'consolidado',
            identificar_tomador TINYINT(1)      NOT NULL DEFAULT 0,

            prest_tipo          VARCHAR(4)      NOT NULL DEFAULT 'CNPJ',
            prest_doc           VARCHAR(14)     NULL,
            prest_im            VARCHAR(15)     NULL,
            prest_nome          VARCHAR(150)    NULL,
            prest_cep           VARCHAR(8)      NULL,
            prest_logradouro    VARCHAR(255)    NULL,
            prest_numero        VARCHAR(60)     NULL,
            prest_complemento   VARCHAR(156)    NULL,
            prest_bairro        VARCHAR(60)     NULL,
            prest_fone          VARCHAR(20)     NULL,
            prest_email         VARCHAR(80)     NULL,

            cod_municipio       VARCHAR(7)      NULL,
            serie_dps           VARCHAR(5)      NOT NULL DEFAULT '1',
            ultimo_numero_dps   BIGINT UNSIGNED NOT NULL DEFAULT 0,

            ctrib_nac           VARCHAR(6)      NOT NULL DEFAULT '210101',
            ctrib_mun           VARCHAR(20)     NULL,
            cnae                VARCHAR(7)      NULL,

            base_calculo        VARCHAR(20)     NOT NULL DEFAULT 'emolumentos',
            reducao_base        DECIMAL(5,2)    NOT NULL DEFAULT 12.00,
            reducao_modo        VARCHAR(10)     NOT NULL DEFAULT 'grupo',
            aliquota_iss        DECIMAL(5,2)    NOT NULL DEFAULT 5.00,
            reg_esp_trib        VARCHAR(2)      NOT NULL DEFAULT '4',
            op_simp_nac         VARCHAR(1)      NOT NULL DEFAULT '1',
            reg_ap_trib_sn      VARCHAR(1)      NULL,
            p_tot_trib_sn       DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
            cst_piscofins       VARCHAR(2)      NOT NULL DEFAULT '08',

            cert_nome           VARCHAR(255)    NULL,
            cert_titular        VARCHAR(255)    NULL,
            cert_validade       DATETIME        NULL,
            cert_blob           LONGTEXT        NULL,
            cert_senha          TEXT            NULL,

            atualizado_em       DATETIME        NULL,
            atualizado_por      VARCHAR(100)    NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("INSERT IGNORE INTO nfse_config (id) VALUES (1)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nfse_notas (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            ordem_servico_id    INT             NOT NULL,
            item_id             INT             NULL,
            ambiente            VARCHAR(1)      NOT NULL,
            serie               VARCHAR(5)      NOT NULL,
            numero_dps          BIGINT UNSIGNED NOT NULL,
            id_dps              VARCHAR(45)     NOT NULL,
            chave_acesso        VARCHAR(60)     NULL,
            numero_nfse         VARCHAR(30)     NULL,
            cod_verificacao     VARCHAR(30)     NULL,
            status              VARCHAR(20)     NOT NULL DEFAULT 'processando',
            valor_servico       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
            valor_reducao       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
            base_calculo        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
            aliquota            DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
            valor_iss           DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
            tomador_doc         VARCHAR(14)     NULL,
            tomador_nome        VARCHAR(150)    NULL,
            discriminacao       TEXT            NULL,
            xml_dps             LONGTEXT        NULL,
            xml_nfse            LONGTEXT        NULL,
            mensagem            TEXT            NULL,
            cancel_motivo       VARCHAR(255)    NULL,
            cancelada_em        DATETIME        NULL,
            criado_em           DATETIME        NULL,
            criado_por          VARCHAR(100)    NULL,
            UNIQUE KEY uk_nfse_dps (ambiente, serie, numero_dps),
            KEY idx_nfse_os (ordem_servico_id),
            KEY idx_nfse_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS nfse_log (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            nota_id      INT          NULL,
            os_id        INT          NULL,
            evento       VARCHAR(40)  NOT NULL,
            nivel        VARCHAR(10)  NOT NULL DEFAULT 'info',
            mensagem     TEXT         NULL,
            usuario      VARCHAR(100) NULL,
            criado_em    DATETIME     NULL,
            KEY idx_log_os (os_id),
            KEY idx_log_nota (nota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Protege o diretório de certificados contra acesso HTTP (Apache).
    if (!is_dir(ATLAS_NFSE_CERTS_DIR)) {
        @mkdir(ATLAS_NFSE_CERTS_DIR, 0700, true);
    }
    $ht = ATLAS_NFSE_CERTS_DIR . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    }
    // Migração incremental (instalações anteriores): garante a coluna do regime
    // de apuração do Simples Nacional, exigida para optantes (opSimpNac 2 ou 3).
    $temRegAp = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'nfse_config'
            AND COLUMN_NAME = 'reg_ap_trib_sn'"
    )->fetchColumn();
    if ($temRegAp === 0) {
        $pdo->exec("ALTER TABLE nfse_config ADD COLUMN reg_ap_trib_sn VARCHAR(1) NULL AFTER op_simp_nac");
    }

    // Percentual total de tributos do Simples Nacional (pTotTribSN), usado no
    // totTrib quando o prestador é optante do Simples.
    $temPTot = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'nfse_config'
            AND COLUMN_NAME = 'p_tot_trib_sn'"
    )->fetchColumn();
    if ($temPTot === 0) {
        $pdo->exec("ALTER TABLE nfse_config ADD COLUMN p_tot_trib_sn DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER reg_ap_trib_sn");
    }

    $temRedMod = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'nfse_config'
            AND COLUMN_NAME = 'reducao_modo'"
    )->fetchColumn();
    if ($temRedMod === 0) {
        $pdo->exec("ALTER TABLE nfse_config ADD COLUMN reducao_modo VARCHAR(10) NOT NULL DEFAULT 'grupo' AFTER reducao_base");
    }

    // Normaliza chaves gravadas com o prefixo "NFS" ("NFS"+50 = 53 chars) para os
    // 50 dígitos usados nas consultas/eventos e no portal. Só toca linhas sujas.
    $pdo->exec("UPDATE nfse_notas SET chave_acesso = SUBSTRING(chave_acesso, 4) WHERE chave_acesso LIKE 'NFS%' AND CHAR_LENGTH(chave_acesso) = 53");
}

function nfse_log(string $evento, string $mensagem, string $nivel = 'info', ?int $osId = null, ?int $notaId = null): void
{
    try {
        $pdo = nfse_pdo();
        $st  = $pdo->prepare(
            "INSERT INTO nfse_log (nota_id, os_id, evento, nivel, mensagem, usuario, criado_em)
             VALUES (:nota, :os, :evt, :niv, :msg, :usr, NOW())"
        );
        $st->execute([
            ':nota' => $notaId,
            ':os'   => $osId,
            ':evt'  => substr($evento, 0, 40),
            ':niv'  => $nivel,
            ':msg'  => $mensagem,
            ':usr'  => $_SESSION['username'] ?? 'sistema',
        ]);
    } catch (Throwable $e) {
        error_log('[nfse_log] ' . $e->getMessage() . ' | original: ' . $mensagem);
    }
}

/* =====================================================================
 * 5. CONFIGURAÇÃO
 * ===================================================================== */

function nfse_config(bool $comSegredos = false): array
{
    nfse_migrar();
    $pdo = nfse_pdo();

    $cfg = $pdo->query("SELECT * FROM nfse_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$comSegredos) {
        unset($cfg['cert_blob'], $cfg['cert_senha']);
    }

    return $cfg;
}

/**
 * Diz se a emissão está habilitada e completa. Retorna [] se ok,
 * ou a lista de pendências.
 */
function nfse_pendencias(array $cfg): array
{
    $faltas = [];

    if (empty($cfg['prest_doc']))      $faltas[] = 'CPF/CNPJ do prestador';
    if (empty($cfg['prest_nome']))     $faltas[] = 'Nome/razão social do prestador';
    if (empty($cfg['cod_municipio']))  $faltas[] = 'Código IBGE do município';
    if (empty($cfg['prest_cep']))      $faltas[] = 'CEP do prestador';
    if (empty($cfg['prest_logradouro'])) $faltas[] = 'Logradouro do prestador';
    if (empty($cfg['prest_numero']))   $faltas[] = 'Número do endereço';
    if (empty($cfg['prest_bairro']))   $faltas[] = 'Bairro';
    if (empty($cfg['cert_blob']))      $faltas[] = 'Certificado digital A1 (.pfx/.p12)';
    if (empty($cfg['aliquota_iss']))   $faltas[] = 'Alíquota do ISSQN';

    return $faltas;
}

/* =====================================================================
 * 6. CERTIFICADO A1
 * ===================================================================== */

/* ---------------------------------------------------------------------
 * 6.0  COMPATIBILIDADE OpenSSL 3  (reempacotamento de .pfx legado)
 * ---------------------------------------------------------------------
 * Muitos certificados A1 da ICP-Brasil vem cifrados com RC2-40/3DES,
 * algoritmos que o OpenSSL 3 moveu para o provider "legacy" e desabilitou
 * por padrao. Nesses casos openssl_pkcs12_read() falha com
 * "error:0308010C ... unsupported". Em vez de exigir intervencao manual,
 * reempacotamos o .pfx para AES-256 (PBES2/PBKDF2) usando o binario
 * openssl (que o proprio XAMPP ja inclui) e seguimos normalmente.
 * ------------------------------------------------------------------- */

/**
 * Localiza um binario openssl utilizavel. Devolve o caminho ou null.
 * Pode ser fixado via constante NFSE_OPENSSL_BIN.
 */
function nfse_openssl_bin(): ?string
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    $candidatos = [];
    if (defined('NFSE_OPENSSL_BIN') && NFSE_OPENSSL_BIN) {
        $candidatos[] = NFSE_OPENSSL_BIN;
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        $candidatos[] = 'C:\\xampp\\apache\\bin\\openssl.exe';
        $candidatos[] = dirname(PHP_BINARY) . '\\openssl.exe';
        $candidatos[] = 'openssl.exe';
        $candidatos[] = 'openssl';
    } else {
        $candidatos[] = '/usr/bin/openssl';
        $candidatos[] = 'openssl';
    }

    foreach ($candidatos as $bin) {
        [$rc] = nfse_exec($bin, ['version'], null, false);
        if ($rc === 0) {
            return $cache = $bin;
        }
    }

    return $cache = null;
}

/**
 * Executa um binario externo SEM shell (proc_open com args em array,
 * evitando problemas de escape/quoting no Windows).
 *
 * @param array<int,string>         $args
 * @param array<string,string>|null $env  Variaveis extras (senha via env:).
 * @return array{0:int,1:string,2:string}  [returnCode, stdout, stderr]
 */
function nfse_exec(string $bin, array $args, ?array $env = null, bool $capturar = true): array
{
    $cmd  = array_merge([$bin], $args);
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    // env explicito no Windows SUBSTITUI o ambiente inteiro; reinjeta o essencial.
    $envFinal = null;
    if ($env !== null) {
        $envFinal = $env;
        foreach (['PATH', 'Path', 'SystemRoot', 'windir', 'TEMP', 'TMP', 'COMSPEC'] as $k) {
            $v = getenv($k);
            if ($v !== false && !isset($envFinal[$k])) {
                $envFinal[$k] = $v;
            }
        }
    }

    $proc = @proc_open($cmd, $spec, $pipes, null, $envFinal);
    if (!is_resource($proc)) {
        return [127, '', 'Nao foi possivel iniciar o processo: ' . $bin];
    }

    fclose($pipes[0]);
    $out = $capturar ? stream_get_contents($pipes[1]) : '';
    $err = $capturar ? stream_get_contents($pipes[2]) : '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);

    return [$rc, (string) $out, (string) $err];
}

/**
 * Garante que o .pfx seja legivel pelo OpenSSL 3 do PHP. Se usar cifra
 * legada, reempacota para AES-256 e devolve o novo binario.
 *
 * @return array{0:string,1:bool}  [pfxBinario, foiReempacotado]
 */
function nfse_normalizar_pfx(string $pfxBinario, string $senha): array
{
    // Caminho rapido: certificados modernos caem aqui.
    $tmp = [];
    if (@openssl_pkcs12_read($pfxBinario, $tmp, $senha)) {
        return [$pfxBinario, false];
    }

    // So reempacota se o motivo for cifra nao suportada; senao devolve o
    // original para o chamador tratar (senha errada, arquivo corrompido...).
    $erros = '';
    while ($m = openssl_error_string()) {
        $erros .= $m . '; ';
    }
    $ehLegado = str_contains($erros, 'unsupported')
        || str_contains($erros, 'digital envelope routines')
        || str_contains($erros, '0308010C');

    if (!$ehLegado) {
        return [$pfxBinario, false];
    }

    $openssl = nfse_openssl_bin();
    if ($openssl === null) {
        throw new RuntimeException(
            'Este certificado usa cifra legada (RC2-40/3DES) que o OpenSSL 3 do PHP recusa, ' .
            'e nao encontrei o binario "openssl" para reempacota-lo automaticamente. ' .
            'Inclua o openssl no PATH (o XAMPP traz em C:\\xampp\\apache\\bin\\openssl.exe) ' .
            'ou defina a constante NFSE_OPENSSL_BIN com o caminho completo.'
        );
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atlas_nfse';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $sfx  = bin2hex(random_bytes(8));
    $fIn  = $dir . DIRECTORY_SEPARATOR . "in_{$sfx}.pfx";
    $fPem = $dir . DIRECTORY_SEPARATOR . "mid_{$sfx}.pem";   // chave em claro: apagado no finally
    $fOut = $dir . DIRECTORY_SEPARATOR . "out_{$sfx}.pfx";

    // Senha via env: (nao aparece na lista de processos).
    $env = ['NFSE_PFX_PASSIN' => $senha, 'NFSE_PFX_PASSOUT' => $senha];

    try {
        if (file_put_contents($fIn, $pfxBinario) === false) {
            throw new RuntimeException('Nao foi possivel gravar o arquivo temporario do certificado.');
        }

        // Passo 1: .pfx legado -> PEM (provider legacy explicito).
        [$rc, , $err] = nfse_exec($openssl, [
            'pkcs12', '-legacy', '-in', $fIn, '-nodes',
            '-passin', 'env:NFSE_PFX_PASSIN', '-out', $fPem,
        ], $env);

        // OpenSSL 1.1.x nao conhece "-legacy": repete sem a flag.
        if ($rc !== 0 && stripos($err, 'legacy') !== false) {
            [$rc, , $err] = nfse_exec($openssl, [
                'pkcs12', '-in', $fIn, '-nodes',
                '-passin', 'env:NFSE_PFX_PASSIN', '-out', $fPem,
            ], $env);
        }
        if ($rc !== 0 || !is_file($fPem) || filesize($fPem) === 0) {
            throw new RuntimeException(
                'Falha ao ler o certificado legado (senha incorreta ou arquivo invalido). ' . trim($err)
            );
        }

        // Passo 2: PEM -> .pfx moderno (AES-256-CBC + MAC sha256).
        [$rc, , $err] = nfse_exec($openssl, [
            'pkcs12', '-export', '-in', $fPem,
            '-passout', 'env:NFSE_PFX_PASSOUT', '-out', $fOut,
            '-keypbe', 'AES-256-CBC', '-certpbe', 'AES-256-CBC', '-macalg', 'sha256',
        ], $env);
        if ($rc !== 0 || !is_file($fOut) || filesize($fOut) === 0) {
            throw new RuntimeException('Falha ao reempacotar o certificado para cifra moderna. ' . trim($err));
        }

        $novo = file_get_contents($fOut);
        if ($novo === false || $novo === '') {
            throw new RuntimeException('O certificado reempacotado ficou vazio.');
        }

        // Confirma que agora o PHP le.
        $chk = [];
        if (!openssl_pkcs12_read($novo, $chk, $senha)) {
            throw new RuntimeException('O certificado reempacotado ainda nao pode ser lido pelo PHP.');
        }

        return [$novo, true];
    } finally {
        foreach ([$fIn, $fPem, $fOut] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
}

/**
 * Lê o .pfx, valida a senha, extrai titular/validade e devolve os metadados.
 * NÃO grava nada — quem grava é nfse_salvar_certificado().
 */
function nfse_inspecionar_pfx(string $pfxBinario, string $senha): array
{
    // Compat. OpenSSL 3: reempacota automaticamente .pfx com cifra legada (RC2-40).
    [$pfxBinario] = nfse_normalizar_pfx($pfxBinario, $senha);

    $certs = [];
    if (!openssl_pkcs12_read($pfxBinario, $certs, $senha)) {
        $erros = '';
        while ($m = openssl_error_string()) {
            $erros .= $m . '; ';
        }

        if (str_contains($erros, 'key too small')) {
            throw new RuntimeException('Certificado com chave inferior a 2048 bits, rejeitado pelas políticas de segurança. Use um A1 atual.');
        }

        // Fallback: normalmente nfse_normalizar_pfx() ja resolveu o RC2-40 acima.
        if (str_contains($erros, 'unsupported') || str_contains($erros, 'digital envelope routines')) {
            throw new RuntimeException(
                'Este .pfx usa cifra legada (RC2-40) e o reempacotamento automatico nao pode ser aplicado. ' .
                'Confirme se o binario openssl esta acessivel (constante NFSE_OPENSSL_BIN). ' .
                'Detalhe OpenSSL: ' . trim($erros)
            );
        }

        throw new RuntimeException('Senha incorreta ou arquivo .pfx inválido/corrompido. Detalhe OpenSSL: ' . trim($erros));
    }

    $info = openssl_x509_parse($certs['cert']);
    if (!$info) {
        throw new RuntimeException('Não foi possível ler os dados do certificado.');
    }

    $titular = $info['subject']['CN'] ?? '(desconhecido)';
    $validoAte = isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : 0;
    $validoDe  = isset($info['validFrom_time_t']) ? (int) $info['validFrom_time_t'] : 0;

    // Extrai o CPF/CNPJ do titular (OID 2.16.76.1.3.3 = CNPJ, 2.16.76.1.3.1 = CPF+dados)
    $docTitular = null;
    if (preg_match('/(\d{11,14})$/', preg_replace('/\D/', '', $titular), $m)) {
        $docTitular = $m[1];
    }

    return [
        'titular'    => $titular,
        'doc'        => $docTitular,
        'valido_de'  => $validoDe ? date('Y-m-d H:i:s', $validoDe) : null,
        'valido_ate' => $validoAte ? date('Y-m-d H:i:s', $validoAte) : null,
        'vencido'    => $validoAte > 0 && time() > $validoAte,
        'dias_para_vencer' => $validoAte > 0 ? (int) floor(($validoAte - time()) / 86400) : null,
    ];
}

function nfse_salvar_certificado(string $pfxBinario, string $senha, string $nomeArquivo): array
{
    // Normaliza uma unica vez e guarda o .pfx ja compativel com o OpenSSL 3.
    [$pfxBinario, $reempacotado] = nfse_normalizar_pfx($pfxBinario, $senha);

    $meta = nfse_inspecionar_pfx($pfxBinario, $senha);
    $meta['reempacotado'] = $reempacotado;

    if ($meta['vencido']) {
        throw new RuntimeException('Certificado vencido em ' . date('d/m/Y', strtotime($meta['valido_ate'])) . '.');
    }

    nfse_migrar();
    $pdo = nfse_pdo();
    $st  = $pdo->prepare(
        "UPDATE nfse_config
            SET cert_blob = :blob, cert_senha = :senha, cert_nome = :nome,
                cert_titular = :titular, cert_validade = :validade,
                atualizado_em = NOW(), atualizado_por = :usr
          WHERE id = 1"
    );
    $st->execute([
        ':blob'     => nfse_encrypt($pfxBinario),
        ':senha'    => nfse_encrypt($senha),
        ':nome'     => $nomeArquivo,
        ':titular'  => $meta['titular'],
        ':validade' => $meta['valido_ate'],
        ':usr'      => $_SESSION['username'] ?? 'sistema',
    ]);

    nfse_log('certificado', 'Certificado A1 instalado: ' . $meta['titular'], 'info');

    return $meta;
}

function nfse_remover_certificado(): void
{
    nfse_migrar();
    nfse_pdo()->exec(
        "UPDATE nfse_config
            SET cert_blob = NULL, cert_senha = NULL, cert_nome = NULL,
                cert_titular = NULL, cert_validade = NULL, ativo = 0
          WHERE id = 1"
    );
    nfse_log('certificado', 'Certificado A1 removido; emissão desativada.', 'warn');
}

/* =====================================================================
 * 7. CONTEXTO DO SDK
 * ===================================================================== */

function nfse_context(?array $cfg = null): \Nfse\Http\NfseContext
{
    if (!nfse_autoload()) {
        throw new RuntimeException('SDK nfse-nacional/nfse-php não instalado. Rode "composer install" na pasta os/nfse.');
    }

    $cfg = $cfg ?: nfse_config(true);

    if (empty($cfg['cert_blob'])) {
        throw new RuntimeException('Nenhum certificado A1 configurado.');
    }

    $pfx   = nfse_decrypt($cfg['cert_blob']);
    $senha = nfse_decrypt($cfg['cert_senha']) ?? '';

    // Certificados gravados antes desta correcao podem estar com cifra legada.
    [$pfx] = nfse_normalizar_pfx($pfx, $senha);

    $ambiente = ($cfg['ambiente'] === '1')
        ? \Nfse\Enums\TipoAmbiente::Producao
        : \Nfse\Enums\TipoAmbiente::Homologacao;

    return new \Nfse\Http\NfseContext(
        ambiente: $ambiente,
        certificatePath: null,
        certificatePassword: $senha,
        codigoMunicipio: $cfg['cod_municipio'] ?: null,
        certificateContent: $pfx
    );
}

/**
 * Instancia o cliente do SDK garantindo que o autoloader do modulo ja esteja
 * registrado ANTES da resolucao da classe do cliente.
 *
 * Detalhe critico: ao passar o contexto como argumento do construtor, o PHP
 * resolve (e autoloada) a classe do cliente ANTES de avaliar o argumento -- e
 * o registro do autoloader do modulo acontece dentro de nfse_context() via
 * nfse_autoload(). Se o vendor/ da raiz do Atlas for carregado primeiro, a
 * classe nao existe nesse instante. Chamar nfse_autoload() aqui, numa
 * instrucao anterior ao new, elimina o problema. Sem type-hint de retorno de
 * proposito, para nao forcar a resolucao antecipada da classe.
 */
function nfse_cliente(?array $cfg = null)
{
    nfse_autoload();
    $contexto = nfse_context($cfg);
    return new \Nfse\Nfse($contexto);
}

/* =====================================================================
 * 8. APURAÇÃO DOS VALORES DA O.S.
 * ---------------------------------------------------------------------
 * Fonte: ordens_de_servico_itens (mesmos valores do orçamento e da
 * liquidação — evita divergência de centavos).
 *
 * Regras:
 *  - o pseudo-item ato='ISS' é o repasse do próprio imposto ao usuário;
 *    nunca entra no valor do serviço;
 *  - atos marcados "(isento)" e os pseudo-atos 0/00/9999 não geram
 *    receita e ficam de fora da base;
 *  - a base do ISSQN é o emolumento (receita do delegatário). FERC,
 *    FADEP, FEMP e FERRFIS são repasses a fundos e não compõem receita.
 * ===================================================================== */

function nfse_ato_ignorado(string $ato): bool
{
    if (stripos($ato, '(isento)') !== false) {
        return true;
    }
    return in_array(trim($ato), ['0', '00', '9999', 'ISS'], true);
}

function nfse_apurar_os(int $osId, ?array $cfg = null): array
{
    $cfg = $cfg ?: nfse_config();
    $pdo = nfse_pdo();

    $os = $pdo->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $os->execute([$osId]);
    $os = $os->fetch(PDO::FETCH_ASSOC);
    if (!$os) {
        throw new RuntimeException("Ordem de Serviço {$osId} não encontrada.");
    }

    $it = $pdo->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ? ORDER BY COALESCE(ordem_exibicao, id), id");
    $it->execute([$osId]);
    $itens = $it->fetchAll(PDO::FETCH_ASSOC);

    $emolumentos = 0.0;
    $taxas       = 0.0;
    $issRepasse  = 0.0;
    $linhas      = [];
    $pendentes   = 0;

    foreach ($itens as $item) {
        $ato = (string) ($item['ato'] ?? '');
        $qtd = (int) $item['quantidade'];
        $liq = (int) $item['quantidade_liquidada'];

        if ($liq < $qtd) {
            $pendentes++;
        }

        if (trim($ato) === 'ISS') {
            $issRepasse += (float) $item['total'];
            continue;
        }
        if (nfse_ato_ignorado($ato)) {
            continue;
        }
        if ($liq <= 0) {
            continue; // ainda não prestado -> não é fato gerador
        }

        // Rateio cumulativo idêntico ao de liquidar_os.php (evita 1 centavo de diferença)
        $rateio = static function ($valorTotalItem) use ($qtd, $liq) {
            $v = (float) $valorTotalItem;
            if ($qtd <= 0) {
                return round($v, 2);
            }
            return round($v * $liq / $qtd, 2);
        };

        $e = $rateio($item['emolumentos']);
        $t = $rateio($item['ferc']) + $rateio($item['fadep']) + $rateio($item['femp']) + $rateio($item['ferrfis'] ?? 0);

        $emolumentos += $e;
        $taxas       += $t;

        $linhas[] = [
            'ato'         => $ato,
            'quantidade'  => $liq,
            'descricao'   => trim((string) $item['descricao']),
            'emolumentos' => $e,
            'taxas'       => round($t, 2),
            'total'       => $rateio($item['total']),
        ];
    }

    $emolumentos = round($emolumentos, 2);
    $taxas       = round($taxas, 2);
    $issRepasse  = round($issRepasse, 2);

    // Valor do serviço declarado na NFS-e
    $valorServico = match ($cfg['base_calculo'] ?? 'emolumentos') {
        'emolumentos_taxas' => round($emolumentos + $taxas, 2),
        'total'             => round($emolumentos + $taxas + $issRepasse, 2),
        default             => $emolumentos,
    };

    $pReducao     = (float) ($cfg['reducao_base'] ?? 0);
    $valorReducao = round($valorServico * $pReducao / 100, 2);
    $baseCalculo  = round($valorServico - $valorReducao, 2);
    $aliquota     = (float) ($cfg['aliquota_iss'] ?? 0);
    $valorIss     = round($baseCalculo * $aliquota / 100, 2);

    return [
        'os'            => $os,
        'itens'         => $linhas,
        'itens_pendentes' => $pendentes,
        'totalmente_liquidada' => ($pendentes === 0 && count($linhas) > 0),
        'emolumentos'   => $emolumentos,
        'taxas'         => $taxas,
        'iss_repassado' => $issRepasse,
        'valor_servico' => $valorServico,
        'p_reducao'     => $pReducao,
        'valor_reducao' => $valorReducao,
        'base_calculo'  => $baseCalculo,
        'aliquota'      => $aliquota,
        'valor_iss'     => $valorIss,
    ];
}

/**
 * Discriminação do serviço (xDescServ, até 2000 caracteres).
 * O regime transitório exige descrição objetiva e individualizada do ato
 * praticado, em conformidade com a Tabela de Emolumentos.
 */
function nfse_discriminacao(array $apuracao, ?array $linha = null): string
{
    $os = $apuracao['os'];

    if ($linha !== null) {
        $txt = sprintf(
            'O.S. n. %d — Ato %s: %s (qtd. %d). Emolumentos R$ %s',
            (int) $os['id'],
            $linha['ato'],
            $linha['descricao'],
            $linha['quantidade'],
            number_format($linha['emolumentos'], 2, ',', '.')
        );
        if ($linha['taxas'] > 0) {
            $txt .= '; taxas e fundos R$ ' . number_format($linha['taxas'], 2, ',', '.');
        }
        return mb_substr($txt, 0, 2000, 'UTF-8');
    }

    $partes = ['Serviços notariais e de registro — Ordem de Serviço n. ' . (int) $os['id'] . '. Atos praticados:'];

    foreach ($apuracao['itens'] as $l) {
        $partes[] = sprintf(
            '%dx %s - %s (emol. R$ %s)',
            $l['quantidade'],
            $l['ato'],
            $l['descricao'],
            number_format($l['emolumentos'], 2, ',', '.')
        );
    }

    $partes[] = sprintf(
        'Emolumentos: R$ %s | Taxas/fundos (FERC, FADEP, FEMP, FERRFIS): R$ %s.',
        number_format($apuracao['emolumentos'], 2, ',', '.'),
        number_format($apuracao['taxas'], 2, ',', '.')
    );

    return mb_substr(implode(' ', $partes), 0, 2000, 'UTF-8');
}

/* =====================================================================
 * 9. NUMERAÇÃO ATÔMICA DA DPS
 * ===================================================================== */

function nfse_proximo_numero_dps(PDO $pdo): int
{
    $pdo->exec("UPDATE nfse_config SET ultimo_numero_dps = LAST_INSERT_ID(ultimo_numero_dps + 1) WHERE id = 1");
    return (int) $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
}

/* =====================================================================
 * 10. MONTAGEM DA DPS
 * ===================================================================== */

function nfse_so_digitos(?string $v): string
{
    return preg_replace('/\D/', '', (string) $v);
}

function nfse_montar_dps(array $cfg, array $apuracao, int $numeroDps, ?array $linha = null): array
{
    nfse_autoload();

    $doc      = nfse_so_digitos($cfg['prest_doc']);
    $codMun   = $cfg['cod_municipio'];
    $serie    = (string) $cfg['serie_dps'];
    $ambiente = (int) $cfg['ambiente'];

    $idDps = \Nfse\Support\IdGenerator::generateDpsId(
        cpfCnpj: $doc,
        codIbge: $codMun,
        serieDps: $serie,
        numDps: $numeroDps
    );

    // ------- Prestador -------
    // tpEmit = 1 => o próprio prestador emite a DPS. Nesse caso a SEFIN só
    // aceita no <prest> a identificação (CNPJ/CPF), a IM e o regTrib. Nome,
    // endereço, fone e e-mail vêm do cadastro do prestador no CNC do município
    // e NÃO podem ser informados (erros E0128, xNome do prestador etc.).
    $tpEmit = '1'; // EmitenteDPS::Prestador
    $prestEhEmitente = ($tpEmit === '1');

    $prest = [
        (strlen($doc) === 14 ? 'CNPJ' : 'CPF') => $doc,
        'regTrib' => [
            'opSimpNac'  => (string) $cfg['op_simp_nac'],   // 1 = Não optante
            'regEspTrib' => (string) $cfg['reg_esp_trib'],  // 4 = Notário ou Registrador
        ],
    ];

    // IM é aceita (e exigida por muitos municípios) mesmo com o prestador emitente.
    if (!empty($cfg['prest_im'])) {
        $prest['IM'] = $cfg['prest_im'];
    }

    // Nome, endereço, fone e e-mail só entram quando o emitente NÃO é o prestador.
    if (!$prestEhEmitente) {
        $prest['xNome'] = mb_substr((string) $cfg['prest_nome'], 0, 150, 'UTF-8');
        $prest['end'] = [
            'endNac' => [
                'cMun' => $codMun,
                'CEP'  => nfse_so_digitos($cfg['prest_cep']),
            ],
            'xLgr'    => mb_substr((string) $cfg['prest_logradouro'], 0, 255, 'UTF-8'),
            'nro'     => mb_substr((string) $cfg['prest_numero'], 0, 60, 'UTF-8'),
            'xBairro' => mb_substr((string) $cfg['prest_bairro'], 0, 60, 'UTF-8'),
        ];
        if (!empty($cfg['prest_complemento'])) {
            $prest['end']['xCpl'] = mb_substr((string) $cfg['prest_complemento'], 0, 156, 'UTF-8');
        }
        if (!empty($cfg['prest_fone'])) {
            $prest['fone'] = nfse_so_digitos($cfg['prest_fone']);
        }
        if (!empty($cfg['prest_email'])) {
            $prest['email'] = $cfg['prest_email'];
        }
    }

    // Optante do Simples Nacional exige o regime de apuração dos tributos.
    // opSimpNac: 2 = MEI, 3 = ME/EPP. Sem isso a SEFIN recusa (erro E0166).
    $opSimp = (string) $cfg['op_simp_nac'];
    if (in_array($opSimp, ['2', '3'], true)) {
        $regAp = (string) ($cfg['reg_ap_trib_sn'] ?? '');
        if (!in_array($regAp, ['1', '2', '3'], true)) {
            $regAp = ($opSimp === '2') ? '3' : '1'; // MEI => 3; ME/EPP => 1 (tudo pelo SN)
        }
        $prest['regTrib']['regApTribSN'] = $regAp;
    }

    // ------- Tomador (facultativo em 2026: "Tomador não informado") -------
    $toma = null;
    $tomadorDoc = nfse_so_digitos($apuracao['os']['cpf_cliente'] ?? '');
    $tomadorNome = trim((string) ($apuracao['os']['cliente'] ?? ''));

    $exigeTomador = !empty($cfg['identificar_tomador']) || nfse_exige_individualizacao();

    if ($tomadorDoc !== '' && (strlen($tomadorDoc) === 11 || strlen($tomadorDoc) === 14)) {
        $toma = [
            (strlen($tomadorDoc) === 14 ? 'CNPJ' : 'CPF') => $tomadorDoc,
            'xNome' => mb_substr($tomadorNome, 0, 150, 'UTF-8'),
        ];
    } elseif ($exigeTomador) {
        throw new RuntimeException(
            'A identificação do tomador é obrigatória nesta configuração (ou a partir de 01/01/2027), ' .
            'mas a O.S. não possui CPF/CNPJ válido do apresentante.'
        );
    }

    // ------- Serviço -------
    $cServ = [
        'cTribNac'   => $cfg['ctrib_nac'] ?: '210101',
        'xDescServ'  => nfse_discriminacao($apuracao, $linha),
    ];
    if (!empty($cfg['ctrib_mun'])) {
        $cServ['cTribMun'] = $cfg['ctrib_mun'];
    }
    if (!empty($cfg['cnae'])) {
        $cServ['cCNAE'] = nfse_so_digitos($cfg['cnae']);
    }
    // cIntContrib deve casar com TSCodigoInternoContribuinte = [a-zA-Z0-9]{1,20}
    // (schema nacional). O hifen quebra a validacao (erro E1235 da SEFIN), entao
    // usamos apenas caracteres alfanumericos.
    $cIntContrib = preg_replace('/[^A-Za-z0-9]/', '', 'OS' . (int) $apuracao['os']['id']);
    if ($cIntContrib === '' || $cIntContrib === null) {
        $cIntContrib = 'OS' . (int) $apuracao['os']['id'];
    }
    $cServ['cIntContrib'] = substr($cIntContrib, 0, 20);

    // ------- Valores -------
    if ($linha !== null) {
        $valorServico = match ($cfg['base_calculo'] ?? 'emolumentos') {
            'emolumentos_taxas' => round($linha['emolumentos'] + $linha['taxas'], 2),
            'total'             => round($linha['total'], 2),
            default             => round($linha['emolumentos'], 2),
        };
    } else {
        $valorServico = $apuracao['valor_servico'];
    }

    $pReducao     = (float) $apuracao['p_reducao'];
    $valorReducao = round($valorServico * $pReducao / 100, 2);
    $baseCalculo  = round($valorServico - $valorReducao, 2);
    $valorIss     = round($baseCalculo * (float) $apuracao['aliquota'] / 100, 2);

    // Forma de aplicar a redução de base:
    //  'grupo'    -> envia vServ cheio + grupo vDedRed/pDR (transparente);
    //  'embutida' -> reduz o próprio vServ e NÃO envia vDedRed. Necessário para
    //                municípios que recusam o grupo de dedução (erro E0440).
    $modoReducao     = (string) ($cfg['reducao_modo'] ?? 'grupo');
    $reducaoEmbutida = ($modoReducao === 'embutida' && $pReducao > 0);
    $vServEnviado     = $reducaoEmbutida ? $baseCalculo : $valorServico;
    $valorReducaoNota = $reducaoEmbutida ? 0.0 : $valorReducao;

    // Alíquota (pAliq): para municípios ADERENTES ao ambiente nacional (convênio
    // ATIVO), a alíquota é definida pela parametrização do CNC e NÃO pode ser
    // informada na DPS. A SEFIN recusa em todos os cenários já observados:
    //  - prestador com regime especial de tributação (regEspTrib != 0);
    //  - optante do Simples com ISSQN fora do SN (erro E0635);
    //  - não optante, opSimpNac = 1 (erro E0617).
    // Como só emitimos via emissor nacional para municípios aderentes, NÃO
    // enviamos pAliq. A alíquota configurada continua usada apenas no cálculo
    // local do valor do ISS (exibição/registro) e deve coincidir com a do CNC.
    $opSimp = (string) ($cfg['op_simp_nac'] ?? '1'); // usado adiante (totTrib)

    $tribMun = [
        'tribISSQN'  => 1,  // Operação tributável
        'tpRetISSQN' => 1,  // Não retido — o prestador é o contribuinte
    ];

    $trib = [
        'tribMun' => $tribMun,
        'tribFed' => [
            'piscofins' => ['CST' => $cfg['cst_piscofins'] ?: '08'],
        ],
    ];
    // totTrib é obrigatório no XSD (é um choice). Não optante: indTotTrib = 0.
    // Optante do Simples: o indTotTrib é proibido (E0712); a via válida é o
    // percentual total de tributos do SN (pTotTribSN), que deve ser > 0.
    if (!in_array($opSimp, ['2', '3'], true)) {
        $trib['totTrib'] = ['indTotTrib' => 0];
    } else {
        $pTotSN = (float) ($cfg['p_tot_trib_sn'] ?? 0);
        if ($pTotSN <= 0) {
            $pTotSN = 6.00; // fallback; ajuste para a alíquota efetiva do Simples
        }
        $trib['totTrib'] = ['pTotTribSN' => $pTotSN];
    }

    $valores = [
        'vServPrest' => ['vServ' => $vServEnviado],
        'trib' => $trib,
    ];

    if ($pReducao > 0 && !$reducaoEmbutida) {
        $valores['vDedRed'] = ['pDR' => $pReducao];
    }

    $infDps = [
        '@attributes' => ['Id' => $idDps],
        'tpAmb'    => (string) $ambiente,   // TipoAmbiente é string-backed
        'dhEmi'    => date('c'),
        'verAplic' => ATLAS_NFSE_VERSAO,
        'serie'    => $serie,
        'nDPS'     => (string) $numeroDps,
        'dCompet'  => date('Y-m-d'),
        'tpEmit'   => $tpEmit, // EmitenteDPS::Prestador (string-backed)
        'cLocEmi'  => $codMun,
        'prest'    => $prest,
        'serv'     => [
            'locPrest' => ['cLocPrestacao' => $codMun],
            'cServ'    => $cServ,
        ],
        'valores'  => $valores,
    ];

    if ($toma !== null) {
        $infDps['toma'] = $toma;
    }

    $dps = new \Nfse\Dto\Nfse\DpsData([
        '@attributes' => ['versao' => '1.00'],
        'infDPS'      => $infDps,
    ]);

    return [
        'dps'           => $dps,
        'id_dps'        => $idDps,
        'valor_servico' => $vServEnviado,
        'valor_reducao' => $valorReducaoNota,
        'base_calculo'  => $baseCalculo,
        'valor_iss'     => $valorIss,
        'tomador_doc'   => $tomadorDoc ?: null,
        'tomador_nome'  => $tomadorNome ?: null,
        'discriminacao' => $cServ['xDescServ'],
    ];
}

/**
 * A partir de 01/01/2027 o regime transitório do exercício de 2026
 * termina: a NFS-e passa a ser individualizada por ato, com tomador
 * identificado.
 */
function nfse_exige_individualizacao(): bool
{
    return time() >= mktime(0, 0, 0, 1, 1, 2027);
}

/* =====================================================================
 * 11. EMISSÃO
 * ===================================================================== */

function nfse_nota_existente(int $osId): ?array
{
    $pdo = nfse_pdo();
    $st  = $pdo->prepare(
        "SELECT * FROM nfse_notas
          WHERE ordem_servico_id = ? AND status IN ('autorizada','processando')
          ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$osId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/**
 * Emite a(s) NFS-e da O.S.
 *
 * @param  bool $forcar Ignora a checagem de "já existe nota autorizada".
 * @return array{ok:bool, notas:array, mensagem:string}
 */
function nfse_emitir_os(int $osId, bool $forcar = false): array
{
    nfse_migrar();

    // Trava por O.S.: dois cliques simultâneos (ou hook + botão) não podem
    // gerar duas DPS para o mesmo fato gerador.
    $pdoLock = nfse_pdo();
    $lockName = 'atlas_nfse_os_' . $osId;
    $obteve = (int) $pdoLock->query("SELECT GET_LOCK(" . $pdoLock->quote($lockName) . ", 10)")->fetchColumn();

    if ($obteve !== 1) {
        return ['ok' => false, 'notas' => [], 'mensagem' => 'Já existe uma emissão em andamento para esta O.S. Aguarde.'];
    }

    try {
        return nfse_emitir_os_interno($osId, $forcar);
    } finally {
        $pdoLock->query("SELECT RELEASE_LOCK(" . $pdoLock->quote($lockName) . ")");
    }
}

function nfse_emitir_os_interno(int $osId, bool $forcar = false): array
{
    $cfg = nfse_config(true);

    if (empty($cfg['ativo'])) {
        return ['ok' => false, 'notas' => [], 'mensagem' => 'Emissão de NFS-e desativada nas configurações.'];
    }

    $faltas = nfse_pendencias($cfg);
    if ($faltas) {
        return ['ok' => false, 'notas' => [], 'mensagem' => 'Configuração incompleta: ' . implode(', ', $faltas) . '.'];
    }

    if (!$forcar && ($existente = nfse_nota_existente($osId))) {
        return [
            'ok'       => false,
            'notas'    => [$existente],
            'mensagem' => 'Esta O.S. já possui NFS-e (' . $existente['status'] . ') sob a chave ' . ($existente['chave_acesso'] ?: '—') . '.',
        ];
    }

    $apuracao = nfse_apurar_os($osId, $cfg);

    if (!$apuracao['totalmente_liquidada']) {
        return [
            'ok'       => false,
            'notas'    => [],
            'mensagem' => 'A NFS-e só pode ser emitida após a liquidação de todos os atos (o depósito prévio não é fato gerador).',
        ];
    }

    if ($apuracao['valor_servico'] <= 0) {
        nfse_log('emissao', "O.S. {$osId} sem valor tributável (ato gratuito/isento). Emissão dispensada.", 'info', $osId);
        return ['ok' => false, 'notas' => [], 'mensagem' => 'O.S. sem valor tributável (ato gratuito ou isento). Nada a emitir.'];
    }

    $pdo = nfse_pdo();
    $nfse = nfse_cliente($cfg);

    $individual = ($cfg['modo_emissao'] === 'individualizado') || nfse_exige_individualizacao();
    $lotes = $individual ? $apuracao['itens'] : [null];

    $notas = [];
    $erros = [];

    foreach ($lotes as $linha) {
        $numero = nfse_proximo_numero_dps($pdo);

        try {
            $montado = nfse_montar_dps($cfg, $apuracao, $numero, $linha);
        } catch (Throwable $e) {
            $erros[] = $e->getMessage();
            continue;
        }

        // Grava a intenção antes de sair para a rede (rastreabilidade)
        $ins = $pdo->prepare(
            "INSERT INTO nfse_notas
             (ordem_servico_id, ambiente, serie, numero_dps, id_dps, status,
              valor_servico, valor_reducao, base_calculo, aliquota, valor_iss,
              tomador_doc, tomador_nome, discriminacao, criado_em, criado_por)
             VALUES (:os, :amb, :serie, :num, :iddps, 'processando',
                     :vs, :vr, :bc, :aliq, :iss, :tdoc, :tnome, :disc, NOW(), :usr)"
        );
        $ins->execute([
            ':os'    => $osId,
            ':amb'   => $cfg['ambiente'],
            ':serie' => $cfg['serie_dps'],
            ':num'   => $numero,
            ':iddps' => $montado['id_dps'],
            ':vs'    => $montado['valor_servico'],
            ':vr'    => $montado['valor_reducao'],
            ':bc'    => $montado['base_calculo'],
            ':aliq'  => $apuracao['aliquota'],
            ':iss'   => $montado['valor_iss'],
            ':tdoc'  => $montado['tomador_doc'],
            ':tnome' => $montado['tomador_nome'],
            ':disc'  => $montado['discriminacao'],
            ':usr'   => $_SESSION['username'] ?? 'sistema',
        ]);
        $notaId = (int) $pdo->lastInsertId();

        try {
            $resultado = $nfse->contribuinte()->emitir($montado['dps']);

            $chave = nfse_chave50($resultado->infNfse->id ?? null) ?: null;
            $upd = $pdo->prepare(
                "UPDATE nfse_notas
                    SET status = 'autorizada', chave_acesso = :chave, numero_nfse = :num,
                        cod_verificacao = :cv, xml_nfse = :xml, mensagem = NULL
                  WHERE id = :id"
            );
            $upd->execute([
                ':chave' => $chave,
                ':num'   => $resultado->infNfse->numeroNfse ?? null,
                ':cv'    => $resultado->infNfse->codigoVerificacao ?? null,
                ':xml'   => $resultado->nfseXml ?? null,
                ':id'    => $notaId,
            ]);

            nfse_log('emissao', "NFS-e autorizada. Chave: {$chave}", 'info', $osId, $notaId);
            $notas[] = ['id' => $notaId, 'chave' => $chave, 'status' => 'autorizada'];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $pdo->prepare("UPDATE nfse_notas SET status = 'rejeitada', mensagem = :m WHERE id = :id")
                ->execute([':m' => mb_substr($msg, 0, 4000, 'UTF-8'), ':id' => $notaId]);

            nfse_log('emissao', 'Falha: ' . $msg, 'error', $osId, $notaId);
            $erros[] = $msg;
        }
    }

    if ($notas && !$erros) {
        return ['ok' => true, 'notas' => $notas, 'mensagem' => count($notas) . ' NFS-e autorizada(s).'];
    }
    if ($notas && $erros) {
        return ['ok' => true, 'notas' => $notas, 'mensagem' => 'Emissão parcial. Erros: ' . implode(' | ', $erros)];
    }

    return ['ok' => false, 'notas' => [], 'mensagem' => implode(' | ', $erros) ?: 'Falha desconhecida na emissão.'];
}

/**
 * Hook para ser chamado após a liquidação (best-effort, nunca lança).
 */
function nfse_hook_pos_liquidacao(int $osId): void
{
    try {
        $cfg = nfse_config();
        if (empty($cfg['ativo']) || empty($cfg['emissao_automatica'])) {
            return;
        }

        $apuracao = nfse_apurar_os($osId, $cfg);
        if (!$apuracao['totalmente_liquidada']) {
            return; // ainda há atos pendentes
        }

        nfse_emitir_os($osId);
    } catch (Throwable $e) {
        error_log('[nfse_hook_pos_liquidacao] ' . $e->getMessage());
        nfse_log('hook', $e->getMessage(), 'error', $osId);
    }
}

/* =====================================================================
 * 12. CANCELAMENTO (evento 101101)
 * ---------------------------------------------------------------------
 * cMotivo: 1 - Erro na Emissão | 2 - Serviço não Prestado | 9 - Outros
 * Fora do prazo de cancelamento direto, o município exige análise fiscal.
 * ===================================================================== */

function nfse_cancelar(int $notaId, string $cMotivo, string $xMotivo): array
{
    nfse_migrar();
    $cfg = nfse_config(true);
    $pdo = nfse_pdo();

    $st = $pdo->prepare("SELECT * FROM nfse_notas WHERE id = ?");
    $st->execute([$notaId]);
    $nota = $st->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        throw new RuntimeException('NFS-e não encontrada.');
    }
    if ($nota['status'] !== 'autorizada') {
        throw new RuntimeException('Só é possível cancelar NFS-e autorizada (status atual: ' . $nota['status'] . ').');
    }
    if (!in_array($cMotivo, ['1', '2', '9'], true)) {
        throw new RuntimeException('Motivo inválido. Use 1 (erro na emissão), 2 (serviço não prestado) ou 9 (outros).');
    }
    if ($cMotivo === '9' && trim($xMotivo) === '') {
        throw new RuntimeException('Para o motivo "Outros" a justificativa é obrigatória.');
    }

    $doc = nfse_so_digitos($cfg['prest_doc']);

    // A chave de acesso é gravada a partir do Id da NFS-e ("NFS" + 50 dígitos),
    // mas o elemento chNFSe e o Id do evento (PRE[0-9]{56}) exigem só os 50
    // dígitos. Removemos o prefixo/qualquer não-dígito.
    $chNFSe = preg_replace('/\D/', '', (string) $nota['chave_acesso']);
    if (strlen((string) $chNFSe) !== 50) {
        throw new RuntimeException('Chave de acesso da NFS-e inválida para cancelamento (esperado 50 dígitos).');
    }

    $evento = [
        'versao' => '1.01',
        'infPedReg' => [
            'tpAmb'      => (int) $cfg['ambiente'],
            'verAplic'   => ATLAS_NFSE_VERSAO,
            'dhEvento'   => date('c'),
            'chNFSe'     => $chNFSe,
            'tipoEvento' => '101101',
            'e101101'    => [
                'xDesc'   => 'Cancelamento de NFS-e',
                'cMotivo' => $cMotivo,
                'xMotivo' => mb_substr(trim($xMotivo), 0, 255, 'UTF-8') ?: 'Cancelamento solicitado pelo prestador.',
            ],
        ],
    ];

    if (strlen($doc) === 14) {
        $evento['infPedReg']['CNPJAutor'] = $doc;
    } else {
        $evento['infPedReg']['CPFAutor'] = $doc;
    }

    $nfse = nfse_cliente($cfg);
    $resposta = $nfse->contribuinte()->cancelar(new \Nfse\Dto\Nfse\PedRegEventoData($evento));

    $pdo->prepare(
        "UPDATE nfse_notas
            SET status = 'cancelada', cancel_motivo = :m, cancelada_em = NOW()
          WHERE id = :id"
    )->execute([
        ':m'  => "[{$cMotivo}] " . mb_substr($xMotivo, 0, 200, 'UTF-8'),
        ':id' => $notaId,
    ]);

    nfse_log('cancelamento', "NFS-e {$nota['chave_acesso']} cancelada. Motivo {$cMotivo}.", 'warn', (int) $nota['ordem_servico_id'], $notaId);

    return ['ok' => true, 'resposta' => $resposta];
}

/* =====================================================================
 * 13. CONSULTAS
 * ===================================================================== */

/**
 * Normaliza a chave de acesso da NFS-e para os 50 dígitos exigidos pelas APIs
 * do Ambiente Nacional e pelo portal. A chave costuma vir do Id da NFS-e
 * ("NFS" + 50 dígitos); aqui removemos o prefixo/qualquer caractere não numérico.
 */
function nfse_chave50(?string $chave): string
{
    return preg_replace('/\D/', '', (string) $chave);
}

function nfse_consultar_chave(string $chave): ?object
{
    $nfse = nfse_cliente();
    return $nfse->contribuinte()->consultar(nfse_chave50($chave));
}

/**
 * Reúne os dados necessários para imprimir a NFS-e (DANFSe A4 e recibo térmico):
 * a nota, a configuração do prestador, a chave de 50 dígitos, a URL de consulta
 * pública do Portal Nacional e os indicadores de homologação/cancelamento.
 */
function nfse_nota_impressao(int $notaId): array
{
    nfse_migrar();
    $cfg = nfse_config(false);

    $st = nfse_pdo()->prepare("SELECT * FROM nfse_notas WHERE id = ?");
    $st->execute([$notaId]);
    $nota = $st->fetch(PDO::FETCH_ASSOC);
    if (!$nota) {
        throw new RuntimeException('NFS-e não encontrada.');
    }

    $chave = nfse_chave50($nota['chave_acesso'] ?? '');
    // URL de consulta pública do Portal Nacional (conteúdo do QR Code) — NT 008/2026.
    $consultaUrl = $chave !== ''
        ? 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . $chave
        : '';

    return [
        'nota'         => $nota,
        'cfg'          => $cfg,
        'chave'        => $chave,
        'consulta_url' => $consultaUrl,
        'homologacao'  => ((string) ($nota['ambiente'] ?? '2')) === '2',
        'cancelada'    => ($nota['status'] ?? '') === 'cancelada',
    ];
}

function nfse_notas_da_os(int $osId): array
{
    nfse_migrar();
    $st = nfse_pdo()->prepare(
        "SELECT id, ordem_servico_id, ambiente, serie, numero_dps, chave_acesso,
                numero_nfse, status, valor_servico, base_calculo, aliquota, valor_iss,
                mensagem, cancel_motivo, criado_em
           FROM nfse_notas
          WHERE ordem_servico_id = ?
          ORDER BY id DESC"
    );
    $st->execute([$osId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se o município aderiu ao Ambiente Nacional (parâmetros de convênio).
 */
function nfse_testar_convenio(?string $codMunicipio = null): array
{
    $cfg = nfse_config(true);
    $cod = $codMunicipio ?: $cfg['cod_municipio'];

    if (!$cod) {
        throw new RuntimeException('Informe o código IBGE do município.');
    }

    $nfse = nfse_cliente($cfg);
    $params = $nfse->contribuinte()->consultarParametrosConvenio($cod);

    return ['ok' => true, 'parametros' => $params];
}

/**
 * Alíquota vigente do serviço 210101 no município, direto do Ambiente Nacional.
 */
/**
 * Formata o código de tributação nacional para o formato exigido pela consulta
 * de alíquota: 9 dígitos em 00.00.00.000. O cTribNac tem 6 dígitos (ex.: 210101
 * = 21.01.01); completamos o desdobramento com 000 quando ausente.
 */
function nfse_formatar_cod_servico(string $codigo): string
{
    $d = preg_replace('/\D/', '', $codigo);
    if (strlen($d) === 6) {
        $d .= '000';
    }
    $d = str_pad(substr($d, 0, 9), 9, '0', STR_PAD_RIGHT);
    return substr($d, 0, 2) . '.' . substr($d, 2, 2) . '.' . substr($d, 4, 2) . '.' . substr($d, 6, 3);
}
function nfse_consultar_aliquota(?string $codMunicipio = null, ?string $competencia = null): array
{
    $cfg = nfse_config(true);
    $cod = $codMunicipio ?: $cfg['cod_municipio'];
    $comp = $competencia ?: date('Y-m-d');

    $nfse = nfse_cliente($cfg);
    $codServico = nfse_formatar_cod_servico($cfg['ctrib_nac'] ?: '210101');
    $res = $nfse->contribuinte()->consultarAliquota($cod, $codServico, $comp);

    return ['ok' => true, 'aliquotas' => $res];
}

/* =====================================================================
 * 14. RESPOSTA JSON PADRÃO
 * ===================================================================== */

function nfse_json(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
