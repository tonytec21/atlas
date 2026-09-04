<?php
// pedidos_certidao/gerar_recibo_pedido.php
// Recibo de Protocolo em A4 — DUAS VIAS na mesma página (Via do Cliente / Via do Cartório),
// separadas por linha de corte pontilhada.
//
// Versão do impresso (aparece no rodapé de cada via)
define('RECIBO_PEDIDO_VERSAO', '2.1.0');

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
$BASE_URL   = $apiConfig['base_url'] ?? 'https://consultapedido.sistemaatlas.com.br';
$urlPublica = rtrim($BASE_URL,'/').'/'.($p['protocolo'] ?? '');

// Caminhos de imagem
$qrPath     = __DIR__."/qrcodes/pedido_{$id}.png";
$bgPath     = __DIR__ . '/../style/img/timbrado.png';

$raw = $p['criado_em'] ?? '';
$dt =
    DateTime::createFromFormat('Y-m-d H:i:s', $raw)   // ex.: 2025-11-03 10:12:34
    ?: DateTime::createFromFormat('Y-m-d', $raw)      // ex.: 2025-11-03
    ?: (strtotime($raw) ? new DateTime($raw) : null); // tenta outros formatos comuns

$dataFmt = $dt ? $dt->format('d/m/Y') : '';
$horaFmt = ($dt && strlen($raw) > 10) ? $dt->format('H:i') : '';

// ========= Descobre "quem protocolou" (nome completo do funcionário) =========
$usuarioProtocolou = '';
foreach (['criado_por','usuario','usuario_criacao','criado_por_usuario','registrado_por','protocolo_por','protocolado_por','atualizado_por'] as $col) {
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
  } catch (Throwable $e) {
    // ignora silenciosamente
  }
}
if ($nomeProtocolou === '' && $usuarioProtocolou !== '') {
  $nomeProtocolou = $usuarioProtocolou; // fallback: usuário se não achou o nome
}

// TCPDF
require_once(__DIR__ . '/../oficios/tcpdf/tcpdf.php');

class PDF extends TCPDF {
  protected $bgPath = null;

  public function setBackground($path){ $this->bgPath = $path; }

  public function Header(){
    // Timbrado ocupando a página inteira (as duas vias ficam dentro do timbrado)
    if ($this->bgPath && file_exists($this->bgPath)) {
      $bMargin = $this->getBreakMargin();
      $auto_page_break = $this->AutoPageBreak;
      $this->SetAutoPageBreak(false, 0);
      $this->Image($this->bgPath, 0, 0, $this->getPageWidth(), $this->getPageHeight(),
                   '', '', '', false, 300, '', false, false, 0);
      $this->SetAutoPageBreak($auto_page_break, $bMargin);
      $this->setPageMark();
    }
  }
  public function Footer(){ /* rodapé é desenhado por via */ }
}

// ====================== LAYOUT ======================
// Áreas reservadas ao timbrado (cabeçalho/rodapé da folha). Ajuste se o timbrado mudar.
$TOPO_RESERVADO   = 42;   // mm ocupados pelo cabeçalho do timbrado (logo)
$RODAPE_RESERVADO = 24;   // mm ocupados pelo rodapé do timbrado (endereço/tel/e-mail)
$MARGEM_LATERAL   = 14;   // mm
$GAP_CORTE        = 7;    // mm de folga em volta da linha de corte

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Atlas');
$pdf->SetAuthor('Atlas');
$pdf->SetTitle('Recibo de Protocolo - '.$p['protocolo']);
$pdf->SetMargins($MARGEM_LATERAL, $TOPO_RESERVADO, $MARGEM_LATERAL);
$pdf->SetAutoPageBreak(false, 0);   // tudo cabe em UMA página; nunca quebrar
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(false);
$pdf->setBackground($bgPath);
$pdf->AddPage();

