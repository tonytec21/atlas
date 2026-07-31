<?php
/**
 * Atlas · Arquivamento Digital
 * Capa de arquivamento.
 *
 * Reescrito para desenhar a página diretamente com Cell/MultiCell/Rect, sem
 * usar Header() nem o parser de HTML do TCPDF. Motivo: o writeHTML depende do
 * estado de margens, quebra de página e tags aninhadas, e quando algo ali
 * falha o resultado é uma página em branco sem mensagem de erro. Desenhando
 * na mão, cada elemento tem posição e tamanho explícitos.
 *
 * Layout mantido igual ao original:
 *   ARQUIVAMENTO
 *   ATRIBUIÇÃO: ...
 *   tabela rótulo/valor com borda
 *   SELOS DE ARQUIVAMENTO:
 *     - uma moldura de 100 mm por selo (QR + texto + funcionário)
 *     - ou uma moldura vazia de 100 x 50 mm quando não há selo
 */

require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

/* ------------------------------------------------------------------ *
 * Entrada
 * ------------------------------------------------------------------ */
$id = isset($_GET['id']) ? preg_replace('/\D/', '', (string) $_GET['id']) : '';
if ($id === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Identificador não informado.');
}

$json = __DIR__ . '/meta-dados/' . $id . '.json';
if (!is_file($json)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Arquivamento não encontrado.');
}

$ato = json_decode((string) file_get_contents($json), true);
if (!is_array($ato)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Arquivo de metadados ilegível.');
}

/* ------------------------------------------------------------------ *
 * Dados
 * ------------------------------------------------------------------ */
function capa_txt($arr, $chave)
{
    return isset($arr[$chave]) ? trim((string) $arr[$chave]) : '';
}

/**
 * Cor da atribuição — exatamente as mesmas da lombada colorida dos cards no
 * acervo (assets/css/arquivamento.css). Devolve [R, G, B].
 */
function capa_cor_atribuicao($atribuicao)
{
    $cores = [
        'Registro Civil'                        => [0xB0, 0x32, 0x2F],
        'Registro de Imóveis'                   => [0x0E, 0x7C, 0x86],
        'Registro de Títulos e Documentos'      => [0x4C, 0x5F, 0xBF],
        'Registro Civil das Pessoas Jurídicas'  => [0x16, 0x7D, 0x53],
        'Notas'                                 => [0xB4, 0x66, 0x1A],
        'Protesto'                              => [0x7A, 0x4B, 0xA8],
        'Contratos Marítimos'                   => [0x1F, 0x6F, 0xB2],
    ];
    return isset($cores[$atribuicao]) ? $cores[$atribuicao] : [0x5F, 0x71, 0x78];
}

$partes = [];
if (isset($ato['partes_envolvidas']) && is_array($ato['partes_envolvidas'])) {
    foreach ($ato['partes_envolvidas'] as $p) {
        if (is_array($p) && !empty($p['nome'])) { $partes[] = trim((string) $p['nome']); }
    }
}

$dataFmt = '';
$dataRaw = capa_txt($ato, 'data_ato');
if ($dataRaw !== '' && $dataRaw !== '0000-00-00') {
    $dt = DateTime::createFromFormat('Y-m-d', $dataRaw);
    if ($dt instanceof DateTime) { $dataFmt = $dt->format('d/m/Y'); }
}

// Só entram na tabela as linhas que têm valor — igual ao original.
$linhas = [];
foreach ([
    ['ATO / TERMO Nº:',       capa_txt($ato, 'termo')],
    ['PROTOCOLO Nº:',         capa_txt($ato, 'protocolo')],
    ['MATRICULA Nº:',         capa_txt($ato, 'matricula')],
    ['NATUREZA DO ATO:',      capa_txt($ato, 'categoria')],
    ['DATA DO ATO:',          $dataFmt],
    ['LIVRO Nº:',             capa_txt($ato, 'livro')],
    ['FOLHA Nº:',             capa_txt($ato, 'folha')],
    ['PARTES ENVOLVIDAS:',    implode('; ', $partes)],
    ['DESCRIÇÃO E DETALHES:', capa_txt($ato, 'descricao')],
] as $l) {
    if ($l[1] !== '') { $linhas[] = $l; }
}

