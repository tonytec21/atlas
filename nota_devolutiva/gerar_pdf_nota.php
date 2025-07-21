<?php  
require('../oficios/tcpdf/tcpdf.php');  

// Carregar o número da nota devolutiva  
$numero = isset($_GET['numero']) ? $_GET['numero'] : '';  

if (empty($numero)) {  
    die("Número da nota devolutiva não informado.");  
}  

// Conexão com o banco de dados  
include(__DIR__ . '/db_connection2.php');  

// Verificar se as colunas necessárias existem  
$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'prazo_cumprimento'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN prazo_cumprimento TEXT AFTER corpo");  
}  

$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'cpf_cnpj'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN cpf_cnpj VARCHAR(20) AFTER apresentante");  
}  

$checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'origem_titulo'");  
if($checkColumns->num_rows == 0) {  
    $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN origem_titulo VARCHAR(200) AFTER titulo");  
}  

// Buscar dados da nota devolutiva  
$stmt = $conn->prepare("SELECT * FROM notas_devolutivas WHERE numero = ?");  
$stmt->bind_param("s", $numero);  
$stmt->execute();  
$result = $stmt->get_result();  

if ($result->num_rows === 0) {  
    die("Nota devolutiva não encontrada.");  
}  

$notaData = $result->fetch_assoc();  
$stmt->close();  

// Buscar dados da serventia  
$stmt = $conn->prepare("SELECT cidade FROM cadastro_serventia WHERE id = 1");  
$stmt->execute();  
$result = $stmt->get_result();  

$cidade = "Sua Cidade";  
if ($result->num_rows > 0) {  
    $serventiaData = $result->fetch_assoc();  
    $cidade = $serventiaData['cidade'];  
}  
$stmt->close();  
$conn->close();  

// Converter data para o formato brasileiro  
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

// Carregar assinaturas  
$signatures = [];  
if (file_exists(__DIR__ . '/../oficios/assinaturas/data.json')) {  
    $signatures = json_decode(file_get_contents(__DIR__ . '/../oficios/assinaturas/data.json'), true);  
}  
$signatureImage = '';  
foreach ($signatures as $signature) {  
    if ($signature['fullName'] == $notaData['assinante']) {  
        $signatureImage = $signature['assinatura'];  
        break;  
    }  
}  

// Configurar a classe PDF  
class PDF extends TCPDF  
{  
    // Cabeçalho do PDF  
    public function Header()  
    {  
        $image_file = '../style/img/timbrado.png'; // Verifique se o caminho está correto  
        
        // Definir largura e altura que você deseja forçar  
        $imageWidth = 210;  // Largura total da página A4 (em mm)  
        $imageHeight = 297; // Altura total da página A4 (em mm)  

        // Desativar as margens e o AutoPageBreak temporariamente  
        $this->SetAutoPageBreak(false, 0); // Desativar temporariamente a quebra automática de página  
        $this->SetMargins(0, 0, 0); // Remover margens para a imagem  
        
        // Redimensionar a imagem para ocupar toda a página, ignorando proporções  
        if (file_exists($image_file)) {  
            $this->Image($image_file, 0, 0, $imageWidth, $imageHeight, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);  
        }  

        // Restaurar as margens e o AutoPageBreak para o conteúdo subsequente  
        $this->SetAutoPageBreak(true, 25); // Restaurar o AutoPageBreak com a margem inferior de 2.5cm  
        $this->SetMargins(15, 45, 15);  // Restaurar as margens para o conteúdo  
        $this->SetY(35); // Ajuste para garantir que o conteúdo não sobreponha a imagem  
    }  

    // Rodapé do PDF  
    public function Footer()  
    {  
        $this->SetY(-15);  
        $this->SetFont('helvetica', 'I', 8);  
    }  

    // Adicionar parágrafo com recuo na primeira linha  
    public function AddParagraph($text, $lineHeight)  
    {  
        $this->SetX(15); // Recuo da margem esquerda  
        $paragraphs = explode("\n", $text);  
        foreach ($paragraphs as $paragraph) {  
            $this->SetX(15);  
            $paragraph = ltrim($paragraph, "\t"); // Remove o tab no início do parágrafo  
            $this->WriteHTML('<p style="text-align:justify; text-indent:2cm;">' . htmlspecialchars_decode($paragraph) . '</p>');  
        }  
    }  
}  

// Criar o documento PDF  
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);  
$pdf->SetCreator(PDF_CREATOR);  
$pdf->SetTitle('Nota Devolutiva ' . $notaData['numero']);  
$pdf->SetMargins(15, 45, 15); // Definir as margens (em mm): esquerda, superior, direita  
$pdf->SetAutoPageBreak(true, 25); // Definir a margem inferior  
$pdf->AddPage();  

// Ajustar o espaçamento entre linhas  
$lineHeight = 10 * 0.5;  

// Cidade e data  
$pdf->SetFont('helvetica', '', 11);  
$pdf->Cell(0, $lineHeight, $cidade . ', ' . formatDateToBrazilian($notaData['data']) . '.', 0, 1, 'R');  
$pdf->Ln(5);  

