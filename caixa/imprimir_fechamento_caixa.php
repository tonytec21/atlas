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

$origemSaldoInicial = fetchData(
    'SELECT data_caixa, data_transporte, valor_transportado, funcionario, data_caixa_uso
     FROM transporte_saldo_caixa
     WHERE funcionario = :funcionario
     AND DATE(data_caixa_uso) = :data
     AND status = "usado"',
    [':funcionario' => $funcionario, ':data' => $data]
);

$totalAtos = array_sum(array_column($atos, 'total'));
$totalAtosManuais = array_sum(array_column($atosManuais, 'total'));

$totalPorForma = [];
foreach ($pagamentos as $p) {
    $forma = $p['forma_de_pagamento'];
    $totalPorForma[$forma] = ($totalPorForma[$forma] ?? 0) + $p['total_pagamento'];
}

$totalDevolucoes = array_sum(array_column($devolucoes, 'total_devolucao'));
$totalDevolucoesEspecie = array_sum(
    array_map(fn($d) => ($d['forma_devolucao'] === 'Espécie' ? $d['total_devolucao'] : 0), $devolucoes)
);

$totalSaidas = array_sum(array_column($saidas, 'valor_saida'));
$totalDepositos = array_sum(array_column($depositos, 'valor_do_deposito'));
$totalSaldoTransportado = array_sum(array_column($saldoTransportado, 'valor_transportado'));

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

// ================= CARDS =====================
$cards = [
    'Saldo Inicial' => $saldoInicial,
    'Atos Liquidados' => $totalAtos,
    'Atos Manuais' => $totalAtosManuais,
    'Recebido em Conta' => array_sum(array_map(
        fn($forma, $valor) => ($forma !== 'Espécie') ? $valor : 0,
        array_keys($totalPorForma),
        $totalPorForma
    )),
    'Recebido em Espécie' => ($totalPorForma['Espécie'] ?? 0),
    'Total Recebido' => array_sum($totalPorForma),
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
    'Total Recebido' => '#0b7285',
    'Devoluções' => '#6c757d',
    'Saídas e Despesas' => '#dc3545',
    'Depósito do Caixa' => '#17a2b8',
    'Saldo Transportado' => '#34495e',
    'Total em Caixa' => '#343a40'
];

$html = '<table cellspacing="5" cellpadding="5" border="0" width="100%">';

$count = 0;
foreach ($cards as $titulo => $valor) {
    if (floatval($valor) <= 0) continue;
    $cor = $cardColors[$titulo] ?? '#000';

    if ($count % 3 == 0) {
        $html .= '<tr>'; 
    }

    $html .= '
    <td width="33%" style="
        background-color: '.$cor.';
        color: white;
        border-radius: 12px;
        padding: 2px;
        text-align: center;
    ">
        <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.2px;">
            '.mb_strtoupper($titulo, 'UTF-8').'<br>
            <span style="font-size: 16px;">R$ '.number_format($valor, 2, ',', '.').'</span><br>
        </div>
    </td>';

    $count++;

    if ($count % 3 == 0) {
        $html .= '</tr>';
    }
}

if ($count % 3 != 0) {
    $html .= str_repeat('<td></td>', 3 - ($count % 3)).'</tr>';
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

    $total = 0;
    foreach ($dataRows as $row) {
        $html .= '<tr>';
        foreach (array_values($row) as $i => $cell) {
            $header = $headers[$i];
            $style = isset($columnWidths[$header]) ? ' style="width:'.$columnWidths[$header].';"' : '';
            $html .= '<td'.$style.'>'.$cell.'</td>';

            // Detecta coluna com "Total" ou "Valor"
            if (preg_match('/total|valor/i', $header)) {
                $valor = floatval(str_replace(['R$', '.', ','], ['', '', '.'], preg_replace('/[^\d,.-]/', '', $cell)));
                $total += $valor;
            }
        }
        $html .= '</tr>';
    }

    // Se houver coluna com "Total" ou "Valor", adiciona linha de rodapé mesclada
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

// ================= Renderizar tabelas =================
renderTable($pdf, 'Saldo Inicial',
    ['DATA CAIXA DE ORIGEM', 'DATA TRANSPORTE', 'DATA DE USO', 'VALOR (R$)', 'FUNCIONÁRIO'],
    array_map(fn($s) => [
        date('d/m/Y', strtotime($s['data_caixa'])),
        date('d/m/Y', strtotime($s['data_transporte'])),
        date('d/m/Y', strtotime($s['data_caixa_uso'])),
        number_format($s['valor_transportado'], 2, ',', '.'),
        $s['funcionario']
    ], $origemSaldoInicial),
    ['DATA CAIXA DE ORIGEM'=>'25%', 'DATA TRANSPORTE'=>'20%', 'DATA DE USO'=>'15%', 'VALOR (R$)'=>'15%', 'FUNCIONÁRIO'=>'25%']
);

renderTable($pdf, 'Atos Liquidados',
    ['O.S', 'CLIENTE', 'ATO', 'DESCRIÇÃO', 'QTD', 'TOTAL (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'],
        $a['cliente'],
        $a['ato'],
        mb_strimwidth($a['descricao'], 0, 34, '...', 'UTF-8'),
        $a['quantidade_liquidada'],
        number_format($a['total'], 2, ',', '.')
    ], $atos),
    ['O.S'=>'8%', 'CLIENTE'=>'34%', 'ATO'=>'10%', 'DESCRIÇÃO'=>'28%', 'QTD'=>'5%', 'TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Atos Manuais',
    ['O.S', 'CLIENTE', 'ATO', 'DESCRIÇÃO', 'QTD', 'TOTAL (R$)'],
    array_map(fn($a) => [
        $a['ordem_servico_id'],
        $a['cliente'],
        $a['ato'],
        mb_strimwidth($a['descricao'], 0, 34, '...', 'UTF-8'),
        $a['quantidade_liquidada'],
        number_format($a['total'], 2, ',', '.')
    ], $atosManuais),
    ['O.S'=>'8%', 'CLIENTE'=>'34%', 'ATO'=>'10%', 'DESCRIÇÃO'=>'28%', 'QTD'=>'5%', 'TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Pagamentos',
    ['O.S', 'CLIENTE', 'FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($p) => [
        $p['ordem_de_servico_id'],
        $p['cliente'],
        $p['forma_de_pagamento'],
        number_format($p['total_pagamento'], 2, ',', '.')
    ], $pagamentos),
    ['O.S'=>'10%', 'CLIENTE'=>'35%', 'FORMA DE PAGAMENTO'=>'40%', 'TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Total por Tipo de Pagamento',
    ['FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($forma) => [
        $forma,
        number_format($totalPorForma[$forma], 2, ',', '.')
    ], array_keys($totalPorForma)),
    ['FORMA DE PAGAMENTO'=>'70%', 'TOTAL (R$)'=>'30%']
);