/* Selos — via PDO, que já degrada sozinho se o banco estiver fora. */
$selos = [];
$pdo = arq_db();
if ($pdo) {
    try {
        $st = $pdo->prepare(
            'SELECT s.numero_selo, s.texto_selo, s.qr_code, s.escrevente, s.quantidade
               FROM selos_arquivamentos sa
         INNER JOIN selos s ON s.id = sa.selo_id
              WHERE sa.arquivo_id = :id
           ORDER BY s.id ASC'
        );
        $st->execute([':id' => $id]);
        $selos = $st->fetchAll();
    } catch (PDOException $e) {
        error_log('[arquivamento] capa: falha ao ler selos — ' . $e->getMessage());
    }
}

$quantidadeTotal = 0;
foreach ($selos as $s) { $quantidadeTotal += (int) (isset($s['quantidade']) ? $s['quantidade'] : 0); }

/* ------------------------------------------------------------------ *
 * TCPDF
 * ------------------------------------------------------------------ */
$tcpdf = __DIR__ . '/../oficios/tcpdf/tcpdf.php';
if (!is_file($tcpdf)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Biblioteca TCPDF não encontrada em ' . $tcpdf);
}
require_once $tcpdf;

$timbradoLigado = false;
$cfg = @file_get_contents(__DIR__ . '/../style/configuracao.json');
if ($cfg !== false) {
    $cfg = json_decode($cfg, true);
    $timbradoLigado = is_array($cfg) && isset($cfg['timbrado']) && $cfg['timbrado'] === 'S';
}
$timbrado = __DIR__ . '/../style/img/timbrado.png';
$usarTimbrado = $timbradoLigado && is_file($timbrado);

$ESQ    = 25;   // margem esquerda
$DIR    = 25;   // margem direita
$LARG   = 210 - $ESQ - $DIR;         // largura útil: 160 mm
$TOPO   = $usarTimbrado ? 40 : 22;   // onde o conteúdo começa
$RODAPE = $usarTimbrado ? 30 : 18;   // espaço reservado no pé

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Atlas');
$pdf->SetAuthor(arq_usuario_nome());
$pdf->SetTitle('Capa de arquivamento ' . $id);

// Sem cabeçalho e rodapé automáticos: o timbrado é desenhado à mão em cada
// página, o que evita depender do ciclo de vida do Header().
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins($ESQ, $TOPO, $DIR);
$pdf->SetAutoPageBreak(true, $RODAPE);

/** Abre uma página nova já com o timbrado desenhado por baixo. */
function capa_nova_pagina($pdf, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE)
{
    $pdf->AddPage();

    if ($usarTimbrado) {
        // A quebra automática precisa estar DESLIGADA aqui. Com ela ligada, o
        // TCPDF vê uma imagem de 297 mm começando em y=0, conclui que não cabe
        // na área útil e reduz/reposiciona a imagem — era por isso que o
        // timbrado não cobria a folha inteira.
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->Image(
            $timbrado, 0, 0, 210, 297, 'PNG', '', '', false, 300, '',
            false, false, 0, false, false, false
        );
        $pdf->SetAutoPageBreak(true, $RODAPE);

        // Marca este ponto como início do conteúdo: tudo que vier depois é
        // escrito por cima da imagem, nunca por baixo.
        $pdf->setPageMark();
    }

    $pdf->SetXY($ESQ, $TOPO);
}

/** Garante espaço; se não houver, abre página nova com timbrado. */
function capa_espaco($pdf, $altura, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE)
{
    if ($pdf->GetY() + $altura > 297 - $RODAPE) {
        capa_nova_pagina($pdf, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);
    }
}

capa_nova_pagina($pdf, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);

$atribuicao = capa_txt($ato, 'atribuicao');
$cor        = capa_cor_atribuicao($atribuicao);