// Protocolo  
$pdf->SetFont('helvetica', 'B', 11);  
$pdf->writeHTML('<div style="text-align: justify;">Protocolo: ' . ($notaData['protocolo']) . '</div>', true, false, true, false, '');  
$pdf->Ln(1);  

// Data do protocolo (se existir)  
if (!empty($notaData['data_protocolo'])) {  
    $pdf->SetFont('helvetica', '', 11);  
    $dataProtocolo = new DateTime($notaData['data_protocolo']);  
    $pdf->writeHTML('<div style="text-align: justify;">Data do Protocolo: ' . $dataProtocolo->format('d/m/Y') . '</div>', true, false, true, false, '');  
    $pdf->Ln(1);  
}  

// Apresentante  
$pdf->SetFont('helvetica', '', 11);  
$pdf->writeHTML('<div style="text-align: justify;">' . 'Apresentante: '. ($notaData['apresentante']) .'</div>', true, false, true, false, '');  
$pdf->Ln(1);  

// CPF/CNPJ (se existir)  
if (!empty($notaData['cpf_cnpj'])) {  
    $pdf->SetFont('helvetica', '', 11);  
    $pdf->writeHTML('<div style="text-align: justify;">CPF/CNPJ: ' . $notaData['cpf_cnpj'] . '</div>', true, false, true, false, '');  
    $pdf->Ln(1);  
}  

// Processo de referência (se houver)  
// if (!empty($notaData['processo_referencia'])) {  
//     $pdf->SetFont('helvetica', '', 11);  
//     $pdf->writeHTML('<div style="text-align: justify;">Processo de Referência: ' . nl2br($notaData['processo_referencia']) . '</div>', true, false, true, false, '');   
//     $pdf->Ln(2);  
// }  

// $pdf->Ln(5);  

// Título  
if (!empty($notaData['titulo'])) {   
    $pdf->SetFont('helvetica', '', 11);  
    $pdf->writeHTML('<div style="text-align: justify;">Título Apresentado: ' . ($notaData['titulo']) . '</div>', true, false, true, false, '');  
    
    // Origem do título (se existir)  
    if (!empty($notaData['origem_titulo'])) {  
        $pdf->Ln(2);  
        $pdf->SetFont('helvetica', '', 11);  
        $pdf->writeHTML('<div style="text-align: justify;">Origem do Título: ' . $notaData['origem_titulo'] . '</div>', true, false, true, false, '');  
    }  
    
    $pdf->Ln(8);  
}  

// // Adicionar o título "Motivos da Devolução" antes do conteúdo do corpo  
// $pdf->SetFont('helvetica', 'B', 11);  
// $pdf->writeHTML('<div style="text-align: justify;">MOTIVO DA DEVOLUÇÃO:</div>', true, false, true, false, '');  
// $pdf->Ln(2);  

// Número da nota devolutiva  
$pdf->SetFont('helvetica', 'B', 11);  
$pdf->writeHTML('<div style="text-align: center;">NOTA DEVOLUTIVA Nº.: ' . $notaData['numero'] . '</div>', true, false, true, false, '');  
$pdf->Ln(5);  

// Processar o corpo da nota devolutiva  
$pdf->SetFont('helvetica', '', 11);  

// Decodificar o conteúdo HTML do banco de dados  
$conteudoNota = html_entity_decode($notaData['corpo']);  

// Usar preg_split para dividir o conteúdo em <p>, <blockquote> e <table>  
$partes = preg_split('/(<blockquote>.*?<\/blockquote>|<table.*?<\/table>)/is', $conteudoNota, -1, PREG_SPLIT_DELIM_CAPTURE);  

// Iterar sobre as partes do conteúdo e processá-las individualmente  
foreach ($partes as $parte) {  
    // Verificar se é um <blockquote>  
    if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $matches)) {  
        // Processar o blockquote  
        $pdf->Ln(-6);  
        $pdf->SetX(60);  
        $blockquoteWidth = $pdf->getPageWidth() - 60 - $pdf->getMargins()['right'] - 1;  
        $pdf->SetFont('helvetica', 'I', 12);  
        $pdf->MultiCell($blockquoteWidth, 5, strip_tags($matches[1]), 0, 'J', false, 1);  
        $pdf->SetY($pdf->GetY() + 3);  
    }  
    // Verificar se é uma <table>  
    elseif (preg_match('/<table.*?<\/table>/is', $parte)) {  
        // Renderizar a tabela diretamente com o HTML completo  
        $pdf->SetFont('helvetica', '', 11);  
        $pdf->writeHTML($parte, true, false, true, false, '');  
        $pdf->Ln(5);  
    } else {  
        // Processar normalmente os conteúdos fora de <blockquote> e <table>  
        if (preg_match_all('/<p>(.*?)<\/p>/is', $parte, $matchesParagrafo)) {  
            foreach ($matchesParagrafo[1] as $paragrafoTexto) {  
                $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $paragrafoTexto . '</div>', true, false, true, false);  
                $pdf->Ln(5);  
            }  
        } else {  
            $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);  
            $pdf->Ln(5);  
        }  
    }  
}  