renderTable($pdf, 'Devoluções',
    ['O.S', 'CLIENTE', 'FORMA DE PAGAMENTO', 'TOTAL (R$)'],
    array_map(fn($d) => [
        $d['ordem_de_servico_id'],
        $d['cliente'],
        $d['forma_devolucao'],
        number_format($d['total_devolucao'], 2, ',', '.')
    ], $devolucoes),
    ['O.S'=>'10%', 'CLIENTE'=>'35%', 'FORMA DE PAGAMENTO'=>'40%', 'TOTAL (R$)'=>'15%']
);

renderTable($pdf, 'Saídas e Despesas',
    ['TÍTULO', 'VALOR (R$)', 'FORMA DE PAGAMENTO', 'DATA CAIXA'],
    array_map(fn($s) => [
        $s['titulo'],
        number_format($s['valor_saida'], 2, ',', '.'),
        $s['forma_de_saida'],
        date('d/m/Y', strtotime($s['data']))
    ], $saidas),
    ['TÍTULO'=>'40%', 'VALOR (R$)'=>'15%', 'FORMA DE PAGAMENTO'=>'30%', 'DATA CAIXA'=>'15%']
);

renderTable($pdf, 'Depósitos',
    ['DATA CAIXA', 'DATA CADASTRO', 'VALOR (R$)', 'TIPO'],
    array_map(fn($d) => [
        date('d/m/Y', strtotime($d['data_caixa'])),
        date('d/m/Y', strtotime($d['data_cadastro'])),
        number_format($d['valor_do_deposito'], 2, ',', '.'),
        $d['tipo_deposito']
    ], $depositos),
    ['DATA CAIXA'=>'25%', 'DATA CADASTRO'=>'25%', 'VALOR (R$)'=>'25%', 'TIPO'=>'25%']
);

renderTable($pdf, 'Saldo Transportado',
    ['DATA CAIXA', 'DATA TRANSPORTE', 'VALOR (R$)', 'FUNCIONÁRIO', 'STATUS'],
    array_map(fn($s) => [
        date('d/m/Y', strtotime($s['data_caixa'])),
        date('d/m/Y', strtotime($s['data_transporte'])),
        number_format($s['valor_transportado'], 2, ',', '.'),
        $s['funcionario'],
        ucfirst(strtolower($s['status']))
    ], $saldoTransportado),
    ['DATA CAIXA'=>'20%', 'DATA TRANSPORTE'=>'20%', 'VALOR (R$)'=>'20%', 'FUNCIONÁRIO'=>'20%', 'STATUS'=>'20%']
);

// ================== Gráfico ==================
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'GRÁFICO DE PAGAMENTOS', 0, 1, 'C');
$pdf->Ln(5);

$labelsGrafico = array_keys($totalPorForma);
$valoresGrafico = array_values($totalPorForma);
$coresGrafico = ["#28a745", "#fd7e14", "#007bff", "#6f42c1", "#17a2b8", "#dc3545", "#ffc107", "#20c997"];

foreach ($valoresGrafico as &$v) {
    $v = round($v, 2);
}
unset($v);

$chartConfig = [
    "type" => "bar",
    "data" => [
        "labels" => $labelsGrafico,
        "datasets" => [[
            "data" => $valoresGrafico,
            "backgroundColor" => array_slice($coresGrafico, 0, count($valoresGrafico))
        ]]
    ],
    "options" => [
        "plugins" => [
            "legend" => false,
            "datalabels" => [
                "color" => "#FFFFFF",
                "font" => ["weight" => "bold", "size" => 14],
                "formatter" => "(value) => {
                    return 'R$ ' + value.toFixed(2);
                }"
            ]
        ],
        "title" => [
            "display" => false
        ]
    ]
];

$chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig)) . "&format=png&backgroundColor=white";

$tempGraph = tempnam(sys_get_temp_dir(), 'chart_') . '.png';
file_put_contents($tempGraph, file_get_contents($chartUrl));

if (file_exists($tempGraph)) {
    $pdf->Image($tempGraph, 30, 60, 150, 100);
    unlink($tempGraph);
}


ob_clean();
$pdf->Output('Fechamento_Caixa_'.$data.'_'.$funcionario.'.pdf', 'I');
?>
