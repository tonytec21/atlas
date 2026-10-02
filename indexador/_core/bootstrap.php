<?php
/**
 * ============================================================================
 *  ATLAS · INDEXADOR — Núcleo compartilhado
 *  Sessão, conexão, helpers e definições dos tipos de ato.
 * ============================================================================
 */
declare(strict_types=1);

if (!defined('IX_ROOT')) {
    define('IX_ROOT', dirname(__DIR__));          // .../indexador
}
require_once __DIR__ . '/version.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
date_default_timezone_set('America/Sao_Paulo');

/* ------------------------------------------------------------------ */
/*  Conexão                                                            */
/* ------------------------------------------------------------------ */
function ix_db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    // Reaproveita a configuração oficial do módulo (db_connection.php da raiz do indexador)
    $prev = $GLOBALS['conn'] ?? null;
    if ($prev instanceof mysqli) {
        $conn = $prev;
    } else {
        include IX_ROOT . '/db_connection.php';
        /** @var mysqli $conn */
        $conn = $conn ?? ($GLOBALS['conn'] ?? null);
    }
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Conexão com o banco não encontrada.');
    }
    $conn->set_charset('utf8mb4');
    $GLOBALS['conn'] = $conn;
    return $conn;
}

/* ------------------------------------------------------------------ */
/*  Sessão / usuário                                                   */
/* ------------------------------------------------------------------ */
function ix_logged(): bool { return !empty($_SESSION['username']); }

function ix_require_page_session(): void
{
    if (!ix_logged()) {
        header('Location: ../../login.php');
        exit;
    }
}

function ix_user(): string { return (string)($_SESSION['username'] ?? ''); }

function ix_is_admin(): bool
{
    return ($_SESSION['nivel_de_acesso'] ?? '') === 'administrador';
}

/* ------------------------------------------------------------------ */
/*  Saída JSON                                                         */
/* ------------------------------------------------------------------ */
function ix_json($data, int $code = 200): void
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ix_fail(string $message, int $code = 400, array $extra = []): void
{
    ix_json(['ok' => false, 'message' => $message] + $extra, $code);
}

function ix_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ------------------------------------------------------------------ */
/*  Texto / datas                                                      */
/* ------------------------------------------------------------------ */

/** Decodifica entidades HTML gravadas por versões antigas (ex.: &amp;, &#39;). */
function ix_decode(?string $s): string
{
    if ($s === null) return '';
    $prev = null;
    // até 2 passadas (casos de dupla codificação)
    for ($i = 0; $i < 2 && $prev !== $s; $i++) {
        $prev = $s;
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $s;
}

/** Normaliza nomes: remove quebras, colapsa espaços, caixa alta. */
function ix_norm_name(?string $s): string
{
    $s = ix_decode($s);
    $s = preg_replace('/[\r\n\t]+/u', ' ', $s);
    $s = preg_replace('/\s{2,}/u', ' ', (string)$s);
    $s = trim((string)$s);
    // apóstrofos tipográficos -> simples
    $s = str_replace(["\u{2019}", "\u{2018}", '`', '´'], "'", $s);
    return mb_strtoupper($s, 'UTF-8');
}

function ix_digits(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }

/** Aceita AAAA-MM-DD ou DD/MM/AAAA. Retorna AAAA-MM-DD ou null. */
function ix_parse_date($s): ?string
{
    $s = trim((string)$s);
    if ($s === '' || $s === '0000-00-00') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
        [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** AAAA-MM-DD -> DD/MM/AAAA, sem depender de timestamp (funciona antes de 1970). */
function ix_br_date(?string $iso, string $empty = ''): string
{
    $iso = trim((string)$iso);
    if ($iso === '' || str_starts_with($iso, '0000-00-00')) return $empty;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $empty;
}

function ix_parse_time($s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m)) return null;
    $h = (int)$m[1]; $i = (int)$m[2];
    if ($h > 23 || $i > 59) return null;
    return sprintf('%02d:%02d', $h, $i);
}

/* ------------------------------------------------------------------ */
/*  Serventia / matrícula                                              */
/* ------------------------------------------------------------------ */
function ix_cns(): string
{
    static $cns = null;
    if ($cns !== null) return $cns;
    $cns = '';
    try {
        $rs = ix_db()->query('SELECT cns FROM cadastro_serventia LIMIT 1');
        if ($rs && ($r = $rs->fetch_assoc())) $cns = trim((string)$r['cns']);
    } catch (Throwable $e) { $cns = ''; }
    return $cns;
}

/** Dígitos verificadores da matrícula (algoritmo original do módulo). */
function ix_matricula_dv(string $base): string
{
    $m1 = 32; $soma = 0;
    for ($i = 0; $i < 30; $i++) { $m1--; $soma += (int)($base[$i] ?? 0) * $m1; }
    $d1 = ($soma * 10) % 11; $d1 = ($d1 == 10) ? 1 : $d1;

    $m2 = 33; $soma2 = 0;
    for ($j = 0; $j < 30; $j++) { $m2--; $soma2 += (int)($base[$j] ?? 0) * $m2; }
    $soma2 += $d1 * 2;
    $d2 = ($soma2 * 10) % 11; $d2 = ($d2 == 10) ? 1 : $d2;
    return $d1 . $d2;
}

/**
 * Monta a matrícula CNJ (32 dígitos): CNS(6) + acervo(2) + 55 + ano(4) + tipoLivro(1)
 * + livro(5) + folha(3) + termo(7) + DV(2). Retorna null se não for possível.
 */
function ix_matricula(string $tipoLivro, $livro, $folha, $termo, ?string $dataRegistroIso): ?string
{
    $cns = ix_cns();
    if ($cns === '' || !$dataRegistroIso) return null;
    $base = str_pad($cns, 6, '0', STR_PAD_LEFT) . '01' . '55'
        . substr($dataRegistroIso, 0, 4) . $tipoLivro
        . str_pad((string)$livro, 5, '0', STR_PAD_LEFT)
        . str_pad((string)$folha, 3, '0', STR_PAD_LEFT)
        . str_pad((string)$termo, 7, '0', STR_PAD_LEFT);
    return $base . ix_matricula_dv($base);
}

/* ------------------------------------------------------------------ */
/*  Tipos                                                              */
/* ------------------------------------------------------------------ */
function ix_tipos(): array
{
    static $t = null;
    if ($t === null) $t = require __DIR__ . '/tipos.php';
    return $t;
}

function ix_tipo(string $key): array
{
    $t = ix_tipos();
    if (!isset($t[$key])) throw new InvalidArgumentException('Tipo de ato inválido.');
    return $t[$key] + ['key' => $key];
}
