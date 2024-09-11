<?php
require('tcpdf/tcpdf.php');

// Carregar o número do ofício
$numero = isset($_GET['numero']) ? $_GET['numero'] : 0;

// Conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados do ofício
$stmt = $conn->prepare("SELECT * FROM oficios WHERE numero = ?");
$stmt->bind_param("s", $numero); // "s" to accept the format "NUMERO/ANO"
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ofício não encontrado.");
}

$oficioData = $result->fetch_assoc();
$stmt->close();

// Buscar dados da serventia
$stmt = $conn->prepare("SELECT cidade FROM cadastro_serventia WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Dados da serventia não encontrados.");
}

$serventiaData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Obter NUMERO_SEQUENCIAL e ANO_VIGENTE
list($numeroSequencial, $anoVigente) = explode('/', $oficioData['numero']);

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
$signatures = json_decode(file_get_contents(__DIR__ . '/assinaturas/data.json'), true);
$signatureImage = '';
foreach ($signatures as $signature) {
    if ($signature['fullName'] == $oficioData['assinante']) {
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
        $this->Image($image_file, 0, 0, $imageWidth, $imageHeight, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

        // Restaurar as margens e o AutoPageBreak para o conteúdo subsequente
        $this->SetAutoPageBreak(true, 25); // Restaurar o AutoPageBreak com a margem inferior de 2.5cm
        $this->SetMargins(25, 45, 25);  // Restaurar as margens para o conteúdo
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
        $this->SetX(25); // Recuo da margem esquerda
        $paragraphs = explode("\n", $text);
        foreach ($paragraphs as $paragraph) {
            $this->SetX(25);
            $paragraph = ltrim($paragraph, "\t"); // Remove o tab no início do parágrafo
            $this->WriteHTML('<p style="text-align:justify; text-indent:2cm;">' . htmlspecialchars_decode($paragraph) . '</p>');
        }
    }
}


// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Ofício ' . $oficioData['numero']);
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 25); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 10 * 0.5;

// Cidade e data
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, $lineHeight, $serventiaData['cidade'] . ', ' . formatDateToBrazilian($oficioData['data']), 0, 1, 'R');
$pdf->Ln(3);

// Caminho para o arquivo configuracao.json
$configFile = __DIR__ . '/configuracao.json';

// Verificar se o arquivo JSON existe
if (file_exists($configFile)) {
    // Carregar o conteúdo do arquivo JSON
    $configData = json_decode(file_get_contents($configFile), true);
    
    // Verificar se a chave 'habilitar' está definida como 'S'
    if (isset($configData['complemento_oficio']['habilitar']) && $configData['complemento_oficio']['habilitar'] === 'S') {
        // Obter o complemento definido no arquivo JSON
        $complemento = isset($configData['complemento_oficio']['complemento']) ? $configData['complemento_oficio']['complemento'] : '';
    } else {
        // Não há complemento
        $complemento = '';
    }
} else {
    // Se o arquivo não existir, não adiciona complemento
    $complemento = '';
}

// Número do ofício
$pdf->SetFont('helvetica', 'B', 12);

// Exibir o número do ofício com o complemento, se existir
$numeroOficio = 'Ofício nº.: ' . $oficioData['numero'];
if (!empty($complemento)) {
    $numeroOficio .= ' - ' . $complemento; // Adicionar complemento ao número do ofício
}

$pdf->writeHTML('<div style="text-align: justify;">' . $numeroOficio . '</div>', true, false, true, false, '');
$pdf->Ln(5);


// Forma de Tratamento e Destinatário
if (!empty($oficioData['tratamento'])) {
    $pdf->SetFont('helvetica', '', 12);
    $pdf->writeHTML('<div style="text-align: justify;">' . ($oficioData['tratamento']) . '</div>', true, false, true, false, '');
    $pdf->Ln(0);
}

$pdf->SetFont('helvetica', 'B', 12);
$pdf->writeHTML('<div style="text-align: justify;">' . ($oficioData['destinatario']) . '</div>', true, false, true, false, '');
$pdf->Ln(0);

