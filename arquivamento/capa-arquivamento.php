<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');

// Configurar a classe PDF
class PDF extends TCPDF
{
    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/logo.png'; // Verifique se o caminho está correto
        $this->Image($image_file, 30, 10, 150, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetY(25); // Ajuste para garantir que o conteúdo comece 2 cm abaixo do cabeçalho
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('arial', 'I', 8);
    }
}

function getSeal($arquivo_id) {
    include 'db_connection.php';
    $stmt = $conn->prepare("SELECT selos.* FROM selos_arquivamentos INNER JOIN selos ON selos_arquivamentos.selo_id = selos.id WHERE selos_arquivamentos.arquivo_id = ?");
    $stmt->bind_param("i", $arquivo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selo = $result->fetch_assoc();
    $stmt->close();
    return $selo;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $filePath = "meta-dados/$id.json";

    if (file_exists($filePath)) {
        $ato = json_decode(file_get_contents($filePath), true);

        $selo = getSeal($id);

        $pdf = new PDF();
        $pdf->SetMargins(25, 50, 25); // Definir as margens: esquerda, superior (ajustada), direita
        $pdf->AddPage();
        $pdf->SetFont('arial', '', 10);

        $html = '
        <h1 style="text-align: center;">ARQUIVAMENTO</h1>
        <br>
        <h3>ATRIBUIÇÃO: ' . mb_strtoupper($ato['atribuicao']) . '</h3>
        <table border="1" cellpadding="4">
            <tr>
                <td>ATO / TERMO Nº:</td>
                <td>' . $ato['termo'] .'</td>
            </tr>
            <tr>
                <td>PROTOCOLO Nº:</td>
                <td>' . $ato['protocolo'] .'</td>
            </tr>
            <tr>
                <td>MATRICULA Nº:</td>
                <td>' . $ato['matricula'] .'</td>
            </tr>
            <tr>
                <td>NATUREZA DO ATO:</td>
                <td>' . $ato['categoria'] . '</td>
            </tr>
            <tr>
                <td>DATA DO ATO:</td>
                <td>' . date('d/m/Y', strtotime($ato['data_ato'])) . '</td>
            </tr>
            <tr>
                <td>LIVRO Nº:</td>
                <td>' . $ato['livro'] . '</td>
            </tr>
            <tr>
                <td>FOLHA Nº:</td>
                <td>' . $ato['folha'] . '</td>
            </tr>
            <tr>
                <td>PARTES ENVOLVIDAS:</td>
                <td>' . implode('; ', array_map(function($parte) { return $parte['nome']; }, $ato['partes_envolvidas'])) . '</td>
            </tr>
            <tr>
                <td>DESCRIÇÃO E DETALHES:</td>
                <td>' . $ato['descricao'] . '</td>
            </tr>
        </table>
        <br><br>
        <h3>SELO DE ARQUIVAMENTO:</h3>';

        if ($selo) {
            $html .= '<div style="border: 1px solid black; width: 100mm;">
                        <table>
                            <tr>
                                <td style="width: 19%; vertical-align: middle;"><p></p><img style="width: 90px;" src="data:image/png;base64,' . $selo['qr_code'] . '" alt="QR Code"></td>
                                <td style="width: 77%; padding-left: 10px;">
                                    <p style="text-align: justify;font-size: 9px;"><strong style="text-align: center!important;font-size: 10px;">Poder Judiciário – TJMA<br>Selo: ' . $selo['numero_selo'] . '</strong>
                                    <br>' . $selo['texto_selo'] . '</p>
                                </td>
                            </tr>
                        </table>
                        <table>
                            <tr>
                                <td>
                                    <strong style="font-size: 10px;">Quantidade: ' . $selo['quantidade'] . '</strong><br>
                                    <strong style="font-size: 10px;">Funcionário: ' . utf8_decode($selo['escrevente']) . '</strong>
                                </td>
                            </tr>
                        </table>
                    </div>';
        } else {
            $html .= '<div style="border: 1px solid black; width: 100mm; height: 50mm;"><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p></div>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        ob_start();
        $pdf->Output('capa_de_arquivamento.pdf', 'I');
        ob_end_flush();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ato não encontrado']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>