// Adicionar seção de Prazo Para Cumprimento (se houver)  
if (!empty($notaData['prazo_cumprimento'])) {  
    $pdf->Ln(3);  
    $pdf->SetFont('helvetica', 'B', 11);  
    $pdf->writeHTML('<div style="text-align: justify;">PRAZO PARA CUMPRIMENTO:</div>', true, false, true, false, '');  
    $pdf->Ln(2);  
    
    $pdf->SetFont('helvetica', '', 11);  
    
    // Decodificar o conteúdo HTML do prazo  
    $conteudoPrazo = html_entity_decode($notaData['prazo_cumprimento']);  
    
    // Usar preg_split para dividir o conteúdo em <p>, <blockquote> e <table>  
    $partesPrazo = preg_split('/(<blockquote>.*?<\/blockquote>|<table.*?<\/table>)/is', $conteudoPrazo, -1, PREG_SPLIT_DELIM_CAPTURE);  
    
    // Iterar sobre as partes do conteúdo do prazo e processá-las individualmente  
    foreach ($partesPrazo as $parte) {  
        // Verificar se é um <blockquote>  
        if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $matches)) {  
            // Processar o blockquote  
            $pdf->Ln(-6);  
            $pdf->SetX(60);  
            $blockquoteWidth = $pdf->getPageWidth() - 60 - $pdf->getMargins()['right'] - 1;  
            $pdf->SetFont('helvetica', 'I', 12);  
            $pdf->MultiCell($blockquoteWidth, 5, strip_tags($matches[1]), 0, 'J', false, 1);  
            $pdf->SetY($pdf->GetY() + 3);  
        }  
        // Verificar se é uma <table>  
        elseif (preg_match('/<table.*?<\/table>/is', $parte)) {  
            // Renderizar a tabela diretamente com o HTML completo  
            $pdf->SetFont('helvetica', '', 11);  
            $pdf->writeHTML($parte, true, false, true, false, '');  
            $pdf->Ln(5);  
        } else {  
            // Processar normalmente os conteúdos fora de <blockquote> e <table>  
            if (preg_match_all('/<p>(.*?)<\/p>/is', $parte, $matchesParagrafo)) {  
                foreach ($matchesParagrafo[1] as $paragrafoTexto) {  
                    $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $paragrafoTexto . '</div>', true, false, true, false);  
                    $pdf->Ln(5);  
                }  
            } else {  
                $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);  
                $pdf->Ln(5);  
            }  
        }  
    }  
}  

// // Dados complementares (se houver)  
// if (!empty($notaData['dados_complementares'])) {  
//     $pdf->Ln(5);  
//     $pdf->SetFont('helvetica', 'B', 11);  
//     $pdf->Cell(0, $lineHeight, 'Dados Complementares:', 0, 1, 'L');  
//     $pdf->Ln(2);  
    
//     $pdf->SetFont('helvetica', '', 11);  
//     $pdf->writeHTML('<div style="text-align: justify;">' . nl2br($notaData['dados_complementares']) . '</div>', true, false, true, false, '');  
//     $pdf->Ln(8);  
// }  

// Assinatura  
$pdf->SetFont('helvetica', '', 11);  
$pdf->writeHTML('<p style="text-indent: 20mm; text-align: justify;">Atenciosamente,</p>', true, false, true, false);  
$pdf->Ln(15);  

// Adicionar imagem da assinatura, se disponível  
if ($signatureImage) {  
    $signatureImagePath = __DIR__ . '/../oficios/assinaturas/' . $signatureImage;  
    if (file_exists($signatureImagePath)) {  
        // Obter dimensões da imagem  
        list($imageWidth, $imageHeight) = getimagesize($signatureImagePath);  

        $signatureWidth = 80; // largura da imagem da assinatura  
        $pageWidth = $pdf->getPageWidth();  
        $marginLeft = $pdf->getMargins()['left'];  
        $marginRight = $pdf->getMargins()['right'];  
        $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;  

        $pdf->Image($signatureImagePath, $centerX, $pdf->GetY() - 10, $signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);  

        // Ajustar o espaço vertical com base na largura da imagem  
        if ($imageWidth < 2000) {  
            $pdf->Ln(15);  
        } else {  
            $pdf->Ln(4);  
        }  
    }  
}  

$pdf->Cell(0, $lineHeight, '__________________________________', 0, 1, 'C');  
$pdf->SetFont('helvetica', 'B', 11);  
$pdf->Cell(0, $lineHeight, ($notaData['assinante']), 0, 1, 'C');  
$pdf->SetFont('helvetica', '', 11);  
$pdf->Cell(0, $lineHeight, ($notaData['cargo_assinante']), 0, 1, 'C');  

// Gerar o PDF  
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF  
$nomeArquivo = str_replace('/', '_', $notaData['numero']);
$pdf->Output('Nota_Devolutiva_' . $nomeArquivo . '.pdf', 'I');
?>