/* ---- Título ---- */
$pdf->SetFont('helvetica', 'B', 17);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($LARG, 8, 'ARQUIVAMENTO', 0, 1, 'C');

// Número do arquivamento, discreto, só para identificação.
$pdf->SetFont('helvetica', '', 9.5);
$pdf->SetTextColor(0x5F, 0x71, 0x78);
$pdf->Cell($LARG, 4.5, 'Nº ' . $id, 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// Filete na cor da atribuição — o mesmo código de cor da lombada dos cards.
$yFilete = $pdf->GetY() + 1.5;
$pdf->SetFillColor($cor[0], $cor[1], $cor[2]);
$pdf->Rect(($ESQ + $LARG / 2) - 18, $yFilete, 36, 1.1, 'F');
$pdf->SetXY($ESQ, $yFilete + 1.1);
$pdf->Ln(7);

/* ---- Atribuição ---- */
if ($atribuicao !== '') {
    $y = $pdf->GetY();
    // Barra vertical à esquerda, como a lombada da ficha no acervo.
    $pdf->SetFillColor($cor[0], $cor[1], $cor[2]);
    $pdf->Rect($ESQ, $y, 2.6, 6.5, 'F');

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY($ESQ + 5, $y);
    $pdf->Cell($LARG - 5, 6.5, 'ATRIBUIÇÃO: ' . mb_strtoupper($atribuicao, 'UTF-8'), 0, 1, 'L');
    $pdf->SetXY($ESQ, $y + 6.5);
    $pdf->Ln(4);
}

/* ---- Tabela do ato ----
   Desenhada linha a linha: a altura de cada uma vem do número de linhas que
   o valor ocupa, então nada estoura nem sobrepõe. */
if ($linhas) {
    $colRotulo = 55;
    $colValor  = $LARG - $colRotulo;

    foreach ($linhas as $l) {
        $pdf->SetFont('helvetica', '', 10);

        $nLinhas = max(
            $pdf->getNumLines($l[0], $colRotulo),
            $pdf->getNumLines($l[1], $colValor)
        );
        $altura = max(6.8, $nLinhas * 5.0 + 1.8);

        capa_espaco($pdf, $altura, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);

        $y = $pdf->GetY();

        // As bordas saem de Rect, e não do parâmetro border do MultiCell: o
        // MultiCell desenha a moldura na posição interna dele, que com
        // alinhamento vertical e altura fixa não coincide com a da célula.
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($ESQ, $y, $colRotulo, $altura);
        $pdf->Rect($ESQ + $colRotulo, $y, $colValor, $altura);

        $pdf->MultiCell($colRotulo, $altura, $l[0], 0, 'L', false, 0,
                        $ESQ + 1.5, $y, true, 0, false, true, $altura, 'M');
        $pdf->MultiCell($colValor, $altura, $l[1], 0, 'L', false, 0,
                        $ESQ + $colRotulo + 1.5, $y, true, 0, false, true, $altura, 'M');

        $pdf->SetXY($ESQ, $y + $altura);
    }
}

$pdf->Ln(7);

/* ---- Selos ---- */
capa_espaco($pdf, 20, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell($LARG, 6.5, 'SELOS DE ARQUIVAMENTO:', 0, 1, 'L');
$pdf->Ln(2.5);

$LARG_SELO = $LARG;   // mesma largura da tabela do ato (160 mm)

if ($selos) {
    if ($quantidadeTotal > 0) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell($LARG, 5.5, 'Quantidade total: ' . $quantidadeTotal, 0, 1, 'L');
        $pdf->Ln(2.5);
    }

    foreach ($selos as $selo) {
        $numero = trim((string) (isset($selo['numero_selo']) ? $selo['numero_selo'] : ''));
        $texto  = trim((string) (isset($selo['texto_selo']) ? $selo['texto_selo'] : ''));
        $func   = trim((string) (isset($selo['escrevente']) ? $selo['escrevente'] : ''));

        // O QR só é usado se for base64 de um PNG de verdade. Com a string
        // vazia ou corrompida, o TCPDF abortava o documento inteiro.
        $qrBin = '';
        if (!empty($selo['qr_code'])) {
            $bin = base64_decode((string) $selo['qr_code'], true);
            if ($bin !== false && strlen($bin) > 32
                && (strncmp($bin, "\x89PNG", 4) === 0 || strncmp($bin, "\xFF\xD8\xFF", 3) === 0)) {
                $qrBin = $bin;
            }
        }

        /* --- Medidas da moldura ---
           O QR fica encostado à esquerda e o texto começa logo depois dele,
           sem o vão que sobrava antes. A altura sai do conteúdo real, então
           não fica espaço morto no meio da caixa. */
        $PAD      = 4;      // respiro interno da moldura
        $ladoQr   = $qrBin !== '' ? 28 : 0;
        $recuoTxt = $qrBin !== '' ? $PAD + $ladoQr + 4 : $PAD;
        $largTxt  = $LARG_SELO - $recuoTxt - $PAD;

        $ALT_CAB  = 4.4;    // altura de linha do cabeçalho do selo
        $ALT_TXT  = 4.0;    // altura de linha do corpo

        $pdf->SetFont('helvetica', 'B', 9);
        $alturaCabecalho = $pdf->getNumLines("Poder Judiciário – TJMA\nSelo: " . $numero, $largTxt) * $ALT_CAB;

        $pdf->SetFont('helvetica', '', 8.5);
        $alturaTexto = $pdf->getNumLines($texto, $largTxt) * $ALT_TXT;

        $alturaCorpo = max($ladoQr, $alturaCabecalho + $alturaTexto + 1);
        $alturaFunc  = $func !== '' ? 6 : 0;
        $alturaTotal = $PAD + $alturaCorpo + $alturaFunc + $PAD;

        capa_espaco($pdf, $alturaTotal + 4, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);

        $x = $ESQ;
        $y = $pdf->GetY();

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $LARG_SELO, $alturaTotal);

        if ($qrBin !== '') {
            // Centralizado na altura do corpo, para não ficar preso ao topo.
            $yQr = $y + $PAD + max(0, ($alturaCorpo - $ladoQr) / 2);
            try {
                $pdf->Image('@' . $qrBin, $x + $PAD, $yQr, $ladoQr, $ladoQr, '', '', '', false, 300);
            } catch (Exception $e) {
                error_log('[arquivamento] capa: QR do selo ' . $numero . ' não pôde ser desenhado.');
            }
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($x + $recuoTxt, $y + $PAD);
        $pdf->MultiCell($largTxt, $ALT_CAB, "Poder Judiciário – TJMA\nSelo: " . $numero, 0, 'C', false, 1);

        // Alinhado à esquerda, e não justificado: a justificação espalhava a
        // última linha de ponta a ponta, deixando vãos enormes entre palavras.
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetX($x + $recuoTxt);
        $pdf->MultiCell($largTxt, $ALT_TXT, $texto, 0, 'L', false, 1);

        if ($func !== '') {
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->SetXY($x + $PAD, $y + $alturaTotal - $PAD - 4.5);
            $pdf->Cell($LARG_SELO - 2 * $PAD, 4.5, 'Funcionário: ' . $func, 0, 0, 'L');
        }

        $pdf->SetXY($ESQ, $y + $alturaTotal + 4.5);
    }
} else {
    // Sem selo: moldura em branco de 100 x 50 mm, para ser preenchida depois.
    capa_espaco($pdf, 54, $usarTimbrado, $timbrado, $ESQ, $TOPO, $RODAPE);
    $y = $pdf->GetY();
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect($ESQ, $y, $LARG_SELO, 50);
    $pdf->SetXY($ESQ, $y + 54);
}

/* ------------------------------------------------------------------ *
 * Saída
 * ------------------------------------------------------------------ */
arq_auditar('capa', $id);

while (ob_get_level() > 0) { ob_end_clean(); }
$pdf->Output('capa_de_arquivamento_' . $id . '.pdf', 'I');