$pageW  = $pdf->getPageWidth();
$pageH  = $pdf->getPageHeight();
$areaW  = $pageW - 2*$MARGEM_LATERAL;
$util   = $pageH - $TOPO_RESERVADO - $RODAPE_RESERVADO;   // altura útil total
$viaMax = ($util - $GAP_CORTE) / 2;                        // altura máxima de cada via

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// --------- Blocos de conteúdo (iguais nas duas vias) ---------
$refs = json_decode($p['referencias_json'] ?? '{}', true);
$refsHtml = '';
if ($refs && is_array($refs)) {
  $itens = [];
  foreach ($refs as $k=>$v) {
    if ($v === '' || $v === null) continue;
    $label = ucwords(str_replace('_',' ',(string)$k));
    $itens[] = '<b>'.$h($label).':</b> '.$h(is_array($v) ? implode(', ', $v) : $v);
  }
  if ($itens) {
    $refsHtml = '<tr><td class="lbl">Referências</td><td class="val">'.implode(' &nbsp;•&nbsp; ', $itens).'</td></tr>';
  }
}

$css = '<style>
  table.d { border-collapse:collapse; width:100%; }
  table.d td { font-size:9.4pt; line-height:1.4; padding:1px 2px; vertical-align:top; }
  td.lbl { width:27%; font-weight:bold; color:#333; }
  td.val { width:73%; color:#111; }
</style>';

$dadosHtml = $css.'<table class="d">
  <tr><td class="lbl">Atribuição / Tipo</td><td class="val">'.$h($p['atribuicao']).' / '.$h($p['tipo']).'</td></tr>
  <tr><td class="lbl">Requerente</td><td class="val">'.$h($p['requerente_nome']).( $p['requerente_doc'] ? ' ('.$h($p['requerente_doc']).')' : '' ).'</td></tr>
  <tr><td class="lbl">Contato</td><td class="val">'.$h($p['requerente_email'] ?: '-').' &nbsp;–&nbsp; '.$h($p['requerente_tel'] ?: '-').'</td></tr>
  <tr><td class="lbl">Portador</td><td class="val">'.$h($p['portador_nome'] ?: '-').( $p['portador_doc'] ? ' ('.$h($p['portador_doc']).')' : '' ).'</td></tr>
  '.$refsHtml.'
  <tr><td class="lbl">O.S.</td><td class="val"><b>#'.(int)$p['ordem_servico_id'].'</b> – '.$h($p['descricao_os']).' &nbsp;–&nbsp; Total: <b>R$ '.number_format((float)$p['total_os'],2,',','.').'</b></td></tr>
</table>';

// ---- Mede a altura do conteúdo para dimensionar as vias pelo conteúdo ----
$QR_SIZE  = 34;
$PAD_VIA  = 4;
$colDirW  = $QR_SIZE + 6;
$colEsqW  = $areaW - 2*$PAD_VIA - $colDirW - 3;
$pdf->startTransaction();
$pdf->SetFont('helvetica','',9.4);
$pdf->writeHTMLCell($colEsqW, 0, $MARGEM_LATERAL + $PAD_VIA, $TOPO_RESERVADO + 20, $dadosHtml, 0, 1, false, true, 'L', true);
$altTabela = $pdf->GetY() - ($TOPO_RESERVADO + 20);
$pdf = $pdf->rollbackTransaction();

$altCabecalho = 18.5;                                  // título + protocolo + divisória
$altLink      = 10;                                    // bloco "Rastreie este pedido"
$altRodape    = 12;                                    // faixa de rodapé/assinatura
$altCorpo     = max($altTabela + 2 + $altLink, $QR_SIZE + 9);
$viaH         = min($viaMax, $PAD_VIA + $altCabecalho + $altCorpo + $altRodape + $PAD_VIA);
$yVia1  = $TOPO_RESERVADO;
$yCorte = $yVia1 + $viaH + $GAP_CORTE/2;
$yVia2  = $yCorte + $GAP_CORTE/2;

/**
 * Desenha uma via completa a partir de $y0 com altura $alt.
 * $tipo: 'cliente' | 'cartorio'
 */
function desenharVia(PDF $pdf, float $x0, float $y0, float $w, float $alt, string $tipo,
                     array $p, string $dadosHtml, string $qrPath, string $urlPublica,
                     string $dataFmt, string $horaFmt, string $nomeProtocolou): void
{
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $isCliente = ($tipo === 'cliente');
  $rotulo    = $isCliente ? 'VIA DO CLIENTE' : 'VIA DO CARTÓRIO';

  // Moldura sutil da via
  $pdf->SetLineStyle(['width'=>0.25, 'color'=>[170,170,170]]);
  $pdf->RoundedRect($x0, $y0, $w, $alt, 2.0, '1111', 'D');

  // ---- Cabeçalho da via ----
  $pad = 4;
  $x = $x0 + $pad; $y = $y0 + $pad; $wi = $w - 2*$pad;

  $pdf->SetFont('helvetica','B',13.5);
  $pdf->SetTextColor(20,20,20);
  $pdf->SetXY($x, $y);
  $pdf->Cell($wi*0.6, 6, 'RECIBO DE PROTOCOLO', 0, 0, 'L');

  // Selo "VIA DO ..." à direita
  $selW = 40; $selH = 6.8;
  $pdf->SetFillColor($isCliente ? 235 : 60, $isCliente ? 235 : 60, $isCliente ? 235 : 60);
  $pdf->SetTextColor($isCliente ? 30 : 255, $isCliente ? 30 : 255, $isCliente ? 30 : 255);
  $pdf->SetFont('helvetica','B',9.5);
  $pdf->RoundedRect($x + $wi - $selW, $y - 0.3, $selW, $selH, 1.2, '1111', 'F');
  $pdf->SetXY($x + $wi - $selW, $y - 0.3);
  $pdf->Cell($selW, $selH, $rotulo, 0, 0, 'C');

  // Protocolo + data
  $y += 7.5;
  $pdf->SetTextColor(60,60,60);
  $pdf->SetFont('helvetica','',10);
  $pdf->SetXY($x, $y);
  $linha = 'Protocolo: ';
  $pdf->Cell($pdf->GetStringWidth($linha), 5, $linha, 0, 0, 'L');
  $pdf->SetFont('helvetica','B',11.5); $pdf->SetTextColor(0,0,0);
  $pdf->Cell($pdf->GetStringWidth($p['protocolo']) + 1, 5, (string)$p['protocolo'], 0, 0, 'L');
  $pdf->SetFont('helvetica','',10); $pdf->SetTextColor(60,60,60);
  $pdf->Cell(0, 5, '   •   Data: '.$dataFmt.($horaFmt ? '  '.$horaFmt : ''), 0, 0, 'L');

  // Linha divisória
  $y += 6;
  $pdf->SetLineStyle(['width'=>0.35, 'color'=>[120,120,120]]);
  $pdf->Line($x, $y, $x + $wi, $y);
  $y += 2;

  // ---- Corpo: coluna esquerda (dados) + coluna direita (QR) ----
  $qrSize = 34;
  $colDirW = $qrSize + 6;
  $colEsqW = $wi - $colDirW - 3;
  $yCorpo  = $y;
  $yRodape = $y0 + $alt - $pad - 9;   // início da faixa de rodapé/assinatura
  $altCorpo = $yRodape - $yCorpo - 2;

  // Dados (HTML dentro de célula com largura fixa)
  $pdf->SetTextColor(0,0,0);
  $pdf->SetFont('helvetica','',9.4);
  $pdf->writeHTMLCell($colEsqW, 0, $x, $yCorpo, $dadosHtml, 0, 1, false, true, 'L', true);
  $yAposDados = $pdf->GetY();

  // QR Code + instrução (coluna direita)
  $xQr = $x + $wi - $qrSize - 3;
  $yQr = $yCorpo + 0.5;
  if (file_exists($qrPath)) {
    $pdf->Image($qrPath, $xQr, $yQr, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0);
  } else {
    $pdf->write2DBarcode($urlPublica, 'QRCODE,H', $xQr, $yQr, $qrSize, $qrSize,
      ['border'=>0,'padding'=>1,'fgcolor'=>[0,0,0],'bgcolor'=>false], 'N');
  }
  $pdf->SetFont('helvetica','',7);
  $pdf->SetTextColor(90,90,90);
  $pdf->SetXY($xQr - 3, $yQr + $qrSize + 0.5);
  $pdf->MultiCell($qrSize + 6, 3.2, "Aponte a câmera do celular\npara consultar o protocolo", 0, 'C', false, 0);

  // Rastreio (link) abaixo dos dados, na coluna esquerda
  $yLink = $yAposDados + 2;
  $yLink = min($yLink, $yRodape - 8);
  $urlCurta = 'https://sistemaatlas.com.br/'.$p['protocolo'];
  $linkHtml = '<div style="font-size:8.4pt; line-height:1.4; color:#444;">
      Rastreie este pedido em <a href="'.$h($urlCurta).'" style="color:#0066cc; text-decoration:none;">'.$h($urlCurta).'</a><br>
      ou acesse <a href="https://sistemaatlas.com.br/" style="color:#0066cc; text-decoration:none;">https://sistemaatlas.com.br/</a>
      e informe o protocolo <b>'.$h($p['protocolo']).'</b>.
    </div>';
  $pdf->writeHTMLCell($colEsqW, 0, $x, $yLink, $linkHtml, 0, 0, false, true, 'L', true);

  // ---- Rodapé da via ----
  $pdf->SetLineStyle(['width'=>0.2, 'color'=>[190,190,190]]);
  $pdf->Line($x, $yRodape, $x + $wi, $yRodape);

  if ($isCliente) {
    // Via do cliente: orientação de guarda
    $pdf->SetFont('helvetica','I',8);
    $pdf->SetTextColor(80,80,80);
    $pdf->SetXY($x, $yRodape + 1.2);
    $pdf->Cell($wi*0.62, 4, 'Guarde este recibo. Ele será exigido na retirada da certidão.', 0, 0, 'L');
  } else {
    // Via do cartório: linha de assinatura do requerente/portador
    $assW = $wi*0.58;
    $pdf->SetLineStyle(['width'=>0.3, 'color'=>[60,60,60]]);
    $pdf->Line($x, $yRodape + 5.5, $x + $assW, $yRodape + 5.5);
    $pdf->SetFont('helvetica','',8);
    $pdf->SetTextColor(80,80,80);
    $pdf->SetXY($x, $yRodape + 5.8);
    $pdf->Cell($assW, 3, 'Assinatura do requerente / portador', 0, 0, 'C');
  }

  // Protocolado por + versão (à direita)
  $pdf->SetFont('helvetica','',7.5);
  $pdf->SetTextColor(100,100,100);
  $pdf->SetXY($x + $wi*0.62, $yRodape + 1.2);
  $txt = ($nomeProtocolou !== '' ? 'Protocolado por: '.$nomeProtocolou : '');
  $pdf->Cell($wi*0.38, 4, $txt, 0, 0, 'R');
  $pdf->SetFont('helvetica','',5.8);
  $pdf->SetTextColor(150,150,150);
  $pdf->SetXY($x + $wi*0.62, $yRodape + 5.2);
  $pdf->Cell($wi*0.38, 3, 'Atlas • Recibo v'.RECIBO_PEDIDO_VERSAO, 0, 0, 'R');
}

// ---- Via 1: CLIENTE ----
desenharVia($pdf, $MARGEM_LATERAL, $yVia1, $areaW, $viaH, 'cliente',
            $p, $dadosHtml, $qrPath, $urlPublica, $dataFmt, $horaFmt, $nomeProtocolou);

// ---- Linha de corte (pontilhada, com tesoura) ----
$pdf->SetLineStyle(['width'=>0.3, 'color'=>[110,110,110], 'dash'=>'2,1.5']);
$pdf->Line($MARGEM_LATERAL - 4, $yCorte, $pageW - $MARGEM_LATERAL + 4, $yCorte);
$pdf->SetLineStyle(['width'=>0.3, 'color'=>[110,110,110], 'dash'=>0]);
$pdf->SetFont('zapfdingbats','',9);       // "#" = tesoura no ZapfDingbats
$pdf->SetTextColor(110,110,110);
$pdf->SetXY($MARGEM_LATERAL - 4, $yCorte - 2.5);
$pdf->Cell(5, 5, '#', 0, 0, 'L');
$pdf->SetFont('helvetica','',6.5);
$pdf->SetXY(($pageW - 30)/2, $yCorte - 2.4);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(30, 4.8, 'destaque aqui', 0, 0, 'C', true);

// ---- Via 2: CARTÓRIO ----
desenharVia($pdf, $MARGEM_LATERAL, $yVia2, $areaW, $viaH, 'cartorio',
            $p, $dadosHtml, $qrPath, $urlPublica, $dataFmt, $horaFmt, $nomeProtocolou);

// Saída
$pdf->Output('recibo-protocolo-'.$p['protocolo'].'.pdf', 'I');
