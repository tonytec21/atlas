<?php
/**
 * atlas/kb/checklist_pdf.php
 * Gera um impresso de conferencia em PDF a partir de uma resposta da Aria.
 *
 * Nao e "imprimir a tela": extrai apenas os itens de checklist da mensagem,
 * troca as citacoes [n] pela norma correspondente e monta um formulario de
 * balcao -- quadros para marcar, campos de identificacao, observacoes e
 * assinaturas.
 *
 * Usa o mesmo timbrado e a mesma geometria do modulo de oficios.
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

date_default_timezone_set('America/Sao_Paulo');

// ---------------------------------------------------------------------------
// Dependencias externas do Atlas
// ---------------------------------------------------------------------------

/** TCPDF: o modulo de oficios ja traz uma copia. */
function kbAcharTcpdf()
{
    $candidatos = array(
        __DIR__ . '/../oficios/tcpdf/tcpdf.php',
        __DIR__ . '/../tcpdf/tcpdf.php',
        __DIR__ . '/../TCPDF/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../includes/tcpdf/tcpdf.php',
        __DIR__ . '/../lib/tcpdf/tcpdf.php',
    );
    foreach ($candidatos as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    $raiz = realpath(__DIR__ . '/..');
    foreach (array('/*', '/*/*') as $nivel) {
        foreach (glob($raiz . $nivel . '/tcpdf.php') as $achado) {
            return $achado;
        }
    }
    return null;
}

/** Papel timbrado: mesma imagem usada pelos oficios. */
function kbAcharTimbrado()
{
    $candidatos = array(
        __DIR__ . '/../style/img/timbrado.png',
        __DIR__ . '/../style/img/timbrado.jpg',
        __DIR__ . '/../oficios/img/timbrado.png',
        __DIR__ . '/../img/timbrado.png',
    );
    foreach ($candidatos as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Extracao dos itens
// ---------------------------------------------------------------------------
function kbExtrairChecklist($markdown, array $mapaFontes)
{
    $secoes = array();
    $atual  = array('titulo' => null, 'itens' => array());
    $achou  = false;

    foreach (preg_split('/\r?\n/', $markdown) as $linha) {

        if (preg_match('/^\s*#{1,3}\s+(.+?)\s*$/u', $linha, $m)) {
            if ($atual['itens']) {
                $secoes[] = $atual;
            }
            $atual = array('titulo' => kbLimparInline($m[1], false), 'itens' => array());
            continue;
        }

        if (preg_match('/^([ \t]*)[-*]\s*\[([ xX])\]\s*(.+)$/u', $linha, $m)) {
            $achou  = true;
            $indent = strlen(str_replace("\t", '    ', $m[1]));
            $texto  = trim($m[3]);

            $rotulo = null;
            if (preg_match('/^\*\*(.+?)\*\*\s*:?\s*(.*)$/u', $texto, $mm)) {
                $rotulo = trim($mm[1]);
                $texto  = trim($mm[2]);
            }

            // Citacao [n] vira a norma por extenso: no papel, "[8]" nao serve.
            $refs = array();
            // O modelo cita em grupo: "[2, 7]". Tratar so "[n]" perdia o
            // fundamento e ainda deixava o colchete no texto impresso.
            if (preg_match_all('/\[(\d+(?:\s*,\s*\d+)*)\]/',
                               $texto . ' ' . (string) $rotulo, $mc)) {
                foreach ($mc[1] as $grupo) {
                    foreach (preg_split('/\s*,\s*/', $grupo) as $n) {
                        $n = trim($n);
                        if (isset($mapaFontes[$n]) && !in_array($mapaFontes[$n], $refs, true)) {
                            $refs[] = $mapaFontes[$n];
                        }
                    }
                }
            }

            $atual['itens'][] = array(
                'rotulo' => $rotulo ? kbLimparInline($rotulo, false) : null,
                'texto'  => kbLimparInline($texto, true),
                'refs'   => $refs,
                'feito'  => strtolower($m[2]) === 'x',
                'indent' => $indent,
                'grupo'  => false,
            );
        }
    }
    if ($atual['itens']) {
        $secoes[] = $atual;
    }

    // Item seguido por outro mais indentado e agrupador, nao tarefa:
    // no impresso ele vira rotulo de secao, sem quadro para marcar.
    foreach ($secoes as $si => $sec) {
        $qtd = count($sec['itens']);
        for ($i = 0; $i < $qtd - 1; $i++) {
            if ($sec['itens'][$i + 1]['indent'] > $sec['itens'][$i]['indent']) {
                $secoes[$si]['itens'][$i]['grupo'] = true;
            }
        }
    }

    return $achou ? $secoes : array();
}

/**
 * Remove marcacao inline e citacoes.
 * $frase = true fecha com ponto (corpo do item); false remove pontuacao
 * terminal (titulo de secao e rotulo em negrito).
 */
function kbLimparInline($t, $frase = true)
{
    $t = preg_replace('/\[\d+(?:\s*,\s*\d+)*\]/', '', $t);
    $t = preg_replace('/\*\*(.+?)\*\*/u', '$1', $t);
    $t = preg_replace('/(^|\s)\*([^*]+)\*/u', '$1$2', $t);
    $t = preg_replace('/`([^`]+)`/u', '$1', $t);
    $t = preg_replace('/\s{2,}/u', ' ', $t);
    $t = trim($t);

    if (!$frase) {
        return rtrim($t, " .:;,\xc2\xa0");
    }
    if ($t === '') {
        return '';
    }
    return preg_match('/[.:;!?]$/u', $t) ? $t : $t . '.';
}

// ---------------------------------------------------------------------------
try {
    $conn = getDatabaseConnection();

    $msgId = isset($_GET['mensagem_id']) ? (int) $_GET['mensagem_id'] : 0;
    $st = $conn->prepare(
        "SELECT m.* FROM kb_mensagens m
           JOIN kb_conversas c ON c.id = m.conversa_id
          WHERE m.id = :id AND m.papel = 'assistant'"
    );
    $st->execute(array(':id' => $msgId));
    $msg = $st->fetch(PDO::FETCH_ASSOC);
    if (!$msg) {
        throw new RuntimeException('Resposta não encontrada.');
    }

    // Mapa [n] -> "Provimento n. 89/2019 CNJ, Art. 176"
    $mapa = array();
    if ($msg['fontes']) {
        foreach ((array) json_decode($msg['fontes'], true) as $f) {
            if (isset($f['n'])) {
                $mapa[(string) $f['n']] = trim($f['provimento'] . ' ' . $f['origem']
                    . (empty($f['referencia']) ? '' : ', ' . $f['referencia']));
            }
        }
    }

    $secoes = kbExtrairChecklist($msg['conteudo'], $mapa);
    if (!$secoes) {
        throw new RuntimeException('Esta resposta não contém itens de checklist.');
    }

    $arqTcpdf = kbAcharTcpdf();
    if (!$arqTcpdf) {
        throw new RuntimeException('TCPDF não localizado. Ajuste kbAcharTcpdf() em kb/checklist_pdf.php.');
    }
    require_once $arqTcpdf;

    $titulo = trim(isset($_GET['titulo']) ? $_GET['titulo'] : '');
    if ($titulo === '') {
        foreach ($secoes as $s) {
            if ($s['titulo']) { $titulo = $s['titulo']; break; }
        }
    }
    if ($titulo === '') {
        $titulo = 'Checklist de conferência';
    }

    $manterMarcas = !empty($_GET['marcados']);
    $usarTimbrado = !isset($_GET['timbrado']) || $_GET['timbrado'] !== '0';
    $timbrado     = $usarTimbrado ? kbAcharTimbrado() : null;

    // -----------------------------------------------------------------------
    // Geometria alinhada ao modulo de oficios:
    //   com timbrado  -> margens 25 / 45 / 25, quebra a 25 do rodape
    //   sem timbrado  -> margens 20 / 20 / 20
    // -----------------------------------------------------------------------
    $ME = $usarTimbrado ? 25 : 20;   // margem esquerda
    $MT = $usarTimbrado ? 40 : 20;   // margem superior (4 cm com timbrado)
    $MB = $usarTimbrado ? 25 : 18;   // margem inferior
    $W  = 210 - ($ME * 2);           // largura util

    class ChecklistPDF extends TCPDF
    {
        public $timbrado = null;
        public $me = 25, $mt = 40, $mb = 25;

        public function Header()
        {
            if (!$this->timbrado) {
                return;
            }
            // Mesma abordagem dos oficios: imagem ocupando a folha A4 inteira.
            $this->SetAutoPageBreak(false, 0);
            $this->SetMargins(0, 0, 0);
            $this->Image($this->timbrado, 0, 0, 210, 297, '', '', '', false, 300,
                         '', false, false, 0, false, false, false);
            $this->SetAutoPageBreak(true, $this->mb);
            $this->SetMargins($this->me, $this->mt, $this->me);
            $this->SetY($this->mt);
        }

        // Sem Footer() aqui: a paginacao e carimbada no final, quando o
        // total de paginas ja e um numero conhecido. Usar os marcadores
        // do TCPDF ({nb}) desalinha o texto a direita, porque a largura
        // e calculada com o marcador e so depois substituida.
    }

    $pdf = new ChecklistPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->timbrado = $timbrado;
    $pdf->me = $ME;
    $pdf->mt = $MT;
    $pdf->mb = $MB;

    $pdf->SetCreator('Atlas / Aria');
    $pdf->SetAuthor(isset($_SESSION['username']) ? $_SESSION['username'] : 'Atlas');
    $pdf->SetTitle($titulo);
    $pdf->SetMargins($ME, $MT, $ME);
    $pdf->SetAutoPageBreak(true, $MB);
    $pdf->setPrintHeader((bool) $timbrado);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    if (!$timbrado) {
        $pdf->SetFillColor(15, 111, 119);
        $pdf->Rect($ME, $MT - 4, $W, 1.2, 'F');
    }

    // ---- Titulo ----
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(20, 40, 45);
    $pdf->MultiCell($W, 7, $titulo, 0, 'C');

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(125, 135, 145);
    $pdf->MultiCell($W, 4,
        'Roteiro de conferência gerado a partir do acervo normativo. Documento de apoio: '
        . 'a responsabilidade pelo ato é do profissional.', 0, 'C');
    $pdf->Ln(4);

    // ---- Identificacao ----
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(60, 70, 80);
    $meia = $W / 2;
    $pdf->Cell($meia, 7, 'Protocolo / Ato: ' . str_repeat('_', 22), 0, 0, 'L');
    $pdf->Cell($meia, 7, 'Data: ___/___/______', 0, 1, 'L');
    $pdf->Cell($meia, 7, 'Interessado: ' . str_repeat('_', 25), 0, 0, 'L');
    $pdf->Cell($meia, 7, 'Conferente: ' . str_repeat('_', 20), 0, 1, 'L');
    $pdf->Ln(1);
    $pdf->SetDrawColor(200, 210, 216);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($ME, $pdf->GetY(), $ME + $W, $pdf->GetY());
    $pdf->Ln(4);

    // ---- Itens ----
    $limiteY = 297 - $MB - 22;   // margem para o item nao ficar partido
    $xTexto  = $ME + 7.5;
    $wTexto  = $W - 7.5;
    $n = 0;

    // O titulo do impresso costuma vir do primeiro cabecalho; nao repetir.
    $norm = function ($s) {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $s, 'UTF-8'));
    };
    $tituloNorm = $norm($titulo);

    foreach ($secoes as $sec) {
        if ($sec['titulo'] && count($secoes) > 1 && $norm($sec['titulo']) !== $tituloNorm) {
            if ($pdf->GetY() > $limiteY - 10) {
                $pdf->AddPage();
            }
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 9.5);
            $pdf->SetTextColor(15, 111, 119);
            $pdf->SetX($ME);
            $pdf->MultiCell($W, 5, mb_strtoupper($sec['titulo'], 'UTF-8'), 0, 'L');
            $pdf->Ln(0.5);
        }

        foreach ($sec['itens'] as $item) {
            if (!$item['grupo']) { $n++; }
            if ($pdf->GetY() > $limiteY) {
                $pdf->AddPage();
            }
            $yi = $pdf->GetY();

            // Filhos recuam 6 mm; agrupador fica alinhado a esquerda.
            $rec = ($item['indent'] > 0) ? 6.0 : 0.0;
            $xi  = $xTexto + $rec;
            $wi  = $wTexto - $rec;

            if ($item['grupo']) {
                // Rotulo de secao: sem quadro, porque marcar um titulo nao
                // significa nada na conferencia.
                $pdf->Ln(1);
                $pdf->SetFont('helvetica', 'B', 9.5);
                $pdf->SetTextColor(15, 111, 119);
                $rotG = $item['rotulo'] ? $item['rotulo'] : rtrim($item['texto'], '.');
                $pdf->MultiCell($W, 4.8, $rotG, 0, 'L', false, 1, $ME, $pdf->GetY());
                if ($item['rotulo'] && $item['texto'] !== '') {
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(70, 82, 92);
                    $pdf->MultiCell($W, 4.4, $item['texto'], 0, 'L', false, 1, $ME);
                }
                $pdf->Ln(1);
                continue;
            }

            // Quadro de marcacao
            $pdf->SetDrawColor(90, 110, 120);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($ME + 0.5 + $rec, $yi + 1.1, 4.2, 4.2);
            if ($manterMarcas && $item['feito']) {
                // Em Text() o $y e o TOPO do texto, nao a linha de base.
                // O quadro vai de $yi+1.1 a $yi+5.3 (4.2 mm); a 8 pt o glifo
                // ocupa ~3.5 mm, entao 1.4 mm centraliza dentro dele.
                $pdf->SetFont('zapfdingbats', '', 8);
                $pdf->SetTextColor(15, 111, 119);
                $pdf->Text($ME + 0.9 + $rec, $yi + 1.4, '4');
            }

            if ($item['rotulo']) {
                $pdf->SetFont('helvetica', 'B', 9.5);
                $pdf->SetTextColor(30, 45, 55);
                $pdf->MultiCell($wi, 4.6, $item['rotulo'], 0, 'L', false, 1, $xi, $yi);
            }
            $pdf->SetFont('helvetica', '', 9.5);
            $pdf->SetTextColor(45, 55, 65);
            $pdf->MultiCell($wi, 4.6, $item['texto'], 0, 'L', false, 1,
                            $xi, $item['rotulo'] ? null : $yi);

            if ($item['refs']) {
                $pdf->SetFont('helvetica', 'I', 7.6);
                $pdf->SetTextColor(125, 138, 148);
                $pdf->MultiCell($wi, 3.8, 'Fundamento: ' . implode(' | ', $item['refs']),
                                0, 'L', false, 1, $xi);
            }
            $pdf->Ln(2.5);
        }
    }

    // ---- Observacoes e assinaturas ----
    if ($pdf->GetY() > $limiteY - 42) {
        $pdf->AddPage();
    }
    $pdf->Ln(2);
    $pdf->SetX($ME);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(15, 111, 119);
    $pdf->Cell($W, 5, 'OBSERVAÇÕES', 0, 1, 'L');

    $pdf->SetDrawColor(205, 214, 220);
    $pdf->SetLineWidth(0.15);
    for ($i = 0; $i < 4; $i++) {
        $yl = $pdf->GetY() + 6;
        $pdf->Line($ME, $yl, $ME + $W, $yl);
        $pdf->Ln(6);
    }

    $pdf->Ln(10);
    $pdf->SetDrawColor(90, 110, 120);
    $pdf->SetLineWidth(0.25);
    $yA = $pdf->GetY();
    $pdf->Line($ME + 8, $yA, $ME + ($W / 2) - 8, $yA);
    $pdf->Line($ME + ($W / 2) + 8, $yA, $ME + $W - 8, $yA);
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 130, 140);
    $pdf->SetX($ME);
    $pdf->Cell($W / 2, 4, 'Conferente', 0, 0, 'C');
    $pdf->Cell($W / 2, 4, 'Responsável pelo ato', 0, 1, 'C');

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(155, 165, 175);
    $pdf->SetX($ME);
    $pdf->Cell($W, 4, $n . ' item(ns) · Gerado em ' . date('d/m/Y H:i')
        . ' por ' . (isset($_SESSION['username']) ? $_SESSION['username'] : '-')
        . ' · Atlas/Aria', 0, 1, 'C');

    // ---- Paginacao: canto inferior direito, encostada na margem ----
    $totalPag = $pdf->getNumPages();
    for ($p = 1; $p <= $totalPag; $p++) {
        $pdf->setPage($p);
        // setPage() religa o AutoPageBreak. Sem desligar AQUI DENTRO, escrever
        // a 12 mm do fim da folha (margem inferior de 25 mm) dispara quebra e o
        // rodape vai parar no topo de uma pagina nova, em cascata.
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetY(-12);
        $pdf->SetX($ME);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(150, 160, 170);
        $pdf->Cell($W, 4, 'Página ' . $p . ' de ' . $totalPag, 0, 0, 'R');
    }

    $nome = 'checklist-' . preg_replace('/[^a-z0-9]+/i', '-',
        mb_strtolower(mb_substr($titulo, 0, 40), 'UTF-8')) . '.pdf';
    $pdf->Output($nome, 'I');

} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8">'
       . '<div style="font-family:sans-serif;padding:40px;max-width:560px;margin:auto">'
       . '<h3>Não foi possível gerar o impresso</h3><p>'
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</p><p><a href="javascript:history.back()">Voltar</a></p></div>';
}
