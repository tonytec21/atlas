<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);

// Função para definir o fuso horário corretamente como sendo brasileiro
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

// Configurar a classe PDF
class PDF extends TCPDF
{
    private $criado_por;
    private $serventia;

    // Cabeçalho do PDF
    public function Header()
    {
        // Removido o cabeçalho padrão
    }

    // Rodapé do PDF
    public function Footer()
    {
        // Removido o rodapé
    }

    public function setCriadoPor($criado_por)
    {
        $this->criado_por = $criado_por;
    }

    public function getCriadoPor()
    {
        return $this->criado_por;
    }

    public function setServentia($serventia)
    {
        $this->serventia = $serventia;
    }

    public function addHeaderContent()
    {
        $image_file = '../style/img/recibo.png'; // Verifique se o caminho está correto
        if (file_exists($image_file)) {
            @$this->Image($image_file, 4, 2, 60, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $this->Ln(3); // Ajuste o espaçamento após a imagem
        } else {
            $this->SetFont('helvetica', 'B', 12);
            $this->MultiCell(0, 10, $this->serventia, 0, 'C', 0, 1, '', '', true);
            $this->Ln(3); // Ajuste o espaçamento após o texto
        }
    }
}

if (isset($_GET['id'])) {
    $os_id = $_GET['id'];

    // Obter dados da SO
    $os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $os_query->bind_param("i", $os_id);
    $os_query->execute();
    $os_result = $os_query->get_result();
    $ordem_servico = $os_result->fetch_assoc();

    // Obter dados de itens da OS ordenados pela coluna ordem_exibicao
    $os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ? ORDER BY ordem_exibicao ASC");
    $os_items_query->bind_param("i", $os_id);
    $os_items_query->execute();
    $os_items_result = $os_items_query->get_result();
    $ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

    // Obter informações do criador
    $criado_por = $ordem_servico['criado_por'];
    $user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $user_query->bind_param("s", $criado_por);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user_info = $user_result->fetch_assoc();
    $criado_por_nome = $user_info['nome_completo'];
    $criado_por_cargo = $user_info['cargo'];

    // Obter informações do usuário logado
    $logged_in_user = $_SESSION['username'];
    $logged_in_user_query = $conn->prepare("SELECT nome_completo, cargo FROM funcionarios WHERE usuario = ?");
    $logged_in_user_query->bind_param("s", $logged_in_user);
    $logged_in_user_query->execute();
    $logged_in_user_result = $logged_in_user_query->get_result();
    $logged_in_user_info = $logged_in_user_result->fetch_assoc();
    $logged_in_user_nome = $logged_in_user_info['nome_completo'];
    $logged_in_user_cargo = $logged_in_user_info['cargo'];

    // Obter soma dos pagamentos da tabela pagamento_os e a data do último pagamento
    $pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamentos_query->bind_param("i", $os_id);
    $pagamentos_query->execute();
    $pagamentos_result = $pagamentos_query->get_result();
    $pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

    // Calcular total dos pagamentos
    $total_pagamentos = 0;
    foreach ($pagamentos as $pagamento) {
        $total_pagamentos += $pagamento['total_pagamento'];
    }

    // Calcular saldo
    $saldo = $total_pagamentos - $ordem_servico['total_os'];

    // Obter total devoluções
    $devolucoes_query = $conn->prepare("SELECT SUM(total_devolucao) as total_devolucoes FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucoes_query->bind_param("i", $os_id);
    $devolucoes_query->execute();
    $devolucoes_result = $devolucoes_query->get_result();
    $total_devolucoes = $devolucoes_result->fetch_assoc()['total_devolucoes'];

    // Obter soma dos valores de repasse credor da tabela repasse_credor
    $repasses_query = $conn->prepare("SELECT SUM(total_repasse) as total_repasses FROM repasse_credor WHERE ordem_de_servico_id = ?");
    $repasses_query->bind_param("i", $os_id);
    $repasses_query->execute();
    $repasses_result = $repasses_query->get_result();
    $total_repasses = $repasses_result->fetch_assoc()['total_repasses'];

    // Obter informações da serventia
    $serventia_query = $conn->prepare("SELECT razao_social FROM cadastro_serventia WHERE id = 1");
    $serventia_query->execute();
    $serventia_result = $serventia_query->get_result();
    $serventia = $serventia_result->fetch_assoc()['razao_social'];

    $pdf = new PDF('P', 'mm', array(80, 297));
    $pdf->SetMargins(1, 1, 10);
    $pdf->setCriadoPor($criado_por_nome);
    $pdf->setServentia($serventia);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    // Adicionar o cabeçalho apenas como início do conteúdo
    $pdf->addHeaderContent();

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: center;">RECIBO DE PAGAMENTO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 8);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? '<br>CPF/CNPJ: ' . maskCpfCnpj($ordem_servico['cpf_cliente']) : '';
    $pdf->writeHTML('<div style="text-align: left;">APRESENTANTE: ' . $ordem_servico['cliente'] . $cpf_cnpj_text . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 8);
    $data_recibo = date('d/m/Y - H:i'); 
    $pdf->writeHTML('<div style="text-align: left;">'.'DATA DO RECIBO: '. $data_recibo .'</div>', true, false, true, false, '');
    $pdf->Ln(0);
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->writeHTML('<div style="text-align: left;">VALOR PAGO: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 8);
    if ($ordem_servico['base_de_calculo'] >= 0.001) {
        $pdf->writeHTML('<div style="text-align: left;">'.'BASE DE CÁLCULO: R$ '. number_format($ordem_servico['base_de_calculo'], 2, ',', '.') .'</div>', true, false, true, false, '');
        $pdf->Ln(0);
    }
    
    $pdf->SetFont('helvetica', 'B', 8);
    if (!empty($ordem_servico['observacoes'])) {
        $pdf->writeHTML('<div style="text-align: justify;">'.'<b>OBS:</b> '. $ordem_servico['observacoes'] .'</div>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // Adicionar informações sobre os pagamentos realizados
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 10px;"><b>PAGAMENTOS REALIZADOS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Função para adicionar o cabeçalho da tabela de pagamentos
    function adicionarCabecalhoTabelaPagamentos() {
        return '<table border="0.1" cellpadding="4">
            <thead>
                <tr>
                    <th style="width: 40%; text-align: center; font-size: 7.5px;"><b>DATA</b></th>
                    <th style="width: 30%; text-align: center; font-size: 7.5px;"><b>FORMA DE PAGAMENTO</b></th>
                    <th style="width: 30%; text-align: center; font-size: 7.5px;"><b>VALOR</b></th>
                </tr>
            </thead>
            <tbody>';
    }

    $html_pagamentos = adicionarCabecalhoTabelaPagamentos();

    foreach ($pagamentos as $pagamento) {
        $data_pagamento = date('d/m/Y - H:i', strtotime($pagamento['data_pagamento']));
        $rowHtmlPagamento = '<tr>
            <td style="width: 40%; font-size: 7.5px;">' . $data_pagamento . '</td>
            <td style="width: 30%; font-size: 7.5px;">' . $pagamento['forma_de_pagamento'] . '</td>
            <td style="width: 30%; font-size: 7.5px;">R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.') . '</td>
        </tr>';

        // Verificar a posição atual e adicionar uma nova página se necessário
        if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
            $html_pagamentos .= '</tbody></table>';
            $pdf->writeHTML($html_pagamentos, true, false, true, false, '');
            $pdf->AddPage();
            $html_pagamentos = adicionarCabecalhoTabelaPagamentos() . $rowHtmlPagamento;
        } else {
            $html_pagamentos .= $rowHtmlPagamento;
        }
    }

    $html_pagamentos .= '</tbody></table>';
    $pdf->writeHTML($html_pagamentos, true, false, true, false, '');

    // Adicionar as informações dos itens da OS
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 10px;"><b>ITENS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Adicionar o cabeçalho da tabela de itens apenas uma vez
    $html = '<table border="0.1" cellpadding="2">
        <thead>
            <tr>
                <th style="width: 20%; text-align: center; font-size: 8px;"><b>ATO</b></th>
                <th style="width: 10%; text-align: center; font-size: 8px;"><b>QTD</b></th>
                <th style="width: 29%; text-align: center; font-size: 8px;"><b>DESCRIÇÃO</b></th>
                <th style="width: 20%; text-align: center; font-size: 8px;"><b>EMOL</b></th>
                <th style="width: 21%; text-align: center; font-size: 8px;"><b>TOTAL</b></th>
            </tr>
        </thead>
        <tbody>';

    $total_ferc = 0;
    $total_femp = 0;
    $total_fadep = 0;

    foreach ($ordem_servico_itens as $index => $item) {
        $total_ferc += $item['ferc'];
        $total_femp += $item['femp'];
        $total_fadep += $item['fadep'];

        $rowHtml = '<tr>
            <td style="width: 20%; font-size: 8px;">' . $item['ato'] . '</td>
            <td style="width: 10%; font-size: 8px;">' . $item['quantidade'] . '</td>
            <td style="width: 29%; font-size: 8px;">' . $item['descricao'] . '</td>
            <td style="width: 20%; font-size: 8px;">' . number_format($item['emolumentos'], 2, ',', '.') . '</td>
            <td style="width: 21%; font-size: 8px;">' . number_format($item['total'], 2, ',', '.') . '</td>
        </tr>';

        // Verificar a posição atual e adicionar uma nova página se necessário
        if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
            $html .= '</tbody></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->AddPage();
            $pdf->SetY(10);
            $html = '<table border="0.1" cellpadding="2">
                <thead>
                    <tr>
                        <th style="width: 20%; text-align: center; font-size: 8px;"><b>ATO</b></th>
                        <th style="width: 10%; text-align: center; font-size: 8px;"><b>QTD</b></th>
                        <th style="width: 29%; text-align: center; font-size: 8px;"><b>DESCRIÇÃO</b></th>
                        <th style="width: 20%; text-align: center; font-size: 8px;"><b>EMOL</b></th>
                        <th style="width: 21%; text-align: center; font-size: 8px;"><b>TOTAL</b></th>
                    </tr>
                </thead>
                <tbody>' . $rowHtml;
        } else {
            $html .= $rowHtml;
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(-4);

    // Adicionar a informação de criado por
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->writeHTML('<div style="text-align: center;">Criado por: ' . $pdf->getCriadoPor() . '</div>', true, false, true, false, '');
    $pdf->Ln(2);

    // Adicionar os somatórios de FERC, FEMP e FADEP
    $pdf->SetFont('helvetica', '', 8);
    $pdf->writeHTML('<div style="text-align: left;">FERC: R$ ' . number_format($total_ferc, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);
    $pdf->writeHTML('<div style="text-align: left;">FEMP: R$ ' . number_format($total_femp, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);
    $pdf->writeHTML('<div style="text-align: left;">FADEP: R$ ' . number_format($total_fadep, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(-13);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetRightMargin(7);
    $pdf->writeHTML('<div style="text-align: right;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: right;">Dep. Prévio: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Adicionar valor de repasse credor se houver
    if ($total_repasses > 0) {
        $pdf->writeHTML('<div style="text-align: right;">Repasse Credor: R$ ' . number_format($total_repasses, 2, ',', '.') . '</div>', true, false, true, false, '');
        $pdf->Ln(1);
    }

    // Calcular saldo a restituir subtraindo o valor devolvido e o repasse credor
    $saldo_a_restituir = $saldo - $total_devolucoes - $total_repasses;

    // Adicionar o saldo com a condição
    if ($saldo_a_restituir > 0) {
        $pdf->writeHTML('<div style="text-align: right;">Valor a Restituir: R$ ' . number_format($saldo_a_restituir, 2, ',', '.') . '</div>', true, false, true, false, '');
    } elseif ($saldo < 0) {
        $pdf->writeHTML('<div style="text-align: right;">Valor a Pagar: R$ ' . number_format(abs($saldo), 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Adicionar valor devolvido se houver
    if ($total_devolucoes > 0) {
        $pdf->writeHTML('<div style="text-align: right;">Valor Restituído: R$ ' . number_format($total_devolucoes, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    $pdf->Ln(5);

    // Adicionar a linha de assinatura
    $pdf->Cell(0, 4, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    $pdf->Output('Recibo de Pagamento nº ' . $os_id . '.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>