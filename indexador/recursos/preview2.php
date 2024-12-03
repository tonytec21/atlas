
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Preview with TinyMCE Integration, Justified Text, and Corrected Page Counter</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/tinymce.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <script src="https://unpkg.com/dompurify@2.3.3/dist/purify.min.js"></script>
    <script>
        // Configurações do documento
        const config = {
            pageWidth: 595.28,
            pageHeight: 841.89,
            marginTop: 4 * 28.35, // 4 cm
            marginBottom: 2 * 28.35, // 2 cm
            marginLeft: 2 * 28.35, // 2 cm
            marginRight: 3 * 28.35, // 3 cm
            lineHeight: 14,
            fontSize: 12,
            paragraphSpacing: - 8, // 0.8 cm convertido para pontos
        };

        tinymce.init({
            selector: '#editor',
            height: 500,
            menubar: false,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            setup: function(editor) {
                editor.on('change', function() {
                    updatePDFPreview();
                });
            }
        });

        async function updatePDFPreview() {
            const { PDFDocument, rgb, StandardFonts } = PDFLib;
            const pdfDoc = await PDFDocument.create();
            const timesRomanFont = await pdfDoc.embedFont(StandardFonts.TimesRoman);
            const timesRomanBoldFont = await pdfDoc.embedFont(StandardFonts.TimesRomanBold);
            const timesRomanItalicFont = await pdfDoc.embedFont(StandardFonts.TimesRomanItalic);

            const contentWidth = config.pageWidth - config.marginLeft - config.marginRight;

            let pages = [];
            let currentPage = pdfDoc.addPage([config.pageWidth, config.pageHeight]);
            pages.push(currentPage);
            let yPosition = config.pageHeight - config.marginTop;

            const drawHeaderAndFooter = (page, pageNum) => {
                page.drawText('Cabeçalho', {
                    x: config.marginLeft,
                    y: config.pageHeight - config.marginTop / 2,
                    size: 16,
                    font: timesRomanFont,
                });

                page.drawText('endereço avenida paulista\nsao paulo 2024', {
                    x: config.marginLeft,
                    y: config.marginBottom / 2,
                    size: 12,
                    font: timesRomanFont,
                });

                // Adiciona o contador de páginas (apenas o número da página atual)
                page.drawText(`${pageNum} /`, {
                    x: config.pageWidth - config.marginRight - 50,
                    y: config.marginBottom / 2,
                    size: 12,
                    font: timesRomanFont,
                });
            };

            const addNewPage = () => {
                currentPage = pdfDoc.addPage([config.pageWidth, config.pageHeight]);
                pages.push(currentPage);
                yPosition = config.pageHeight - config.marginTop;
                drawHeaderAndFooter(currentPage, pages.length);
            };

            drawHeaderAndFooter(currentPage, 1);

            const content = tinymce.get('editor').getContent();
            const cleanContent = DOMPurify.sanitize(content);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = cleanContent;

            let currentLine = [];
            let currentLineWidth = 0;
            let isFirstParagraph = true;

            function processNode(node, currentFont = timesRomanFont) {
                if (node.nodeType === Node.TEXT_NODE) {
                    const words = node.textContent.trim().split(/\s+/);
                    for (const word of words) {
                        addWordToLine(word, currentFont);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    let font = currentFont;
                    if (node.tagName === 'STRONG' || node.tagName === 'B') {
                        font = timesRomanBoldFont;
                    } else if (node.tagName === 'EM' || node.tagName === 'I') {
                        font = timesRomanItalicFont;
                    }
                    
                    if (node.tagName === 'P') {
                        if (!isFirstParagraph) {
                            yPosition -= config.paragraphSpacing;
                        }
                        isFirstParagraph = false;
                        if (currentLine.length > 0) {
                            drawJustifiedLine(true);
                        }
                    }

                    for (const childNode of node.childNodes) {
                        processNode(childNode, font);
                    }

                    if (node.tagName === 'P' && currentLine.length > 0) {
                        drawJustifiedLine(true);
                    }
                }
            }

            function addWordToLine(word, font) {
                const wordWidth = font.widthOfTextAtSize(word, config.fontSize);
                if (currentLineWidth + wordWidth > contentWidth) {
                    drawJustifiedLine();
                    currentLine = [{text: word, font: font, width: wordWidth}];
                    currentLineWidth = wordWidth;
                } else {
                    currentLine.push({text: word, font: font, width: wordWidth});
                    currentLineWidth += wordWidth + font.widthOfTextAtSize(' ', config.fontSize);
                }
            }

            function drawJustifiedLine(isLastLine = false) {
                if (yPosition - config.lineHeight < config.marginBottom) {
                    addNewPage();
                }

                if (currentLine.length === 0) return;

                const totalWidth = currentLine.reduce((sum, word) => sum + word.width, 0);
                const totalSpacing = contentWidth - totalWidth;
                const spaceBetweenWords = isLastLine || currentLine.length === 1 ? 
                    currentLine[0].font.widthOfTextAtSize(' ', config.fontSize) : 
                    totalSpacing / (currentLine.length - 1);

                let xPosition = config.marginLeft;

                currentLine.forEach((word, index) => {
                    currentPage.drawText(word.text, {
                        x: xPosition,
                        y: yPosition,
                        size: config.fontSize,
                        font: word.font,
                    });
                    xPosition += word.width + (index < currentLine.length - 1 ? spaceBetweenWords : 0);
                });

                yPosition -= config.lineHeight;
                currentLine = [];
                currentLineWidth = 0;
            }

            processNode(tempDiv);
            if (currentLine.length > 0) {
                drawJustifiedLine(true);
            }

            // Adiciona o número total de páginas em todas as páginas
            const totalPages = pages.length;
            pages.forEach((page, index) => {
                page.drawText(`${totalPages}`, {
                    x: config.pageWidth - config.marginRight - 30,
                    y: config.marginBottom / 2,
                    size: 12,
                    font: timesRomanFont,
                });
            });

            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            document.getElementById('pdfPreview').src = url;
        }

        window.onload = function() {
            // TinyMCE will call updatePDFPreview on change
        }
    </script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .editor-container {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
        }
        #editor {
            width: 45%;
            height: 70vh;
        }
        iframe {
            width: 45%;
            height: 70vh;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <textarea id="editor"></textarea>
        <iframe id="pdfPreview"></iframe>
    </div>
</body>
</html>
