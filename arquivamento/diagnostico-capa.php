<?php
/**
 * Atlas · Arquivamento Digital
 * Diagnóstico da capa de arquivamento.
 *
 * Script temporário: reproduz passo a passo o que o capa_arquivamento.php faz
 * e mostra onde a coisa para. Não gera PDF nenhum, só relata.
 *
 * Uso:  /atlas/arquivamento/diagnostico-capa.php?id=1755787460
 *
 * APAGUE ESTE ARQUIVO do servidor depois de resolver.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

header('Content-Type: text/plain; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/\D/', '', $_GET['id']) : '';

function linha($rotulo, $valor)
{
    printf("%-38s %s\n", $rotulo . ':', $valor);
}

echo "DIAGNÓSTICO DA CAPA DE ARQUIVAMENTO\n";
echo str_repeat('=', 70) . "\n\n";

/* ---------- Ambiente ---------- */
echo "-- AMBIENTE --\n";
linha('Versão do PHP', PHP_VERSION);
linha('Sistema', PHP_OS_FAMILY);
linha('memory_limit', ini_get('memory_limit'));
linha('max_execution_time', ini_get('max_execution_time'));
linha('OPcache ativo', function_exists('opcache_get_status') && @opcache_get_status(false) ? 'sim' : 'não');
linha('mbstring', extension_loaded('mbstring') ? 'sim' : 'NÃO');
linha('gd', extension_loaded('gd') ? 'sim' : 'NÃO');
linha('mysqli', extension_loaded('mysqli') ? 'sim' : 'NÃO');
echo "\n";

/* ---------- Arquivos ---------- */
echo "-- ARQUIVOS --\n";
$tcpdf = __DIR__ . '/../oficios/tcpdf/tcpdf.php';
linha('TCPDF encontrado', is_file($tcpdf) ? $tcpdf : 'NÃO em ' . $tcpdf);

$timbrado = __DIR__ . '/../style/img/timbrado.png';
linha('timbrado.png', is_file($timbrado)
    ? 'sim (' . round(filesize($timbrado) / 1024) . ' KB)'
    : 'NÃO em ' . $timbrado);

// Data e hora do próprio gerador: confirma se o arquivo novo chegou mesmo.
$gerador = __DIR__ . '/capa_arquivamento.php';
linha('capa_arquivamento.php', is_file($gerador)
    ? date('d/m/Y H:i:s', filemtime($gerador)) . ' · ' . filesize($gerador) . ' bytes'
    : 'NÃO ENCONTRADO');
linha('  contém a validação nova', is_file($gerador) && strpos(file_get_contents($gerador), 'preg_match') !== false
    ? 'sim (versão nova em uso)'
    : 'NÃO (o servidor ainda está com o arquivo antigo)');
echo "\n";

/* ---------- Metadados ---------- */
echo "-- METADADOS --\n";
if ($id === '') {
    echo "Nenhum id informado. Chame com ?id=NUMERO\n";
    exit;
}
linha('ID pedido', $id);

$json = __DIR__ . "/meta-dados/$id.json";
linha('Caminho do JSON', $json);
linha('JSON existe', is_file($json) ? 'sim (' . filesize($json) . ' bytes)' : 'NÃO');

if (!is_file($json)) {
    echo "\nÉ isto: o gerador não encontra o arquivo de metadados.\n";
    exit;
}

$ato = json_decode(file_get_contents($json), true);
linha('JSON decodifica', is_array($ato) ? 'sim' : 'NÃO — ' . json_last_error_msg());
if (!is_array($ato)) { exit; }

foreach (['atribuicao','categoria','data_ato','livro','folha','termo','protocolo','matricula','descricao'] as $c) {
    linha('  ' . $c, isset($ato[$c]) ? '"' . mb_substr((string) $ato[$c], 0, 60) . '"' : '(ausente)');
}
linha('  partes_envolvidas', isset($ato['partes_envolvidas']) && is_array($ato['partes_envolvidas'])
    ? count($ato['partes_envolvidas']) . ' parte(s)' : 'AUSENTE ou não é lista');
echo "\n";

