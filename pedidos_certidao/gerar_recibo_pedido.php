<?php
// pedidos_certidao/gerar_recibo_pedido.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) die('ID inválido');

$conn = getDatabaseConnection();
$stmt = $conn->prepare("SELECT p.*, o.descricao_os FROM pedidos_certidao p
                        LEFT JOIN ordens_de_servico o ON o.id = p.ordem_servico_id
                        WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) die('Pedido não encontrado');

$apiConfig  = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL   = $apiConfig['base_url'] ?? 'https://sistemaatlas.com.br';
$urlPublica = rtrim($BASE_URL,'/').'/'.$p['protocolo'];

$qrPath     = __DIR__."/qrcodes/pedido_{$id}.png";
$bgPath     = __DIR__ . '/../style/img/timbrado.png';

// TCPDF
require_once(__DIR__ . '/../oficios/tcpdf/tcpdf.php');

class PDF extends TCPDF {
  protected $bgPath = null;

  public function setBackground($path){
    $this->bgPath = $path;
  }

  public function Header(){
    // Aplica o timbrado ocupando a página inteira
    if ($this->bgPath && file_exists($this->bgPath)) {
      $bMargin = $this->getBreakMargin();
      $auto_page_break = $this->AutoPageBreak;
      $this->SetAutoPageBreak(false, 0);
      $this->Image(
        $this->bgPath,
        0, 0,
        $this->getPageWidth(),        // largura total
        $this->getPageHeight(),       // altura total
        '', '', '', false, 300, '', false, false, 0
      );
      $this->SetAutoPageBreak($auto_page_break, $bMargin);
      $this->setPageMark(); // garante que o conteúdo venha por cima
    }
  }
}

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Atlas');
$pdf->SetAuthor('Atlas');
$pdf->SetTitle('Recibo - Protocolo '.$p['protocolo']);

// Margens: 15 mm nas laterais e **30 mm** no topo (3 cm) para não sobrepor o cabeçalho do timbrado
$pdf->SetMargins(15, 30, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Cabeçalho/rodapé: usamos o Header() para o fundo (timbrado)
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

// Define o timbrado
$pdf->setBackground($bgPath);

$pdf->AddPage();

// ====== CONTEÚDO ======
$pdf->SetFont('helvetica','',11);

// Título “moderno” (sem competir com o cabeçalho do timbrado)
$html = '
  <div style="text-align:center; font-size:16px; font-weight:bold; letter-spacing:.3px; margin-bottom:6px;">
    Recibo do Pedido de Certidão
  </div>
  <div style="text-align:center; font-size:12px; color:#444; margin-bottom:12px;">
    Protocolo: <strong>'.htmlspecialchars($p['protocolo']).'</strong> &nbsp;•&nbsp;
    Status: <strong>'.str_replace('_',' ',htmlspecialchars($p['status'])).'</strong>
  </div>
  <hr style="border:0; height:1px; background:#999; margin:6px 0 10px 0;">
';

$html.= '
  <h4 style="font-size:13px; margin:0 0 6px 0;">Dados</h4>
  <div style="line-height:1.55;">
    <strong>Atribuição/Tipo:</strong> '.htmlspecialchars($p['atribuicao']).' / '.htmlspecialchars($p['tipo']).'<br>
    <strong>Requerente:</strong> '.htmlspecialchars($p['requerente_nome']).( $p['requerente_doc'] ? ' ('.htmlspecialchars($p['requerente_doc']).')' : '' ).'<br>
    <strong>Contato:</strong> '.htmlspecialchars($p['requerente_email'] ?: '-').' &nbsp;–&nbsp; '.htmlspecialchars($p['requerente_tel'] ?: '-').'<br>
    <strong>Portador:</strong> '.htmlspecialchars($p['portador_nome'] ?: '-').( $p['portador_doc'] ? ' ('.htmlspecialchars($p['portador_doc']).')' : '' ).'
  </div>
';

$refs = json_decode($p['referencias_json']??'{}', true);
if ($refs && is_array($refs)){
  $html.='<h4 style="font-size:13px; margin:10px 0 4px 0;">Referências</h4><ul style="margin:0 0 6px 18px; padding:0;">';
  foreach($refs as $k=>$v){
    $label = ucwords(str_replace('_',' ',$k));
    $html.='<li><strong>'.$label.':</strong> '.htmlspecialchars($v).'</li>';
  }
  $html.='</ul>';
}

$html.= '
  <h4 style="font-size:13px; margin:10px 0 4px 0;">O.S.</h4>
  <div style="line-height:1.55;">
    <strong>#'.(int)$p['ordem_servico_id'].'</strong> – '.htmlspecialchars($p['descricao_os']).' – Total:
    <strong>R$ '.number_format((float)$p['total_os'],2,',','.').'</strong>
  </div>
';

$pdf->writeHTML($html, true, false, true, false, '');

// ====== QR CODE (centralizado, tamanho menor + indicação de uso) ======
$pdf->Ln(4);
$qrSize = 42; // menor e mais elegante

// Y atual antes do QR
$startY = $pdf->GetY();

// Calcula X para centralizar (relativo à página)
$pageW = $pdf->getPageWidth();
$x = ($pageW - $qrSize) / 2;
$y = $startY + 2;

if (file_exists($qrPath)) {
  $pdf->Image($qrPath, $x, $y, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0);
} else {
  // Fallback: gera o QR internamente se o PNG não existir
  $style = [
    'border' => 0,
    'padding' => 2,
    'fgcolor' => [0,0,0],
    'bgcolor' => false
  ];
  $pdf->write2DBarcode($urlPublica, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, 'N');
}

// Indicação para consultar via QR Code (logo abaixo do QR)
$pdf->SetY($y + $qrSize + 2);
$pdf->SetFont('helvetica','',9.5);
$pdf->SetTextColor(80,80,80);
$pdf->Cell(0, 5, 'Aponte a câmera do celular para consultar o pedido via QR Code', 0, 1, 'C');

// Posiciona o cursor abaixo do texto do QR
$pdf->SetY($y + $qrSize + 8);

// Link centralizado, com instruções adicionais
$portalBase = rtrim($BASE_URL, '/');
$linkHtml = '
  <div style="text-align:center; font-size:11px; line-height:1.6; margin-top:2px;">
    <span style="display:block; color:#444; margin-bottom:2px;">Rastreie este pedido:</span>
    <a href="https://sistemaatlas.com.br/'.htmlspecialchars($p['protocolo']).'" style="color:#0066cc; text-decoration:none;">https://sistemaatlas.com.br/'.htmlspecialchars($p['protocolo']).'</a>
    <div style="margin-top:6px; color:#444;">
      <span>Ou acesse </span>
      <a href="https://sistemaatlas.com.br/" style="color:#0066cc; text-decoration:none;">https://sistemaatlas.com.br/</a>
      <span> e informe o número do protocolo </span>
      <strong>'.htmlspecialchars($p['protocolo']).'</strong>
      <span> para consultar.</span>
    </div>
  </div>
';
$pdf->writeHTML($linkHtml, true, false, true, false, 'C');

// Saída
$pdf->Output('recibo-pedido-'.$p['protocolo'].'.pdf', 'I');
