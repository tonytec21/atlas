<?php
// pedidos_certidao/gerar_requerimento.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('ID inválido');

$conn = getDatabaseConnection();
$stmt = $conn->prepare("SELECT p.*, o.descricao_os FROM pedidos_certidao p
                        LEFT JOIN ordens_de_servico o ON o.id = p.ordem_servico_id
                        WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) die('Pedido não encontrado');

// Data formatada
$raw = $p['criado_em'] ?? '';
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw)
    ?: DateTime::createFromFormat('Y-m-d', $raw)
    ?: (strtotime($raw) ? new DateTime($raw) : null);
$dataFmt = $dt ? $dt->format('d/m/Y') : '';
$dataHoraFmt = $dt ? $dt->format('d/m/Y \à\s H:i') : '';

// Caminhos
$bgPath = __DIR__ . '/../style/img/timbrado.png';

// Descobre quem protocolou
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
  } catch (Throwable $e) {}
}
if ($nomeProtocolou === '' && $usuarioProtocolou !== '') {
  $nomeProtocolou = $usuarioProtocolou;
}

// Status legível
$statusMap = [
  'pendente'      => 'Pendente',
  'em_andamento'  => 'Em Andamento',
  'emitida'       => 'Emitida',
  'entregue'      => 'Entregue',
  'cancelada'     => 'Cancelada',
];
$statusLabel = $statusMap[trim($p['status'] ?? 'pendente')] ?? ucfirst($p['status'] ?? 'Pendente');

// Referências
$refs = json_decode($p['referencias_json'] ?? '{}', true);
if (!is_array($refs)) $refs = [];

$labelsRef = [
  'nome_registrado' => 'Nome do Registrado',
  'nome_noivo'      => 'Nome do Noivo',
  'nome_noiva'      => 'Nome da Noiva',
  'nome_falecido'   => 'Nome do Falecido',
  'partes'          => 'Partes',
  'matricula'       => 'Matrícula',
  'imovel'          => 'Imóvel',
  'circunscricao'   => 'Circunscrição',
  'livro'           => 'Livro',
  'folha'           => 'Folha',
  'termo'           => 'Termo',
  'data_registro'   => 'Data do Registro',
  'cartorio'        => 'Cartório',
  'cidade'          => 'Cidade',
  'uf'              => 'UF',
];

// O.S. info
$osId = (int)($p['ordem_servico_id'] ?? 0);
$totalOS = (float)($p['total_os'] ?? 0);
$baseCalculo = (float)($p['base_calculo'] ?? 0);

// TCPDF
require_once(__DIR__ . '/../oficios/tcpdf/tcpdf.php');

class RequerimentoPDF extends TCPDF {
  protected $bgPath = null;

  public function setBackground($path) { $this->bgPath = $path; }

  public function Header() {
    if ($this->bgPath && file_exists($this->bgPath)) {
      $bMargin = $this->getBreakMargin();
      $auto_page_break = $this->AutoPageBreak;
      $this->SetAutoPageBreak(false, 0);
      $this->Image($this->bgPath, 0, 0, $this->getPageWidth(), $this->getPageHeight(), '', '', '', false, 300, '', false, false, 0);
      $this->SetAutoPageBreak($auto_page_break, $bMargin);
      $this->setPageMark();
    }
  }
}

