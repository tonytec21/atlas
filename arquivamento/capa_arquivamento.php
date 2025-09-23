<?php
include(__DIR__ . '/session_check.php');
checkSession();
require_once('../oficios/tcpdf/tcpdf.php');

// Configurar a classe PDF
class PDF extends TCPDF
{
    // Cabeçalho do PDF
    public function Header()
    {
        $image_file = '../style/img/timbrado.png'; // Verifique se o caminho está correto
        
        // Definir largura e altura que você deseja forçar
        $imageWidth = 210;  // Largura total da página A4 (em mm)
        $imageHeight = 297; // Altura total da página A4 (em mm)

        // Desativar as margens e o AutoPageBreak temporariamente
        $this->SetAutoPageBreak(false, 0); // Desativar temporariamente a quebra automática de página
        $this->SetMargins(0, 0, 0); // Remover margens para a imagem
        
        // Redimensionar a imagem para ocupar toda a página, ignorando proporções
        $this->Image($image_file, 0, 0, $imageWidth, $imageHeight, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

        // Restaurar as margens e o AutoPageBreak para o conteúdo subsequente
        $this->SetAutoPageBreak(true, 25); // Restaurar o AutoPageBreak com a margem inferior de 2.5cm
        $this->SetMargins(25, 45, 25);  // Restaurar as margens para o conteúdo
        $this->SetY(35); // Ajuste para garantir que o conteúdo não sobreponha a imagem
    }

    // Rodapé do PDF
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
    }

    // Adicionar parágrafo com recuo na primeira linha
    public function AddParagraph($text, $lineHeight)
    {
        $this->SetX(25); // Recuo da margem esquerda
        $paragraphs = explode("\n", $text);
        foreach ($paragraphs as $paragraph) {
            $this->SetX(25);
            $paragraph = ltrim($paragraph, "\t"); // Remove o tab no início do parágrafo
            $this->WriteHTML('<p style="text-align:justify; text-indent:2cm;">' . htmlspecialchars_decode($paragraph) . '</p>');
        }
    }
}

/**
 * Retorna TODOS os selos vinculados ao arquivo (arquivamento)
 */
