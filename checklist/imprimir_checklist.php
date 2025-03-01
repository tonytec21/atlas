<?php
include(__DIR__ . '/session_check.php');
checkSession();

// Ajuste o caminho conforme a localização do seu TCPDF
require_once('../oficios/tcpdf/tcpdf.php');

// Ajuste o caminho conforme seu arquivo de conexão
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros (opcional)
error_reporting(E_ERROR | E_PARSE);

// Definir fuso horário brasileiro
date_default_timezone_set('America/Sao_Paulo');

// --------------------------------------
// Definição da classe PDF (herda de TCPDF)
// --------------------------------------
class PDF extends TCPDF
{
    private $criado_por;
    private $isCanceled = false;

    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/timbrado.png'; // caminho da imagem de fundo
        // Salva as margens atuais
        $currentMargins = $this->getMargins();

        // Desativa temporariamente as margens e o AutoPageBreak
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);

        // Insere a imagem ocupando toda a página (A4: 210 x 297 mm)
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Se tiver que exibir uma marca d'água "CANCELADO"
        if ($this->isCanceled) {
            $this->SetAlpha(0.2);
            $this->StartTransform();
            // Rotaciona 45 graus
            $this->Rotate(45, $this->getPageWidth() / 2, $this->getPageHeight() / 2);
            $this->SetFont('helvetica', 'B', 60);
            $this->SetTextColor(255, 0, 0);
            // Texto no centro
            $this->Text($this->getPageWidth() / 7, $this->getPageHeight() / 2.5, 'CANCELADO');
            $this->StopTransform();
            $this->SetAlpha(1);
        }

        // Restaura margens e AutoPageBreak
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
        $this->SetY(25);
    }

    // Rodapé do PDF
    public function Footer()
    {
        // Você pode, se quiser, configurar algo para ler JSON e mudar cor do rodapé.
        // Aqui vamos manter em preto, como exemplo.
        $textColor = [0, 0, 0];

        // Posição vertical para o rodapé
        $this->SetY(-14.5);
        $this->SetFont('arial', 'I', 8);
        $this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

        // Ajusta posição horizontal
        $this->SetX(-23);

        // Exibir número da página
        $this->Cell(0, 11,
            'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(),
            0, false, 'L', 0, '', 0, false, 'T', 'M'
        );

        // Exibe "Criado por" na lateral
        // $this->SetTextColor(0, 0, 0);
        // $this->SetXY(-10, ($this->getPageHeight() / 2)); 
        // $this->StartTransform();
        // $this->Rotate(90);
        // $this->Cell(0, 10, 'Criado por: ' . $this->criado_por, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        // $this->StopTransform();
    }

    // Define o nome do criador
    public function setCriadoPor($criado_por)
    {
        $this->criado_por = $criado_por;
    }

    // Função para adicionar a chancela da assinatura
    public function addSignature($assinatura_path)
    {
        if (file_exists($assinatura_path)) {
            $signatureWidth = 80; // Largura da imagem da assinatura
            $pageWidth = $this->getPageWidth();
            $marginLeft = $this->getMargins()['left'];
            $marginRight = $this->getMargins()['right'];
            $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;

            $this->Image($assinatura_path, (float)$centerX, $this->GetY() - 2, (float)$signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
    }

    // Define se está cancelado (marca d'água)
    public function setIsCanceled($isCanceled)
    {
        $this->isCanceled = $isCanceled;
    }
}

// Verifica se recebeu o ID do checklist
if (isset($_GET['id'])) {
    $checklist_id = $_GET['id'];

    $checklist_query = $conn->prepare("SELECT * FROM checklists WHERE id = ?");
    $checklist_query->bind_param("i", $checklist_id);
    $checklist_query->execute();
    $checklist_result = $checklist_query->get_result();
    $checklist = $checklist_result->fetch_assoc();

    if (!$checklist) {
        echo "Checklist não encontrado.";
        exit;
    }

    $criado_por = $checklist['criado_por'] ?: 'Sistema';

    // Obter informações do usuário logado
    $logged_in_user = $_SESSION['username'];
    $logged_in_user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $logged_in_user_query->bind_param("s", $logged_in_user);
    $logged_in_user_query->execute();
    $logged_in_user_result = $logged_in_user_query->get_result();
    $logged_in_user_info = $logged_in_user_result->fetch_assoc();
    $logged_in_user_nome = $logged_in_user_info['nome_completo'];
    $logged_in_user_cargo = $logged_in_user_info['cargo'];

    // Verificar e carregar a assinatura
    $assinatura_path = '';
    $json_file = '../oficios/assinaturas/data.json';
    if (file_exists($json_file)) {
        $assinaturas_data = json_decode(file_get_contents($json_file), true);
        foreach ($assinaturas_data as $assinatura) {
            if ($assinatura['fullName'] == $logged_in_user_nome) {
                $assinatura_path = '../oficios/assinaturas/' . $assinatura['assinatura'];
                break;
            }
        }
    }

    // Busca itens do checklist
    $itens_query = $conn->prepare("SELECT * FROM checklist_itens WHERE checklist_id = ?");
    $itens_query->bind_param("i", $checklist_id);
    $itens_query->execute();
    $itens_result = $itens_query->get_result();
    $itens = $itens_result->fetch_all(MYSQLI_ASSOC);

    $isCanceled = ($checklist['status'] === 'removido');

    // Configurando o PDF
    $pdf = new PDF();
    $pdf->SetMargins(12, 40, 11);
    $pdf->setCriadoPor($criado_por);
    $pdf->setIsCanceled($isCanceled);

    // Adiciona página
    $pdf->AddPage();
    // Define fonte padrão
    $pdf->SetFont('helvetica', '', 11);

    // Título do Checklist
    $pdf->SetFont('helvetica', 'B', 12);
        $pdf->writeHTML('<div style="text-align: center;">' . 'CHECKLIST PARA ' . mb_strtoupper($checklist['titulo'], 'UTF-8') . '</div>', true, false, true, false, '');
        $pdf->Ln(4);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Image('../style/img/list-icon.png', $pdf->GetX(), $pdf->GetY() + 1.5, 5); 
    $pdf->SetX($pdf->GetX() + 7);
    $pdf->Cell(0, 8, 'ITENS DO CHECKLIST:', 0, 1, 'L');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', '', 11);

    $htmlItens = '<table>';
    foreach ($itens as $item) {
        $htmlItens .= '<tr>
            <td style="width: 5%;text-align: right;"><img src="../style/img/check-icon.png" width="10">&nbsp;&nbsp;&nbsp;</td>
            <td style="width: 94%;text-align: justify;">' . htmlspecialchars($item['item']) . '</td>
        </tr>';
    }
    $htmlItens .= '</table>';
    
    $pdf->writeHTML($htmlItens, true, false, true, false, '');
    $pdf->Ln(0);
    

    if (!empty($checklist['observacoes'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Image('../style/img/note-text.png', $pdf->GetX(), $pdf->GetY() + 1.5, 5); 
        $pdf->SetX($pdf->GetX() + 7);
        $pdf->Cell(0, 8, 'OBSERVAÇÃO:', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML('<div style="text-align: justify;">' . $checklist['observacoes'] . '</div>', true, false, true, false, '');
        $pdf->Ln(5);
    }

    // // Adicionar a imagem da assinatura
    // if (!empty($assinatura_path)) {
    //     $pdf->addSignature($assinatura_path);
    // }

    // // Adicionar a linha de assinatura
    // $pdf->Ln(15);
    // $pdf->Cell(0, 4, '__________________________________', 0, 1, 'C');
    // $pdf->SetFont('helvetica', 'B', 11);
    // $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    // $pdf->SetFont('helvetica', '', 11);
    // $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    // Saída
    $pdf->Output('Checklist_' . mb_strtoupper($checklist['titulo'], 'UTF-8') . '.pdf', 'I');

} else {
    echo "ID do checklist não fornecido.";
    exit;
}
?>
