<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');

// Configurar a classe PDF sem cabeçalho e rodapé
class PDF extends TCPDF
{
    // Remover o cabeçalho definindo uma função vazia
    public function Header()
    {
        // Cabeçalho vazio
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('arial', 'I', 8);
    }

    // Adicionar parágrafo com recuo na primeira linha
    public function AddParagraph($text)
    {
        $paragraphs = explode("\n", $text);
        foreach ($paragraphs as $paragraph) {
            $this->WriteHTML('<p style="text-align:justify; text-indent:2cm;">' . $paragraph . '</p>', true, false, true, false, '');
        }
    }
}

// Função para formatar a data no formato desejado
function formatDateToBrazilian($date)
{
    $dateTime = new DateTime($date);
    $mes = array(
        'January' => 'janeiro',
        'February' => 'fevereiro',
        'March' => 'março',
        'April' => 'abril',
        'May' => 'maio',
        'June' => 'junho',
        'July' => 'julho',
        'August' => 'agosto',
        'September' => 'setembro',
        'October' => 'outubro',
        'November' => 'novembro',
        'December' => 'dezembro'
    );
    return $dateTime->format('d') . ' de ' . $mes[$dateTime->format('F')] . ' de ' . $dateTime->format('Y');
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Obter os dados do requerimento
    include 'db_connection.php';

    $stmt = $conn->prepare("SELECT * FROM requerimentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requerimento = $result->fetch_assoc();
        // Mostrar os dados exatamente como estão no banco de dados
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Requerimento não encontrado']);
        exit;
    }
    $stmt->close();

    // Obter os dados da serventia
    $serventiaStmt = $conn->prepare("SELECT razao_social, cidade FROM cadastro_serventia WHERE status = '1' LIMIT 1");
    $serventiaStmt->execute();
    $serventiaResult = $serventiaStmt->get_result();

    if ($serventiaResult->num_rows > 0) {
        $serventia = $serventiaResult->fetch_assoc();
        // Mostrar os dados exatamente como estão no banco de dados
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Serventia não encontrada']);
        exit;
    }
    $serventiaStmt->close();

    // Gerar o PDF
    $pdf = new PDF();
    $pdf->SetMargins(25, 25, 25); // Ajustar as margens: esquerda, superior, direita
    $pdf->AddPage();
    $pdf->SetFont('arial', '', 12);

    $data = formatDateToBrazilian($requerimento['data']);
    $html = '
    <p style="text-transform: uppercase; text-align: center;"><b>AO REGISTRADOR DA ' . $serventia['razao_social'] . '</b></p>
    <br>
    <p style="text-align: center;"><b>REQUERIMENTO</b></p>
    <br>
    <p style="text-align: justify; text-indent: 2cm;">
        ' . $requerimento['requerente'] . ', ' . $requerimento['qualificacao'] . ', vem, mediante este, expor e requerer o quanto segue:
    </p>
    <p style="text-align: justify; text-indent: 2cm;">' . $requerimento['motivo'] . '</p>
    <p style="text-align: justify; text-indent: 2cm;">' . $requerimento['peticao'] . '</p>
    <p style="text-align: justify; text-indent: 2cm;">Pede Deferimento.</p>
    <p style="text-align: center;">' . $serventia['cidade'] . ', ' . $data . '</p>
    <br>
    <p style="text-align: center;">______________________________________________<br>' . $requerimento['requerente'] . '<br>Requerente</p>';

    $pdf->writeHTML($html, true, false, true, false, '');

    ob_start();
    $pdf->Output('requerimento.pdf', 'I');
    ob_end_flush();
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
