<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$dini = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : null;
$dfim = isset($_GET['data_final'])   ? $_GET['data_final']   : null;
$func = isset($_GET['funcionario'])  ? $_GET['funcionario']  : 'todos';

if (!$dini || !$dfim) {
    echo 'Parâmetros "data_inicial" e "data_final" são obrigatórios.';
    exit;
}

$conn = getDatabaseConnection();
$fmt = function($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); };
function dtBR($d){ return date('d/m/Y', strtotime($d)); }

function sumBetween(PDO $conn, string $sqlBase, array $binds, ?string $func){
    $sql = $sqlBase;
    if ($func && $func !== 'todos') {
        $sql .= ' AND funcionario = :func';
        $binds[':func'] = $func;
    }
    $st=$conn->prepare($sql);
    foreach($binds as $k=>$v) $st->bindValue($k,$v);
    $st->execute();
    $v=$st->fetchColumn();
    return $v? (float)$v : 0.0;
}

$totalAtos = sumBetween($conn, 'SELECT SUM(total) FROM atos_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalAtosManuais = sumBetween($conn, 'SELECT SUM(total) FROM atos_manuais_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalDevolucoes = sumBetween($conn, 'SELECT SUM(total_devolucao) FROM devolucao_os WHERE DATE(data_devolucao) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalSaidas = sumBetween($conn, 'SELECT SUM(valor_saida) FROM saidas_despesas WHERE status="ativo" AND DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalDepositos = sumBetween($conn, 'SELECT SUM(valor_do_deposito) FROM deposito_caixa WHERE status="ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalSaldoTransportadoAberto = sumBetween($conn, "SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE status = 'aberto' AND DATE(data_caixa) BETWEEN :dini AND :dfim", [':dini'=>$dini, ':dfim'=>$dfim], $func);

// Saldo Inicial do 1º dia = saldo_inicial (caixa) + saldo transportado "usado" no 1º dia (status != 'aberto')
if ($func && $func !== 'todos') {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func');
    $st->execute([':dini'=>$dini, ':func'=>$func]);
} else {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini');
    $st->execute([':dini'=>$dini]);
}
$saldoInicialCaixa = (float)($st->fetchColumn() ?: 0);

if ($func && $func !== 'todos') {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func AND status <> 'aberto'");
    $st->execute([':dini'=>$dini, ':func'=>$func]);
} else {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND status <> 'aberto'");
    $st->execute([':dini'=>$dini]);
}
$saldoTransUsadoPrimeiroDia = (float)($st->fetchColumn() ?: 0);

$saldoInicial = $saldoInicialCaixa + $saldoTransUsadoPrimeiroDia;

// Total por tipo de pagamento (para tabela)
if ($func && $func !== 'todos') {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim AND funcionario = :func
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini'=>$dini, ':dfim'=>$dfim, ':func'=>$func]);
} else {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini'=>$dini, ':dfim'=>$dfim]);
}
$porTipo = $st->fetchAll(PDO::FETCH_ASSOC);

$totalRecebidoConta = 0.0; $totalRecebidoEspecie = 0.0;
foreach ($porTipo as $r) {
    $fp=$r['forma_de_pagamento']; $s=(float)$r['tot'];
    if (in_array($fp, ['PIX','Centrais Eletrônicas','Boleto','Transferência Bancária','Crédito','Débito'], true)) $totalRecebidoConta += $s;
    elseif ($fp==='Espécie') $totalRecebidoEspecie += $s;
}

// Fórmula final conforme solicitado
$totalEmCaixaPeriodo = $saldoInicial + $totalRecebidoEspecie - $totalDevolucoes - $totalSaidas - $totalDepositos - $totalSaldoTransportadoAberto;

// ===== Detalhes (linhas) do período para as tabelas =====
function fetchDataP($conn, $sql, $binds){
    $st = $conn->prepare($sql);
    foreach ($binds as $k=>$v) $st->bindValue($k, $v);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
$wf = ($func && $func !== 'todos');
$bindD = [':dini'=>$dini, ':dfim'=>$dfim];
if ($wf) $bindD[':func'] = $func;

$atos = fetchDataP($conn,
    'SELECT os.id AS ordem_servico_id, os.cliente, al.funcionario, al.ato, al.descricao, al.quantidade_liquidada, al.total
     FROM atos_liquidados al JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
     WHERE DATE(al.data) BETWEEN :dini AND :dfim'.($wf?' AND al.funcionario = :func':''), $bindD);

$atosManuais = fetchDataP($conn,
    'SELECT os.id AS ordem_servico_id, os.cliente, aml.funcionario, aml.ato, aml.descricao, aml.quantidade_liquidada, aml.total
     FROM atos_manuais_liquidados aml JOIN ordens_de_servico os ON aml.ordem_servico_id = os.id
     WHERE DATE(aml.data) BETWEEN :dini AND :dfim'.($wf?' AND aml.funcionario = :func':''), $bindD);

$pagamentos = fetchDataP($conn,
    'SELECT os.id AS ordem_de_servico_id, os.cliente, po.funcionario, po.forma_de_pagamento, po.total_pagamento
     FROM pagamento_os po JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
     WHERE DATE(po.data_pagamento) BETWEEN :dini AND :dfim'.($wf?' AND po.funcionario = :func':''), $bindD);

$devolucoes = fetchDataP($conn,
    'SELECT os.id AS ordem_de_servico_id, os.cliente, do.funcionario, do.forma_devolucao, do.total_devolucao
     FROM devolucao_os do JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
     WHERE DATE(do.data_devolucao) BETWEEN :dini AND :dfim'.($wf?' AND do.funcionario = :func':''), $bindD);

$saidas = fetchDataP($conn,
    'SELECT titulo, valor_saida, forma_de_saida, funcionario, data
     FROM saidas_despesas WHERE status = "ativo" AND DATE(data) BETWEEN :dini AND :dfim'.($wf?' AND funcionario = :func':''), $bindD);

$depositos = fetchDataP($conn,
    'SELECT data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, funcionario
     FROM deposito_caixa WHERE status = "ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim'.($wf?' AND funcionario = :func':''), $bindD);

$saldoTransportadoLista = fetchDataP($conn,
    'SELECT data_caixa, data_transporte, valor_transportado, funcionario, status
     FROM transporte_saldo_caixa WHERE DATE(data_caixa) BETWEEN :dini AND :dfim'.($wf?' AND funcionario = :func':''), $bindD);

$repasseCredor = fetchDataP($conn,
    'SELECT rc.ordem_de_servico_id, rc.cliente, rc.funcionario, rc.data_repasse, rc.forma_repasse, rc.total_repasse
     FROM repasse_credor rc WHERE rc.status = "ativo" AND DATE(rc.data_repasse) BETWEEN :dini AND :dfim'.($wf?' AND rc.funcionario = :func':''), $bindD);
$totalRepasseCredor = array_sum(array_column($repasseCredor, 'total_repasse'));

// ===================== PDF (TCPDF) =====================
require('../oficios/tcpdf/tcpdf.php');

class PDF extends TCPDF
{
    public function Header()
    {
        $image_file = '../style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        if (file_exists($image_file)) {
            $this->Image($image_file, 0, 0, 210, 297, 'PNG');
        }
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(25, 45, 25);
        $this->SetY(35);
    }
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'L');
    }
}

$labelFunc = ($func === 'todos') ? 'Unificado (Todos os funcionários)' : ('Funcionário: '.$func);

$pdf = new PDF();
$pdf->SetTitle('Fechamento de Caixa (Período)');
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Relatório de Fechamento do Caixa (Período)', 0, 1, 'C');
$pdf->Ln(-3);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, 'Período: '.dtBR($dini).' a '.dtBR($dfim), 0, 1, 'C');
$pdf->Cell(0, 8, $labelFunc, 0, 1, 'C');
$pdf->Ln(2);

// ================= Cards =================
$cards = [
    'Saldo Inicial' => $saldoInicial,
    'Atos Liquidados' => $totalAtos,
    'Atos Manuais' => $totalAtosManuais,
    'Recebido em Conta' => $totalRecebidoConta,
    'Recebido em Espécie' => $totalRecebidoEspecie,
    'Total Recebido' => ($totalRecebidoConta + $totalRecebidoEspecie),
    'Devoluções' => $totalDevolucoes,
    'Saídas e Despesas' => $totalSaidas,
    'Depósito do Caixa' => $totalDepositos,
    'Saldo Transportado' => $totalSaldoTransportadoAberto,
    'Repasse a Credores' => $totalRepasseCredor,
    'Total em Caixa' => $totalEmCaixaPeriodo
];
$cardColors = [
    'Saldo Inicial' => '#0a5d0a',
    'Atos Liquidados' => '#007bff',
    'Atos Manuais' => '#6f42c1',
    'Recebido em Conta' => '#fd7e14',
    'Recebido em Espécie' => '#218838',
    'Total Recebido' => '#0b7285',
    'Devoluções' => '#6c757d',
    'Saídas e Despesas' => '#dc3545',
    'Depósito do Caixa' => '#17a2b8',
    'Saldo Transportado' => '#34495e',
    'Repasse a Credores' => '#64748b',
    'Total em Caixa' => '#343a40'
];

$html = '<table cellspacing="5" cellpadding="5" border="0" width="100%">';
$count = 0;
foreach ($cards as $titulo => $valor) {
    if (floatval($valor) <= 0) continue;
    $cor = $cardColors[$titulo] ?? '#000';
    if ($count % 3 == 0) { $html .= '<tr>'; }
    $html .= '
    <td width="33%" style="background-color: '.$cor.'; color: white; border-radius: 12px; padding: 2px; text-align: center;">
        <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.2px;">
            '.mb_strtoupper($titulo, 'UTF-8').'<br>
            <span style="font-size: 16px;">R$ '.number_format($valor, 2, ',', '.').'</span><br>
        </div>
    </td>';
    $count++;
    if ($count % 3 == 0) { $html .= '</tr>'; }
}
if ($count % 3 != 0) { $html .= str_repeat('<td></td>', 3 - ($count % 3)).'</tr>'; }
$html .= '</table><br>';
$pdf->writeHTML($html, true, false, true, false, '');

function renderTable($pdf, $title, $headers, $dataRows, $columnWidths = [])
{
    if (empty($dataRows)) return;
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, mb_strtoupper($title, 'UTF-8'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $html = '<table border="1" cellpadding="3"><tr style="background-color:#e6e6e6;">';
    foreach ($headers as $h) {
        $style = isset($columnWidths[$h]) ? ' style="width:'.$columnWidths[$h].';"' : '';
        $html .= '<th'.$style.'>'.$h.'</th>';
    }
    $html .= '</tr>';
    $total = 0;
    foreach ($dataRows as $row) {
        $html .= '<tr>';
        foreach (array_values($row) as $i => $cell) {
            $header = $headers[$i];
            $style = isset($columnWidths[$header]) ? ' style="width:'.$columnWidths[$header].';"' : '';
            $html .= '<td'.$style.'>'.$cell.'</td>';
            if (preg_match('/total|valor/i', $header)) {
                $valor = floatval(str_replace(['R$', '.', ','], ['', '', '.'], preg_replace('/[^\d,.-]/', '', $cell)));
                $total += $valor;
            }
        }
        $html .= '</tr>';
    }
    if (preg_grep('/total|valor/i', $headers) && strtolower($title) !== 'total por tipo de pagamento') {
        $colspan = count($headers);
        $html .= '<tr style="background-color:#f1f1f1; font-weight:bold;">
            <td colspan="'.$colspan.'" style="text-align:center;">
                 TOTAL '.mb_strtoupper($title, 'UTF-8').': R$ '.number_format($total, 2, ',', '.').'
            </td>
        </tr>';
    }
    $html .= '</table><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ================= Tabelas =================
renderTable($pdf, 'Atos Liquidados',
    ['O.S', 'CLIENTE', 'FUNCIONÁRIO', 'ATO', 'DESCRIÇÃO', 'QTD', 'TOTAL (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'], $a['cliente'], $a['funcionario'], $a['ato'],
        mb_strimwidth($a['descricao'], 0, 28, '...', 'UTF-8'),
        $a['quantidade_liquidada'], number_format($a['total'], 2, ',', '.')
    ], $atos),
    ['O.S'=>'7%','CLIENTE'=>'26%','FUNCIONÁRIO'=>'16%','ATO'=>'9%','DESCRIÇÃO'=>'22%','QTD'=>'5%','TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Atos Manuais',
    ['O.S', 'CLIENTE', 'FUNCIONÁRIO', 'ATO', 'DESCRIÇÃO', 'QTD', 'TOTAL (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'], $a['cliente'], $a['funcionario'], $a['ato'],
        mb_strimwidth($a['descricao'], 0, 28, '...', 'UTF-8'),
        $a['quantidade_liquidada'], number_format($a['total'], 2, ',', '.')
    ], $atosManuais),
    ['O.S'=>'7%','CLIENTE'=>'26%','FUNCIONÁRIO'=>'16%','ATO'=>'9%','DESCRIÇÃO'=>'22%','QTD'=>'5%','TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Pagamentos',
    ['O.S', 'CLIENTE', 'FUNCIONÁRIO', 'FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($p) => [
        $p['ordem_de_servico_id'], $p['cliente'], $p['funcionario'], $p['forma_de_pagamento'],
        number_format($p['total_pagamento'], 2, ',', '.')
    ], $pagamentos),
    ['O.S'=>'9%','CLIENTE'=>'31%','FUNCIONÁRIO'=>'20%','FORMA DE PAGAMENTO'=>'25%','TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Total por Tipo de Pagamento',
    ['FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($linha) => [
        $linha['forma_de_pagamento'], number_format($linha['tot'], 2, ',', '.')
    ], $porTipo),
    ['FORMA DE PAGAMENTO'=>'70%', 'TOTAL (R$)'=>'30%']
);

renderTable($pdf, 'Devoluções',
    ['O.S', 'CLIENTE', 'FUNCIONÁRIO', 'FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($d) => [
        $d['ordem_de_servico_id'], $d['cliente'], $d['funcionario'], $d['forma_devolucao'],
        number_format($d['total_devolucao'], 2, ',', '.')
    ], $devolucoes),
    ['O.S'=>'9%','CLIENTE'=>'31%','FUNCIONÁRIO'=>'20%','FORMA DE PAGAMENTO'=>'25%','TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Saídas e Despesas',
    ['TÍTULO', 'FUNCIONÁRIO', 'VALOR (R$)', 'FORMA DE PAGAMENTO', 'DATA'],
    array_map(fn($s) => [
        $s['titulo'], $s['funcionario'], number_format($s['valor_saida'], 2, ',', '.'),
        $s['forma_de_saida'], date('d/m/Y', strtotime($s['data']))
    ], $saidas),
    ['TÍTULO'=>'30%','FUNCIONÁRIO'=>'20%','VALOR (R$)'=>'15%','FORMA DE PAGAMENTO'=>'20%','DATA'=>'15%']
);

renderTable($pdf, 'Depósitos',
    ['DATA CAIXA', 'FUNCIONÁRIO', 'VALOR (R$)', 'TIPO'],
    array_map(fn($d) => [
        date('d/m/Y', strtotime($d['data_caixa'])), $d['funcionario'],
        number_format($d['valor_do_deposito'], 2, ',', '.'), $d['tipo_deposito']
    ], $depositos),
    ['DATA CAIXA'=>'25%','FUNCIONÁRIO'=>'30%','VALOR (R$)'=>'25%','TIPO'=>'20%']
);

renderTable($pdf, 'Saldo Transportado',
    ['DATA CAIXA', 'DATA TRANSPORTE', 'VALOR (R$)', 'FUNCIONÁRIO', 'STATUS'],
    array_map(fn($s) => [
        date('d/m/Y', strtotime($s['data_caixa'])), date('d/m/Y', strtotime($s['data_transporte'])),
        number_format($s['valor_transportado'], 2, ',', '.'), $s['funcionario'],
        ucfirst(strtolower($s['status']))
    ], $saldoTransportadoLista),
    ['DATA CAIXA'=>'20%','DATA TRANSPORTE'=>'20%','VALOR (R$)'=>'15%','FUNCIONÁRIO'=>'25%','STATUS'=>'20%']
);

renderTable($pdf, 'Repasse a Credores',
    ['O.S', 'CLIENTE', 'FUNCIONÁRIO', 'DATA', 'FORMA', 'VALOR (R$)'],
    array_map(fn($r) => [
        $r['ordem_de_servico_id'], $r['cliente'], $r['funcionario'],
        date('d/m/Y', strtotime($r['data_repasse'])), $r['forma_repasse'],
        number_format($r['total_repasse'], 2, ',', '.')
    ], $repasseCredor),
    ['O.S'=>'8%','CLIENTE'=>'30%','FUNCIONÁRIO'=>'22%','DATA'=>'13%','FORMA'=>'12%','VALOR (R$)'=>'15%']
);

if (ob_get_length()) { ob_end_clean(); }
$pdf->Output('Fechamento_Caixa_Periodo_'.$dini.'_a_'.$dfim.'.pdf', 'I');

