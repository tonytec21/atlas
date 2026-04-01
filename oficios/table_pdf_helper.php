<?php
/**
 * Atlas - Table PDF Helper
 * Normaliza HTML de tabelas geradas pelo CKEditor para renderização perfeita no TCPDF
 */

/**
 * Normaliza o HTML de tabelas para compatibilidade com TCPDF
 * - Converte larguras em px para porcentagem
 * - Garante border, cellpadding, cellspacing
 * - Adiciona estilos inline para cada célula
 * - Remove classes CSS que o TCPDF não entende
 * 
 * @param string $html O HTML completo da tabela
 * @param float $contentWidthMm Largura da área de conteúdo em mm (padrão: 160mm para A4 com margens de 25mm)
 * @return string HTML normalizado
 */
function normalizeTableForPdf($html, $contentWidthMm = 160) {
    if (empty($html) || stripos($html, '<table') === false) {
        return $html;
    }
    
    // Processar cada tabela individualmente
    $html = preg_replace_callback(
        '/<table(.*?)>(.*?)<\/table>/is',
        function($match) use ($contentWidthMm) {
            return processTableForPdf($match[0], $contentWidthMm);
        },
        $html
    );
    
    return $html;
}

/**
 * Processa uma tabela individual para TCPDF
 */
function processTableForPdf($tableHtml, $contentWidthMm = 160) {
    // Extrair largura total da tabela em px se existir (referência para cálculos)
    $tableWidthPx = 0;
    if (preg_match('/width:\s*(\d+(?:\.\d+)?)px/i', $tableHtml, $m)) {
        $tableWidthPx = floatval($m[1]);
    }
    
    // Se não encontrou largura em px, tenta pelo atributo width
    if ($tableWidthPx == 0 && preg_match('/<table[^>]*\bwidth\s*=\s*["\']?(\d+)/i', $tableHtml, $m)) {
        $tableWidthPx = floatval($m[1]);
    }
    
    // Fallback padrão
    if ($tableWidthPx == 0) $tableWidthPx = 640;
    
    // === NORMALIZAR TAG <table> ===
    
    // Remover atributos class (TCPDF não usa)
    $tableHtml = preg_replace('/(<table[^>]*)\s+class\s*=\s*["\'][^"\']*["\']/i', '$1', $tableHtml);
    
    // Garantir atributos essenciais na tag <table>
    // Remover border existente e recriar
    $tableHtml = preg_replace('/(<table[^>]*)\s+border\s*=\s*["\']?\d+["\']?/i', '$1', $tableHtml);
    $tableHtml = preg_replace('/<table/i', '<table border="1"', $tableHtml, 1);
    
    // Remover cellpadding/cellspacing existentes e recriar
    $tableHtml = preg_replace('/(<table[^>]*)\s+cellpadding\s*=\s*["\']?\d+["\']?/i', '$1', $tableHtml);
    $tableHtml = preg_replace('/(<table[^>]*)\s+cellspacing\s*=\s*["\']?\d+["\']?/i', '$1', $tableHtml);
    $tableHtml = preg_replace('/<table\s+border="1"/i', '<table border="1" cellpadding="4" cellspacing="0"', $tableHtml, 1);
    
    // Normalizar style da tabela - forçar width:100% e border-collapse
    $tableHtml = preg_replace_callback(
        '/<table([^>]*)>/i',
        function($m) {
            $attrs = $m[1];
            // Remover style existente
            $attrs = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
            return '<table' . $attrs . ' style="width: 100%; border-collapse: collapse;">';
        },
        $tableHtml,
        1
    );
    
    // === NORMALIZAR CÉLULAS <td> e <th> ===
    
    $tableHtml = preg_replace_callback(
        '/<(td|th)([^>]*)>/i',
        function($match) use ($tableWidthPx) {
            $tag = $match[1];
            $attrs = $match[2];
            
            // Extrair style existente
            $existingStyle = '';
            if (preg_match('/style\s*=\s*["\']([^"\']*?)["\']/i', $attrs, $sm)) {
                $existingStyle = $sm[1];
            }
            
            // Converter largura px para %
            if (preg_match('/width:\s*(\d+(?:\.\d+)?)px/i', $existingStyle, $wm)) {
                $pxWidth = floatval($wm[1]);
                $pctWidth = round(($pxWidth / $tableWidthPx) * 100, 1);
                $pctWidth = max(5, min(95, $pctWidth)); // clamp entre 5% e 95%
                $existingStyle = preg_replace('/width:\s*\d+(?:\.\d+)?px/i', 'width: ' . $pctWidth . '%', $existingStyle);
            }
            
            // Converter largura do atributo width em px para %
            if (preg_match('/\bwidth\s*=\s*["\']?(\d+)(?:px)?["\']?/i', $attrs, $wm2) && 
                stripos($existingStyle, 'width') === false) {
                $pxWidth = floatval($wm2[1]);
                $pctWidth = round(($pxWidth / $tableWidthPx) * 100, 1);
                $pctWidth = max(5, min(95, $pctWidth));
                $existingStyle .= '; width: ' . $pctWidth . '%;';
            }
            
            // Garantir border nas células
            if (stripos($existingStyle, 'border') === false) {
                $existingStyle .= '; border: 1px solid #333;';
            }
            
            // Garantir padding
            if (stripos($existingStyle, 'padding') === false) {
                $existingStyle .= '; padding: 4px 6px;';
            }
            
            // Para TH, garantir negrito e centralizado
            if (strtolower($tag) === 'th') {
                if (stripos($existingStyle, 'font-weight') === false) {
                    $existingStyle .= '; font-weight: bold;';
                }
                if (stripos($existingStyle, 'text-align') === false) {
                    $existingStyle .= '; text-align: center;';
                }
                if (stripos($existingStyle, 'background') === false) {
                    $existingStyle .= '; background-color: #f0f0f0;';
                }
            }
            
            // Limpar style
            $existingStyle = trim($existingStyle, ' ;');
            $existingStyle = preg_replace('/;\s*;/', ';', $existingStyle);
            $existingStyle = ltrim($existingStyle, '; ');
            
            // Remover atributos de style e width antigos
            $attrs = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
            $attrs = preg_replace('/\s*width\s*=\s*["\']?[^"\'>\s]*["\']?/i', '', $attrs);
            // Remover class
            $attrs = preg_replace('/\s*class\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
            
            return '<' . $tag . $attrs . ' style="' . $existingStyle . '">';
        },
        $tableHtml
    );
    
    // Remover tags <colgroup> e <col> que o TCPDF não processa bem
    $tableHtml = preg_replace('/<colgroup>.*?<\/colgroup>/is', '', $tableHtml);
    $tableHtml = preg_replace('/<col[^>]*\/?>/i', '', $tableHtml);
    
    // Remover &nbsp; soltos que podem causar problemas
    // (manter dentro de células)
    
    return $tableHtml;
}

/**
 * Processa todo o corpo do ofício para o PDF, separando tabelas e normalizando
 * 
 * @param TCPDF $pdf Instância do TCPDF
 * @param string $conteudoOficio O corpo HTML do ofício
 * @param float $contentWidthMm Largura da área de conteúdo em mm
 */
function renderCorpoOficioPdf($pdf, $conteudoOficio, $contentWidthMm = 160) {
    // Decodificar entidades HTML
    $conteudoOficio = html_entity_decode($conteudoOficio, ENT_QUOTES, 'UTF-8');
    
    // Usar preg_split para dividir o conteúdo em <p>, <blockquote> e <table>
    $partes = preg_split('/(<blockquote>.*?<\/blockquote>|<table.*?<\/table>)/is', $conteudoOficio, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if (empty($parte)) continue;
        
        // Verificar se é um <blockquote>
        if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $matches)) {
            $pdf->Ln(-6);
            $pdf->SetX(60);
            $blockquoteWidth = $pdf->getPageWidth() - 60 - $pdf->getMargins()['right'] - 1;
            $pdf->SetFont('helvetica', 'I', 12);
            $pdf->MultiCell($blockquoteWidth, 5, strip_tags($matches[1]), 0, 'J', false, 1);
            $pdf->SetY($pdf->GetY() + 3);
        }
        // Verificar se é uma <table>
        elseif (preg_match('/<table.*?<\/table>/is', $parte)) {
            // Normalizar a tabela para TCPDF
            $tabelaNormalizada = normalizeTableForPdf($parte, $contentWidthMm);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->writeHTML($tabelaNormalizada, true, false, true, false, '');
            $pdf->Ln(5);
        } 
        else {
            // Processar normalmente os conteúdos fora de <blockquote> e <table>
            $pdf->SetFont('helvetica', '', 12);
            if (preg_match_all('/<p>(.*?)<\/p>/is', $parte, $matchesParagrafo)) {
                foreach ($matchesParagrafo[1] as $paragrafoTexto) {
                    $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $paragrafoTexto . '</div>', true, false, true, false);
                    $pdf->Ln(5);
                }
            } else {
                $textoLimpo = trim(strip_tags($parte));
                if (!empty($textoLimpo)) {
                    $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);
                    $pdf->Ln(5);
                }
            }
        }
    }
}
?>
