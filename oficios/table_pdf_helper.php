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
 * ============================================================================
 * IMAGENS NO PDF - CONVERSAO DE UNIDADES
 * ----------------------------------------------------------------------------
 * O TCPDF interpreta os atributos width/height da tag <img> SEMPRE em PIXELS
 * (ver tcpdf.php: getHTMLUnitToUnits($tag['width'], ..., 'px', false)).
 * Antes, o helper gravava esses atributos em milimetros, entao uma imagem
 * calculada para 128mm era desenhada como 128px = 128 / 2.8346 = 45mm,
 * ou seja, apenas ~35% do tamanho pretendido (imagens "minusculas").
 * As funcoes abaixo fazem a conversao correta mm (unidades do documento) -> px.
 * ============================================================================
 */

/**
 * Fator de conversao: 1 unidade do documento (mm) equivale a quantos "px" do TCPDF
 * px_tcpdf = unidades * getScaleFactor() * getImageScale()
 *
 * @param TCPDF|null $pdf Instancia do TCPDF (opcional)
 * @return float
 */
function atlasPdfUnitsToPx($pdf = null) {
    $k = 72 / 25.4;   // fator padrao para documentos em milimetros
    $scale = 1.0;
    if (is_object($pdf)) {
        if (method_exists($pdf, 'getScaleFactor')) {
            $v = (float) $pdf->getScaleFactor();
            if ($v > 0) $k = $v;
        }
        if (method_exists($pdf, 'getImageScale')) {
            $v = (float) $pdf->getImageScale();
            if ($v > 0) $scale = $v;
        }
    }
    return $k * $scale;
}

/**
 * Converte um valor CSS (px, %, mm, cm, pt, in, em) para unidades do documento (mm)
 *
 * @param string $value      Valor CSS bruto (ex.: "320px", "80%", "12cm")
 * @param float  $refWidthMm Largura de referencia para valores percentuais
 * @param float  $unitsToPx  Fator de conversao (ver atlasPdfUnitsToPx)
 * @return float|null Valor em mm ou null se nao for possivel converter
 */
function atlasCssToMm($value, $refWidthMm, $unitsToPx) {
    $value = trim((string) $value);
    if ($value === '') return null;

    if (!preg_match('/^(-?\d+(?:\.\d+)?)\s*(px|%|mm|cm|pt|in|em|rem)?$/i', $value, $m)) {
        return null;
    }

    $num  = floatval($m[1]);
    $unit = isset($m[2]) ? strtolower($m[2]) : 'px';
    if ($unit === '') $unit = 'px';

    // 1 px CSS = 0.75pt; converte para unidades do documento usando o proprio fator do TCPDF
    $cssPxToMm = 0.75 / max(0.0001, ($unitsToPx));

    switch ($unit) {
        case '%':   return $refWidthMm * $num / 100;
        case 'mm':  return $num;
        case 'cm':  return $num * 10;
        case 'in':  return $num * 25.4;
        case 'pt':  return $num * 25.4 / 72;
        case 'em':
        case 'rem': return $num * 12 * 25.4 / 72; // aproximacao: 1em = 12pt (fonte do corpo)
        case 'px':
        default:    return $num * $cssPxToMm;
    }
}

/**
 * Normaliza tags <img> no HTML para compatibilidade com TCPDF
 * - Converte caminhos relativos/URLs para caminhos absolutos no filesystem
 * - Normaliza barras (funciona em Windows e Linux)
 * - Calcula largura/altura reais em mm respeitando a proporcao original
 * - Grava width/height em PIXELS (unica unidade que o TCPDF entende na tag <img>)
 * - Limita a imagem a largura util e a altura util da pagina
 * - Suporta imagens em base64 (data:image/...)
 *
 * @param string     $html
 * @param float      $contentWidthMm Largura util do conteudo em mm
 * @param TCPDF|null $pdf            Instancia do TCPDF (para conversao exata de unidades)
 * @return string
 */
