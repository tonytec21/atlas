<?php
// os/guia_entrega.php
// Guia de Entrega da Ordem de Serviço (com timbrado, igual ao imprimir_os.php).
// Requer que a O.S. já tenha sido marcada como entregue (os_entregas).

include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection.php'); // getDatabaseConnection() -> PDO
date_default_timezone_set('America/Sao_Paulo');

function maskCpfCnpj($valor) {
    $v = preg_replace('/\D/', '', (string)$valor);
    if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
    if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
    return $valor;
}
function fmtDataBR($d) { try { $dt = new DateTime($d); return $dt->format('d/m/Y H:i'); } catch (Throwable $e) { return $d; } }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Classe PDF com timbrado de página inteira (mesmo padrão do imprimir_os.php)
class GuiaPDF extends TCPDF {
    private $criado_por = '';
    public function setCriadoPor($c) { $this->criado_por = $c; }
    public function Header() {
        $image_file = '../style/img/timbrado.png';
        $currentMargins = $this->getMargins();
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
        $this->SetY(25);
    }
    public function Footer() {
        $configFile = "../style/configuracao_timbrado.json";
        $textColor = [0, 0, 0];
        if (file_exists($configFile)) {
            $configData = json_decode(file_get_contents($configFile), true);
            if (isset($configData['rodape']) && $configData['rodape'] === "S") { $textColor = [255, 255, 255]; }
        }
        $this->SetY(-14.5);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
        $this->SetX(-33);
        $this->Cell(0, 11, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        $this->SetTextColor(0, 0, 0);
        if ($this->criado_por !== '') {
            $this->SetXY(-10, ($this->getPageHeight() / 2));
            $this->StartTransform();
            $this->Rotate(90);
            $this->Cell(0, 10, 'Emitido por: ' . $this->criado_por, 0, false, 'C', 0, '', 0, false, 'T', 'M');
            $this->StopTransform();
        }
    }
}

$os_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($os_id <= 0) { die('O.S. inválida.'); }

try {
    $pdo = getDatabaseConnection();

    $st = $pdo->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $st->execute([$os_id]);
    $os = $st->fetch(PDO::FETCH_ASSOC);
    if (!$os) { die('Ordem de Serviço não encontrada.'); }

    $st = $pdo->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ? ORDER BY ordem_exibicao ASC, id ASC");
    $st->execute([$os_id]);
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);