$pdf = new RequerimentoPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Atlas');
$pdf->SetAuthor('Atlas');
$pdf->SetTitle('Requerimento - ' . $p['protocolo']);
$pdf->SetMargins(15, 40, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->setBackground($bgPath);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

// ====== TÍTULO ======
$html = '
  <div style="text-align:center; font-size:18px; font-weight:bold; letter-spacing:.3px; margin-bottom:2px;">
    REQUERIMENTO DE CERTIDÃO
  </div>
  <div style="text-align:center; font-size:11px; color:#555; margin-bottom:6px;">
    Protocolo: <strong>' . htmlspecialchars($p['protocolo']) . '</strong> &nbsp;•&nbsp;
    Data: <strong>' . htmlspecialchars($dataFmt) . '</strong> &nbsp;•&nbsp;
    Status: <strong>' . htmlspecialchars($statusLabel) . '</strong>
  </div>
  <hr style="border:0; height:1px; background:#999; margin:2px 0 10px 0;">
';

// ====== TIPO DO PEDIDO ======
$html .= '
  <h4 style="font-size:13px; margin:0 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Tipo do Pedido</h4>
  <table cellpadding="3" style="font-size:11px; line-height:1.5;">
    <tr>
      <td width="140"><strong>Atribuição:</strong></td>
      <td>' . htmlspecialchars($p['atribuicao'] ?? '-') . '</td>
    </tr>
    <tr>
      <td><strong>Tipo:</strong></td>
      <td>' . htmlspecialchars($p['tipo'] ?? '-') . '</td>
    </tr>
    <tr>
      <td><strong>Base de Cálculo:</strong></td>
      <td>R$ ' . number_format($baseCalculo, 2, ',', '.') . '</td>
    </tr>
  </table>
';

// ====== REQUERENTE ======
$html .= '
  <h4 style="font-size:13px; margin:10px 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Dados do Requerente</h4>
  <table cellpadding="3" style="font-size:11px; line-height:1.5;">
    <tr>
      <td width="140"><strong>Nome:</strong></td>
      <td>' . htmlspecialchars($p['requerente_nome'] ?? '-') . '</td>
    </tr>
    <tr>
      <td><strong>Documento:</strong></td>
      <td>' . htmlspecialchars($p['requerente_doc'] ?: '-') . '</td>
    </tr>
    <tr>
      <td><strong>E-mail:</strong></td>
      <td>' . htmlspecialchars($p['requerente_email'] ?: '-') . '</td>
    </tr>
    <tr>
      <td><strong>Telefone:</strong></td>
      <td>' . htmlspecialchars($p['requerente_tel'] ?: '-') . '</td>
    </tr>
  </table>
';

// ====== PORTADOR ======
if (!empty($p['portador_nome'])) {
  $html .= '
    <h4 style="font-size:13px; margin:10px 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Dados do Portador</h4>
    <table cellpadding="3" style="font-size:11px; line-height:1.5;">
      <tr>
        <td width="140"><strong>Nome:</strong></td>
        <td>' . htmlspecialchars($p['portador_nome']) . '</td>
      </tr>
      <tr>
        <td><strong>Documento:</strong></td>
        <td>' . htmlspecialchars($p['portador_doc'] ?: '-') . '</td>
      </tr>
    </table>
  ';
}

// ====== REFERÊNCIAS ======
if (!empty($refs)) {
  $html .= '<h4 style="font-size:13px; margin:10px 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Referências do Pedido</h4>';
  $html .= '<table cellpadding="3" style="font-size:11px; line-height:1.5;">';
  foreach ($refs as $k => $v) {
    $label = $labelsRef[$k] ?? ucwords(str_replace('_', ' ', $k));
    $valor = is_array($v) ? implode(', ', array_map(function($x){ return is_scalar($x) ? $x : json_encode($x, JSON_UNESCAPED_UNICODE); }, $v)) : (string)$v;
    $html .= '<tr><td width="140"><strong>' . htmlspecialchars($label) . ':</strong></td><td>' . htmlspecialchars($valor) . '</td></tr>';
  }
  $html .= '</table>';
}

// ====== ORDEM DE SERVIÇO ======
if ($osId > 0) {
  $html .= '
    <h4 style="font-size:13px; margin:10px 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Ordem de Serviço</h4>
    <table cellpadding="3" style="font-size:11px; line-height:1.5;">
      <tr>
        <td width="140"><strong>Nº da O.S.:</strong></td>
        <td>#' . $osId . '</td>
      </tr>
      <tr>
        <td><strong>Descrição:</strong></td>
        <td>' . htmlspecialchars($p['descricao_os'] ?? '-') . '</td>
      </tr>
      <tr>
        <td><strong>Total:</strong></td>
        <td>R$ ' . number_format($totalOS, 2, ',', '.') . '</td>
      </tr>
    </table>
  ';
}

// ====== OBSERVAÇÕES ======
if (!empty($p['observacoes'])) {
  $html .= '
    <h4 style="font-size:13px; margin:10px 0 6px 0; color:#333; border-bottom:1px solid #ddd; padding-bottom:3px;">Observações</h4>
    <div style="font-size:11px; line-height:1.6; padding:4px 0;">' . nl2br(htmlspecialchars($p['observacoes'])) . '</div>
  ';
}

// ====== REGISTRADO POR / DATA ======
$html .= '
  <br><br>
  <div style="font-size:10px; color:#666; text-align:center; margin-top:16px;">
    Requerimento registrado em <strong>' . htmlspecialchars($dataHoraFmt) . '</strong>'
    . ($nomeProtocolou ? ' por <strong>' . htmlspecialchars($nomeProtocolou) . '</strong>' : '')
    . '
  </div>
';

// ====== ASSINATURA ======
$html .= '
  <br><br><br>
  <div style="text-align:center;">
    <div style="border-top:1px solid #333; width:250px; margin:0 auto; padding-top:4px; font-size:11px;">
      Assinatura do Requerente
    </div>
  </div>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Saída
$pdf->Output('requerimento-' . $p['protocolo'] . '.pdf', 'I');
