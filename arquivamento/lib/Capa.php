<?php
/**
 * Atlas · Arquivamento Digital
 * Capa de arquivamento em TCPDF.
 *
 * Mantém o layout original do módulo — inclusive os MOLDES DOS SELOS:
 * cada selo vinculado sai numa moldura de 100 mm com QR, texto e
 * funcionário, e quando não há selo emitido a capa reserva uma moldura
 * vazia de 100 x 50 mm para o selo ser afixado depois.
 *
 * Usada por:
 *   - capa_arquivamento.php        → capa avulsa (comportamento de sempre)
 *   - compilar.php?formato=capa    → mesma capa + índice de folhas do dossiê
 */

/** Localiza a biblioteca TCPDF já instalada no Atlas. */
function arq_carregar_tcpdf()
{
    if (class_exists('TCPDF')) { return true; }
    $candidatos = [
        dirname(__DIR__) . '/../oficios/tcpdf/tcpdf.php',
        dirname(__DIR__) . '/../tcpdf/tcpdf.php',
        dirname(__DIR__) . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        dirname(__DIR__) . '/tcpdf/tcpdf.php',
    ];
    foreach ($candidatos as $c) {
        if (is_file($c)) { require_once $c; return true; }
    }
    return false;
}

/** O Atlas está configurado para papel timbrado? */
function arq_usa_timbrado()
{
    $cfg = @file_get_contents(dirname(__DIR__) . '/../style/configuracao.json');
    if ($cfg === false) { return false; }
    $cfg = json_decode($cfg, true);
    return is_array($cfg) && isset($cfg['timbrado']) && $cfg['timbrado'] === 'S';
}

function arq_capa_esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function arq_capa_tem($v)
{
    return trim((string) $v) !== '';
}

/**
 * Monta a capa.
 *
 * @param array $dossies    arquivamentos normalizados (com 'selos' e 'anexos')
 * @param array $documentos [{nome, tipo, paginas, tamanho_legivel}] — quando
 *                          preenchido, acrescenta o índice de folhas do dossiê
 * @param array $ids        ids envolvidos
 * @return string|false     bytes do PDF
 */
