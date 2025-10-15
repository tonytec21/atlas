<?php
// pedidos_certidao/gerar_recibo_pedido_termica.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

$conn = getDatabaseConnection();

// ================== Pedido + O.S. ==================
$stmt = $conn->prepare("
  SELECT p.*, o.descricao_os
  FROM pedidos_certidao p
  LEFT JOIN ordens_de_servico o ON o.id = p.ordem_servico_id
  WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { die('Pedido não encontrado'); }

// ================== Razão social da serventia ==================
$razaoSocial = 'Cartório';
try {
  $stServ = $conn->query("SELECT razao_social FROM cadastro_serventia WHERE status = 1 ORDER BY id LIMIT 1");
  $razaoSocial = (string)($stServ->fetchColumn() ?: '');
  if ($razaoSocial === '') {
    $stServ2 = $conn->query("SELECT razao_social FROM cadastro_serventia ORDER BY id LIMIT 1");
    $razaoSocial = (string)($stServ2->fetchColumn() ?: 'Cartório');
  }
} catch (Throwable $e) {
  // mantém fallback
}

// ================== Quem protocolou (nome completo) ==================
$usuarioProtocolou = '';
foreach ([
  'criado_por','usuario','usuario_criacao','criado_por_usuario',
  'registrado_por','protocolo_por','protocolado_por','atualizado_por'
] as $col) {
  if (!empty($p[$col])) { $usuarioProtocolou = (string)$p[$col]; break; }
}
if (!$usuarioProtocolou && !empty($_SESSION['username'])) {
  $usuarioProtocolou = (string)$_SESSION['username'];
}

$nomeProtocolou = '';
if ($usuarioProtocolou !== '') {
  try {
    $stU = $conn->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ? LIMIT 1");
    $stU->execute([$usuarioProtocolou]);
    $nomeProtocolou = (string)($stU->fetchColumn() ?: '');
  } catch (Throwable $e) { /* ignore */ }
}
if ($nomeProtocolou === '' && $usuarioProtocolou !== '') {
  $nomeProtocolou = $usuarioProtocolou;
}

// ================== URLs / QR ==================
$apiConfig  = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL   = 'https://sistemaatlas.com.br';
$urlPublica = rtrim($BASE_URL, '/') . '/' . ($p['protocolo'] ?? '');
$qrPath     = __DIR__ . "/qrcodes/pedido_{$id}.png";

// ================== TCPDF ==================
require_once(__DIR__ . '/../oficios/tcpdf/tcpdf.php');

// Formato 80mm (largura) x 297mm (altura A4; a impressora só imprime o necessário)
$pageWidth  = 80;
$pageHeight = 297;
$pageFormat = [ $pageWidth, $pageHeight ];

class PDF_TERMICA extends TCPDF {
  protected $protTexto;
  protected $razaoSocial;

  public function setProtocoladoPor(?string $texto) { $this->protTexto = $texto ?: null; }
  public function setRazaoSocial(?string $rs) { $this->razaoSocial = $rs ?: 'Cartório'; }

  public function Header() {
    // Cabeçalho
    $this->SetFont('helvetica', 'B', 11);
    // Razão social (quebra automática)
    $this->MultiCell(0, 5, $this->razaoSocial, 0, 'C', false, 1, '', '', true, 0, true, true, 0);

    $this->SetFont('helvetica', 'B', 12);
    $this->Cell(0, 6, 'Recibo de Protocolo', 0, 1, 'C');

    if ($this->protTexto) {
      $this->SetFont('helvetica', '', 8);
      $this->SetTextColor(80, 80, 80);
      $this->MultiCell(0, 5, 'Protocolado por: ' . $this->protTexto, 0, 'C', false, 1);
      $this->SetTextColor(0, 0, 0);
    }

    // Um pequeno espaço e marca de início de conteúdo
    $this->Ln(1);
    $this->setPageMark(); // <<< garante que o conteúdo comece abaixo do cabeçalho
  }

  public function Footer() {
    $this->SetY(-70);
    $this->SetFont('helvetica', '', 8);
    $this->Cell(0, 5, 'Impresso em ' . date('d/m/Y H:i'), 0, 0, 'C');
  }
}

// Construtor com 5 argumentos
$pdf = new PDF_TERMICA('P', 'mm', $pageFormat, true, 'UTF-8');

$pdf->SetCreator('Atlas');
$pdf->SetAuthor('Atlas');
$pdf->SetTitle('Recibo de Protocolo - ' . ($p['protocolo'] ?? ''));

// >>> Margens: aumentei a superior para 24mm para não sobrepor o conteúdo
$pdf->SetMargins(4, 24, 4);
$pdf->SetAutoPageBreak(true, 8);

$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

$pdf->setRazaoSocial($razaoSocial);
$pdf->setProtocoladoPor($nomeProtocolou);

$pdf->AddPage();

// ============== CONTEÚDO (com quebras automáticas) ==============
$pdf->SetFont('helvetica','',10);

// Protocolo e Status
$pdf->MultiCell(0, 5, 'Protocolo: ' . (string)($p['protocolo'] ?? ''), 0, 'L', false, 1);
$pdf->MultiCell(0, 5, 'Status: ' . str_replace('_',' ', (string)($p['status'] ?? '')), 0, 'L', false, 1);
$pdf->Ln(1);

// Dados
$pdf->SetFont('helvetica','B',10);
$pdf->MultiCell(0, 5, 'Dados', 0, 'L', false, 1);

$pdf->SetFont('helvetica','',10);
$atrTipo = trim(($p['atribuicao'] ?? '') . ' / ' . ($p['tipo'] ?? ''));
if ($atrTipo !== '/' && trim($atrTipo) !== '') {
  $pdf->MultiCell(0, 5, 'Atribuicao/Tipo: ' . $atrTipo, 0, 'L', false, 1);
}

$req = (string)($p['requerente_nome'] ?? '');
if (!empty($p['requerente_doc'])) { $req .= ' (' . $p['requerente_doc'] . ')'; }
if ($req !== '') {
  $pdf->MultiCell(0, 5, 'Requerente: ' . $req, 0, 'L', false, 1);
}

$contatoEmail = trim((string)($p['requerente_email'] ?? ''));
$contatoTel   = trim((string)($p['requerente_tel'] ?? ''));
$contato = ($contatoEmail !== '' ? $contatoEmail : '-') . ' – ' . ($contatoTel !== '' ? $contatoTel : '-');
$pdf->MultiCell(0, 5, 'Contato: ' . $contato, 0, 'L', false, 1);

$port = (string)($p['portador_nome'] ?? '-');
if (!empty($p['portador_doc'])) { $port .= ' (' . $p['portador_doc'] . ')'; }
$pdf->MultiCell(0, 5, 'Portador: ' . $port, 0, 'L', false, 1);

// Referências
$refs = json_decode($p['referencias_json'] ?? '{}', true);
if (is_array($refs) && $refs) {
  $pdf->Ln(1);
  $pdf->SetFont('helvetica','B',10);
  $pdf->MultiCell(0, 5, 'Referencias', 0, 'L', false, 1);
  $pdf->SetFont('helvetica','',10);
  foreach ($refs as $k => $v) {
    $label = ucwords(str_replace('_',' ', (string)$k));
    $pdf->MultiCell(0, 5, $label . ': ' . (string)$v, 0, 'L', false, 1);
  }
}

// O.S.
if (!empty($p['ordem_servico_id']) || !empty($p['descricao_os']) || isset($p['total_os'])) {
  $pdf->Ln(1);
  $pdf->SetFont('helvetica','B',10);
  $pdf->MultiCell(0, 5, 'O.S.', 0, 'L', false, 1);

  $pdf->SetFont('helvetica','',10);
  if (!empty($p['ordem_servico_id'])) {
    $pdf->MultiCell(0, 5, 'Numero: #' . (int)$p['ordem_servico_id'], 0, 'L', false, 1);
  }
  if (!empty($p['descricao_os'])) {
    $pdf->MultiCell(0, 5, 'Descricao: ' . (string)$p['descricao_os'], 0, 'L', false, 1);
  }
  if (isset($p['total_os'])) {
    $pdf->MultiCell(0, 5, 'Total: R$ ' . number_format((float)$p['total_os'], 2, ',', '.'), 0, 'L', false, 1);
  }
}

// Linha separadora
$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T', 1, 'L');
$pdf->Ln(1);

// QR Code
$pdf->SetFont('helvetica','',9);
$pdf->MultiCell(0, 5, 'Rastreie este protocolo:', 0, 'C', false, 1);

$qrSize = 32;
$x = ($pageWidth - $qrSize) / 2;
$y = $pdf->GetY() + 1;

if (file_exists($qrPath)) {
  $pdf->Image($qrPath, $x, $y, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0);
} else {
  $style = ['border' => 0, 'padding' => 0, 'fgcolor' => [0,0,0], 'bgcolor' => false];
  $pdf->write2DBarcode($urlPublica, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, 'N');
}
$pdf->Ln($qrSize + 2);

$pdf->SetFont('helvetica','',8);
$pdf->SetTextColor(80,80,80);
$pdf->MultiCell(0, 5, 'Aponte a camera do celular para o QR Code', 0, 'C', false, 1);
$pdf->SetTextColor(0,0,0);

// Link (wrap)
$pdf->SetFont('helvetica','',9);
$pdf->MultiCell(0, 5, 'Ou acesse:', 0, 'C', false, 1);
$pdf->MultiCell(0, 5, $urlPublica, 0, 'C', false, 1);

// Saída
$pdf->Output('recibo-protocolo-termica-' . ($p['protocolo'] ?? '') . '.pdf', 'I');
