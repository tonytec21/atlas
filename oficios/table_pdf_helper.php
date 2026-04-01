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
    
    // Normalizar imagens para caminhos absolutos (necessário para TCPDF)
    $conteudoOficio = normalizeImagesForPdf($conteudoOficio, $contentWidthMm);
    
    // Dividir conteúdo em blockquote, table e o restante
    $partes = preg_split('/(<blockquote>.*?<\/blockquote>|<table.*?<\/table>)/is', $conteudoOficio, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if (empty($parte)) continue;
        
        // Blockquote
        if (preg_match('/<blockquote>(.*?)<\/blockquote>/is', $parte, $matches)) {
            $pdf->Ln(-6);
            $pdf->SetX(60);
            $blockquoteWidth = $pdf->getPageWidth() - 60 - $pdf->getMargins()['right'] - 1;
            $pdf->SetFont('helvetica', 'I', 12);
            $pdf->MultiCell($blockquoteWidth, 5, strip_tags($matches[1]), 0, 'J', false, 1);
            $pdf->SetY($pdf->GetY() + 3);
        }
        // Tabela
        elseif (preg_match('/<table.*?<\/table>/is', $parte)) {
            $tabelaNormalizada = normalizeTableForPdf($parte, $contentWidthMm);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->writeHTML($tabelaNormalizada, true, false, true, false, '');
            $pdf->Ln(5);
        }
        else {
            $pdf->SetFont('helvetica', '', 12);
            
            // Regex com suporte a <p> COM ou SEM atributos: <p>, <p style="...">, <p class="...">
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $parte, $matchesParagrafo)) {
                foreach ($matchesParagrafo[1] as $paragrafoTexto) {
                    if (preg_match('/<img\s/i', $paragrafoTexto)) {
                        // Parágrafo com imagem: renderizar centralizado, sem text-indent
                        $pdf->writeHTML('<div style="text-align:center;">' . $paragrafoTexto . '</div>', true, false, true, false);
                    } else {
                        $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $paragrafoTexto . '</div>', true, false, true, false);
                    }
                    $pdf->Ln(5);
                }
            } else {
                // Conteúdo fora de <p> — pode ser imagem solta ou texto
                $temImagem = preg_match('/<img\s/i', $parte);
                $temConteudo = $temImagem || !empty(trim(strip_tags($parte)));
                
                if ($temConteudo) {
                    if ($temImagem) {
                        $pdf->writeHTML('<div style="text-align:center;">' . $parte . '</div>', true, false, true, false);
                    } else {
                        $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);
                    }
                    $pdf->Ln(5);
                }
            }
        }
    }
}

/**
 * Normaliza tags <img> no HTML para compatibilidade com TCPDF
 * - Converte caminhos relativos para caminhos absolutos no filesystem
 * - Normaliza caminhos para barras normais (/) — funciona em Windows e Linux
 * - Converte largura CSS (%) para width/height em mm (unidade do TCPDF)
 * - Remove atributos CSS que TCPDF não entende (max-width, float, etc.)
 */
function normalizeImagesForPdf($html, $contentWidthMm = 160) {
    if (empty($html) || stripos($html, '<img') === false) {
        return $html;
    }
    
    // Diretório do módulo oficios (onde fica a pasta imagens/)
    $oficiosDir = str_replace('\\', '/', rtrim(__DIR__, '/\\'));
    
    // Document root do servidor
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
    
    $html = preg_replace_callback(
        '/<img[^>]*>/i',
        function($match) use ($oficiosDir, $docRoot, $contentWidthMm) {
            $tag = $match[0];
            
            // Extrair src
            if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
                return $tag;
            }
            $src = $srcMatch[1];
            if (empty($src)) return $tag;
            
            // ---- Resolver caminho absoluto da imagem ----
            $absPath = '';
            
            // data:image — TCPDF suporta nativamente, deixar como está
            if (strpos($src, 'data:image') === 0) {
                return $tag;
            }
            
            // Caminho relativo (ex: "imagens/25_2025/foto.jpg")
            if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
                $candidate = $oficiosDir . '/' . $src;
                if (file_exists($candidate)) {
                    $absPath = realpath($candidate);
                }
            }
            
            // Caminho absoluto no servidor (ex: "/atlas/oficios/imagens/foto.jpg")
            if (!$absPath && strpos($src, '/') === 0) {
                $candidate = $docRoot . $src;
                if (file_exists($candidate)) {
                    $absPath = realpath($candidate);
                }
            }
            
            // URL completa — extrair path local
            if (!$absPath && strpos($src, 'http') === 0) {
                $parsed = parse_url($src);
                if (!empty($parsed['path'])) {
                    $candidate = $docRoot . $parsed['path'];
                    if (file_exists($candidate)) {
                        $absPath = realpath($candidate);
                    }
                }
            }
            
            // Não encontrou o arquivo — retorna tag original (TCPDF tentará com o src original)
            if (!$absPath) {
                return $tag;
            }
            
            // Normalizar barras para / (TCPDF aceita forward slashes no Windows)
            $absPath = str_replace('\\', '/', $absPath);
            
            // ---- Calcular dimensões em mm para o TCPDF ----
            $pctWidth = 80; // padrão
            if (preg_match('/max-width:\s*(\d+)%/i', $tag, $wm)) {
                $pctWidth = intval($wm[1]);
            } elseif (preg_match('/width:\s*(\d+)%/i', $tag, $wm)) {
                $pctWidth = intval($wm[1]);
            } elseif (preg_match('/width\s*=\s*["\']?(\d+)/i', $tag, $wm)) {
                // width como atributo em px — estimar proporção
                $pctWidth = min(100, round(intval($wm[1]) / 640 * 100));
            }
            
            $imgWidthMm = round($contentWidthMm * $pctWidth / 100);
            $imgWidthMm = max(10, min($contentWidthMm, $imgWidthMm));
            
            // Calcular altura proporcional com base nas dimensões reais
            $imgHeightMm = 0;
            $imgInfo = @getimagesize($absPath);
            if ($imgInfo && $imgInfo[0] > 0) {
                $ratio = $imgInfo[1] / $imgInfo[0];
                $imgHeightMm = round($imgWidthMm * $ratio);
            }
            
            // ---- Montar tag limpa para TCPDF ----
            // TCPDF interpreta width/height como mm (unidade do documento)
            // Não usar htmlspecialchars no path — TCPDF precisa do caminho literal
            $newTag = '<img src="' . $absPath . '" width="' . $imgWidthMm . '"';
            if ($imgHeightMm > 0) {
                $newTag .= ' height="' . $imgHeightMm . '"';
            }
            $newTag .= '>';
            
            return $newTag;
        },
        $html
    );
    
    return $html;
}
?>

