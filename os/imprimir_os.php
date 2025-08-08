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

    // Obter soma dos valores de repasse credor da tabela repasse_credor
    $repasses_query = $conn->prepare("SELECT SUM(total_repasse) as total_repasses FROM repasse_credor WHERE ordem_de_servico_id = ?");
    $repasses_query->bind_param("i", $os_id);
    $repasses_query->execute();
    $repasses_result = $repasses_query->get_result();
    $total_repasses = $repasses_result->fetch_assoc()['total_repasses'];

    // Obter informações das contas bancárias
    $contas_query = $conn->prepare("SELECT banco, agencia, tipo_conta, numero_conta, titular_conta, cpf_cnpj_titular, chave_pix, qr_code_pix FROM configuracao_os WHERE status = 'ativa'");
    $contas_query->execute();
    $contas_result = $contas_query->get_result();
    $contas = $contas_result->fetch_all(MYSQLI_ASSOC);

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
    $pdf->writeHTML('<div style="text-align: center;">ORDEM DE SERVIÇO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
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

    // Verificar se o texto e os valores vão ultrapassar os 3cm da margem inferior
    if ($pdf->GetY() + 30 > $pdf->getPageHeight() - 30) {
        $pdf->AddPage();
    }

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetLeftMargin(10); // Ajusta a margem esquerda se necessário
    $pdf->SetRightMargin(60); // Define a margem direita de 5 cm (aproximadamente 50mm)
    $pdf->writeHTML('<div style="text-align: justify; margin-top: 20px;">Os emolumentos apresentados estão corretos para os atos discriminados, conforme a Tabela vigente e normas da Corregedoria; se houver atos complementares ou serviços não previstos, serão acrescidos os valores legais correspondentes. Ao final do serviço, será realizado um levantamento detalhado de todos os atos praticados para confirmar o valor total. Caso haja um aumento, será necessário complementar o pagamento para a entrega do serviço. Se houver saldo remanescente, o valor será devolvido no ato da entrega.</div>', true, false, true, false, '');
    $pdf->Ln(-18);

    // Adicionar os valores totais abaixo da tabela
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetRightMargin(8);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 20px;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Dep. Prévio: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    // Calcular saldo a restituir subtraindo o valor devolvido e o repasse credor
    $saldo_a_restituir = $saldo - $total_devolucoes - $total_repasses;

    // Adicionar o saldo com a condição
    if ($saldo_a_restituir > 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor a Restituir: R$ ' . number_format($saldo_a_restituir, 2, ',', '.') . '</div>', true, false, true, false, '');
    } elseif ($saldo < 0) {
        $pdf->writeHTML('<div style="text-align: right; color: red; margin-top: 10px;">Valor a Pagar: R$ ' . number_format(abs($saldo), 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Adicionar valor devolvido se houver
    if ($total_devolucoes > 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Valor Restituído: R$ ' . number_format($total_devolucoes, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Adicionar valor de repasse credor se houver
    if ($total_repasses > 0) {
        $pdf->writeHTML('<div style="text-align: right; margin-top: 10px;">Repasse Credor: R$ ' . number_format($total_repasses, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    $pdf->Ln(12);

    $configFile = '../style/configuracao_contas.json';
    if (file_exists($configFile)) {
        $configData = json_decode(file_get_contents($configFile), true);
        $exibircpf = $configData['exibircpf'] ?? 'S';
    } else {
        $exibircpf = 'S';
    }

    if ($exibircpf === 'S') {
        // Função de cabeçalho COM a coluna CPF/CNPJ
        function adicionarCabecalhoTabelaContas(&$pdf, $numContas) {
            $titulo = ($numContas > 1) ? 'CONTAS BANCÁRIAS' : 'CONTA BANCÁRIA';
            $pdf->SetFont('helvetica', '', 10);
            $pdf->writeHTML('<div style="text-align: left; margin-top: 20px;"><b>' . $titulo . '</b></div>', true, false, true, false, '');
            $pdf->Ln(1);

            $html = '<table border="0.1" cellpadding="4">
                <thead>
                    <tr>
                        <th style="width: 12%; text-align: center; font-size: 7.5px;"><b>Banco</b></th>
                        <th style="width: 7%; text-align: center; font-size: 7.5px;"><b>Agência</b></th>
                        <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Tipo de Conta</b></th>
                        <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Nº Conta</b></th>
                        <th style="width: 18%; text-align: center; font-size: 7.5px;"><b>Titular da Conta</b></th>
                        <th style="width: 15%; text-align: center; font-size: 7.5px;"><b>CPF/CNPJ do Titular</b></th>
                        <th style="width: 20%; text-align: center; font-size: 7.5px;"><b>Chave PIX</b></th>
                        <th style="width: 11%; text-align: center; font-size: 7.5px;"><b>QR Code PIX</b></th>
                    </tr>
                </thead>
                <tbody>';
            return $html;
        }
    } else {
        // Função de cabeçalho SEM a coluna CPF/CNPJ
        function adicionarCabecalhoTabelaContas(&$pdf, $numContas) {
            $titulo = ($numContas > 1) ? 'CONTAS BANCÁRIAS' : 'CONTA BANCÁRIA';
            $pdf->SetFont('helvetica', '', 10);
            $pdf->writeHTML('<div style="text-align: left; margin-top: 20px;"><b>' . $titulo . '</b></div>', true, false, true, false, '');
            $pdf->Ln(1);

            $html = '<table border="0.1" cellpadding="4">
                <thead>
                    <tr>
                        <th style="width: 12%; text-align: center; font-size: 7.5px;"><b>Banco</b></th>
                        <th style="width: 7%; text-align: center; font-size: 7.5px;"><b>Agência</b></th>
                        <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Tipo de Conta</b></th>
                        <th style="width: 8%; text-align: center; font-size: 7.5px;"><b>Nº Conta</b></th>
                        <th style="width: 26%; text-align: center; font-size: 7.5px;"><b>Titular da Conta</b></th>
                        <th style="width: 27%; text-align: center; font-size: 7.5px;"><b>Chave PIX</b></th>
                        <th style="width: 11%; text-align: center; font-size: 7.5px;"><b>QR Code PIX</b></th>
                    </tr>
                </thead>
                <tbody>';
            return $html;
        }
    }

    // Verificar se há contas bancárias ativas e adicionar ao PDF
    if (!empty($contas)) {
        $numContas = count($contas);
        $html = adicionarCabecalhoTabelaContas($pdf, $numContas);

        foreach ($contas as $index => $conta) {
            $rowHtmlCPF = ($exibircpf === 'S')
                ? '<td style="width: 15%; font-size: 7.5px;">' . $conta['cpf_cnpj_titular'] . '</td>'
                : ''; 
            
            if ($exibircpf === 'S') {
                // Montar a linha COM CPF/CNPJ
                $rowHtml = '<tr>
                    <td style="width: 12%; font-size: 7.5px;">' . $conta['banco'] . '</td>
                    <td style="width: 7%; font-size: 7.5px;">' . $conta['agencia'] . '</td>
                    <td style="width: 8%; font-size: 7.5px;">' . $conta['tipo_conta'] . '</td>
                    <td style="width: 8%; font-size: 7.5px;">' . $conta['numero_conta'] . '</td>
                    <td style="width: 18%; font-size: 7.5px;">' . $conta['titular_conta'] . '</td>
                    ' . $rowHtmlCPF . '
                    <td style="width: 20%; font-size: 7.5px;">' . $conta['chave_pix'] . '</td>
                    <td style="width: 11%; font-size: 7.5px; text-align: center;"><img src="@' . $conta['qr_code_pix'] . '" height="60" /></td>
                </tr>';
            } else {
                // Montar a linha SEM CPF/CNPJ
                $rowHtml = '<tr>
                    <td style="width: 12%; font-size: 7.5px;">' . $conta['banco'] . '</td>
                    <td style="width: 7%; font-size: 7.5px;">' . $conta['agencia'] . '</td>
                    <td style="width: 8%; font-size: 7.5px;">' . $conta['tipo_conta'] . '</td>
                    <td style="width: 8%; font-size: 7.5px;">' . $conta['numero_conta'] . '</td>
                    <td style="width: 26%; font-size: 7.5px;">' . $conta['titular_conta'] . '</td>
                    <td style="width: 27%; font-size: 7.5px;">' . $conta['chave_pix'] . '</td>
                    <td style="width: 11%; font-size: 7.5px; text-align: center;"><img src="@' . $conta['qr_code_pix'] . '" height="60" /></td>
                </tr>';
            }

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
    
    // Adicionar a imagem da assinatura
    if (!empty($assinatura_path)) {
        $pdf->addSignature($assinatura_path);
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
