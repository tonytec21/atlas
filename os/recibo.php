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

    // Verificar se deve mostrar FERRFIS (somente se algum item tiver valor > 0)
    $show_ferrfis = false;
    foreach ($ordem_servico_itens as $it) {
        $v = floatval($it['ferrfis'] ?? 0);
        if ($v > 0) { $show_ferrfis = true; break; }
    }

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
    $pdf->SetMargins(2, 1, 2);
    $pdf->setCriadoPor($criado_por_nome);
    $pdf->setServentia($serventia);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    // Adicionar o cabeçalho apenas como início do conteúdo
    $pdf->addHeaderContent();

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->writeHTML('<div style="text-align: center;">RECIBO DE PAGAMENTO Nº.: ' . $os_id . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;">'. $ordem_servico['descricao_os'] .'</div>', true, false, true, false, '');
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 10);
    $cpf_cnpj_text = !empty($ordem_servico['cpf_cliente']) ? '<br>CPF/CNPJ: ' . maskCpfCnpj($ordem_servico['cpf_cliente']) : '';
    $pdf->writeHTML('<div style="text-align: left;">APRESENTANTE: ' . $ordem_servico['cliente'] . $cpf_cnpj_text . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    $data_recibo = date('d/m/Y - H:i'); 
    $pdf->writeHTML('<div style="text-align: left;">'.'DATA DO RECIBO: '. $data_recibo .'</div>', true, false, true, false, '');
    $pdf->Ln(0);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: left;">VALOR PAGO: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->Ln(0);

    $pdf->SetFont('helvetica', 'B', 10);
    if ($ordem_servico['base_de_calculo'] >= 0.001) {
        $pdf->writeHTML('<div style="text-align: left;">'.'BASE DE CÁLCULO: R$ '. number_format($ordem_servico['base_de_calculo'], 2, ',', '.') .'</div>', true, false, true, false, '');
        $pdf->Ln(0);
    }
    
    $pdf->SetFont('helvetica', 'B', 10);
    if (!empty($ordem_servico['observacoes'])) {
        $pdf->writeHTML('<div style="text-align: justify;">'.'<b>OBS:</b> '. $ordem_servico['observacoes'] .'</div>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    // ================== PAGAMENTOS REALIZADOS ==================
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;"><b>PAGAMENTOS REALIZADOS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Linha separadora
    $pdf->writeHTML('<div style="text-align: center; font-size: 6px;">------------------------------------------------</div>', true, false, true, false, '');

    // Cabeçalho dos pagamentos (sem borda)
    $pdf->SetFont('helvetica', 'B', 10);
    $html_pag_header = '<table cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 38%; text-align: left;">DATA</td>
            <td style="width: 32%; text-align: left;">FORMA</td>
            <td style="width: 30%; text-align: right;">VALOR</td>
        </tr>
    </table>';
    $pdf->writeHTML($html_pag_header, true, false, true, false, '');

    // Itens de pagamento
    $pdf->SetFont('helvetica', '', 10);
    foreach ($pagamentos as $pagamento) {
        $data_pagamento = date('d/m/Y H:i', strtotime($pagamento['data_pagamento']));
        $html_pag_row = '<table cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 38%; text-align: left;">' . $data_pagamento . '</td>
                <td style="width: 32%; text-align: left;">' . $pagamento['forma_de_pagamento'] . '</td>
                <td style="width: 30%; text-align: right;">R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.') . '</td>
            </tr>
        </table>';
        
        // Verificar quebra de página
        if ($pdf->GetY() + 10 > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
            $pdf->SetY(5);
        }
        $pdf->writeHTML($html_pag_row, true, false, true, false, '');
    }

    // ================== ITENS DA OS ==================
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;"><b>ITENS</b></div>', true, false, true, false, '');
    $pdf->Ln(1);

    // Linha separadora
    $pdf->writeHTML('<div style="text-align: center; font-size: 6px;">------------------------------------------------</div>', true, false, true, false, '');

    // Acumuladores
    $total_emolumentos = 0;
    $total_ferc = 0;
    $total_femp = 0;
    $total_fadep = 0;
    $total_ferrfis = 0;
    $total_geral = 0;

    // Processar cada item
    $pdf->SetFont('helvetica', '', 10);
    foreach ($ordem_servico_itens as $index => $item) {
        // Acumular totais
        $total_emolumentos += floatval($item['emolumentos']);
        $total_ferc += floatval($item['ferc']);
        $total_femp += floatval($item['femp']);
        $total_fadep += floatval($item['fadep']);
        $total_ferrfis += floatval($item['ferrfis'] ?? 0);
        $total_geral += floatval($item['total']);

        // Verificar quebra de página antes de adicionar o item
        if ($pdf->GetY() + 12 > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
            $pdf->SetY(5);
        }

        // Linha 1: ATO + QTD + DESCRIÇÃO (com quebra de linha automática)
        $ato = $item['ato'];
        $qtd = $item['quantidade'];
        $descricao = $item['descricao'];
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->writeHTML('<div style="line-height: 1.1;"><b>ATO ' . $ato . ' (x' . $qtd . ')</b> - ' . $descricao . '</div>', true, false, true, false, '');

        // Linha 2: Valores (EMOL, FERC, FADEP, FEMP, FERRFIS, TOTAL) - sem espaço extra
        $pdf->SetFont('helvetica', '', 10);
        
        // Montar linha de valores dinamicamente
        $valores = [];
        $valores[] = 'EMOL: ' . number_format($item['emolumentos'], 2, ',', '.');
        $valores[] = 'FERC: ' . number_format($item['ferc'], 2, ',', '.');
        $valores[] = 'FADEP: ' . number_format($item['fadep'], 2, ',', '.');
        $valores[] = 'FEMP: ' . number_format($item['femp'], 2, ',', '.');
        
        if ($show_ferrfis) {
            $valores[] = 'FERRFIS: ' . number_format($item['ferrfis'] ?? 0, 2, ',', '.');
        }
        
        $valores[] = '<b>TOTAL: ' . number_format($item['total'], 2, ',', '.') . '</b>';
        
        $pdf->writeHTML('<div style="font-size: 10px; line-height: 1.1;">' . implode(' | ', $valores) . '</div>', true, false, true, false, '');
        
        // Espaçamento entre itens (maior que entre elementos do mesmo item)
        $pdf->Ln(2);
    }

    // Linha separadora
    $pdf->writeHTML('<div style="text-align: center; font-size: 6px;">------------------------------------------------</div>', true, false, true, false, '');

    // ================== SOMATÓRIOS ==================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: center;"><b>SOMATÓRIO</b></div>', true, false, true, false, '');

    $pdf->SetFont('helvetica', '', 10);
    
    // Somatórios em formato compacto
    $pdf->writeHTML('<div style="line-height: 1.2;">EMOL: R$ ' . number_format($total_emolumentos, 2, ',', '.') . ' | FERC: R$ ' . number_format($total_ferc, 2, ',', '.') . '</div>', true, false, true, false, '');
    $pdf->writeHTML('<div style="line-height: 1.2;">FADEP: R$ ' . number_format($total_fadep, 2, ',', '.') . ' | FEMP: R$ ' . number_format($total_femp, 2, ',', '.') . '</div>', true, false, true, false, '');

    // FERRFIS se houver
    if ($show_ferrfis) {
        $pdf->writeHTML('<div style="line-height: 1.2;">FERRFIS: R$ ' . number_format($total_ferrfis, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Linha separadora
    $pdf->writeHTML('<div style="text-align: center; font-size: 6px;">------------------------------------------------</div>', true, false, true, false, '');

    // ================== TOTAIS FINAIS ==================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Valor Total: R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.') . '</div>', true, false, true, false, '');

    $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Dep. Prévio: R$ ' . number_format($total_pagamentos, 2, ',', '.') . '</div>', true, false, true, false, '');

    // Adicionar valor de repasse credor se houver
    if ($total_repasses > 0) {
        $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Repasse Credor: R$ ' . number_format($total_repasses, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Calcular saldo a restituir subtraindo o valor devolvido e o repasse credor
    $saldo_a_restituir = $saldo - $total_devolucoes - $total_repasses;

    // Adicionar o saldo com a condição
    if ($saldo_a_restituir > 0) {
        $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Valor a Restituir: R$ ' . number_format($saldo_a_restituir, 2, ',', '.') . '</div>', true, false, true, false, '');
    } elseif ($saldo < 0) {
        $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Valor a Pagar: R$ ' . number_format(abs($saldo), 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    // Adicionar valor devolvido se houver
    if ($total_devolucoes > 0) {
        $pdf->writeHTML('<div style="text-align: right; line-height: 1.3;">Valor Restituído: R$ ' . number_format($total_devolucoes, 2, ',', '.') . '</div>', true, false, true, false, '');
    }

    $pdf->Ln(3);

    // Informação de criado por
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->writeHTML('<div style="text-align: center;">Criado por: ' . $pdf->getCriadoPor() . '</div>', true, false, true, false, '');
    
    $pdf->Ln(3);

    // Adicionar a linha de assinatura
    $pdf->Cell(0, 4, '________________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 4, $logged_in_user_nome, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 4, $logged_in_user_cargo, 0, 1, 'C');

    $pdf->Output('Recibo de Pagamento nº ' . $os_id . '.pdf', 'I');
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>