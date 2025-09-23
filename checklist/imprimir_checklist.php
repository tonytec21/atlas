<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  

require_once('../oficios/tcpdf/tcpdf.php');  
include(__DIR__ . '/db_connection2.php');  
error_reporting(E_ERROR | E_PARSE);  
date_default_timezone_set('America/Sao_Paulo');  

class PDF extends TCPDF  
{  
    private $criado_por;  
    private $isCanceled = false;  

    public function Header()  
    {  
        $image_file = '../style/img/timbrado.png';  
        $currentMargins = $this->getMargins();  
        $this->SetAutoPageBreak(false, 0);  
        $this->SetMargins(0, 0, 0);  
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);  

        if ($this->isCanceled) {  
            $this->SetAlpha(0.2);  
            $this->StartTransform();  
            $this->Rotate(45, $this->getPageWidth() / 2, $this->getPageHeight() / 2);  
            $this->SetFont('helvetica', 'B', 60);  
            $this->SetTextColor(255, 0, 0);  
            $this->Text($this->getPageWidth() / 7, $this->getPageHeight() / 2.5, 'CANCELADO');  
            $this->StopTransform();  
            $this->SetAlpha(1);  
        }  

        $this->SetAutoPageBreak(true, 25);  
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);  
        $this->SetY(25);  
    }  

    public function Footer()  
    {  
        $textColor = [0, 0, 0];  
        $this->SetY(-14.5);  
        $this->SetFont('helvetica', 'I', 8);  
        $this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);  
        $this->SetX(-23);  
        $this->Cell(0, 11,  
            'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(),  
            0, false, 'L', 0, '', 0, false, 'T', 'M'  
        );  
    }  

    public function setCriadoPor($criado_por)  
    {  
        $this->criado_por = $criado_por;  
    }  

    public function addSignature($assinatura_path)  
    {  
        if (file_exists($assinatura_path)) {  
            $signatureWidth = 80;  
            $pageWidth = $this->getPageWidth();  
            $marginLeft = $this->getMargins()['left'];  
            $marginRight = $this->getMargins()['right'];  
            $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;  

            $this->Image($assinatura_path, (float)$centerX, $this->GetY() - 2, (float)$signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);  
        }  
    }  

    public function setIsCanceled($isCanceled)  
    {  
        $this->isCanceled = $isCanceled;  
    }  
}  

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

    $logged_in_user = $_SESSION['username'];  
    $logged_in_user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");  
    $logged_in_user_query->bind_param("s", $logged_in_user);  
    $logged_in_user_query->execute();  
    $logged_in_user_result = $logged_in_user_query->get_result();  
    $logged_in_user_info = $logged_in_user_result->fetch_assoc();  
    $logged_in_user_nome = $logged_in_user_info['nome_completo'];  
    $logged_in_user_cargo = $logged_in_user_info['cargo'];  

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

    // Buscar títulos do checklist  
    $titulos_query = $conn->prepare("SELECT * FROM checklist_titulos WHERE checklist_id = ? ORDER BY id");  
    $titulos_query->bind_param("i", $checklist_id);  
    $titulos_query->execute();  
    $titulos_result = $titulos_query->get_result();  
    $titulos = $titulos_result->fetch_all(MYSQLI_ASSOC);  
    
    // Criar um mapa de títulos para fácil acesso  
    $titulos_map = [];  
    foreach ($titulos as $titulo) {  
        $titulos_map[$titulo['id']] = $titulo;  
    }  

    // Buscar itens do checklist  
    $itens_query = $conn->prepare("SELECT * FROM checklist_itens WHERE checklist_id = ?");  
    $itens_query->bind_param("i", $checklist_id);  
    $itens_query->execute();  
    $itens_result = $itens_query->get_result();  
    $itens = $itens_result->fetch_all(MYSQLI_ASSOC);  

    // Organizar itens por título/grupo  
    $itens_por_titulo = [];  
    $itens_sem_titulo = [];  
    
    foreach ($itens as $item) {  
        if (!empty($item['checklist_titulos_id']) && isset($titulos_map[$item['checklist_titulos_id']])) {  
            if (!isset($itens_por_titulo[$item['checklist_titulos_id']])) {  
                $itens_por_titulo[$item['checklist_titulos_id']] = [];  
            }  
            $itens_por_titulo[$item['checklist_titulos_id']][] = $item;  
        } else {  
            $itens_sem_titulo[] = $item;  
        }  
    }  

    $isCanceled = ($checklist['status'] === 'removido');  

    $pdf = new PDF();  
    $pdf->SetMargins(12, 40, 11);  
    $pdf->setCriadoPor($criado_por);  
    $pdf->setIsCanceled($isCanceled);  

    $pdf->AddPage();  
    $pdf->SetFont('helvetica', '', 11);  

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
    
    // Processar títulos/grupos e seus itens  
    foreach ($titulos as $titulo) {  
        if (isset($itens_por_titulo[$titulo['id']]) && !empty($itens_por_titulo[$titulo['id']])) {  
            // Adicionar título do grupo  
            $htmlItens .= '<tr>  
                <td colspan="2" style="width: 99%; background-color: #e9ecef; padding: 5px; font-weight: bold;">  
                    <i style="color: #007bff;">' . htmlspecialchars($titulo['titulo']) . '</i>  
                </td>
            </tr>';  
            $htmlItens .= '<tr><td colspan="2" style="height: 1px; font-size: 4px"></td></tr>';
            
            // Adicionar itens do grupo  
            foreach ($itens_por_titulo[$titulo['id']] as $item) {  
                $htmlItens .= '<tr>  
                    <td style="width: 5%;text-align: right;"><img src="../style/img/check-icon.png" width="10">&nbsp;&nbsp;&nbsp;</td>  
                    <td style="width: 94%;text-align: justify;">' . htmlspecialchars($item['item']) . '</td>  
                </tr>';  
            }  
            $htmlItens .= '<tr><td colspan="2" style="height: 1px; font-size: 4px"></td></tr>';
            
            // Adicionar observação do título/grupo, se existir  
            if (!empty($titulo['observacoes'])) {  
                $htmlItens .= '<tr>  
                    <td style="width: 2%;"></td>  
                    <td style="width: 97%; font-style: italic; text-align: justify; ">  
                    <b>Observação:</b> ' . htmlspecialchars($titulo['observacoes']) . '
                    </td>  
                </tr>';  
            }  
            
            // Espaço após cada grupo  
            $htmlItens .= '<tr><td colspan="2" style="height: 8px;"></td></tr>';  
        }  
    }  
    
    // Processar itens sem título/grupo  
    if (!empty($itens_sem_titulo)) {  
        if (!empty($titulos) && !empty($itens_por_titulo)) {  
            $htmlItens .= '<tr>  
                <td colspan="2" style="background-color: #f8f9fa; padding: 5px; font-weight: bold;">  
                    <i>Itens sem grupo</i>  
                </td>  
            </tr>';  
        }  
        
        foreach ($itens_sem_titulo as $item) {  
            $htmlItens .= '<tr>  
                <td style="width: 5%;text-align: right;"><img src="../style/img/check-icon.png" width="10">&nbsp;&nbsp;&nbsp;</td>  
                <td style="width: 94%;text-align: justify;">' . htmlspecialchars($item['item']) . '</td>  
            </tr>';  
        }  
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

    $pdf->Output('Checklist_' . mb_strtoupper($checklist['titulo'], 'UTF-8') . '.pdf', 'I');  

} else {  
    echo "ID do checklist não fornecido.";  
    exit;  
}  
?>