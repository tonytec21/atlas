<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);

// Função para definir o fuso horário corretamente como sendo brasileiro
date_default_timezone_set('America/Sao_Paulo');

// Configurar a classe PDF
class PDF extends TCPDF
{
    private $criado_por;

    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/logo.png'; // Verifique se o caminho está correto
        @$this->Image($image_file, 20, 8, 170, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetY(25);
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('arial', 'I', 8);
        // Adiciona o nome do usuário que criou a OS
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Criado por: ' . $this->criado_por, 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }

    public function setCriadoPor($criado_por)
    {
        $this->criado_por = $criado_por;
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

    // Obter dados de itens da SO
    $os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
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

    // Obter soma dos pagamentos da tabela pagamento_os
    $pagamentos_query = $conn->prepare("SELECT SUM(total_pagamento) as total_pagamentos FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamentos_query->bind_param("i", $os_id);
    $pagamentos_query->execute();
    $pagamentos_result = $pagamentos_query->get_result();
    $total_pagamentos = $pagamentos_result->fetch_assoc()['total_pagamentos'];

    // Calcular saldo
    $saldo = $total_pagamentos - $ordem_servico['total_os'];

    // Obter soma dos valores devolvidos da tabela devolucao_os
    $devolucoes_query = $conn->prepare("SELECT SUM(total_devolucao) as total_devolucoes FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucoes_query->bind_param("i", $os_id);
    $devolucoes_query->execute();
    $devolucoes_result = $devolucoes_query->get_result();
    $total_devolucoes = $devolucoes_result->fetch_assoc()['total_devolucoes'];

    // Obter informações das contas bancárias
    $contas_query = $conn->prepare("SELECT banco, agencia, tipo_conta, numero_conta, titular_conta, cpf_cnpj_titular, chave_pix, qr_code_pix FROM configuracao_os WHERE status = 'ativa'");
    $contas_query->execute();
    $contas_result = $contas_query->get_result();
    $contas = $contas_result->fetch_all(MYSQLI_ASSOC);

    $pdf = new PDF();
    $pdf->SetMargins(10, 45, 10);
    $pdf->setCriadoPor($criado_por_nome); // Define o nome do usuário que criou a OS
    $pdf->AddPage();
    $pdf->SetFont('arial', '', 10);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;">ORDEM DE SERVIÇO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 9);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? ' - CPF/CNPJ: ' . $ordem_servico['cpf_cliente'] : '';
    $pdf->writeHTML('<div style="text-align: left;">Cliente: ' . $ordem_servico['cliente'] . $cpf_cnpj_text . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 9);
    $data_criacao = date('d/m/Y - H:i', strtotime($ordem_servico['data_criacao']));
    $pdf->writeHTML('<div style="text-align: left;">'.'Data: '. $data_criacao .'</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 9);
    if ($ordem_servico['base_de_calculo'] >= 0.001) {
        $pdf->writeHTML('<div style="text-align: left;">'.'Base de Cálculo: R$ '. number_format($ordem_servico['base_de_calculo'], 2, ',', '.') .'</div>', true, false, true, false, '');
        $pdf->Ln(1);
    }
    
    $pdf->SetFont('helvetica', '', 9);
    if (!empty($ordem_servico['observacoes'])) {
        $pdf->writeHTML('<div style="text-align: justify;">'.'<b>OBS:</b> '. $ordem_servico['observacoes'] .'</div>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // Adicionar as informações dos itens da OS
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 20px;"><b>ITENS DA ORDEM DE SERVIÇO</b></div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 20px;">Valores válidos até 31/12/'. date('Y', strtotime($ordem_servico['data_criacao'])) . ' </b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Função para adicionar o cabeçalho da tabela de itens
    function adicionarCabecalhoTabelaItens(&$pdf) {
        $html = '<table border="0.1" cellpadding="4">
            <thead>
                <tr>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>ATO</b></th>
                    <th style="width: 5%; text-align: center; font-size: 7.5px;"><b>QTD</b></th>
                    <th style="width: 7%; text-align: center; font-size: 7.5px;"><b>DESC. LEGAL (%)</b></th>
                    <th style="width: 36%; text-align: center; font-size: 7.5px;"><b>DESCRIÇÃO</b></th>
                    <th style="width: 10%; text-align: center; font-size: 7.5px;"><b>EMOL</b></th>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>FERC</b></th>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>FADEP</b></th>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>FEMP</b></th>
                    <th style="width: 10%; text-align: center; font-size: 7.5px;"><b>TOTAL</b></th>
                </tr>
            </thead>
            <tbody>';
        return $html;
    }

    $html = adicionarCabecalhoTabelaItens($pdf);

    foreach ($ordem_servico_itens as $index => $item) {
        $rowHtml = '<tr>
            <td style="width: 8%; font-size: 7.5px;">' . $item['ato'] . '</td>
            <td style="width: 5%; font-size: 7.5px;">' . $item['quantidade'] . '</td>
            <td style="width: 7%; font-size: 7.5px;">' . $item['desconto_legal'] . '</td>
            <td style="width: 36%; font-size: 7px;">' . $item['descricao'] . '</td>
            <td style="width: 10%; font-size: 7.5px;">R$ ' . number_format($item['emolumentos'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 7.5px;">R$ ' . number_format($item['ferc'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 7.5px;">R$ ' . number_format($item['fadep'], 2, ',', '.') . '</td>
            <td style="width: 8%; font-size: 7.5px;">R$ ' . number_format($item['femp'], 2, ',', '.') . '</td>
            <td style="width: 10%; font-size: 7.5px;">R$ ' . number_format($item['total'], 2, ',', '.') . '</td>
        </tr>';

        // Verificar a posição atual e adicionar uma nova página se necessário
        if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
            $html .= '</tbody></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->AddPage();
            $html = adicionarCabecalhoTabelaItens($pdf) . $rowHtml;
        } else {
            $html .= $rowHtml;
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->Ln(-5);

    // Verificar se o texto e os valores vão ultrapassar os 3cm da margem inferior
    if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
        $pdf->AddPage();
    }

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetLeftMargin(10); // Ajusta a margem esquerda se necessário
    $pdf->SetRightMargin(58); // Define a margem direita de 5 cm (aproximadamente 50mm)
    $pdf->writeHTML('<div style="text-align: justify; margin-top: 20px;">Os valores apresentados são aproximados. Ao final do serviço, será realizado um levantamento detalhado de todos os atos praticados para confirmar o valor total. Caso haja um aumento, será necessário complementar o pagamento para a entrega do serviço. Se houver saldo remanescente, o valor será devolvido no ato da entrega. <b>É importante ter ciência de que o valor dos emolumentos é uma estimativa e pode sofrer alterações em função dos atos realizados.</b></div>', true, false, true, false, '');
    $pdf->Ln(-18);

    // Adicionar os valores totais abaixo da tabela
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetRightMargin(8);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 20px;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Dep. Prévio: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    // Calcular saldo a restituir subtraindo o valor devolvido
    $saldo_a_restituir = $saldo - $total_devolucoes;

    // Adicionar o saldo com a condição
    if ($saldo_a_restituir > 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor a Restituir: R$ ' . number_format($saldo_a_restituir, 2, ',', '.') . '</div>', true, false, true, false, '');
    } elseif ($saldo < 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor a Pagar: R$ ' . number_format(abs($saldo), 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Adicionar valor devolvido se houver
    if ($total_devolucoes > 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor Restituído: R$ ' . number_format($total_devolucoes, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    $pdf->Ln(10);

    // Função para adicionar o cabeçalho da tabela de contas bancárias
    function adicionarCabecalhoTabelaContas(&$pdf, $numContas) {
        $titulo = ($numContas > 1) ? 'CONTAS BANCÁRIAS' : 'CONTA BANCÁRIA';
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML('<div style="text-align: left; margin-top: 20px;"><b>' . $titulo . '</b></div>', true, false, true, false, '');
        $pdf->Ln(1);

        $html = '<table border="0.1" cellpadding="4">
            <thead>
                <tr>
                    <th style="width: 10%; text-align: center; font-size: 7.5px;"><b>Banco</b></th>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Agência</b></th>
                    <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Tipo de Conta</b></th>
                    <th style="width: 9%; text-align: center; font-size: 7.5px;"><b>Nº da Conta</b></th>
                    <th style="width: 25%; text-align: center; font-size: 7.5px;"><b>Titular da Conta</b></th>
                    <th style="width: 15%; text-align: center; font-size: 7.5px;"><b>CPF/CNPJ do Titular</b></th>
                    <th style="width: 15%; text-align: center; font-size: 7.5px;"><b>Chave PIX</b></th>
                    <th style="width: 9%; text-align: center; font-size: 7.5px;"><b>QR Code PIX</b></th>
                </tr>
            </thead>
            <tbody>';
        return $html;
    }

    // Verificar se há contas bancárias ativas e adicionar ao PDF
    if (!empty($contas)) {
        $numContas = count($contas);
        $html = adicionarCabecalhoTabelaContas($pdf, $numContas);

        foreach ($contas as $index => $conta) {
            $rowHtml = '<tr>
                <td style="width: 10%; font-size: 7.5px;">' . $conta['banco'] . '</td>
                <td style="width: 8%; font-size: 7.5px;">' . $conta['agencia'] . '</td>
                <td style="width: 8%; font-size: 7.5px;">' . $conta['tipo_conta'] . '</td>
                <td style="width: 9%; font-size: 7.5px;">' . $conta['numero_conta'] . '</td>
                <td style="width: 25%; font-size: 7.5px;">' . $conta['titular_conta'] . '</td>
                <td style="width: 15%; font-size: 7.5px;">' . $conta['cpf_cnpj_titular'] . '</td>
                <td style="width: 15%; font-size: 7.5px;">' . $conta['chave_pix'] . '</td>
                <td style="width: 9%; font-size: 7.5px; text-align: center;"><img src="@' . $conta['qr_code_pix'] . '" height="40" /></td>
            </tr>';

            // Verificar a posição atual e adicionar uma nova página se necessário
            if ($index == 0 && $pdf->GetY() + 20 > $pdf->getPageHeight() - 30) {
                $pdf->AddPage();
                $html = adicionarCabecalhoTabelaContas($pdf, $numContas);
            } elseif ($index > 0 && $pdf->GetY() + 40 > $pdf->getPageHeight() - 30) {
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->AddPage();
                $html = adicionarCabecalhoTabelaContas($pdf, $numContas);
            }
            
            $html .= $rowHtml;
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Adicionar a linha de assinatura
    $pdf->Ln(5);
    $pdf->Cell(0, 4, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    $pdf->Output('Ordem de serviço nº ' . $os_id . '.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>
