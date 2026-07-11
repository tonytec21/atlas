<?php
include(__DIR__ . '/session_check.php');
checkSession();
// Modo captura: quando incluído pelo fluxo de assinatura, retorna os bytes do PDF em vez de enviar ao navegador.
$__CAP_CAPTURE__ = (defined('OS_PDF_CAPTURE') || (isset($_GET['__capture']) && $_GET['__capture']=='1'));
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);

// Fuso horário BR
date_default_timezone_set('America/Sao_Paulo');

function maskCpfCnpj($valor){
    $s = (string)$valor;
    $digitsOnly = preg_replace('/\D/', '', $s);
    $len = strlen($digitsOnly);

    if ($len <= 5) return preg_replace('/\d/', '*', $s);

    $keepPrefix = 3;
    $keepSuffix = 2;

    $result = '';
    $digitIndex = 0;

    for ($i = 0; $i < strlen($s); $i++){
        $ch = $s[$i];
        if (ctype_digit($ch)){
            $digitIndex++;
            if ($digitIndex <= $keepPrefix || $digitIndex > $len - $keepSuffix){
                $result .= $ch;
            } else {
                $result .= '*';
            }
        } else {
            $result .= $ch;
        }
    }
    return $result;
}

// Título de seção (apenas texto em negrito, sem fundo)
function secaoBar($titulo){
    return '<div style="text-align:center; font-size:11px;"><b>'.$titulo.'</b></div>';
}

// Configurar a classe PDF
class PDF extends TCPDF
{
    private $criado_por;

    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/timbrado.png';

        // Salva margens atuais
        $currentMargins = $this->getMargins();