    $entrega = null;
    try {
        $st = $pdo->prepare("SELECT * FROM os_entregas WHERE ordem_servico_id = ? LIMIT 1");
        $st->execute([$os_id]);
        $entrega = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { $entrega = null; }

    $protocolo = null; $token = null; $statusPedido = null; $retiradoPedido = null;
    try {
        $st = $pdo->prepare("SELECT protocolo, token_publico, status, retirado_por FROM pedidos_certidao WHERE ordem_servico_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$os_id]);
        if ($p = $st->fetch(PDO::FETCH_ASSOC)) {
            $protocolo = $p['protocolo']; $token = $p['token_publico'];
            $statusPedido = $p['status']; $retiradoPedido = $p['retirado_por'];
        }
    } catch (Throwable $e) {}

    $razao = '';
    try {
        $st = $pdo->query("SELECT razao_social FROM cadastro_serventia LIMIT 1");
        if ($st && ($row = $st->fetch(PDO::FETCH_ASSOC))) { $razao = trim((string)($row['razao_social'] ?? '')); }
    } catch (Throwable $e) {}

    $emitidoPorNome = $_SESSION['username'] ?? '';
    try {
        $st = $pdo->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ?");
        $st->execute([$_SESSION['username'] ?? '']);
        if ($u = $st->fetch(PDO::FETCH_ASSOC)) { $emitidoPorNome = $u['nome_completo'] ?: $emitidoPorNome; }
    } catch (Throwable $e) {}

    $apiCfg = @json_decode(@file_get_contents(__DIR__ . '/../pedidos_certidao/api_secrets.json'), true) ?: [];
    $publicBase = $apiCfg['public_base'] ?? 'https://sistemaatlas.com.br';
    $rastreioUrl = $protocolo ? rtrim($publicBase, '/') . '/' . $protocolo : null;

    // Nome de quem recebeu: prioriza os_entregas, cai para retirado_por do pedido
    $recebidoPor = trim((string)($entrega['recebido_por'] ?? '')) ?: trim((string)($retiradoPedido ?? ''));
    $recebidoDoc = $entrega['recebido_doc'] ?? '';
    $obsEntrega  = $entrega['observacoes'] ?? '';
    $entreguePor = trim((string)($entrega['entregue_por'] ?? '')) ?: $emitidoPorNome;
    $dataEntrega = $entrega['data_entrega'] ?? date('Y-m-d H:i:s');
    $recebidoMostrar = $recebidoPor !== '' ? $recebidoPor : '—';

    // ====================== PDF ======================
    $pdf = new GuiaPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setCriadoPor($emitidoPorNome);
    $pdf->SetCreator('Atlas');
    $pdf->SetTitle('Guia de Entrega - O.S. nº ' . $os_id);
    $pdf->SetMargins(14, 42, 14);     // topo 42 para limpar o timbrado
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // ---- Título ----
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(168, 15, 30);
    $pdf->Cell(0, 8, 'GUIA DE ENTREGA', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->Cell(0, 5, 'Ordem de Serviço nº ' . $os_id, 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);

    $cssCard = '
    <style>
      .lbl { color:#6b7280; font-size:7.5px; }
      .val { color:#111827; font-size:9.5px; font-weight:bold; }
      .cell { border:1px solid #e5e7eb; background-color:#f9fafb; }
    </style>';

    $statusLabel = $statusPedido === 'entregue' ? 'ENTREGUE' : ($statusPedido ? strtoupper($statusPedido) : 'PENDENTE DE ENTREGA');

    // ---- Grid de informações (tabelas limpas, sem colspan) ----
    $g1 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="34%"><span class="lbl">PROTOCOLO DE RASTREIO</span><br><span class="val">' . h($protocolo ?: '—') . '</span></td>
        <td class="cell" width="33%"><span class="lbl">DATA DA ENTREGA</span><br><span class="val">' . h(fmtDataBR($dataEntrega)) . '</span></td>
        <td class="cell" width="33%"><span class="lbl">SITUAÇÃO</span><br><span class="val">' . h($statusLabel) . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($g1, true, false, true, false, '');

    $g2 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="60%"><span class="lbl">APRESENTANTE / CLIENTE</span><br><span class="val">' . h($os['cliente']) . '</span></td>
        <td class="cell" width="40%"><span class="lbl">CPF / CNPJ</span><br><span class="val">' . h(!empty($os['cpf_cliente']) ? maskCpfCnpj($os['cpf_cliente']) : '—') . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($g2, true, false, true, false, '');

    $g3 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="60%"><span class="lbl">RECEBIDO POR</span><br><span class="val">' . h($recebidoMostrar) . '</span></td>
        <td class="cell" width="40%"><span class="lbl">DOC. DO RECEPTOR</span><br><span class="val">' . h($recebidoDoc !== '' ? maskCpfCnpj($recebidoDoc) : '—') . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($g3, true, false, true, false, '');

    $g4 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="50%"><span class="lbl">ENTREGUE POR</span><br><span class="val">' . h($entreguePor ?: '—') . '</span></td>
        <td class="cell" width="50%"><span class="lbl">TÍTULO DA O.S.</span><br><span class="val">' . h($os['descricao_os'] ?: 'Ordem de Serviço') . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($g4, true, false, true, false, '');
    $pdf->Ln(2);

    // ---- Tabela de atos entregues (fonte menor + thead repetível) ----
    $rows = '';
    $i = 0;
    foreach ($itens as $it) {
        $i++;
        $bg = ($i % 2 === 0) ? '#ffffff' : '#f3f4f6';
        $desc = trim((string)($it['descricao'] ?? ''));
        $rows .= '<tr>
            <td width="8%" style="background-color:' . $bg . ';" align="center">' . $i . '</td>
            <td width="16%" style="background-color:' . $bg . ';">' . h($it['ato']) . '</td>
            <td width="60%" style="background-color:' . $bg . ';">' . h($desc !== '' ? $desc : '—') . '</td>
            <td width="16%" style="background-color:' . $bg . ';" align="center">' . (int)$it['quantidade'] . '</td>
        </tr>';
    }
    $pdf->SetFont('helvetica', '', 8);
    $htmlT = '
    <style> .atos td { font-size:7.8px; } .atoshead td { font-size:8px; color:#ffffff; font-weight:bold; } </style>
    <table border="0" cellpadding="3" cellspacing="0" class="atos">
      <thead>
        <tr class="atoshead"><td colspan="4" style="background-color:#a80f1e;">&nbsp;DOCUMENTOS / ATOS ENTREGUES</td></tr>
        <tr class="atoshead" style="background-color:#374151;">
          <td width="8%" align="center">#</td>
          <td width="16%">Ato</td>
          <td width="60%">Descrição</td>
          <td width="16%" align="center">Qtd.</td>
        </tr>
      </thead>
      <tbody>
      ' . ($rows !== '' ? $rows : '<tr><td colspan="4" align="center">Sem itens.</td></tr>') . '
      </tbody>
    </table>';
    $pdf->writeHTML($htmlT, true, false, true, false, '');
    $pdf->Ln(2);

    if (trim((string)$obsEntrega) !== '') {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Observações:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $obsEntrega, 0, 'J', false, 1);
        $pdf->Ln(1);
    }

    // ---- Declaração + QR (com quebra de página se faltar espaço) ----
    $bottom = $pdf->getPageHeight() - 25;
    if ($pdf->GetY() > ($bottom - 50)) { $pdf->AddPage(); }
    $yD = $pdf->GetY() + 2;
    $declaracao = 'Declaro, para os devidos fins, que recebi nesta data os documentos e/ou serviços '
        . 'discriminados nesta guia, referentes à Ordem de Serviço nº ' . $os_id
        . ($protocolo ? ' (protocolo de rastreio ' . $protocolo . ')' : '') . ', '
        . 'conferindo-os e dando plena quitação quanto à entrega.';
    $pdf->writeHTMLCell(120, 0, 14, $yD, '<div style="font-size:9.5px; text-align:justify;">' . h($declaracao) . '</div>', 0, 1, false, true, 'J');
    $yAfterDecl = $pdf->GetY();
    if ($rastreioUrl) {
        $style = ['border' => 0, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false];
        $pdf->write2DBarcode($rastreioUrl, 'QRCODE,M', 162, $yD, 30, 30, $style, 'N');
        $pdf->SetXY(150, $yD + 30.5);
        $pdf->SetFont('helvetica', '', 6.2);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(54, 3, 'Rastreie em: ' . $rastreioUrl, 0, 'C', false, 1);
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->SetY(max($yAfterDecl, $yD + 34));

    // ---- Assinaturas ----
    $pdf->Ln(8);
    $ySig = $pdf->GetY();
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Line(22, $ySig, 95, $ySig);
    $pdf->Line(115, $ySig, 188, $ySig);
    $pdf->SetXY(22, $ySig + 1);  $pdf->Cell(73, 4, 'Assinatura do Receptor', 0, 0, 'C');
    $pdf->SetXY(115, $ySig + 1); $pdf->Cell(73, 4, 'Responsável pela Entrega', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(22, $ySig + 5);  $pdf->Cell(73, 4, $recebidoMostrar !== '—' ? $recebidoMostrar : '', 0, 0, 'C');
    $pdf->SetXY(115, $ySig + 5); $pdf->Cell(73, 4, $entreguePor ?: '', 0, 1, 'C');

    // ---- Canhoto: sempre em página própria (dispensa rasgar/destacar o papel) ----
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(168, 15, 30);
    $pdf->Cell(0, 6, 'COMPROVANTE DE ENTREGA (CANHOTO)', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(1);


    $c1 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="22%"><span class="lbl">O.S. Nº</span><br><span class="val">' . $os_id . '</span></td>
        <td class="cell" width="38%"><span class="lbl">PROTOCOLO</span><br><span class="val">' . h($protocolo ?: '—') . '</span></td>
        <td class="cell" width="40%"><span class="lbl">DATA DA ENTREGA</span><br><span class="val">' . h(fmtDataBR($dataEntrega)) . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($c1, true, false, true, false, '');

    $c2 = $cssCard . '
    <table border="0" cellpadding="4" cellspacing="0">
      <tr>
        <td class="cell" width="60%"><span class="lbl">RECEBIDO POR</span><br><span class="val">' . h($recebidoMostrar) . '</span></td>
        <td class="cell" width="40%"><span class="lbl">CLIENTE</span><br><span class="val">' . h($os['cliente']) . '</span></td>
      </tr>
    </table>';
    $pdf->writeHTML($c2, true, false, true, false, '');
    $pdf->Ln(7);
    $yc = $pdf->GetY();
    $pdf->Line(22, $yc, 95, $yc);
    $pdf->SetXY(22, $yc + 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(73, 4, 'Assinatura do Receptor', 0, 1, 'C');

    if (ob_get_length()) { ob_clean(); }
    $pdf->Output('Guia_Entrega_OS_' . $os_id . '.pdf', 'I');
} catch (Throwable $e) {
    die('Erro ao gerar a guia de entrega: ' . h($e->getMessage()));
}
