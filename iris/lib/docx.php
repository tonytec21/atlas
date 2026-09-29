<?php
/**
 * Atlas Iris — gerador mínimo de .docx (Word) a partir de texto puro.
 * Parágrafos = blocos separados por linha em branco; quebras simples viram <w:br/>.
 * Marcações de dúvida [?...?] e [ilegível] saem realçadas em amarelo para conferência.
 */
function iris_docx_run($txt, $realce = false)
{
    $rpr = $realce ? '<w:rPr><w:highlight w:val="yellow"/></w:rPr>' : '';
    return '<w:r>' . $rpr . '<w:t xml:space="preserve">' . htmlspecialchars($txt, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
}
function iris_docx_linha($linha, $realcar)
{
    if (!$realcar) return iris_docx_run($linha);
    $out = ''; $pos = 0;
    if (preg_match_all('~\[\?[^\]\n]*?\?\]|\[ileg[ií]vel\]~iu', $linha, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$trecho, $off]) {
            if ($off > $pos) $out .= iris_docx_run(substr($linha, $pos, $off - $pos));
            $out .= iris_docx_run($trecho, true);
            $pos = $off + strlen($trecho);
        }
    }
    if ($pos < strlen($linha)) $out .= iris_docx_run(substr($linha, $pos));
    return $out;
}

function iris_docx_gerar($texto, $titulo = '', $realcar = true)
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('A extensão zip do PHP não está habilitada (necessária para gerar .docx).');
    $texto = str_replace(["\r\n", "\r"], "\n", (string)$texto);
    $corpo = '';
    if ($titulo !== '')
        $corpo .= '<w:p><w:pPr><w:spacing w:after="240"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t xml:space="preserve">'
                . htmlspecialchars($titulo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
    foreach (preg_split("~\n{2,}~", trim($texto)) as $par) {
        $linhas = explode("\n", $par); $runs = [];
        foreach ($linhas as $i => $l) $runs[] = ($i > 0 ? '<w:r><w:br/></w:r>' : '') . iris_docx_linha($l, $realcar);
        $corpo .= '<w:p><w:pPr><w:spacing w:after="160" w:line="300" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr>' . implode('', $runs) . '</w:p>';
    }
    $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $corpo
         . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1134" w:bottom="1134" w:left="1701" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
         . '</w:body></w:document>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:lang w:val="pt-BR"/>'
            . '</w:rPr></w:rPrDefault></w:docDefaults></w:styles>';
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
          . '</Relationships>';
    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
             . '</Relationships>';
    $tmp = tempnam(iris_dir_tmp(), 'docx');
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Não foi possível gerar o .docx.');
    $z->addFromString('[Content_Types].xml', $ct);
    $z->addFromString('_rels/.rels', $rels);
    $z->addFromString('word/document.xml', $doc);
    $z->addFromString('word/styles.xml', $styles);
    $z->addFromString('word/_rels/document.xml.rels', $docRels);
    $z->close();
    $bin = file_get_contents($tmp); @unlink($tmp);
    return $bin;
}
