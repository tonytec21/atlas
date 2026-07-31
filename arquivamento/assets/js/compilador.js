/* =====================================================================
   Atlas · Arquivamento Digital
   Compilador de dossiê — junta PDFs e imagens em um único PDF.

   A junção roda no navegador com pdf-lib. Motivo: as bibliotecas livres de
   importação de PDF em PHP (FPDI) só leem PDF 1.4, e praticamente todo
   documento que chega ao cartório hoje — scanner, Word, impressão do Chrome —
   é 1.5 ou superior, com xref comprimido. No navegador não existe essa
   limitação e o servidor não precisa carregar arquivos grandes na memória.

   Fluxo:
     1. manifesto  → o que existe e o que é compilável
     2. download   → busca cada anexo pelo endpoint autenticado
     3. contagem   → quantas folhas cada documento ocupa
     4. capa       → o servidor gera capa + índice em TCPDF com as folhas certas
     5. mesclagem  → capa + documentos, com carimbo de folha em cada página
   ===================================================================== */
(function (global) {
  'use strict';

  var A4 = { largura: 595.28, altura: 841.89 };
  var MARGEM_IMAGEM = 28;

  function semAcento(t) {
    return String(t == null ? '' : t).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function nomeArquivoSeguro(t) {
    return semAcento(t).replace(/[^\w\-. ]+/g, '_').replace(/\s+/g, ' ').trim() || 'dossie';
  }

  /* ------------------------------------------------------------------ *
   * Conversão de imagens que o pdf-lib não embute nativamente
   * (webp, gif, bmp, tiff parcial) — passa pelo canvas e vira JPEG.
   * ------------------------------------------------------------------ */
  function paraJpegViaCanvas(bytes, mime) {
    return new Promise(function (resolve, reject) {
      var blob = new Blob([bytes], { type: mime || 'image/png' });
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        try {
          var c = document.createElement('canvas');
          // Limita a 4000px no maior lado: acima disso o PDF fica gigante sem ganho.
          var escala = Math.min(1, 4000 / Math.max(img.naturalWidth, img.naturalHeight));
          c.width = Math.max(1, Math.round(img.naturalWidth * escala));
          c.height = Math.max(1, Math.round(img.naturalHeight * escala));
          var ctx = c.getContext('2d');
          ctx.fillStyle = '#FFFFFF';
          ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(img, 0, 0, c.width, c.height);
          URL.revokeObjectURL(url);
          c.toBlob(function (b) {
            if (!b) { reject(new Error('falha ao converter a imagem')); return; }
            b.arrayBuffer().then(function (buf) { resolve(new Uint8Array(buf)); }).catch(reject);
          }, 'image/jpeg', 0.92);
        } catch (e) {
          URL.revokeObjectURL(url);
          reject(e);
        }
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error('formato de imagem não reconhecido pelo navegador'));
      };
      img.src = url;
    });
  }

  /* ------------------------------------------------------------------ *
   * Posição do carimbo respeitando páginas giradas
   * ------------------------------------------------------------------ */
  function pontoVisual(pagina, vx, vy) {
    var t = pagina.getSize();
    var r = ((pagina.getRotation().angle % 360) + 360) % 360;
    if (r === 90)  { return { x: t.width - vy, y: vx, giro: 90,  vw: t.height }; }
    if (r === 180) { return { x: t.width - vx, y: t.height - vy, giro: 180, vw: t.width }; }
    if (r === 270) { return { x: vy, y: t.height - vx, giro: 270, vw: t.height }; }
    return { x: vx, y: vy, giro: 0, vw: t.width };
  }

  function larguraVisual(pagina) {
    var t = pagina.getSize();
    var r = ((pagina.getRotation().angle % 360) + 360) % 360;
    return (r === 90 || r === 270) ? t.height : t.width;
  }

  /* ================================================================== *
   * Compilador
   * ================================================================== */
  var Compilador = {

    disponivel: function () {
      return typeof global.PDFLib !== 'undefined';
    },

    /** Busca o manifesto dos arquivamentos escolhidos. */
    manifesto: function (ids) {
      var q = encodeURIComponent(Array.isArray(ids) ? ids.join(',') : String(ids));
      return fetch('compilar.php?formato=manifesto&ids=' + q, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { throw new Error(j.erro || 'Falha ao ler o manifesto.'); }
        return j;
      });
    },

    /**
     * Compila os documentos em um PDF único.
     *
     * @param {Array}  itens    [{id, indice, nome, ext, url, tamanho_legivel}]
     * @param {Array}  ids      arquivamentos envolvidos
     * @param {Object} opcoes   {csrf, carimbar, aoProgredir(pct, texto)}
     * @returns {Promise<{blob, nome, paginas, falhas}>}
     */
    compilar: function (itens, ids, opcoes) {
      opcoes = opcoes || {};
      var progresso = opcoes.aoProgredir || function () {};
      var carimbar = opcoes.carimbar !== false;

      if (!Compilador.disponivel()) {
        return Promise.reject(new Error('Biblioteca de PDF não carregada. Recarregue a página.'));
      }
      if (!itens || !itens.length) {
        return Promise.reject(new Error('Selecione ao menos um documento.'));
      }

      var PDFLib = global.PDFLib;
      var baixados = [];
      var falhas = [];
      var total = itens.length;

      /* ---------- 1. Baixa e analisa cada documento ---------- */
      var cadeia = Promise.resolve();
      itens.forEach(function (item, i) {
        cadeia = cadeia.then(function () {
          progresso(Math.round((i / total) * 55), 'Lendo ' + item.nome);
          return fetch(item.url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.arrayBuffer();
          }).then(function (buf) {
            var bytes = new Uint8Array(buf);
            var ext = String(item.ext || '').toLowerCase();

            if (ext === 'pdf') {
              return PDFLib.PDFDocument.load(bytes, { ignoreEncryption: true })
                .then(function (doc) {
                  baixados.push({ item: item, tipo: 'pdf', doc: doc, paginas: doc.getPageCount() });
                });
            }
            baixados.push({ item: item, tipo: 'img', bytes: bytes, paginas: 1 });
          }).catch(function (e) {
            falhas.push(item.nome + ' — ' + (e.message || 'não pôde ser lido'));
          });
        });
      });

      /* ---------- 2. Pede a capa ao servidor, já com as folhas ---------- */
      var pdfFinal, fonte, capaPaginas = 0;

      return cadeia.then(function () {
        if (!baixados.length) {
          throw new Error('Nenhum documento pôde ser lido. Verifique os anexos.');
        }
        progresso(58, 'Montando a capa e o índice');

        var documentos = baixados.map(function (b) {
          return {
            nome: b.item.nome,
            tipo: b.item.ext,
            paginas: b.paginas,
            tamanho_legivel: b.item.tamanho_legivel || '',
            hash: b.item.hash || ''
          };
        });

        return fetch('compilar.php?formato=capa&ids=' + encodeURIComponent(ids.join(',')), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': opcoes.csrf || '',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({ documentos: documentos })
        }).then(function (r) {
          if (!r.ok) {
            return r.json().catch(function () { return {}; }).then(function (j) {
              throw new Error(j.erro || 'Falha ao gerar a capa do dossiê.');
            });
          }
          return r.arrayBuffer();
        });
      })

      /* ---------- 3. Mescla ---------- */
      .then(function (capaBuf) {
        return PDFLib.PDFDocument.create().then(function (novo) {
          pdfFinal = novo;
          return PDFLib.PDFDocument.load(new Uint8Array(capaBuf), { ignoreEncryption: true });
        }).then(function (capa) {
          return pdfFinal.copyPages(capa, capa.getPageIndices());
        }).then(function (paginas) {
          paginas.forEach(function (p) { pdfFinal.addPage(p); });
          capaPaginas = paginas.length;
        });
      })

      .then(function () {
        var passo = Promise.resolve();
        baixados.forEach(function (b, i) {
          passo = passo.then(function () {
            progresso(60 + Math.round((i / baixados.length) * 30), 'Anexando ' + b.item.nome);

            if (b.tipo === 'pdf') {
              return pdfFinal.copyPages(b.doc, b.doc.getPageIndices()).then(function (pgs) {
                pgs.forEach(function (p) { pdfFinal.addPage(p); });
              });
            }

            // Imagem: uma folha A4 por arquivo, ajustada à margem.
            var ext = String(b.item.ext || '').toLowerCase();
            var embutir;
            if (ext === 'jpg' || ext === 'jpeg') {
              embutir = pdfFinal.embedJpg(b.bytes).catch(function () {
                return paraJpegViaCanvas(b.bytes, 'image/jpeg').then(function (jp) { return pdfFinal.embedJpg(jp); });
              });
            } else if (ext === 'png') {
              embutir = pdfFinal.embedPng(b.bytes).catch(function () {
                return paraJpegViaCanvas(b.bytes, 'image/png').then(function (jp) { return pdfFinal.embedJpg(jp); });
              });
            } else {
              embutir = paraJpegViaCanvas(b.bytes, b.item.mime).then(function (jp) { return pdfFinal.embedJpg(jp); });
            }

            return embutir.then(function (img) {
              var pagina = pdfFinal.addPage([A4.largura, A4.altura]);
              var maxL = A4.largura - MARGEM_IMAGEM * 2;
              var maxA = A4.altura - MARGEM_IMAGEM * 2 - 22; // espaço do carimbo
              var escala = Math.min(maxL / img.width, maxA / img.height, 1);
              var l = img.width * escala;
              var a = img.height * escala;
              pagina.drawImage(img, {
                x: (A4.largura - l) / 2,
                y: (A4.altura - a) / 2 + 8,
                width: l,
                height: a
              });
            }).catch(function (e) {
              falhas.push(b.item.nome + ' — ' + (e.message || 'imagem não pôde ser embutida'));
            });
          });
        });
        return passo;
      })

      /* ---------- 4. Carimbo de folha ---------- */
      .then(function () {
        if (!carimbar) { return; }
        progresso(92, 'Carimbando as folhas');
        return pdfFinal.embedFont(PDFLib.StandardFonts.Helvetica).then(function (f) {
          fonte = f;
          var paginas = pdfFinal.getPages();
          var corpo = paginas.length - capaPaginas;
          var cinza = PDFLib.rgb(0.42, 0.48, 0.5);
          var etiqueta = (opcoes.rodape || '').slice(0, 110);

          for (var i = capaPaginas; i < paginas.length; i++) {
            var pagina = paginas[i];
            var n = i - capaPaginas + 1;
            var texto = 'fl. ' + n + '/' + corpo;
            var lv = larguraVisual(pagina);

            // faixa branca para o carimbo não sumir sobre conteúdo escuro
            var faixa = pontoVisual(pagina, 0, 0);
            try {
              pagina.drawRectangle({
                x: faixa.x, y: faixa.y,
                width: lv, height: 18,
                rotate: PDFLib.degrees(faixa.giro),
                color: PDFLib.rgb(1, 1, 1),
                opacity: 0.72
              });
            } catch (e) { /* página sem suporte a opacidade: segue sem faixa */ }

            var largTexto = fonte.widthOfTextAtSize(texto, 7.5);
            var pDir = pontoVisual(pagina, lv - largTexto - 24, 6.5);
            pagina.drawText(texto, {
              x: pDir.x, y: pDir.y, size: 7.5, font: fonte, color: cinza,
              rotate: PDFLib.degrees(pDir.giro)
            });

            if (etiqueta) {
              var pEsq = pontoVisual(pagina, 24, 6.5);
              pagina.drawText(semAcento(etiqueta), {
                x: pEsq.x, y: pEsq.y, size: 6.5, font: fonte, color: cinza,
                rotate: PDFLib.degrees(pEsq.giro)
              });
            }
          }
        });
      })

      /* ---------- 5. Metadados e saída ---------- */
      .then(function () {
        progresso(96, 'Finalizando o arquivo');
        try {
          pdfFinal.setTitle('Dossiê digital de arquivamento ' + ids.join(', '));
          pdfFinal.setSubject('Arquivamento ' + ids.join(', '));
          pdfFinal.setProducer('Atlas · Arquivamento Digital');
          pdfFinal.setCreator('Atlas · Arquivamento Digital');
          if (opcoes.autor) { pdfFinal.setAuthor(String(opcoes.autor)); }
          pdfFinal.setKeywords(['arquivamento', 'cartório', 'dossiê'].concat(ids.map(String)));
          pdfFinal.setCreationDate(new Date());
        } catch (e) { /* metadados são opcionais */ }

        return pdfFinal.save({ useObjectStreams: true });
      })

      .then(function (bytes) {
        progresso(100, 'Pronto');
        var nome = ids.length === 1
          ? 'dossie-' + ids[0] + '.pdf'
          : 'dossie-' + nomeArquivoSeguro(ids.length + '-arquivamentos-' + new Date().toISOString().slice(0, 10)) + '.pdf';
        return {
          blob: new Blob([bytes], { type: 'application/pdf' }),
          nome: nome,
          paginas: pdfFinal.getPageCount(),
          falhas: falhas
        };
      });
    },

    /** Dispara o download de um Blob. */
    baixar: function (blob, nome) {
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = nome;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }
  };

  global.ArqCompilador = Compilador;
})(window);