        // Desativa margens e AutoPageBreak p/ imagem de fundo
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);

        // Fundo
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Marca d'água se cancelado
        global $isCanceled;
        if ($isCanceled) {
            $this->SetAlpha(0.2);
            $this->StartTransform();
            $this->Rotate(45, $this->getPageWidth() / 2, $this->getPageHeight() / 2);
            $this->SetFont('helvetica', 'B', 60);
            $this->SetTextColor(255, 0, 0);
            $this->Text($this->getPageWidth() / 7, $this->getPageHeight() / 2.5, 'O.S. CANCELADA');
            $this->StopTransform();
            $this->SetAlpha(1);
        }

        // Restaura AutoPageBreak e margens
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
        $this->SetY(25);
    }

    // Rodapé do PDF
    public function Footer()
    {
        $configFile = "../style/configuracao_timbrado.json";
        $textColor = [0, 0, 0];

        if (file_exists($configFile)) {
            $configData = json_decode(file_get_contents($configFile), true);
            if (isset($configData['rodape']) && $configData['rodape'] === "S") {
                $textColor = [255, 255, 255];
            }
        }

        $this->SetY(-14.5);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
        $this->SetX(-23);
        $this->Cell(0, 11, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');

        // “Criado por” na vertical
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(-10, ($this->getPageHeight() / 2));
        $this->StartTransform();
        $this->Rotate(90);
        $this->Cell(0, 10, 'Criado por: ' . $this->criado_por, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->StopTransform();
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
            $marginLeft  = $this->getMargins()['left'];
            $marginRight = $this->getMargins()['right'];
            $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;

            $this->Image($assinatura_path, (float)$centerX, $this->GetY() - 2, (float)$signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
    }
}

if (isset($_GET['id'])) {
    $os_id = $_GET['id'];

    // Ordem de Serviço
    $os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $os_query->bind_param("i", $os_id);
    $os_query->execute();
    $os_result = $os_query->get_result();
    $ordem_servico = $os_result->fetch_assoc();

    // Status
    $status_os = $ordem_servico['status'];
    $isCanceled = ($status_os === 'Cancelado');

    // Itens
    $os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ? ORDER BY ordem_exibicao ASC");
    $os_items_query->bind_param("i", $os_id);
    $os_items_query->execute();
    $os_items_result = $os_items_query->get_result();
    $ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

    // mostrar "DESC. LEGAL %" somente se existir algum valor > 0
    $show_desc_legal = false;
    foreach ($ordem_servico_itens as $it) {
        $v = str_replace(',', '.', (string)($it['desconto_legal'] ?? ''));
        if ($v !== '' && floatval($v) > 0) { $show_desc_legal = true; break; }
    }
    
    // Verificar se deve mostrar a coluna FERRFIS (somente se algum item tiver valor > 0)
    $show_ferrfis = false;
    foreach ($ordem_servico_itens as $it) {
        $v = floatval($it['ferrfis'] ?? 0);
        if ($v > 0) { $show_ferrfis = true; break; }
    }
    
    // largura da coluna DESCRIÇÃO (absorve os 8% se esconder o desconto e/ou ferrfis)
    if ($show_desc_legal && $show_ferrfis) {
        $descricaoWidth = '29%';
    } elseif ($show_desc_legal && !$show_ferrfis) {
        $descricaoWidth = '35%';
    } elseif (!$show_desc_legal && $show_ferrfis) {
        $descricaoWidth = '37%';
    } else {
        $descricaoWidth = '43%';
    }

    // Criador
    $criado_por = $ordem_servico['criado_por'];
    $user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $user_query->bind_param("s", $criado_por);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user_info = $user_result->fetch_assoc();
    $criado_por_nome = $user_info['nome_completo'];
    $criado_por_cargo = $user_info['cargo'];

    // Usuário logado
    $logged_in_user = $_SESSION['username'];
    $logged_in_user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $logged_in_user_query->bind_param("s", $logged_in_user);
    $logged_in_user_query->execute();
    $logged_in_user_result = $logged_in_user_query->get_result();
    $logged_in_user_info = $logged_in_user_result->fetch_assoc();
    $logged_in_user_nome = $logged_in_user_info['nome_completo'];
    $logged_in_user_cargo = $logged_in_user_info['cargo'];

    // Pagamentos
    $pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamentos_query->bind_param("i", $os_id);
    $pagamentos_query->execute();
    $pagamentos_result = $pagamentos_query->get_result();
    $pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

    $total_pagamentos = 0;
    foreach ($pagamentos as $pagamento) {
        $total_pagamentos += $pagamento['total_pagamento'];
    }

    // Saldos / repasses / devoluções
    $saldo = $total_pagamentos - $ordem_servico['total_os'];

    $devolucoes_query = $conn->prepare("SELECT SUM(total_devolucao) as total_devolucoes FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucoes_query->bind_param("i", $os_id);
    $devolucoes_query->execute();
    $devolucoes_result = $devolucoes_query->get_result();
    $total_devolucoes = $devolucoes_result->fetch_assoc()['total_devolucoes'];

    $repasses_query = $conn->prepare("SELECT SUM(total_repasse) as total_repasses FROM repasse_credor WHERE ordem_de_servico_id = ?");
    $repasses_query->bind_param("i", $os_id);
    $repasses_query->execute();
    $repasses_result = $repasses_query->get_result();
    $total_repasses = $repasses_result->fetch_assoc()['total_repasses'];

    // ===== Rastreio: protocolo/token vinculado a esta O.S. (para o QR Code) =====
    $rastreioUrl = null;
    try {
        $rq = $conn->prepare("SELECT protocolo, token_publico FROM pedidos_certidao WHERE ordem_servico_id = ? ORDER BY id DESC LIMIT 1");
        $rq->bind_param("i", $os_id);
        $rq->execute();
        $rRes = $rq->get_result();
        if ($rRes && ($rRow = $rRes->fetch_assoc())) {
            $apiCfg = @json_decode(@file_get_contents(__DIR__ . '/../pedidos_certidao/api_secrets.json'), true) ?: [];
            $publicBase = $apiCfg['public_base'] ?? 'https://sistemaatlas.com.br';
            $rastreioUrl = rtrim($publicBase, '/') . '/' . $rRow['protocolo'];
        }
    } catch (Throwable $eRastreio) {
        $rastreioUrl = null;
    }

    // Início do PDF
    $pdf = new PDF();
    $pdf->SetMargins(12, 40, 10);
    $pdf->setCriadoPor($criado_por_nome);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Assinatura
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

    // ===== Cabeçalho do recibo =====
    $statusLabel = $isCanceled ? ' — CANCELADA' : '';
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->writeHTML('<div style="text-align:center; font-size:13px;"><b>RECIBO DE PAGAMENTO Nº '.$os_id.$statusLabel.'</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    if (!empty($ordem_servico['descricao_os'])) {
        $pdf->writeHTML('<div style="text-align:center; font-size:9px; color:#555555;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // ===== Dados da O.S. (caixa) =====
    $pdf->SetMargins(10, 40, 10);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? maskCpfCnpj($ordem_servico['cpf_cliente']) : '—';
    $data_criacao  = date('d/m/Y - H:i', strtotime($ordem_servico['data_criacao']));
    $baseCalc = ($ordem_servico['base_de_calculo'] >= 0.001)
        ? 'R$ '. number_format($ordem_servico['base_de_calculo'], 2, ',', '.')
        : '—';

    $obsRow = '';
    if (!empty($ordem_servico['observacoes'])) {
        $obsRow = '<tr>
          <td style="width:16%; background-color:#f2f2f2; font-size:8.5px;"><b>OBS</b></td>
          <td colspan="3" style="font-size:8.5px; text-align:justify;">'. $ordem_servico['observacoes'] .'</td>
        </tr>';
    }

    $pdf->writeHTML('
      <table border="0.1" cellpadding="4" width="100%">
        <thead>
        <tr>
          <td style="width:16%; background-color:#f2f2f2; font-size:8.5px;"><b>Apresentante</b></td>
          <td style="width:54%; font-size:8.5px;">'. $ordem_servico['cliente'] .'</td>
          <td style="width:14%; background-color:#f2f2f2; font-size:8.5px;"><b>Data</b></td>
          <td style="width:16%; font-size:8.5px;">'. $data_criacao .'</td>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td style="width:16%; background-color:#f2f2f2; font-size:8.5px;"><b>CPF/CNPJ</b></td>
          <td style="width:54%; font-size:8.5px;">'. $cpf_cnpj_text .'</td>
          <td style="width:14%; background-color:#f2f2f2; font-size:8.5px;"><b>Base Cálc.</b></td>
          <td style="width:16%; font-size:8.5px;">'. $baseCalc .'</td>
        </tr>
        '. $obsRow .'
        </tbody>
      </table>', true, false, true, false, '');
    $pdf->Ln(1);

    // ITENS
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(1);
    $pdf->writeHTML(secaoBar('ITENS DA ORDEM DE SERVIÇO'), true, false, true, false, '');
    $pdf->Ln(0);

    function adicionarCabecalhoTabelaItens($show_desc_legal, $descricaoWidth, $show_ferrfis = false) {
        $ths = 'text-align:center; font-size:8px; background-color:#a80f1e; color:#ffffff;';
        $descTh = $show_desc_legal
            ? '<th style="width:8%; '.$ths.'"><b>DESC. LEGAL %</b></th>'
            : '';

        $ferrfisTh = $show_ferrfis
            ? '<th style="width:8%; '.$ths.'"><b>FERRFIS</b></th>'
            : '';

        $html = '<table border="0.1" cellpadding="4" width="100%">
            <thead>
                <tr>
                    <th style="width:8%; '.$ths.'"><b>ATO</b></th>
                    <th style="width:5%; '.$ths.'"><b>QTD</b></th>'
                    . $descTh .
                '<th style="width:'.$descricaoWidth.'; '.$ths.'"><b>DESCRIÇÃO</b></th>
                    <th style="width:9%; '.$ths.'"><b>EMOL</b></th>
                    <th style="width:8%; '.$ths.'"><b>FERC</b></th>
                    <th style="width:8%; '.$ths.'"><b>FADEP</b></th>
                    <th style="width:8%; '.$ths.'"><b>FEMP</b></th>
                    '.$ferrfisTh.'
                    <th style="width:9%; '.$ths.'"><b>TOTAL</b></th>
                </tr>
            </thead>
            <tbody>';
        return $html;
    }

    $html = adicionarCabecalhoTabelaItens($show_desc_legal, $descricaoWidth, $show_ferrfis);

    // Acumuladores
    $total_emolumentos = 0.0; // será exibido na coluna FERJ
    $total_ferc = 0.0;
    $total_fadep = 0.0;
    $total_femp = 0.0;
    $total_ferrfis = 0.0;
    $total_geral = 0.0;
    $total_outros = 0.0; // TOTAL apenas dos itens com ATO = 0
    $total_iss = 0.0;    // TOTAL apenas dos itens com ATO = 'ISS'

    foreach ($ordem_servico_itens as $index => $item) {
        $descTd = $show_desc_legal
            ? '<td style="width: 8%; font-size: 8.5px;">' . $item['desconto_legal'] . '</td>'
            : '';
        
        $ferrfisTd = $show_ferrfis
            ? '<td style="width: 8%; font-size: 8px;">R$ ' . number_format($item['ferrfis'] ?? 0, 2, ',', '.') . '</td>'
            : '';

        $rowHtml = '<tr>
            <td style="width: 8%; font-size: 8px;">' . $item['ato'] . '</td>
            <td style="width: 5%; font-size: 8px;">' . $item['quantidade'] . '</td>'
            . $descTd .
        '<td style="width: '.$descricaoWidth.'; font-size: 8px;">' . $item['descricao'] . '</td>
            <td style="width: 9%; font-size: 8px;">R$ ' . number_format($item['emolumentos'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 8px;">R$ ' . number_format($item['ferc'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 8px;">R$ ' . number_format($item['fadep'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 8px;">R$ ' . number_format($item['femp'], 2, ',', '.') . '</td>
            '.$ferrfisTd.'
            <td style="width: 9%; font-size: 8px;">R$ ' . number_format($item['total'], 2, ',', '.') . '</td>
        </tr>';

        // Somatórios
        $total_emolumentos += (float)$item['emolumentos']; // FERJ
        $total_ferc  += (float)$item['ferc'];
        $total_fadep += (float)$item['fadep'];
        $total_femp  += (float)$item['femp'];
        $total_ferrfis += (float)($item['ferrfis'] ?? 0);
        $total_geral += (float)$item['total'];

        // OUTROS = somente atos com ATO == 0
        $ato_raw = isset($item['ato']) ? (string)$item['ato'] : '';
        $isAtoZero = preg_match('/^\s*0+(?:[.,]0+)?\s*$/', $ato_raw) === 1;
        if ($isAtoZero) {
            $total_outros += (float)$item['total'];
        }

        // ISS = somente atos cujo ATO é "ISS" (case-insensitive)
        if (strcasecmp(trim($ato_raw), 'ISS') === 0) {
            $total_iss += (float)$item['total'];
        }

        // Quebra de página, se necessário
        if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->AddPage();
        $html = adicionarCabecalhoTabelaItens($show_desc_legal, $descricaoWidth, $show_ferrfis) . $rowHtml;
        } else {
            $html .= $rowHtml;
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    // ===== TÍTULO + TABELA DE SOMATÓRIOS (colunas dinâmicas) =====
    $pdf->Ln(1);
    $pdf->writeHTML(secaoBar('SOMATÓRIO DOS VALORES (ITENS)'), true, false, true, false, '');
    $pdf->Ln(1);

    // Monta colunas dinamicamente
    $columns = [
        ['label' => 'FERJ',  'value' => $total_emolumentos],
        ['label' => 'FERC',  'value' => $total_ferc],
        ['label' => 'FEMP',  'value' => $total_femp],
        ['label' => 'FADEP', 'value' => $total_fadep],
    ];
    if ($show_ferrfis) {
        $columns[] = ['label' => 'FERRFIS', 'value' => $total_ferrfis];
    }
    if ($total_iss > 0) {
        $columns[] = ['label' => 'ISS', 'value' => $total_iss];
    }
    if ($total_outros > 0) {
        $columns[] = ['label' => 'OUTROS', 'value' => $total_outros];
    }
    // TOTAL sempre
    $columns[] = ['label' => 'TOTAL', 'value' => $total_geral];

    $colCount = count($columns);
    $colWidth = 100.0 / max(1, $colCount);

    // Constrói HTML da tabela
    $thead = '';
    $tbody = '';
    foreach ($columns as $col) {
        $isTotal = ($col['label'] === 'TOTAL');
        $thBg = $isTotal ? '#7d0b16' : '#a80f1e';
        $tdBg = $isTotal ? 'background-color:#f7e6e8; ' : '';
        $thead .= '<th style="width: '.sprintf('%.2f', $colWidth).'%; text-align:center; font-size:8.5px; background-color:'.$thBg.'; color:#ffffff;"><b>'.$col['label'].'</b></th>';
        $tbody .= '<td style="width: '.sprintf('%.2f', $colWidth).'%; text-align:center; font-size:8.5px; '.$tdBg.'"><b>R$ '.number_format((float)$col['value'], 2, ',', '.').'</b></td>';
    }

    $sumTableHtml = '
    <table border="0.1" cellpadding="4" width="100%">
        <thead>
            <tr>'.$thead.'</tr>
        </thead>
        <tbody>
            <tr>'.$tbody.'</tr>
        </tbody>
    </table>';
    $pdf->writeHTML($sumTableHtml, true, false, true, false, '');

    $pdf->Ln(3);

    // PAGAMENTOS
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML(secaoBar('PAGAMENTOS REALIZADOS'), true, false, true, false, '');
    $pdf->Ln(1);

    function adicionarCabecalhoTabelaPagamentos() {
        $ths = 'text-align:center; font-size:8.5px; background-color:#a80f1e; color:#ffffff;';
        return '<table border="0.1" cellpadding="4" width="100%">
            <thead>
                <tr>
                    <th style="width:15%; '.$ths.'"><b>DATA</b></th>
                    <th style="width:40%; '.$ths.'"><b>CLIENTE</b></th>
                    <th style="width:25%; '.$ths.'"><b>FORMA DE PAGAMENTO</b></th>
                    <th style="width:20%; '.$ths.'"><b>VALOR</b></th>
                </tr>
            </thead>
            <tbody>';
    }

    $html_pagamentos = adicionarCabecalhoTabelaPagamentos();

    foreach ($pagamentos as $pagamento) {
        $data_pagamento = date('d/m/Y - H:i', strtotime($pagamento['data_pagamento']));
        $rowHtmlPagamento = '<tr>
            <td style="width: 15%; font-size: 8.5px;">' . $data_pagamento . '</td>
            <td style="width: 40%; font-size: 8.5px;">' . $pagamento['cliente'] . '</td>
            <td style="width: 25%; font-size: 8.5px;">' . $pagamento['forma_de_pagamento'] . '</td>
            <td style="width: 20%; font-size: 8.5px;">R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.') . '</td>
        </tr>';

        if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
            $html_pagamentos .= '</tbody></table>';
            $pdf->writeHTML($html_pagamentos, true, false, true, false, '');
            $pdf->AddPage();
            $html_pagamentos = adicionarCabecalhoTabelaPagamentos() . $rowHtmlPagamento;
        } else {
            $html_pagamentos .= $rowHtmlPagamento;
        }
    }

    // TOTAL PAGO como última linha da própria tabela de pagamentos
    $html_pagamentos .= '<tr>
        <td colspan="3" style="text-align:right; font-size:9px; background-color:#f2f2f2;"><b>TOTAL PAGO</b></td>
        <td style="width:20%; text-align:center; font-size:9px; background-color:#f7e6e8;"><b>R$ '. number_format($total_pagamentos, 2, ',', '.') .'</b></td>
    </tr>';
    $html_pagamentos .= '</tbody></table>';
    $pdf->writeHTML($html_pagamentos, true, false, true, false, '');

    // Assinatura
    $pdf->Ln(16);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    // ===== QR Code de rastreio da O.S. =====
    if (!empty($rastreioUrl)) {
        // Quebra de página se o bloco do QR (~48mm) não couber
        if ($pdf->GetY() + 48 > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
        }
        $pdf->Ln(6);
        $pdf->writeHTML(secaoBar('RASTREAMENTO DA ORDEM DE SERVIÇO'), true, false, true, false, '');
        $pdf->Ln(2);

        $qrSize = 34;
        $x = ($pdf->getPageWidth() - $qrSize) / 2;
        $y = $pdf->GetY() + 1;
        $style = ['border' => 0, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false];
        $pdf->write2DBarcode($rastreioUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, 'N');
        $pdf->SetY($y + $qrSize + 1);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell(0, 4, 'Aponte a câmera do celular para o QR Code', 0, 'C', false, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 4, 'Ou acesse: ' . $rastreioUrl, 0, 'C', false, 1);
    }

    if ($__CAP_CAPTURE__) { $GLOBALS['__CAP_PDF_BYTES__'] = $pdf->Output('doc.pdf', 'S'); return; }
    $pdf->Output('Recibo nº ' . $os_id . '.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>