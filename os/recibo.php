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
    private $serventia;

    // Cabeçalho do PDF
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 12);
        $this->SetY(1);
        $this->MultiCell(0, 10, $this->serventia, 0, 'C', 0, 1, '', '', true);
        $this->SetY(20);
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

    // Obter soma dos pagamentos da tabela pagamento_os e a data do último pagamento
    $pagamentos_query = $conn->prepare("SELECT SUM(total_pagamento) as total_pagamentos, MAX(data_pagamento) as ultima_data_pagamento FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamentos_query->bind_param("i", $os_id);
    $pagamentos_query->execute();
    $pagamentos_result = $pagamentos_query->get_result();
    $pagamentos_data = $pagamentos_result->fetch_assoc();
    $total_pagamentos = $pagamentos_data['total_pagamentos'];
    $ultima_data_pagamento = $pagamentos_data['ultima_data_pagamento'];

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

    // Obter o nome da serventia
    $serventia_query = $conn->prepare("SELECT CONVERT(razao_social USING utf8) as razao_social FROM cadastro_serventia WHERE id = 1");
    $serventia_query->execute();
    $serventia_result = $serventia_query->get_result();
    $serventia_data = $serventia_result->fetch_assoc();
    $serventia = $serventia_data['razao_social'];

    $pdf = new PDF('P', 'mm', array(80, 297));
    $pdf->SetMargins(1, 20, 10);
    $pdf->setCriadoPor($criado_por_nome); // Define o nome do usuário que criou a OS
    $pdf->setServentia($serventia); // Define o nome da serventia
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9); // Ajuste de fonte e tamanho

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: center;">RECIBO DE PAGAMENTO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', '', 8);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? '<br>CPF/CNPJ: ' . $ordem_servico['cpf_cliente'] : '';
    $pdf->writeHTML('<div style="text-align: left;">CLIENTE: ' . $ordem_servico['cliente'] . $cpf_cnpj_text . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 8);
    $data_pagamento = !empty($ultima_data_pagamento) ? date('d/m/Y - H:i', strtotime($ultima_data_pagamento)) : 'Data não disponível';
    $pdf->writeHTML('<div style="text-align: left;">'.'DATA DO PAGAMENTO: '. $data_pagamento .'</div>', true, false, true, false, '');
    $pdf->Ln(1);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->writeHTML('<div style="text-align: left;">VALOR PAGO: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 8);
    if ($ordem_servico['base_de_calculo'] >= 0.001) {
        $pdf->writeHTML('<div style="text-align: left;">'.'BASE DE CÁLCULO: R$ '. number_format($ordem_servico['base_de_calculo'], 2, ',', '.') .'</div>', true, false, true, false, '');
        $pdf->Ln(1);
    }
    
    $pdf->SetFont('helvetica', '', 8);
    if (!empty($ordem_servico['observacoes'])) {
        $pdf->writeHTML('<div style="text-align: justify;">'.'<b>OBS:</b> '. $ordem_servico['observacoes'] .'</div>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // Adicionar as informações dos itens da OS
    $pdf->SetFont('helvetica', '', 9);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 10px;"><b>ITENS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Função para adicionar o cabeçalho da tabela de itens
    function adicionarCabecalhoTabelaItens(&$pdf) {
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
        return $html;
    }

    $html = adicionarCabecalhoTabelaItens($pdf);

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
            $html = adicionarCabecalhoTabelaItens($pdf) . $rowHtml;
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
    $pdf->SetRightMargin(7); // Define a margem direita de 5 cm (aproximadamente 50mm)
    $pdf->writeHTML('<div style="text-align: right;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: right;">Dep. Prévio: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Calcular saldo a restituir subtraindo o valor devolvido
    $saldo_a_restituir = $saldo - $total_devolucoes;

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

    $pdf->Ln(10);

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
