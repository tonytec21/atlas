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
        $image_file = '../style/img/timbrado.png'; // Verifique se o caminho está correto

        // Salva as margens atuais
        $currentMargins = $this->getMargins();

        // Desativa temporariamente as margens e AutoPageBreak
        $this->SetAutoPageBreak(false, 0); // Desativa o AutoPageBreak para permitir a imagem cobrir toda a página
        $this->SetMargins(0, 0, 0);

        // Inserir a imagem ocupando toda a página
        @$this->Image($image_file, 0, 0, 210, 297, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Adicionar a marca d'água se necessário
        global $isCanceled;
        if ($isCanceled) {
            $this->SetAlpha(0.2);
            $this->StartTransform();

            // Rotaciona e adiciona a marca d'água
            $this->Rotate(45, $this->getPageWidth() / 2, $this->getPageHeight() / 2);
            $this->SetFont('helvetica', 'B', 60);
            $this->SetTextColor(255, 0, 0);

            // Adiciona o texto da marca d'água no centro da página
            $this->Text($this->getPageWidth() / 7, $this->getPageHeight() / 2.5, 'O.S. CANCELADA');
            $this->StopTransform();
            $this->SetAlpha(1);
        }

        // Restaura o AutoPageBreak e as margens para o conteúdo subsequente
        $this->SetAutoPageBreak(true, 25); // Ativa novamente o AutoPageBreak com a margem inferior padrão
        $this->SetMargins($currentMargins['left'], $currentMargins['top'], $currentMargins['right']);
        $this->SetY(25); // Define o ponto Y após a imagem para o conteúdo
    }

    // Rodapé do PDF
    public function Footer()
    {
        // Caminho do arquivo de configuração
        $configFile = "../style/configuracao_timbrado.json";

        // Definir cor padrão (preta)
        $textColor = [0, 0, 0];

        // Verifica se o arquivo existe e lê a configuração
        if (file_exists($configFile)) {
            $configData = json_decode(file_get_contents($configFile), true);
            // Se a chave "rodape" for "S", define a cor branca
            if (isset($configData['rodape']) && $configData['rodape'] === "S") {
                $textColor = [255, 255, 255];
            }
        }

        // Número da página no canto inferior direito
        $this->SetY(-14.5);
        $this->SetFont('arial', 'I', 8);
        $this->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

        // Ajustar a posição horizontal com SetX para aproximar mais do canto
        $this->SetX(-23);

        // Exibir o número da página
        $this->Cell(0, 11, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');

        // Definir cor preta para o texto "Criado por"
        $this->SetTextColor(0, 0, 0); // Definir cor preta

        // Posicionar o texto na lateral direita, com rotação para orientação vertical
        $this->SetXY(-10, ($this->getPageHeight() / 2)); // Posição X na margem direita, Y centralizada verticalmente
        $this->StartTransform(); // Iniciar transformação
        $this->Rotate(90); // Rotacionar 90 graus
        $this->Cell(0, 10, 'Criado por: ' . $this->criado_por, 0, false, 'C', 0, '', 0, false, 'T', 'M'); // Texto centralizado verticalmente
        $this->StopTransform(); // Parar transformação
    }

    // Método para definir o nome do criador
    public function setCriadoPor($criado_por)
    {
        $this->criado_por = $criado_por; // Atribui o valor à propriedade privado
    }

    // Função para adicionar a chancela da assinatura
    public function addSignature($assinatura_path)
    {
        if (file_exists($assinatura_path)) {
            $signatureWidth = 80; // Largura da imagem da assinatura
            $pageWidth = $this->getPageWidth();
            $marginLeft = $this->getMargins()['left'];
            $marginRight = $this->getMargins()['right'];
            $centerX = ($pageWidth - $marginLeft - $marginRight - $signatureWidth) / 2 + $marginLeft;

            $this->Image($assinatura_path, (float)$centerX, $this->GetY() - 2, (float)$signatureWidth, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
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

    // Verificar o status da OS
    $status_os = $ordem_servico['status'];
    $isCanceled = ($status_os === 'Cancelado');

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

    // Obter soma dos pagamentos da tabela pagamento_os
    $pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamentos_query->bind_param("i", $os_id);
    $pagamentos_query->execute();
    $pagamentos_result = $pagamentos_query->get_result();
    $pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

    // Obter total de pagamentos
    $total_pagamentos = 0;
    foreach ($pagamentos as $pagamento) {
        $total_pagamentos += $pagamento['total_pagamento'];
    }

    // Calcular saldo
    $saldo = $total_pagamentos - $ordem_servico['total_os'];

    // Obter soma dos valores devolvidos da tabela devolucao_os
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

    // Início do PDF
    $pdf = new PDF();
    $pdf->SetMargins(12, 40, 10);
    $pdf->setCriadoPor($criado_por_nome); // Define o nome do usuário que criou a OS
    $pdf->AddPage();
    $pdf->SetFont('arial', '', 10);

    // Verificar e carregar a assinatura
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

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;">RECIBO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetMargins(10, 40, 10);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? ' - CPF/CNPJ: ' . $ordem_servico['cpf_cliente'] : '';
    $pdf->writeHTML('<div style="text-align: left;">Apresentante: ' . $ordem_servico['cliente'] . $cpf_cnpj_text . '</div>', true, false, true, false, '');
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
                    <th style="width: 8%; text-align: center; font-size: 7px;"><b>DESC. LEGAL %</b></th>
                    <th style="width: 35%; text-align: center; font-size: 7.5px;"><b>DESCRIÇÃO</b></th>
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
            <td style="width: 8%; font-size: 7.5px;">' . $item['desconto_legal'] . '</td>
            <td style="width: 35%; font-size: 7px;">' . $item['descricao'] . '</td>
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

    // Adicionar os valores dos pagamentos
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML('<div style="text-align: center; margin-top: 20px;"><b>PAGAMENTOS REALIZADOS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Função para adicionar o cabeçalho da tabela de pagamentos
    function adicionarCabecalhoTabelaPagamentos() {
        return '<table border="0.1" cellpadding="4">
            <thead>
                <tr>
                    <th style="width: 15%; text-align: center; font-size: 7.5px;"><b>DATA</b></th>
                    <th style="width: 30%; text-align: center; font-size: 7.5px;"><b>CLIENTE</b></th>
                    <th style="width: 15%; text-align: center; font-size: 7.5px;"><b>FORMA DE PAGAMENTO</b></th>
                    <th style="width: 20%; text-align: center; font-size: 7.5px;"><b>VALOR</b></th>
                    <th style="width: 20%; text-align: center; font-size: 7.5px;"><b>STATUS</b></th>
                </tr>
            </thead>
            <tbody>';
    }

    $html_pagamentos = adicionarCabecalhoTabelaPagamentos();

    foreach ($pagamentos as $pagamento) {
        $data_pagamento = date('d/m/Y - H:i', strtotime($pagamento['data_pagamento']));
        $rowHtmlPagamento = '<tr>
            <td style="width: 15%; font-size: 7.5px;">' . $data_pagamento . '</td>
            <td style="width: 30%; font-size: 7.5px;">' . $pagamento['cliente'] . '</td>
            <td style="width: 15%; font-size: 7.5px;">' . $pagamento['forma_de_pagamento'] . '</td>
            <td style="width: 20%; font-size: 7.5px;">R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.') . '</td>
            <td style="width: 20%; font-size: 7.5px;">' . $pagamento['status'] . '</td>
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

    // Adicionar a linha de assinatura
    $pdf->Ln(5);
    $pdf->Cell(0, 4, '__________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    $pdf->Output('Recibo nº ' . $os_id . '.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>