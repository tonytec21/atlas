<?php
include(__DIR__ . '/db_connection.php');
require('../../oficios/tcpdf/tcpdf.php');
date_default_timezone_set('America/Sao_Paulo');

// Carregar o número do ID da triagem
$triagem_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados da triagem comunitária
$stmt = $conn->prepare("SELECT * FROM triagem_comunitario WHERE id = ?");
$stmt->bind_param("i", $triagem_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Triagem comunitária não encontrada.");
}

$triagemData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Converter data para o formato brasileiro
function formatDateToBrazilian($date) {
    $dateTime = new DateTime($date);
    return $dateTime->format('d/m/Y');
}

// Função para converter a codificação para UTF-8
function convertToUtf8($data) {
    return mb_convert_encoding($data, 'UTF-8', 'auto');
}

// Configurar a classe PDF
class PDF extends TCPDF {
    public function Header() {
        $image_file = '../../style/img/timbrado.png'; // Verifique o caminho
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300);
        $this->SetAutoPageBreak(true, 10);
        $this->SetMargins(25, 45, 25);
        $this->SetY(25);
    }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Guia de Triagem Comunitária');
$pdf->SetMargins(25, 45, 25);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 8 * 0.5;

// Cabeçalho da Guia
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'GUIA DE TRIAGEM COMUNITÁRIA', 0, 1, 'C');
$pdf->Ln(3);

// Conteúdo da Guia
$pdf->SetFont('helvetica', '', 10);
$html = '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="20%">Nº Protocolo:</td>
        <td class="header-cell" width="40%">Cidade:</td>
        <td class="header-cell" width="40%">Data do Cadastro:</td>
    </tr>
    <tr>
        <td class="data-cell" width="20%">' . convertToUtf8($triagemData['n_protocolo']) . '</td>
        <td class="data-cell" width="40%">' . convertToUtf8($triagemData['cidade']) . '</td>
        <td class="data-cell" width="40%">' . convertToUtf8(formatDateToBrazilian($triagemData['data'])) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="45%">Nome do Noivo:</td>
        <td class="header-cell" width="45%">Novo Nome do Noivo:</td>
        <td class="header-cell" width="10%">Menor:</td>
    </tr>
    <tr>
        <td class="data-cell" width="45%">' . convertToUtf8($triagemData['nome_do_noivo']) . '</td>
        <td class="data-cell" width="45%">' . convertToUtf8($triagemData['novo_nome_do_noivo']) . '</td>
        <td class="data-cell" width="10%">' . ($triagemData['noivo_menor'] ? 'Sim' : 'Não') . '</td>
    </tr>
    <br>
    <tr>
        <td class="header-cell" width="45%">Nome da Noiva:</td>
        <td class="header-cell" width="45%">Novo Nome da Noiva:</td>
        <td class="header-cell" width="10%">Menor:</td>
    </tr>
    <tr>
        <td class="data-cell" width="45%">' . convertToUtf8($triagemData['nome_da_noiva']) . '</td>
        <td class="data-cell" width="45%">' . convertToUtf8($triagemData['novo_nome_da_noiva']) . '</td>
        <td class="data-cell" width="10%">' . ($triagemData['noiva_menor'] ? 'Sim' : 'Não') . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio
$pdf->Output('Guia_Triagem_' . $triagemData['n_protocolo'] . '.pdf', 'I');
?>