function arq_gerar_capa(array $dossies, array $documentos, array $ids)
{
    if (!arq_carregar_tcpdf()) { return false; }

    require_once __DIR__ . '/CapaPDF.php';

    $timbrado = arq_usa_timbrado();

    $pdf = new ArqCapaPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->arqTimbrado = $timbrado;
    $pdf->SetCreator('Atlas · Arquivamento Digital');
    $pdf->SetAuthor(arq_usuario_nome());
    $pdf->SetTitle('Capa de arquivamento ' . implode(', ', $ids));
    $pdf->setPrintHeader($timbrado);
    $pdf->setPrintFooter(false);
    // Margens do capa_arquivamento.php original.
    $pdf->SetMargins(25, 50, 25);
    $pdf->SetAutoPageBreak(true, 25);

    foreach ($dossies as $k => $d) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        /* ---- dados do ato, na ordem e com os rótulos de sempre ---- */
        $partes = [];
        foreach ($d['partes_envolvidas'] as $p) {
            if (arq_capa_tem($p['nome'])) { $partes[] = $p['nome']; }
        }
        $partesStr = implode('; ', $partes);

        $dataFmt = '';
        if (arq_capa_tem($d['data_ato']) && $d['data_ato'] !== '0000-00-00') {
            $dt = DateTime::createFromFormat('Y-m-d', $d['data_ato']);
            if ($dt instanceof DateTime) { $dataFmt = $dt->format('d/m/Y'); }
        }

        $linhas = [
            ['ATO / TERMO Nº:',       $d['termo']],
            ['PROTOCOLO Nº:',         $d['protocolo']],
            ['MATRICULA Nº:',         $d['matricula']],
            ['NATUREZA DO ATO:',      $d['categoria']],
            ['DATA DO ATO:',          $dataFmt],
            ['LIVRO Nº:',             $d['livro']],
            ['FOLHA Nº:',             $d['folha']],
            ['PARTES ENVOLVIDAS:',    $partesStr],
            ['DESCRIÇÃO E DETALHES:', $d['descricao']],
        ];
        $rows = '';
        foreach ($linhas as $l) {
            if (arq_capa_tem($l[1])) {
                $rows .= '<tr><td>' . arq_capa_esc($l[0]) . '</td><td>' . arq_capa_esc($l[1]) . '</td></tr>';
            }
        }

        $html  = '<h1 style="text-align: center;">ARQUIVAMENTO</h1><br>';
        if (arq_capa_tem($d['atribuicao'])) {
            $html .= '<h3>ATRIBUIÇÃO: ' . arq_capa_esc(mb_strtoupper($d['atribuicao'])) . '</h3>';
        }
        if ($rows !== '') {
            $html .= '<table border="1" cellpadding="4">' . $rows . '</table>';
        }

        /* ---- índice de folhas: só no dossiê compilado ---- */
        if ($documentos && $k === 0) {
            $html .= '<br><br><h3>ÍNDICE DOS DOCUMENTOS:</h3>';
            $html .= '<table border="1" cellpadding="4" style="font-size:9px;">'
                   . '<tr><td width="8%"><b>Nº</b></td><td width="52%"><b>DOCUMENTO</b></td>'
                   . '<td width="12%"><b>TIPO</b></td><td width="13%"><b>FOLHAS</b></td>'
                   . '<td width="15%"><b>TAMANHO</b></td></tr>';
            $n = 0; $atual = 1;
            foreach ($documentos as $doc) {
                $n++;
                $pgs = max(1, (int) (isset($doc['paginas']) ? $doc['paginas'] : 1));
                $de = $atual; $ate = $atual + $pgs - 1; $atual = $ate + 1;
                $html .= '<tr><td>' . $n . '</td>'
                       . '<td>' . arq_capa_esc(mb_substr((string) (isset($doc['nome']) ? $doc['nome'] : ''), 0, 70)) . '</td>'
                       . '<td>' . arq_capa_esc(mb_strtoupper((string) (isset($doc['tipo']) ? $doc['tipo'] : ''))) . '</td>'
                       . '<td>' . ($de === $ate ? $de : $de . ' a ' . $ate) . '</td>'
                       . '<td>' . arq_capa_esc(isset($doc['tamanho_legivel']) ? $doc['tamanho_legivel'] : '') . '</td></tr>';
            }
            $html .= '</table>';
            $html .= '<p style="font-size:9px;">Total: ' . $n . ' documento(s), ' . ($atual - 1)
                   . ' folha(s). A numeração refere-se ao corpo do dossiê, iniciando após esta capa.</p>';

            $fora = [];
            foreach ($dossies as $dd) {
                foreach ($dd['anexos'] as $a) {
                    $ok = in_array(mb_strtolower($a['ext']), ['pdf','jpg','jpeg','png','gif','webp','bmp'], true);
                    if (!$ok || !$a['disponivel']) {
                        $fora[] = $a['nome'] . (!$a['disponivel'] ? ' (indisponível)' : ' (formato não incorporável)');
                    }
                }
            }
            if ($fora) {
                $html .= '<p style="font-size:9px;"><b>Documentos não incorporados a este PDF:</b><br>'
                       . arq_capa_esc(implode(' · ', $fora))
                       . '<br>Use o download em ZIP para obtê-los no formato original.</p>';
            }
        }

        /* ---- SELOS: moldes preenchidos ou molde em branco ---- */
        $html .= '<br><br><h3>SELOS DE ARQUIVAMENTO:</h3>';

        $selos = isset($d['selos']) && is_array($d['selos']) ? $d['selos'] : [];

        if (!empty($selos)) {
            $quantidadeTotal = 0;
            foreach ($selos as $s) {
                $quantidadeTotal += (int) (isset($s['quantidade']) ? $s['quantidade'] : 0);
            }
            if ($quantidadeTotal > 0) {
                $html .= '<p><strong>Quantidade total:</strong> ' . $quantidadeTotal . '</p>';
            }

            foreach ($selos as $selo) {
                $qr   = isset($selo['qr_code']) ? $selo['qr_code'] : '';
                $num  = isset($selo['numero_selo']) ? $selo['numero_selo'] : '';
                $txt  = isset($selo['texto_selo']) ? $selo['texto_selo'] : '';
                $func = isset($selo['escrevente']) ? $selo['escrevente'] : '';

                $html .= '<div style="border: 1px solid black; width: 100mm; margin-bottom: 6mm;">';
                $html .= '<table><tr>';
                if ($qr !== '') {
                    $html .= '<td style="width: 19%; vertical-align: middle;"><p></p>'
                           . '<img style="width: 90px;" src="@' . $qr . '" alt="QR Code"></td>'
                           . '<td style="width: 77%; padding-left: 10px;">';
                } else {
                    $html .= '<td style="width: 96%;">';
                }
                $html .= '<p style="text-align: justify;font-size: 9px;">'
                       . '<strong style="text-align: center!important;font-size: 10px;">Poder Judiciário – TJMA<br>'
                       . 'Selo: ' . arq_capa_esc($num) . '</strong><br>' . $txt . '</p>';
                $html .= '</td></tr></table>';
                if (arq_capa_tem($func)) {
                    $html .= '<table><tr><td>'
                           . '<strong style="font-size: 10px;">Funcionário: ' . arq_capa_esc($func) . '</strong>'
                           . '</td></tr></table>';
                }
                $html .= '</div>';
            }
        } else {
            // Molde em branco, reservado para o selo ser afixado depois.
            $html .= '<div style="border: 1px solid black; width: 100mm; height: 50mm;">'
                   . '<p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p><p></p></div>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    return $pdf->Output('capa_de_arquivamento.pdf', 'S');
}
