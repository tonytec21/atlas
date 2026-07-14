<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Módulo Atlas Conformidade — Provimento CN-CNJ n. 213/2026
 *
 * Ajuste APENAS este arquivo para o seu ambiente.
 */

// ---------------------------------------------------------------------------
// Banco de dados (mesmo banco do Atlas)
// ---------------------------------------------------------------------------
define('P213_DB_HOST', 'localhost');
define('P213_DB_USER', 'root');
define('P213_DB_PASS', '');
define('P213_DB_NAME', 'atlas');
define('P213_DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Caminhos relativos ao módulo (pasta atlas/prov213/)
// ---------------------------------------------------------------------------
define('P213_MENU', __DIR__ . '/../menu.php');              // navbar/sidebar do Atlas
define('P213_ASSETS', '../style');                           // ../style/css, ../style/js, ../style/img

// session_check.php do Atlas — a primeira que existir é usada
$P213_SESSION_CANDIDATES = [
    __DIR__ . '/../os/session_check.php',
    __DIR__ . '/../session_check.php',
    __DIR__ . '/session_check.php',
];

// TCPDF — a primeira que existir é usada
$P213_TCPDF_CANDIDATES = [
    __DIR__ . '/../oficios/tcpdf/tcpdf.php',
    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/../os/tcpdf/tcpdf.php',
    __DIR__ . '/../../tcpdf/tcpdf.php',
];

// ---------------------------------------------------------------------------
// Marco temporal da norma
// ---------------------------------------------------------------------------
// Entrada em vigor: publicação no DJe/CNJ n. 40/2026 (23/02/2026).
define('P213_VIGENCIA', '2026-02-23');

date_default_timezone_set('America/Fortaleza');
