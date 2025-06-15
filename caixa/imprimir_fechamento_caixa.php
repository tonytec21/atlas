<?php
require('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_GET['id'])) {
    die('ID do caixa não informado.');
}

$id_caixa = intval($_GET['id']);
$conn = getDatabaseConnection();

$stmt = $conn->prepare('SELECT * FROM caixa WHERE id = :id');
$stmt->execute([':id' => $id_caixa]);
$caixa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caixa) {
    die('Caixa não encontrado.');
}

$data = $caixa['data_caixa'];
$funcionario = $caixa['funcionario'];
$tipo = $caixa['tipo'] ?? 'normal';
$saldoInicial = floatval($caixa['saldo_inicial']);

$params = [':data' => $data];
if ($tipo !== 'unificado') {
    $params[':funcionario'] = $funcionario;
}

function fetchData($sql, $params = [])
{
    global $conn;
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$atos = fetchData(
    'SELECT os.id as ordem_servico_id, os.cliente, al.ato, al.descricao, al.quantidade_liquidada, al.total
     FROM atos_liquidados al
     JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
     WHERE ' . ($tipo === 'unificado' ? '' : 'al.funcionario = :funcionario AND ') . 'DATE(al.data) = :data',
    $params
);

$atosManuais = fetchData(
    'SELECT os.id as ordem_servico_id, os.cliente, aml.ato, aml.descricao, aml.quantidade_liquidada, aml.total
     FROM atos_manuais_liquidados aml
     JOIN ordens_de_servico os ON aml.ordem_servico_id = os.id
     WHERE ' . ($tipo === 'unificado' ? '' : 'aml.funcionario = :funcionario AND ') . 'DATE(aml.data) = :data',
    $params
);

$pagamentos = fetchData(
    'SELECT os.id as ordem_de_servico_id, os.cliente, po.forma_de_pagamento, po.total_pagamento
     FROM pagamento_os po
     JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
     WHERE ' . ($tipo === 'unificado' ? '' : 'po.funcionario = :funcionario AND ') . 'DATE(po.data_pagamento) = :data',
    $params
);

$devolucoes = fetchData(
    'SELECT os.id as ordem_de_servico_id, os.cliente, do.forma_devolucao, do.total_devolucao
     FROM devolucao_os do
     JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
     WHERE ' . ($tipo === 'unificado' ? '' : 'do.funcionario = :funcionario AND ') . 'DATE(do.data_devolucao) = :data',
    $params
);

$saidas = fetchData(
    'SELECT titulo, valor_saida, forma_de_saida, data
     FROM saidas_despesas
     WHERE ' . ($tipo === 'unificado' ? '' : 'funcionario = :funcionario AND ') . 'DATE(data) = :data AND status = "ativo"',
    $params
);

$depositos = fetchData(
    'SELECT data_caixa, data_cadastro, valor_do_deposito, tipo_deposito
     FROM deposito_caixa
     WHERE ' . ($tipo === 'unificado' ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_caixa) = :data AND status = "ativo"',
    $params
);

$saldoTransportado = fetchData(
    'SELECT data_caixa, data_transporte, valor_transportado, funcionario, status
     FROM transporte_saldo_caixa
     WHERE ' . ($tipo === 'unificado' ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_caixa) = :data',
    $params
);

$totalAtos = array_sum(array_column($atos, 'total'));
$totalAtosManuais = array_sum(array_column($atosManuais, 'total'));

$totalPorForma = [];
foreach ($pagamentos as $p) {
    $forma = $p['forma_de_pagamento'];
    $totalPorForma[$forma] = ($totalPorForma[$forma] ?? 0) + $p['total_pagamento'];
}

// Soma do total de devoluções (para exibir no card normalmente)
$totalDevolucoes = array_sum(array_column($devolucoes, 'total_devolucao'));

// Soma do total de devoluções em espécie (para cálculo do total em caixa)
$totalDevolucoesEspecie = array_sum(
    array_map(fn($d) => ($d['forma_devolucao'] === 'Espécie' ? $d['total_devolucao'] : 0), $devolucoes)
);

// Outras somas
$totalSaidas = array_sum(array_column($saidas, 'valor_saida'));
$totalDepositos = array_sum(array_column($depositos, 'valor_do_deposito'));
$totalSaldoTransportado = array_sum(array_column($saldoTransportado, 'valor_transportado'));

// Cálculo do Total em Caixa
$totalRecebidoEmEspecie = $totalPorForma['Espécie'] ?? 0;

$totalEmCaixa = $saldoInicial 
    + $totalRecebidoEmEspecie
    - $totalDevolucoesEspecie
    - $totalSaidas
    - $totalDepositos
    - $totalSaldoTransportado;

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

$pdf = new PDF();
$pdf->SetTitle('Fechamento de Caixa');
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Relatório de Fechamento do Caixa - '.date('d/m/Y', strtotime($data)), 0, 1, 'C');
$pdf->Ln(-3);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, 'Funcionário: '.$funcionario, 0, 1, 'C');
$pdf->Ln(2);

$cards = [
    'Saldo Inicial' => $saldoInicial,
    'Atos Liquidados' => $totalAtos,
    'Atos Manuais' => $totalAtosManuais,
    'Recebido em Conta' => ($totalPorForma['Débito'] ?? 0),
    'Recebido em Espécie' => ($totalPorForma['Espécie'] ?? 0),
    'Devoluções' => $totalDevolucoes,
    'Saídas e Despesas' => $totalSaidas,
    'Depósito do Caixa' => $totalDepositos,
    'Saldo Transportado' => $totalSaldoTransportado,
    'Total em Caixa' => $totalEmCaixa
];

