<?php
/**
 * Atlas · Tarefas — impressão da Guia de Recebimento (com logo, sem timbrado).
 *
 * Aceita dois formatos de chamada:
 *   ?guia_id=45   imprime exatamente aquela guia (usado na reimpressão pelo
 *                 histórico, onde a tarefa pode ter várias guias)
 *   ?id=123       compatibilidade: imprime a guia mais recente da tarefa 123
 *
 * Cada abertura conta como uma impressão. A partir da segunda, o documento
 * sai marcado como reimpressão.
 */

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/guia_helpers.php');
require('../oficios/tcpdf/tcpdf.php');

date_default_timezone_set('America/Sao_Paulo');

$guia_id = isset($_GET['guia_id']) ? (int) $_GET['guia_id'] : 0;
$task_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$guiaData = guia_buscar($conn, $guia_id, $task_id);

if (!$guiaData) {
    die('Guia de recebimento não encontrada.');
}

$guia_id = (int) $guiaData['id'];
$task_id = (int) $guiaData['task_id'];

// Buscar dados da tarefa
$stmt = $conn->prepare('SELECT * FROM tarefas WHERE id = ?');
$stmt->bind_param('i', $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Tarefa não encontrada.');
}

$tarefaData = $result->fetch_assoc();
$stmt->close();

// Contabiliza esta impressão e descobre se é reimpressão
$numeroImpressao = guia_registrar_impressao($conn, $guia_id);
$ehReimpressao   = $numeroImpressao > 1;

$emitidoPor = isset($guiaData['emitido_por']) && trim((string) $guiaData['emitido_por']) !== ''
    ? $guiaData['emitido_por']
    : $guiaData['funcionario'];

$emitidaEm = isset($guiaData['criado_em']) && $guiaData['criado_em'] !== null && $guiaData['criado_em'] !== ''
    ? $guiaData['criado_em']
    : $guiaData['data_recebimento'];

$conn->close();

// Converter data para o formato brasileiro
function formatDateToBrazilian($date)
{
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y H:i:s');
}

// Função para converter a codificação para UTF-8
function convertToUtf8($data)
{
    return mb_convert_encoding($data, 'UTF-8', 'auto');
}

/**
 * Corpo da guia (idêntico na via do apresentante e no canhoto).
 */
function corpoDaGuia($tarefaData, $guiaData)
{
    return '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="23%">Protocolo Geral:</td>
        <td class="header-cell" width="26%">Data de Recebimento:</td>
        <td class="header-cell" width="50%">Apresentante:</td>
    </tr>
    <tr>
        <td class="data-cell" width="23%">' . convertToUtf8($tarefaData['id']) . '</td>
        <td class="data-cell" width="26%">' . convertToUtf8(formatDateToBrazilian($guiaData['data_recebimento'])) . '</td>
        <td class="data-cell" width="50%">' . convertToUtf8($guiaData['cliente']) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="49.5%">Funcionário:</td>
        <td class="header-cell" width="49.5%">Observações:</td>
    </tr>
    <tr>
        <td class="data-cell" width="49.5%">' . convertToUtf8($guiaData['funcionario']) . '</td>
        <td class="data-cell" width="49.5%" style="text-align:justify;">' . (!empty($guiaData['observacoes']) ? convertToUtf8($guiaData['observacoes']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="99%">Documento(s) Recebido(s):</td>
    </tr>
    <tr>
        <td class="data-cell" width="99%" style="text-align:justify;">' . convertToUtf8($guiaData['documentos_recebidos']) . '</td>
    </tr>
</table>';
}

// Configurar a classe PDF
class PDF extends TCPDF
{
    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/logo.png'; // Verifique se o caminho está correto
        $this->Image($image_file, 30, 10, 150, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetY(35); // Ajuste para garantir que o conteúdo não sobreponha a imagem
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
    }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Guia de Recebimento nº ' . $guia_id . ' - Protocolo Geral nº ' . $tarefaData['id']);
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 10); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 8 * 0.5;

// Linha de controle impressa no pé de cada via
$linhaControle = 'Guia nº ' . $guia_id . ' · emitida em ' . convertToUtf8(guia_data_br($emitidaEm))
               . ' por ' . convertToUtf8($emitidoPor)
               . ' · impressa em ' . date('d/m/Y H:i:s');

// Cabeçalho da Guia
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'GUIA DE RECEBIMENTO DE DOCUMENTOS Nº ' . convertToUtf8($guia_id), 0, 1, 'C');

if ($ehReimpressao) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, $lineHeight, 'REIMPRESSÃO — ' . guia_ordinal($numeroImpressao) . ' impressão', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(3);

// Conteúdo da Guia
$pdf->SetFont('helvetica', '', 10);
$pdf->writeHTML(corpoDaGuia($tarefaData, $guiaData), true, false, true, false, '');

// Campos de assinatura
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Cliente', 0, 1, 'L');

// Rastreabilidade da emissão
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, $lineHeight, $linhaControle, 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// Linha de corte
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, $lineHeight, '----------------------------------------------------- Corte Aqui -----------------------------------------------------', 0, 1, 'C');

// Cabeçalho do Canhoto
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'COMPROVANTE', 0, 1, 'C');
$pdf->Cell(0, $lineHeight, 'GUIA DE RECEBIMENTO DE DOCUMENTOS Nº ' . convertToUtf8($guia_id), 0, 1, 'C');

if ($ehReimpressao) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, $lineHeight, 'REIMPRESSÃO — ' . guia_ordinal($numeroImpressao) . ' impressão', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Ln(3);

// Conteúdo do Canhoto
$pdf->SetFont('helvetica', '', 10);
$pdf->writeHTML(corpoDaGuia($tarefaData, $guiaData), true, false, true, false, '');

// Campos de assinatura no canhoto
$pdf->Ln(1);
$pdf->Cell(0, $lineHeight, '______________________________________', 0, 1, 'L');
$pdf->Cell(0, $lineHeight, 'Assinatura do Funcionário', 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, $lineHeight, $linhaControle, 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Guia_Recebimento_' . $guia_id . '_Protocolo_' . $tarefaData['id'] . '.pdf', 'I');
