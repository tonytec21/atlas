<?php
/**
 * oficios/stamp_seal_oficio.php
 * ---------------------------------------------------------------------------
 * Recebe a POSIÇÃO do selo (escolhida por clique na tela de assinatura),
 * gera o PDF-base do ofício, estampa o selo VISUAL naquela posição e devolve
 * o PDF resultante em base64 — pronto para ser enviado ao Assinador SERPRO
 * (assinatura criptográfica ICP-Brasil / PAdES) pelo navegador.
 *
 * POST:
 *   numero    (string)  obrigatório
 *   page      (int)     página onde o selo será colocado (1..N)
 *   xn,yn     (float)   canto superior-esquerdo do selo, normalizado 0..1
 *   wn        (float)   largura do selo, normalizada 0..1
 *   timbrado  (S|N)     opcional (default: lê ../style/configuracao.json)
 *
 * Saída JSON:
 *   { status:"success", pdf_base64:"...", pageCount:N, codigo:"XXXX-XXXX-...",
 *     nome:"...", cargo:"...", quando:"dd/mm/aaaa HH:MM:SS" }
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';

header('Content-Type: application/json; charset=utf-8');

function fail($human, $tech = '')
{
    if ($tech) {
        assin_log('STAMP ERROR: ' . $tech);
    }
    echo json_encode(['status' => 'error', 'message' => $human], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Método inválido.');
    }

    assin_ensure_schema();

    $numero = isset($_POST['numero']) ? trim((string)$_POST['numero']) : '';
    if ($numero === '') {
        fail('Número do ofício não informado.');
    }

    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $xn   = isset($_POST['xn']) ? (float)$_POST['xn'] : 0.55;
    $yn   = isset($_POST['yn']) ? (float)$_POST['yn'] : 0.80;
    $wn   = isset($_POST['wn']) ? (float)$_POST['wn'] : 0.38;
    $timbrado = assin_timbrado_flag($_POST['timbrado'] ?? null);

    // Dados do ofício (assinante/cargo) para compor o selo
    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, status, assinado FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        fail('Ofício não encontrado.');
    }
    $of = $res->fetch_assoc();
    $stmt->close();

    if ((int)($of['assinado'] ?? 0) === 1) {
        fail('Este ofício já está assinado digitalmente.');
    }

    // Gera o PDF-base (layout idêntico ao visualizado)
    $baseBytes = assin_generate_pdf_bytes($numero, $timbrado);

    // Código de verificação a partir do conteúdo do PDF-base
    $hash   = strtoupper(substr(hash('sha256', $baseBytes), 0, 16));
    $codigo = implode('-', str_split($hash, 4));

    $quando = date('d/m/Y H:i:s');

    // Estampa o selo na posição escolhida
    $stamped = assin_stamp_seal($baseBytes, [
        'page'   => $page,
        'xn'     => $xn,
        'yn'     => $yn,
        'wn'     => $wn,
        'nome'   => $of['assinante'] ?? '',
        'cargo'  => $of['cargo_assinante'] ?? '',
        'numero' => $numero,
        'codigo' => $codigo,
        'quando' => $quando,
    ]);

    if (!$stamped || strncmp(ltrim($stamped), '%PDF', 4) !== 0) {
        fail('Falha ao gerar o PDF com o selo.', 'stamp result inválido');
    }

    // Descobre número de páginas (para o front, se precisar)
    $pageCount = 1;
    if (preg_match_all('~/Type\s*/Page[^s]~', $stamped, $m)) {
        $pageCount = max(1, count($m[0]));
    }

    echo json_encode([
        'status'     => 'success',
        'pdf_base64' => base64_encode($stamped),
        'pageCount'  => $pageCount,
        'codigo'     => $codigo,
        'nome'       => $of['assinante'] ?? '',
        'cargo'      => $of['cargo_assinante'] ?? '',
        'quando'     => $quando,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    fail('Erro ao preparar o documento para assinatura.', $e->getMessage());
}
