<?php
/**
 * pagamento_anexos_config.php — Comprovantes de pagamento da O.S.
 * Anexos (PDF/imagem) por linha de pagamento (tabela pagamento_os).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

function pa_db()
{
    static $c = null;
    if ($c instanceof mysqli && @$c->ping()) return $c;
    $c = new mysqli('localhost', 'root', '', 'atlas');
    if ($c->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $c->set_charset('utf8mb4');
    return $c;
}

function pa_ensure_schema()
{
    pa_db()->query("CREATE TABLE IF NOT EXISTS pagamento_os_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pagamento_id INT NOT NULL,
        os_id INT NULL,
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        mime VARCHAR(100) NULL,
        tamanho INT NULL,
        enviado_por VARCHAR(120) NULL,
        enviado_em DATETIME NULL,
        INDEX idx_pag (pagamento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Só permite anexo em pagamentos que NÃO são espécie. */
function pa_forma_permite_anexo($forma)
{
    $f = mb_strtolower(trim((string)$forma), 'UTF-8');
    return !($f === 'espécie' || $f === 'especie' || $f === 'dinheiro');
}

/** Busca o pagamento (forma, os_id) por id. */
function pa_pagamento($pagamentoId)
{
    $conn = pa_db();
    $stmt = $conn->prepare("SELECT id, ordem_de_servico_id, forma_de_pagamento FROM pagamento_os WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $pagamentoId); $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $r ?: null;
}

function pa_csrf_token()
{
    if (empty($_SESSION['pa_csrf'])) $_SESSION['pa_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['pa_csrf'];
}
function pa_csrf_check($t)
{
    return is_string($t) && !empty($_SESSION['pa_csrf']) && hash_equals($_SESSION['pa_csrf'], $t);
}

function pa_dir()
{
    $d = __DIR__ . '/comprovantes_pagamento';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d.'/.htaccess')) @file_put_contents($d.'/.htaccess', "php_flag engine off\nOptions -Indexes\n");
    return $d;
}

/** Extensões e MIME aceitos. */
function pa_tipos_aceitos()
{
    return [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
}

function pa_lista($pagamentoId)
{
    pa_ensure_schema();
    $conn = pa_db();
    $stmt = $conn->prepare("SELECT * FROM pagamento_os_anexos WHERE pagamento_id=? ORDER BY id DESC");
    $stmt->bind_param('i', $pagamentoId); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}

/** Contagem de anexos por pagamento, para um conjunto de ids. */
function pa_contagens(array $ids)
{
    $out = [];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return $out;
    pa_ensure_schema();
    $in = implode(',', $ids);
    $r = pa_db()->query("SELECT pagamento_id, COUNT(*) c FROM pagamento_os_anexos WHERE pagamento_id IN ($in) GROUP BY pagamento_id");
    while ($r && $row = $r->fetch_assoc()) $out[(int)$row['pagamento_id']] = (int)$row['c'];
    return $out;
}