// Cargo
if (!empty($oficioData['cargo'])) {
    $pdf->SetFont('helvetica', '', 12);
    $pdf->writeHTML('<div style="text-align: justify;">' . ($oficioData['cargo']) . '</div>', true, false, true, false, '');
    $pdf->Ln(0);
}
$pdf->Ln(5);

// Assunto
$pdf->SetFont('helvetica', 'B', 12);
$pdf->writeHTML('<div style="text-align: justify;">' . ('Assunto: ' . $oficioData['assunto']) . '</div>', true, false, true, false, '');
$pdf->Ln(8);

// Agora processar o corpo do ofício, separando os <p> e <blockquote>
$pdf->SetFont('helvetica', '', 12);

// Decodificar o conteúdo HTML do banco de dados
$conteudoOficio = html_entity_decode($oficioData['corpo']);

// Usar preg_split para dividir o conteúdo em <p> e <blockquote>
$partes = preg_split('/(<blockquote>.*?<\/blockquote>)/is', $conteudoOficio, -1, PREG_SPLIT_DELIM_CAPTURE);

// Definir as margens padrão do documento
$leftMarginOriginal = 25;  // Margem original de 2,5 cm (25 mm)
$rightMarginOriginal = 25; // Margem direita de 2,5 cm (25 mm)
$topMargin = 45;           // Margem superior de 4,5 cm (45 mm)

// Definir as margens iniciais
$pdf->SetMargins($leftMarginOriginal, $topMargin, $rightMarginOriginal);

// Iterar sobre as partes do conteúdo e processá-las individualmente
foreach ($partes as $parte) {
    // Verificar se é um <blockquote>
    if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $matches)) {
        // Ajustar a distância antes do blockquote
        $pdf->Ln(-6);  // Ajustar para diminuir o espaçamento antes do blockquote

        // Forçar a posição X para 6 cm (60 mm) da margem esquerda
        $pdf->SetX(60);

        // Calcular a largura do bloco de texto para caber nas margens definidas
        $blockquoteWidth = $pdf->getPageWidth() - 60 - $rightMarginOriginal - 1;  // Redução de 1 mm

        // Aplicar estilo para o blockquote e ajustar o conteúdo para caber nas margens
        $pdf->SetFont('helvetica', 'I', 10); // Itálico apenas para o blockquote
        $pdf->MultiCell($blockquoteWidth, 5, strip_tags($matches[1]), 0, 'J', false, 1); // Remove as tags HTML com strip_tags

        // Restaurar as margens originais após o blockquote
        $pdf->SetMargins($leftMarginOriginal, $topMargin, $rightMarginOriginal);

        // Ajustar o espaçamento após o blockquote
        $pdf->Ln(5);  // Ajustar o espaçamento após o blockquote
    } else {
        // Processar os parágrafos individualmente dentro da parte não pertencente ao blockquote
        $paragrafos = preg_split('/(<p>.*?<\/p>)/is', $parte, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($paragrafos as $paragrafo) {
            if (preg_match('/<p>(.*?)<\/p>/is', $paragrafo, $matchParagrafo)) {
                // Renderizar cada parágrafo individualmente
                $pdf->SetFont('helvetica', '', 12);
                $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . strip_tags($matchParagrafo[1]) . '</div>', true, false, true, false);
                $pdf->Ln(5); // Espaçamento entre parágrafos
            }
        }
    }
}

// Assinatura
$pdf->SetFont('helvetica', '', 12);
$pdf->writeHTML('<p style="text-indent: 20mm; text-align: justify;">Atenciosamente,</p>', true, false, true, false);
$pdf->Ln(15);

// Adicionar imagem da assinatura, se disponível
if ($signatureImage) {
    $signatureImagePath = __DIR__ . '/assinaturas/' . $signatureImage;
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
    } else {
        // Debug: show the path of the signature image if it doesn't exist
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, $lineHeight, 'Assinatura não encontrada: ' . $signatureImagePath, 0, 1, 'C');
        $pdf->Ln(5);
    }
}

$pdf->Cell(0, $lineHeight, '__________________________________', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, $lineHeight, ($oficioData['assinante']), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, $lineHeight, ($oficioData['cargo_assinante']), 0, 1, 'C');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Oficio_' . $numeroSequencial . '_' . $anoVigente . '.pdf', 'I');

?>