function normalizeImagesForPdf($html, $contentWidthMm = 160, $pdf = null) {
    if (empty($html) || stripos($html, '<img') === false) {
        return $html;
    }

    $unitsToPx = atlasPdfUnitsToPx($pdf);

    // Altura util da pagina (para nao estourar a area de conteudo)
    $maxHeightMm = 200;
    if (is_object($pdf) && method_exists($pdf, 'getPageHeight')) {
        $margins = method_exists($pdf, 'getMargins') ? $pdf->getMargins() : array('top' => 45, 'bottom' => 25);
        $top     = isset($margins['top']) ? $margins['top'] : 45;
        $bottom  = isset($margins['bottom']) ? $margins['bottom'] : 25;
        $calc    = $pdf->getPageHeight() - $top - $bottom;
        if ($calc > 20) $maxHeightMm = $calc;
    }

    // Diretorio do modulo oficios (onde fica a pasta imagens/)
    $oficiosDir = str_replace('\\', '/', rtrim(__DIR__, '/\\'));

    // Document root do servidor
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')) : '';

    $html = preg_replace_callback(
        '/<img[^>]*>/i',
        function($match) use ($oficiosDir, $docRoot, $contentWidthMm, $maxHeightMm, $unitsToPx) {
            $tag = $match[0];

            // Extrair src
            if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
                return $tag;
            }
            $src = trim($srcMatch[1]);
            if ($src === '') return $tag;

            $absPath    = '';
            $isDataUri  = (stripos($src, 'data:image') === 0);
            $natWidthPx = 0;
            $natHeightPx = 0;

            if ($isDataUri) {
                // Dimensoes reais da imagem embutida em base64
                if (preg_match('/^data:image\/[^;]+;base64,(.*)$/is', $src, $dm)) {
                    $bin = @base64_decode(preg_replace('/\s+/', '', $dm[1]));
                    if ($bin !== false && function_exists('getimagesizefromstring')) {
                        $info = @getimagesizefromstring($bin);
                        if ($info && $info[0] > 0) {
                            $natWidthPx  = $info[0];
                            $natHeightPx = $info[1];
                        }
                    }
                }
            } else {
                // ---- Resolver caminho absoluto da imagem ----

                // Caminho relativo (ex: "imagens/25_2025/foto.jpg")
                if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
                    $candidate = $oficiosDir . '/' . $src;
                    if (file_exists($candidate)) {
                        $absPath = realpath($candidate);
                    }
                }

                // Caminho absoluto no servidor (ex: "/atlas/oficios/imagens/foto.jpg")
                if (!$absPath && $docRoot !== '' && strpos($src, '/') === 0) {
                    $candidate = $docRoot . $src;
                    if (file_exists($candidate)) {
                        $absPath = realpath($candidate);
                    }
                }

                // URL completa - extrair path local
                if (!$absPath && strpos($src, 'http') === 0) {
                    $parsed = @parse_url($src);
                    if (!empty($parsed['path'])) {
                        if ($docRoot !== '' && file_exists($docRoot . $parsed['path'])) {
                            $absPath = realpath($docRoot . $parsed['path']);
                        } else {
                            // tentar relativo ao modulo (ex: http://host/atlas/oficios/imagens/x.jpg)
                            $rel = $parsed['path'];
                            if (($pos = stripos($rel, '/imagens/')) !== false) {
                                $candidate = $oficiosDir . substr($rel, $pos);
                                if (file_exists($candidate)) {
                                    $absPath = realpath($candidate);
                                }
                            }
                        }
                    }
                }

                // Nao encontrou o arquivo - retorna a tag original
                if (!$absPath) {
                    return $tag;
                }

                // Normalizar barras para / (TCPDF aceita forward slashes no Windows)
                $absPath = str_replace('\\', '/', $absPath);

                $info = @getimagesize($absPath);
                if ($info && $info[0] > 0) {
                    $natWidthPx  = $info[0];
                    $natHeightPx = $info[1];
                }
            }

            // ---- Descobrir a largura desejada (em mm) ----
            $style = '';
            if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $tag, $sm)) {
                $style = $sm[1];
            }

            $widthMm    = null;
            $maxWidthMm = null;
            $heightMm   = null;

            // 1) style width (largura efetiva) e max-width (limite, como no navegador)
            if (preg_match('/(?<!-)\bwidth\s*:\s*([0-9.]+\s*(?:px|%|mm|cm|pt|in|em|rem)?)/i', $style, $wm)) {
                $widthMm = atlasCssToMm($wm[1], $contentWidthMm, $unitsToPx);
            }
            if (preg_match('/max-width\s*:\s*([0-9.]+\s*(?:px|%|mm|cm|pt|in|em|rem)?)/i', $style, $mwm)) {
                $maxWidthMm = atlasCssToMm($mwm[1], $contentWidthMm, $unitsToPx);
            }
            // 2) style height (somente se explicita e diferente de auto)
            if (preg_match('/(?<!-)\bheight\s*:\s*([0-9.]+\s*(?:px|%|mm|cm|pt|in|em|rem)?)/i', $style, $hm2)) {
                $heightMm = atlasCssToMm($hm2[1], $maxHeightMm, $unitsToPx);
            }
            // 3) atributo width="..." (px)
            if ($widthMm === null && preg_match('/\swidth\s*=\s*["\']?([0-9.]+%?)["\']?/i', $tag, $wm3)) {
                $widthMm = atlasCssToMm($wm3[1], $contentWidthMm, $unitsToPx);
            }
            // 4) atributo height="..." (px)
            if ($heightMm === null && preg_match('/\sheight\s*=\s*["\']?([0-9.]+%?)["\']?/i', $tag, $hm3)) {
                $heightMm = atlasCssToMm($hm3[1], $maxHeightMm, $unitsToPx);
            }

            // 5) Sem largura definida: usar o tamanho natural da imagem (96 DPI, como no navegador)
            if ($widthMm === null) {
                if ($heightMm !== null && $natWidthPx > 0 && $natHeightPx > 0) {
                    $widthMm = $heightMm * ($natWidthPx / $natHeightPx);
                } elseif ($natWidthPx > 0) {
                    $widthMm = $natWidthPx * 25.4 / 96;
                } else {
                    $widthMm = $contentWidthMm; // ultimo recurso: largura total do conteudo
                }
            }

            // ---- Limites: max-width (se houver), area util e minimo de seguranca ----
            if ($maxWidthMm !== null && $maxWidthMm > 0) {
                $widthMm = min($widthMm, $maxWidthMm);
            }
            $widthMm = min($widthMm, $contentWidthMm);
            $widthMm = max($widthMm, 10);

            // ---- Altura proporcional ----
            if ($natWidthPx > 0 && $natHeightPx > 0) {
                $heightMm = $widthMm * ($natHeightPx / $natWidthPx);
            }

            // Se a altura estourar a area util da pagina, reduzir mantendo a proporcao
            if ($heightMm !== null && $heightMm > $maxHeightMm) {
                if ($natWidthPx > 0 && $natHeightPx > 0) {
                    $widthMm = $maxHeightMm * ($natWidthPx / $natHeightPx);
                }
                $heightMm = $maxHeightMm;
            }

            // ---- Converter mm -> px (unidade lida pelo TCPDF na tag <img>) ----
            $widthPx  = (int) round($widthMm * $unitsToPx);
            $heightPx = ($heightMm !== null && $heightMm > 0) ? (int) round($heightMm * $unitsToPx) : 0;

            $finalSrc = $isDataUri ? $src : $absPath;

            // ---- Montar tag limpa para TCPDF ----
            $newTag = '<img src="' . $finalSrc . '" width="' . $widthPx . '"';
            if ($heightPx > 0) {
                $newTag .= ' height="' . $heightPx . '"';
            }
            // Guardar a altura em mm para o controle de quebra de pagina
            if ($heightMm > 0) {
                $newTag .= ' data-atlas-h="' . round($heightMm, 2) . '"';
            }
            $newTag .= ' />';

            return $newTag;
        },
        $html
    );

    return $html;
}

