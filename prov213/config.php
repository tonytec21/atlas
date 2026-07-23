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
    __DIR__ . '/../tcpdf/tcpdf.php',
    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/../oficios/tcpdf/tcpdf.php',
    __DIR__ . '/../../tcpdf/tcpdf.php',
];

// ---------------------------------------------------------------------------
// Marcos temporais da norma
// ---------------------------------------------------------------------------
// Provimento 213/2026 — entrada em vigor original (DJe/CNJ n. 40/2026).
// Mantido apenas como referência histórica.
define('P213_VIGENCIA', '2026-02-23');

// Provimento 243, de 21/07/2026 (DJe/CNJ n. 172/2026, disponibilizado em 23/07/2026),
// que alterou o Prov. 213: "entra em vigor 30 dias após a data de sua publicação" (art. 2º).
//
// TODOS os prazos dos arts. 20, 22 e 23 passaram a ser contados desta data.
//
// Cálculo adotado: disponibilização em 23/07/2026 (quinta) => publicação no primeiro dia
// útil seguinte, 24/07/2026 (Lei 11.419/2006, art. 4º, §3º); contagem com inclusão do dia
// da publicação e do último dia, entrando em vigor no dia subsequente (LC 95/1998, art. 8º,
// §1º) => 23/08/2026.
//
// Se a sua Corregedoria adotar entendimento diverso (por exemplo, contando da própria
// disponibilização, o que levaria a 22/08/2026), ajuste APENAS esta linha — todos os
// prazos do módulo são recalculados a partir dela.
define('P213_VIGENCIA_243', '2026-08-23');

date_default_timezone_set('America/Fortaleza');
