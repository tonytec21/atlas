<?php
include(__DIR__ . '/db_connection.php');
require('../oficios/tcpdf/tcpdf.php');

// Carregar o número do protocolo
$numero = isset($_GET['id']) ? $_GET['id'] : 0;

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8"); // Define a codificação do charset para utf8

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Buscar dados da tarefa
$stmt = $conn->prepare("SELECT * FROM tarefas WHERE id = ?");
$stmt->bind_param("i", $numero); // "i" para aceitar o formato de número inteiro
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tarefa não encontrada.");
}

$tarefaData = $result->fetch_assoc();
$stmt->close();

// Buscar título da categoria
$stmt = $conn->prepare("SELECT titulo FROM categorias WHERE id = ?");
$stmt->bind_param("i", $tarefaData['categoria']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Categoria não encontrada.");
}

$categoriaData = $result->fetch_assoc();
$stmt->close();

// Buscar título da origem
$stmt = $conn->prepare("SELECT titulo FROM origem WHERE id = ?");
$stmt->bind_param("i", $tarefaData['origem']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Origem não encontrada.");
}

$origemData = $result->fetch_assoc();
$stmt->close();
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

// Configurar a classe PDF
class PDF extends TCPDF
{
    private $criado_por;

       // Cabeçalho do PDF
       public function Header()
       {
           $image_file = '../style/img/timbrado.png'; // Verifique se o caminho está correto
           
           // Salva as margens atuais
           $currentMargins = $this->getMargins();
           
           // Desativa temporariamente as margens e AutoPageBreak
           $this->SetAutoPageBreak(false, 0); // Desativa o AutoPageBreak para permitir a imagem cobrir toda a página
           $this->SetMargins(0, 0, 0);
   
           // Inserir a imagem ocupando toda a página
           @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
           
           // Restaura o AutoPageBreak e as margens para o conteúdo subsequente
           $this->SetAutoPageBreak(true, 10); // Ativa novamente o AutoPageBreak com a margem inferior padrão
           $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
           $this->SetY(25); // Define o ponto Y após a imagem para o conteúdo
       }
}

// Criar o documento PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Protocolo Geral ' . $tarefaData['id']);
$pdf->SetMargins(25, 45, 25); // Definir as margens (em mm): esquerda, superior, direita
$pdf->SetAutoPageBreak(true, 10); // Definir a margem inferior
$pdf->AddPage();

// Ajustar o espaçamento entre linhas
$lineHeight = 10 * 0.5;

// Protocolo Geral
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, $lineHeight, 'PROTOCOLO GERAL', 0, 1, 'C');
$pdf->Ln(10);

// Início da Tabela
$pdf->SetFont('helvetica', '', 10);
$html = '
<style>
    .header-cell { padding: 2px 5px; }
    .data-cell { background-color: #e9ecef; border: 1px solid #ced4da; border-radius: .25rem; padding: 5px; }
</style>
<table border="0" cellpadding="5">
    <tr>
        <td class="header-cell" width="33%">Número de Protocolo Geral:</td>
        <td class="header-cell" width="33%">Data do Protocolo:</td>
        <td class="header-cell" width="33%">Data Limite:</td>
    </tr>
    <tr>
        <td class="data-cell" width="33%">' . convertToUtf8($tarefaData['id']) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8(formatDateToBrazilian($tarefaData['data_criacao'])) . '</td>
        <td class="data-cell" width="33%">' . convertToUtf8(formatDateToBrazilian($tarefaData['data_limite'])) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="39.5%">Categoria:</td>
        <td class="header-cell" width="59.5%">Título:</td>
    </tr>
    <tr>
        <td class="data-cell" width="39.5%">' . convertToUtf8($categoriaData['titulo']) . '</td>
        <td class="data-cell" width="59.5%" style="text-align:justify;">' . convertToUtf8($tarefaData['titulo']) . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="39.5%">Origem:</td>
        <td class="header-cell" width="59.5%">Funcionário Responsável:</td>
    </tr>
    <tr>
        <td class="data-cell" width="39.5%">' . convertToUtf8($origemData['titulo']) . '</td>
        <td class="data-cell" width="59.5%">' . (!empty($tarefaData['funcionario_responsavel']) ? convertToUtf8($tarefaData['funcionario_responsavel']) : 'Não informado') . '</td>
    </tr><br>
    <tr>
        <td class="header-cell" width="100%">Descrição:</td>
    </tr>
    <tr>
        <td class="data-cell" width="99.2%" style="text-align:justify;">' . (!empty($tarefaData['descricao']) ? convertToUtf8($tarefaData['descricao']) : 'Não informado') . '</td>
    </tr>
</table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Criado Por (exibir no canto inferior direito)
$pdf->SetY(-30); // Ajusta a posição vertical para o rodapé
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Protocolo criado por: ' . convertToUtf8($tarefaData['criado_por']), 0, 0, 'R');

// Gerar o PDF
ob_clean(); // Limpar buffer de saída para evitar erros de envio de PDF
$pdf->Output('Protocolo_Geral_' . $tarefaData['id'] . '.pdf', 'I');
?>