/**
 * Escreve um bloco que contem imagem, garantindo espaco suficiente na pagina.
 * Sem isso, o TCPDF (que usa fitonpage = true ao desenhar <img> no writeHTML)
 * ENCOLHE a imagem para caber no espaco restante da pagina.
 *
 * @param TCPDF  $pdf
 * @param string $innerHtml HTML ja normalizado (contendo <img>)
 * @param string $align     center|left|right|justify
 */
function atlasWriteBlocoImagem($pdf, $innerHtml, $align = 'center') {
    // Somar a altura (em mm) das imagens do bloco
    $alturaMm = 0;
    if (preg_match_all('/data-atlas-h\s*=\s*["\']([0-9.]+)["\']/i', $innerHtml, $hm)) {
        foreach ($hm[1] as $h) {
            $alturaMm += floatval($h);
        }
    }

    if ($alturaMm > 0 && method_exists($pdf, 'getPageHeight') && method_exists($pdf, 'getBreakMargin')) {
        $limiteY = $pdf->getPageHeight() - $pdf->getBreakMargin();
        if (($pdf->GetY() + $alturaMm + 2) > $limiteY) {
            $pdf->AddPage();
        }
    }

    // data-atlas-h e apenas um marcador interno; remover antes de enviar ao TCPDF
    $innerHtml = preg_replace('/\s*data-atlas-h\s*=\s*["\'][0-9.]+["\']/i', '', $innerHtml);

    $pdf->writeHTML('<div style="text-align:' . $align . ';">' . $innerHtml . '</div>', true, false, true, false);
}