$cardColors = [
    'Saldo Inicial' => '#0a5d0a',
    'Atos Liquidados' => '#007bff',
    'Atos Manuais' => '#6f42c1',
    'Recebido em Conta' => '#fd7e14',
    'Recebido em Espécie' => '#218838',
    'Devoluções' => '#6c757d',
    'Saídas e Despesas' => '#dc3545',
    'Depósito do Caixa' => '#17a2b8',
    'Saldo Transportado' => '#34495e',
    'Total em Caixa' => '#343a40'
];

$html = '<table cellpadding="3" cellspacing="5">';
foreach (array_chunk($cards, 3, true) as $linha) {
    $html .= '<tr>';
    foreach ($linha as $titulo => $valor) {
        $cor = $cardColors[$titulo] ?? '#000';
        $html .= '<td style="
            border:0px solid #333;
            text-align:center;
            background-color:'.$cor.';
            color:#fff;
            border-radius:8px;
            padding:2px 6px;
            width:170px; /* Aumenta a largura do card */
            ">
            <div style="font-size:10px; font-weight:bold;">'.$titulo.'</div>
            <div style="font-size:16px; font-weight:bold;">R$ '.number_format($valor,2,',','.').'</div>
        </td>';

    }
    $html .= '</tr>';
}
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


    foreach ($dataRows as $row) {
        $html .= '<tr>';
        foreach (array_values($row) as $i => $cell) {
            $header = $headers[$i];
            $style = isset($columnWidths[$header]) ? ' style="width:'.$columnWidths[$header].';"' : '';
            $html .= '<td'.$style.'>'.$cell.'</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============== Renderizar tabelas ==============

renderTable($pdf, 'Atos Liquidados',
    ['OS', 'Cliente', 'Ato', 'Descrição', 'Qtd', 'Total (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'],
        $a['cliente'],
        $a['ato'],
        mb_strimwidth($a['descricao'], 0, 30, '...', 'UTF-8'),
        $a['quantidade_liquidada'],
        number_format($a['total'], 2, ',', '.')
    ], $atos),
    ['OS'=>'8%', 'Cliente'=>'34%', 'Ato'=>'10%', 'Descrição'=>'28%', 'Qtd'=>'5%', 'Total (R$)'=>'15%']
);

renderTable($pdf, 'Atos Manuais',
    ['OS', 'Cliente', 'Ato', 'Descrição', 'Qtd', 'Total (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'],
        $a['cliente'],
        $a['ato'],
        mb_strimwidth($a['descricao'], 0, 30, '...', 'UTF-8'),
        $a['quantidade_liquidada'],
        number_format($a['total'], 2, ',', '.')
    ], $atosManuais),
    ['OS'=>'8%', 'Cliente'=>'34%', 'Ato'=>'10%', 'Descrição'=>'28%', 'Qtd'=>'5%', 'Total (R$)'=>'15%']
);

renderTable($pdf, 'Pagamentos',
    ['OS', 'Cliente', 'Forma de Pagamento', 'Total (R$)'],
    array_map(fn($p) => [
        $p['ordem_de_servico_id'],
        $p['cliente'],
        $p['forma_de_pagamento'],
        number_format($p['total_pagamento'], 2, ',', '.')
    ], $pagamentos),
    ['OS'=>'10%', 'Cliente'=>'35%', 'Forma de Pagamento'=>'40%', 'Total (R$)'=>'15%']
);

renderTable($pdf, 'Devoluções',
    ['OS', 'Cliente', 'Forma de Pagamento', 'Total (R$)'],
    array_map(fn($d) => [
        $d['ordem_de_servico_id'],
        $d['cliente'],
        $d['forma_devolucao'],
        number_format($d['total_devolucao'], 2, ',', '.')
    ], $devolucoes),
    ['OS'=>'10%', 'Cliente'=>'35%', 'Forma de Pagamento'=>'40%', 'Total (R$)'=>'15%']
);

renderTable($pdf, 'Saídas e Despesas',
    ['Título', 'Valor (R$)', 'Forma de Pagamento', 'Data Caixa'],
    array_map(fn($s) => [
        $s['titulo'],
        number_format($s['valor_saida'], 2, ',', '.'),
        $s['forma_de_saida'],
        date('d/m/Y', strtotime($s['data']))
    ], $saidas),
    ['Título'=>'40%', 'Valor (R$)'=>'15%', 'Forma de Pagamento'=>'30%', 'Data Caixa'=>'15%']
);

renderTable($pdf, 'Depósitos',
    ['Data Caixa', 'Data Cadastro', 'Valor (R$)', 'Tipo'],
    array_map(fn($d) => [
        date('d/m/Y', strtotime($d['data_caixa'])),
        date('d/m/Y', strtotime($d['data_cadastro'])),
        number_format($d['valor_do_deposito'], 2, ',', '.'),
        $d['tipo_deposito']
    ], $depositos),
    ['Data Caixa'=>'25%', 'Data Cadastro'=>'25%', 'Valor (R$)'=>'25%', 'Tipo'=>'25%']
);

renderTable($pdf, 'Saldo Transportado',
    ['Data Caixa', 'Data Transporte', 'Valor (R$)', 'Funcionário', 'Status'],
    array_map(fn($s) => [
        date('d/m/Y', strtotime($s['data_caixa'])),
        date('d/m/Y', strtotime($s['data_transporte'])),
        number_format($s['valor_transportado'], 2, ',', '.'),
        $s['funcionario'],
        $s['status']
    ], $saldoTransportado),
    ['Data Caixa'=>'20%', 'Data Transporte'=>'20%', 'Valor (R$)'=>'20%', 'Funcionário'=>'20%', 'Status'=>'20%']
);

ob_clean();
$pdf->Output('Fechamento_Caixa_'.$data.'_'.$funcionario.'.pdf', 'I');
?>