function getSelos($arquivo_id) {
    include 'db_connection.php';
    $stmt = $conn->prepare("SELECT selos.* 
                              FROM selos_arquivamentos 
                              INNER JOIN selos ON selos_arquivamentos.selo_id = selos.id 
                             WHERE selos_arquivamentos.arquivo_id = ?
                          ORDER BY selos.id ASC");
    $stmt->bind_param("i", $arquivo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selos = [];
    while ($row = $result->fetch_assoc()) {
        $selos[] = $row;
    }
    $stmt->close();
    return $selos;
}

/** Helpers seguros */
function val(array $arr, string $key): string {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : '';
}
function has($v): bool {
    return isset($v) && trim((string)$v) !== '';
}
function esc($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $filePath = "meta-dados/$id.json";

    if (file_exists($filePath)) {
        $ato = json_decode(file_get_contents($filePath), true);

        // Busca TODOS os selos
        $selos = getSelos($id);

        // Soma das quantidades
        $quantidade_total = 0;
        foreach ($selos as $s) {
            $quantidade_total += (int)($s['quantidade'] ?? 0);
        }

        // Monta string de partes
        $partesNomes = [];
        if (isset($ato['partes_envolvidas']) && is_array($ato['partes_envolvidas'])) {
            foreach ($ato['partes_envolvidas'] as $parte) {
                if (!empty($parte['nome'])) {
                    $partesNomes[] = $parte['nome'];
                }
            }
        }
        $partesStr = implode('; ', $partesNomes);

        // Campos do ato (já saneados)
        $atribuicao = val($ato, 'atribuicao');
        $termo      = val($ato, 'termo');
        $protocolo  = val($ato, 'protocolo');
        $matricula  = val($ato, 'matricula');
        $categoria  = val($ato, 'categoria');
        $dataAtoRaw = val($ato, 'data_ato');
        $livro      = val($ato, 'livro');
        $folha      = val($ato, 'folha');
        $descricao  = val($ato, 'descricao');

        // Formata data se válida (Y-m-d)
        $dataAtoFmt = '';
        if ($dataAtoRaw !== '' && $dataAtoRaw !== '0000-00-00') {
            $dt = DateTime::createFromFormat('Y-m-d', $dataAtoRaw);
            if ($dt instanceof DateTime) {
                $dataAtoFmt = $dt->format('d/m/Y');
            }
        }

        // Monta as linhas da tabela condicionalmente
        $rows = '';
        if (has($termo))     { $rows .= '  <tr><td>ATO / TERMO Nº:</td><td>' . esc($termo) . '</td></tr>'; }
        if (has($protocolo)) { $rows .= '  <tr><td>PROTOCOLO Nº:</td><td>' . esc($protocolo) . '</td></tr>'; }
        if (has($matricula)) { $rows .= '  <tr><td>MATRICULA Nº:</td><td>' . esc($matricula) . '</td></tr>'; }
        if (has($categoria)) { $rows .= '  <tr><td>NATUREZA DO ATO:</td><td>' . esc($categoria) . '</td></tr>'; }
        if (has($dataAtoFmt)){ $rows .= '  <tr><td>DATA DO ATO:</td><td>' . esc($dataAtoFmt) . '</td></tr>'; }
        if (has($livro))     { $rows .= '  <tr><td>LIVRO Nº:</td><td>' . esc($livro) . '</td></tr>'; }
        if (has($folha))     { $rows .= '  <tr><td>FOLHA Nº:</td><td>' . esc($folha) . '</td></tr>'; }
        if (has($partesStr)) { $rows .= '  <tr><td>PARTES ENVOLVIDAS:</td><td>' . esc($partesStr) . '</td></tr>'; }
        if (has($descricao)) { $rows .= '  <tr><td>DESCRIÇÃO E DETALHES:</td><td>' . esc($descricao) . '</td></tr>'; }

        $pdf = new PDF();
        $pdf->SetMargins(25, 50, 25); // Definir as margens: esquerda, superior (ajustada), direita
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $html  = '';
        $html .= '<h1 style="text-align: center;">ARQUIVAMENTO</h1>';
        $html .= '<br>';

        // ATRIBUIÇÃO só aparece se houver valor
        if (has($atribuicao)) {
            $html .= '<h3>ATRIBUIÇÃO: ' . esc(mb_strtoupper($atribuicao)) . '</h3>';
        }

        // Tabela só aparece se houver ao menos uma linha
        if ($rows !== '') {
            $html .= '<table border="1" cellpadding="4">';
            $html .= $rows;
            $html .= '</table>';
        }

        $html .= '<br><br>';
        $html .= '<h3>SELOS DE ARQUIVAMENTO:</h3>';

        if (!empty($selos)) {
            // Exibe a QUANTIDADE TOTAL (soma de todas as quantidades)
            $html .= '<p><strong>Quantidade total:</strong> ' . $quantidade_total . '</p>';

            // Renderiza cada selo, um abaixo do outro
            foreach ($selos as $selo) {
                $qr   = $selo['qr_code'];
                $num  = $selo['numero_selo'];
                $txt  = $selo['texto_selo'];
                $func = $selo['escrevente'];

                $html .= '<div style="border: 1px solid black; width: 100mm; margin-bottom: 6mm;">';
                $html .= '  <table>';
                $html .= '    <tr>';
                $html .= '      <td style="width: 19%; vertical-align: middle;"><p></p><img style="width: 90px;" src="data:image/png;base64,' . $qr . '" alt="QR Code"></td>';
                $html .= '      <td style="width: 77%; padding-left: 10px;">';
                $html .= '        <p style="text-align: justify;font-size: 9px;"><strong style="text-align: center!important;font-size: 10px;">Poder Judiciário – TJMA<br>Selo: ' . esc($num) . '</strong><br>' . $txt . '</p>';
                $html .= '      </td>';
                $html .= '    </tr>';
                $html .= '  </table>';
                $html .= '  <table>';
                $html .= '    <tr>';
                $html .= '      <td>';
                $html .= '        <strong style="font-size: 10px;">Funcionário: ' . utf8_decode($func) . '</strong>';
                $html .= '      </td>';
                $html .= '    </tr>';
                $html .= '  </table>';
                $html .= '</div>';
            }
        } else {
            // Sem selos: reserva um espaço
            $html .= '<div style="border: 1px solid black; width: 100mm; height: 50mm;"><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p></div>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        ob_start();
        $pdf->Output('capa_de_arquivamento.pdf', 'I');
        ob_end_flush();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ato não encontrado']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>