/**
 * Processa todo o corpo do oficio para o PDF, separando tabelas e normalizando
 *
 * @param TCPDF $pdf Instancia do TCPDF
 * @param string $conteudoOficio O corpo HTML do oficio
 * @param float $contentWidthMm Largura da area de conteudo em mm
 */
function renderCorpoOficioPdf($pdf, $conteudoOficio, $contentWidthMm = 160) {
    // Decodificar entidades HTML
    $conteudoOficio = html_entity_decode($conteudoOficio, ENT_QUOTES, 'UTF-8');

    // Normalizar imagens (caminhos absolutos + dimensoes corretas em px do TCPDF)
    $conteudoOficio = normalizeImagesForPdf($conteudoOficio, $contentWidthMm, $pdf);

    // Dividir conteudo em blockquote, table e o restante
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
            $tabelaNormalizada = preg_replace('/\s*data-atlas-h\s*=\s*["\'][0-9.]+["\']/i', '', $tabelaNormalizada);
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
                        // Paragrafo com imagem: renderizar centralizado, sem text-indent
                        atlasWriteBlocoImagem($pdf, $paragrafoTexto, 'center');
                    } else {
                        $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $paragrafoTexto . '</div>', true, false, true, false);
                    }
                    $pdf->Ln(5);
                }
            } else {
                // Conteudo fora de <p> - pode ser imagem solta ou texto
                $temImagem = preg_match('/<img\s/i', $parte);
                $temConteudo = $temImagem || !empty(trim(strip_tags($parte)));

                if ($temConteudo) {
                    if ($temImagem) {
                        atlasWriteBlocoImagem($pdf, $parte, 'center');
                    } else {
                        $pdf->writeHTML('<div style="text-indent: 20mm; text-align: justify;">' . $parte . '</div>', true, false, true, false);
                    }
                    $pdf->Ln(5);
                }
            }
        }
    }
}
?>