/* ---------- Selos ---------- */
echo "-- SELOS --\n";
$conn = @new mysqli(ARQ_DB_HOST, ARQ_DB_USER, ARQ_DB_PASS, ARQ_DB_NAME);
if ($conn->connect_error) {
    linha('Conexão mysqli', 'FALHOU — ' . $conn->connect_error);
} else {
    linha('Conexão mysqli', 'ok');
    $stmt = $conn->prepare("SELECT selos.* FROM selos_arquivamentos
                            INNER JOIN selos ON selos_arquivamentos.selo_id = selos.id
                            WHERE selos_arquivamentos.arquivo_id = ? ORDER BY selos.id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result();
    $n = 0;
    while ($s = $r->fetch_assoc()) {
        $n++;
        linha("  selo #$n", $s['numero_selo']);
        linha('    qr_code (bytes)', strlen((string) $s['qr_code']));
        linha('    qr_code é base64 válido', base64_decode((string) $s['qr_code'], true) !== false ? 'sim' : 'NÃO');
        $bin = base64_decode((string) $s['qr_code'], true);
        linha('    é PNG de verdade', ($bin !== false && strncmp($bin, "\x89PNG", 4) === 0) ? 'sim' : 'NÃO');
        linha('    texto_selo (chars)', mb_strlen((string) $s['texto_selo']));
        linha('    escrevente', (string) $s['escrevente']);
    }
    if ($n === 0) { linha('  selos vinculados', 'nenhum (a capa deve mostrar a moldura vazia)'); }
    $stmt->close();
}
echo "\n";

/* ---------- Geração ---------- */
echo "-- GERAÇÃO DO PDF --\n";
if (!is_file($tcpdf)) {
    echo "TCPDF não encontrado; nada a testar.\n";
    exit;
}
require_once $tcpdf;
linha('Versão do TCPDF', defined('TCPDF_STATIC::getTCPDFVersion') ? '?' : (defined('PDF_PRODUCER') ? PDF_PRODUCER : TCPDF_STATIC::getTCPDFVersion()));
linha('K_TCPDF_THROW_EXCEPTION', defined('K_TCPDF_THROW_EXCEPTION') ? (K_TCPDF_THROW_EXCEPTION ? 'true' : 'false') : 'não definida');

try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(25, 50, 25);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<h1 style="text-align:center;">ARQUIVAMENTO</h1><p>teste</p>', true, false, true, false, '');
    $bytes = $pdf->Output('t.pdf', 'S');
    linha('PDF simples (sem timbrado)', strlen($bytes) . ' bytes — ok');
} catch (Throwable $e) {
    linha('PDF simples', 'FALHOU — ' . $e->getMessage());
}

try {
    $pdf2 = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf2->setPrintHeader(false);
    $pdf2->setPrintFooter(false);
    $pdf2->SetAutoPageBreak(false, 0);
    $pdf2->SetMargins(0, 0, 0);
    $pdf2->AddPage();
    $pdf2->Image($timbrado, 0, 0, 210, 297, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
    $pdf2->SetAutoPageBreak(true, 25);
    $pdf2->SetMargins(25, 45, 25);
    $pdf2->SetY(35);
    $pdf2->SetFont('helvetica', '', 10);
    $pdf2->writeHTML('<h1 style="text-align:center;">ARQUIVAMENTO</h1><p>teste sobre o timbrado</p>', true, false, true, false, '');
    $bytes2 = $pdf2->Output('t2.pdf', 'S');
    linha('PDF com timbrado', strlen($bytes2) . ' bytes — ok');
} catch (Throwable $e) {
    linha('PDF com timbrado', 'FALHOU — ' . $e->getMessage());
}

echo "\n";
echo "-- ÚLTIMAS LINHAS DO LOG DE ERRO DO MÓDULO --\n";
$log = __DIR__ . '/logs/php-error.log';
if (is_file($log)) {
    $linhas = array_slice(file($log), -15);
    echo $linhas ? implode('', $linhas) : "(vazio)\n";
} else {
    echo "(arquivo de log ainda não existe)\n";
}

echo "\nFim. Mande este relatório inteiro.\n";
