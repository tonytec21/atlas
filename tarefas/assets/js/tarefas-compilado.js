/*!
 * Atlas · Tarefas — "Baixar compilado" da aba Anexos (revisão 2.1.0).
 *
 * Junta, num único PDF, os anexos escolhidos pelo usuário (PDFs e imagens),
 * na ordem definida na tela. Tudo é feito no navegador com a biblioteca
 * pdf-lib (assets/js/vendor/pdf-lib.min.js, licença MIT), carregada só no
 * primeiro uso — nada muda no servidor nem nos arquivos originais.
 *
 * Regras:
 *  - PDF: todas as páginas entram como estão. PDF protegido por senha/
 *    criptografia não pode ser copiado e é pulado com aviso.
 *  - Imagem (JPG, PNG, GIF, WEBP, BMP): cada uma vira uma página A4, na
 *    orientação que melhor aproveita a imagem, centralizada com margem.
 *    Fotos de celular giradas pelo EXIF são endireitadas.
 *  - Demais formatos (Word, Excel, ZIP, áudio...) não entram no compilado.
 *  - Nome do arquivo: "Protocolo <nº> - <título> - Compilado.pdf" (sem
 *    acentos, para funcionar em qualquer navegador). O título completo, com
 *    acentos, vai nas propriedades do PDF.
 */

/* global jQuery, Tarefas, Swal, toastr */
var TarefasCompilado = (function ($) {
    'use strict';

    var VERSAO_LIB = '1.17.1';
    var URL_LIB = 'assets/js/vendor/pdf-lib.min.js?v=' + VERSAO_LIB;

    /** Extensões aceitas e como cada uma é tratada. */
    var TIPOS = {
        pdf: 'pdf',
        jpg: 'jpg', jpeg: 'jpg',
        png: 'png',
        gif: 'img', webp: 'img', bmp: 'img'
    };

    /* A4 em pontos PDF. */
    var A4_L = 595.28, A4_A = 841.89, MARGEM = 18;
    /* Limite de lado ao redesenhar imagem em canvas (evita estourar memória). */
    var LADO_MAX_CANVAS = 6000;

    var promessaLib = null;
    var gerando = false;

    function esc(v) { return Tarefas.esc(v); }

    function compilavel(a) {
        return !!(a && a.existe && TIPOS[String(a.ext || '').toLowerCase()]);
    }

    /* ============================================================== */
    /* HTML da aba                                                    */
    /* ============================================================== */

    /** Botão que vai na barra da aba Anexos. */
    function botao(t) {
        var n = (t.anexos || []).filter(compilavel).length;
        if (!n) { return ''; }
        return '<button type="button" class="tf-btn tf-btn-sm" id="tfCompBotao"'
            + ' title="Juntar PDFs e imagens num único PDF">'
            + '<i class="fa fa-file-pdf-o"></i> Baixar compilado</button>';
    }

    /** Caixa de seleção (fica oculta até clicar no botão). */
    function painel(t) {
        var anexos = t.anexos || [];
        var aceitos = anexos.filter(compilavel);
        if (!aceitos.length) { return ''; }

        var fora = anexos.filter(function (a) { return !compilavel(a); });

        var itens = aceitos.map(function (a) {
            return '<div class="tf-comp-item" data-rel="' + esc(a.rel) + '">'
                + '<label class="tf-comp-marca">'
                + '<input type="checkbox" class="tf-comp-check" checked>'
                + '<i class="fa ' + Tarefas.iconeArquivo(a.ext) + '"></i>'
                + '<span class="tf-comp-nome">' + esc(a.nome)
                + '<small>' + esc(a.tamanho_br || '') + '</small></span>'
                + '</label>'
                + '<span class="tf-comp-ordem">'
                + '<button type="button" class="tf-btn tf-btn-sm" data-comp-mover="-1" title="Subir">'
                + '<i class="fa fa-arrow-up"></i></button>'
                + '<button type="button" class="tf-btn tf-btn-sm" data-comp-mover="1" title="Descer">'
                + '<i class="fa fa-arrow-down"></i></button>'
                + '</span></div>';
        }).join('');

        var avisoFora = '';
        if (fora.length) {
            avisoFora = '<p class="tf-mini tf-mudo" style="margin:10px 0 0">'
                + '<i class="fa fa-info-circle"></i> '
                + (fora.length === 1 ? '1 anexo não entra' : fora.length + ' anexos não entram')
                + ' no compilado (somente PDF e imagens): '
                + fora.map(function (a) { return esc(a.nome); }).join(', ') + '.</p>';
        }

        return '<div class="tf-bloco tf-comp" id="tfCompPainel" style="display:none">'
            + '<div class="tf-bloco-titulo"><i class="fa fa-files-o"></i> Baixar compilado em PDF'
            + '<button type="button" class="tf-btn tf-btn-sm" id="tfCompFechar" title="Fechar">'
            + '<i class="fa fa-times"></i></button></div>'
            + '<p class="tf-mini tf-mudo" style="margin:0 0 10px">'
            + 'Escolha os anexos e a ordem. Eles serão reunidos num único PDF, na sequência abaixo.</p>'
            + '<label class="tf-comp-todos">'
            + '<input type="checkbox" id="tfCompTodos" checked> <strong>Todos</strong>'
            + ' <span class="tf-mini tf-mudo" id="tfCompContagem"></span></label>'
            + '<div class="tf-comp-lista" id="tfCompLista">' + itens + '</div>'
            + avisoFora
            + '<div class="tf-comp-progresso" id="tfCompProgresso" style="display:none">'
            + '<div class="tf-progresso"><div id="tfCompBarra" style="width:0%"></div></div>'
            + '<p class="tf-mini tf-mudo" id="tfCompStatus" style="margin:4px 0 0"></p></div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap">'
            + '<button type="button" class="tf-btn tf-btn-primario" id="tfCompGerar">'
            + '<i class="fa fa-download"></i> Gerar e baixar PDF</button>'
            + '</div></div>';
    }

    /* ============================================================== */
    /* Eventos                                                        */
    /* ============================================================== */

    /**
     * Liga os eventos no contêiner do detalhe. `ns` é o namespace de evento
     * usado pelo detalhe (".tfdet"), que é desligado a cada nova renderização.
     */
    function ligar($c, ns, obterTarefa) {
        $c.on('click' + ns, '#tfCompBotao', function () {
            var $p = $('#tfCompPainel');
            if ($p.is(':visible')) { $p.slideUp(120); return; }
            atualizarContagem();
            $p.slideDown(150);
        });

        $c.on('click' + ns, '#tfCompFechar', function () {
            if (!gerando) { $('#tfCompPainel').slideUp(120); }
        });

        $c.on('change' + ns, '#tfCompTodos', function () {
            $('#tfCompLista .tf-comp-check').prop('checked', this.checked);
            atualizarContagem();
        });

        $c.on('change' + ns, '.tf-comp-check', atualizarContagem);

        $c.on('click' + ns, '[data-comp-mover]', function () {
            if (gerando) { return; }
            var $item = $(this).closest('.tf-comp-item');
            if (parseInt($(this).data('comp-mover'), 10) < 0) {
                $item.prev('.tf-comp-item').before($item);
            } else {
                $item.next('.tf-comp-item').after($item);
            }
        });

        $c.on('click' + ns, '#tfCompGerar', function () {
            var tarefa = obterTarefa();
            if (!tarefa || gerando) { return; }

            var porRel = {};
            (tarefa.anexos || []).forEach(function (a) { porRel[a.rel] = a; });

            var escolhidos = [];
            $('#tfCompLista .tf-comp-item').each(function () {
                if ($(this).find('.tf-comp-check').is(':checked')) {
                    var a = porRel[$(this).data('rel')];
                    if (a) { escolhidos.push(a); }
                }
            });

            if (!escolhidos.length) {
                Tarefas.dlg.aviso('Selecione ao menos um anexo para compilar.');
                return;
            }

            executar(tarefa, escolhidos);
        });
    }

    function atualizarContagem() {
        var $checks = $('#tfCompLista .tf-comp-check');
        var marcados = $checks.filter(':checked').length;
        $('#tfCompTodos')
            .prop('checked', marcados === $checks.length)
            .prop('indeterminate', marcados > 0 && marcados < $checks.length);
        $('#tfCompContagem').text('(' + marcados + ' de ' + $checks.length + ' selecionado'
            + ($checks.length === 1 ? '' : 's') + ')');
        $('#tfCompGerar').prop('disabled', marcados === 0);
    }

    function travar(sim) {
        gerando = sim;
        $('#tfCompPainel').find('input, button').not('#tfCompFechar').prop('disabled', sim);
        $('#tfCompBotao').prop('disabled', sim);
        $('#tfCompGerar').html(sim
            ? '<i class="fa fa-circle-o-notch tf-girando"></i> Gerando…'
            : '<i class="fa fa-download"></i> Gerar e baixar PDF');
        if (!sim) { atualizarContagem(); }
    }

    function progresso(feitos, total, texto) {
        $('#tfCompProgresso').show();
        $('#tfCompBarra').css('width', Math.round(feitos * 100 / Math.max(1, total)) + '%');
        $('#tfCompStatus').text(texto || '');
    }

    /* ============================================================== */
    /* Geração                                                        */
    /* ============================================================== */

    function executar(tarefa, escolhidos) {
        travar(true);
        progresso(0, escolhidos.length, 'Preparando…');

        carregarLib()
            .then(function () { return montar(tarefa, escolhidos); })
            .then(function (res) {
                travar(false);
                progresso(1, 1, '');
                $('#tfCompProgresso').hide();

                if (!res.paginas) {
                    mostrarFalhas('error', 'Nada para compilar',
                        'Nenhum dos anexos selecionados pôde ser incluído.', res.falhas);
                    return;
                }

                baixar(res.bytes, nomeArquivo(tarefa));

                if (res.falhas.length) {
                    mostrarFalhas('warning', 'Compilado gerado com ressalvas',
                        'O PDF foi baixado, mas estes anexos ficaram de fora:', res.falhas);
                } else if (window.toastr) {
                    toastr.success('Compilado gerado: ' + res.incluidos + ' anexo(s), '
                        + res.paginas + ' página(s).');
                }
            })
            .catch(function (erro) {
                travar(false);
                $('#tfCompProgresso').hide();
                Tarefas.dlg.erro('Não foi possível gerar o compilado',
                    (erro && erro.message) ? erro.message : String(erro || 'Erro desconhecido.'));
            });
    }

    /** Diálogo com a lista de anexos que não entraram no compilado. */
    function mostrarFalhas(icone, titulo, intro, falhas) {
        var lista = falhas.map(function (f) {
            return '<li><strong>' + esc(f.nome) + '</strong> — ' + esc(f.motivo) + '</li>';
        }).join('');
        var html = '<p>' + esc(intro) + '</p>'
            + (lista ? '<ul style="text-align:left;font-size:.88rem;margin:0;padding-left:18px">' + lista + '</ul>' : '');

        if (window.Swal) {
            Swal.fire(Tarefas.opcoesDialogo({ icon: icone, title: titulo, html: html }));
        } else {
            window.alert(titulo + '\n\n' + intro + '\n' + falhas.map(function (f) {
                return '- ' + f.nome + ': ' + f.motivo;
            }).join('\n'));
        }
    }

    function montar(tarefa, escolhidos) {
        var L = window.PDFLib;
        var falhas = [];
        var incluidos = 0;
        var doc;

        return L.PDFDocument.create().then(function (d) {
            doc = d;
            var titulo = 'Protocolo ' + tarefa.id + ' - ' + (tarefa.titulo || '') + ' - Compilado';
            doc.setTitle(titulo, { showInWindowTitleBar: true });
            doc.setSubject('Anexos compilados da tarefa — protocolo geral nº ' + tarefa.id);
            doc.setCreator('Atlas · Tarefas');
            doc.setProducer('Atlas · Tarefas');
            doc.setCreationDate(new Date());
            doc.setModificationDate(new Date());

            /* Processa em sequência para preservar a ordem e poupar memória. */
            var cadeia = Promise.resolve();
            escolhidos.forEach(function (a, i) {
                cadeia = cadeia.then(function () {
                    progresso(i, escolhidos.length,
                        'Processando ' + (i + 1) + ' de ' + escolhidos.length + ': ' + a.nome);
                    return incluir(doc, a)
                        .then(function () { incluidos++; })
                        .catch(function (e) {
                            falhas.push({ nome: a.nome, motivo: (e && e.message) || 'erro ao processar' });
                        });
                });
            });
            return cadeia;
        }).then(function () {
            progresso(escolhidos.length, escolhidos.length, 'Finalizando o arquivo…');
            var paginas = doc.getPageCount();
            if (!paginas) {
                return { bytes: null, paginas: 0, incluidos: 0, falhas: falhas };
            }
            return doc.save({ useObjectStreams: true }).then(function (bytes) {
                return { bytes: bytes, paginas: paginas, incluidos: incluidos, falhas: falhas };
            });
        });
    }

    function incluir(doc, a) {
        var tipo = TIPOS[String(a.ext || '').toLowerCase()];
        return baixarBytes(a).then(function (bytes) {
            if (tipo === 'pdf') { return incluirPdf(doc, bytes); }
            return incluirImagem(doc, bytes, tipo);
        });
    }

    function incluirPdf(doc, bytes) {
        var L = window.PDFLib;
        return L.PDFDocument.load(bytes, { ignoreEncryption: true, updateMetadata: false })
            .catch(function () {
                throw new Error('PDF corrompido ou em formato não reconhecido');
            })
            .then(function (origem) {
                if (origem.isEncrypted) {
                    throw new Error('PDF protegido por senha/criptografia');
                }
                return doc.copyPages(origem, origem.getPageIndices());
            })
            .then(function (paginas) {
                paginas.forEach(function (p) { doc.addPage(p); });
            });
    }

    function incluirImagem(doc, bytes, tipo) {
        var direto = null;

        if (tipo === 'jpg' && orientacaoJpeg(bytes) <= 1) {
            direto = doc.embedJpg(bytes);
        } else if (tipo === 'png') {
            direto = doc.embedPng(bytes);
        }

        var embutir = direto
            ? direto.catch(function () { return viaCanvas(doc, bytes, tipo); })
            : viaCanvas(doc, bytes, tipo);

        return embutir.then(function (img) { paginaImagem(doc, img); });
    }

    /** Coloca a imagem numa página A4, na orientação que melhor a aproveita. */
    function paginaImagem(doc, img) {
        var deitada = img.width > img.height;
        var pl = deitada ? A4_A : A4_L;
        var pa = deitada ? A4_L : A4_A;
        var uw = pl - 2 * MARGEM;
        var ua = pa - 2 * MARGEM;

        // Encaixa na área útil; imagens pequenas são ampliadas no máximo 2x.
        var escala = Math.min(uw / img.width, ua / img.height, 2);
        var w = img.width * escala;
        var h = img.height * escala;

        var pagina = doc.addPage([pl, pa]);
        pagina.drawImage(img, { x: (pl - w) / 2, y: (pa - h) / 2, width: w, height: h });
    }

    /**
     * Redesenha a imagem num canvas (fundo branco) e embute como JPEG.
     * Usado para GIF/WEBP/BMP, para JPEG com rotação EXIF e como plano B
     * quando o PNG/JPEG não é aceito diretamente pela biblioteca.
     */
    function viaCanvas(doc, bytes, tipo) {
        var mime = { jpg: 'image/jpeg', png: 'image/png' }[tipo] || '';
        var blob = new Blob([bytes], mime ? { type: mime } : {});
        var url = URL.createObjectURL(blob);

        return new Promise(function (ok, falha) {
            var im = new Image();
            im.onload = function () { ok(im); };
            im.onerror = function () { falha(new Error('imagem em formato não suportado pelo navegador')); };
            im.src = url;
        }).then(function (im) {
            var w = im.naturalWidth, h = im.naturalHeight;
            if (!w || !h) { throw new Error('imagem vazia'); }
            var f = Math.min(1, LADO_MAX_CANVAS / Math.max(w, h));
            w = Math.round(w * f); h = Math.round(h * f);

            var cv = document.createElement('canvas');
            cv.width = w; cv.height = h;
            var ctx = cv.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(im, 0, 0, w, h);

            return new Promise(function (ok, falha) {
                cv.toBlob(function (b) {
                    if (!b) { falha(new Error('falha ao converter a imagem')); return; }
                    b.arrayBuffer().then(ok, falha);
                }, 'image/jpeg', 0.92);
            });
        }).then(function (jpg) {
            URL.revokeObjectURL(url);
            return doc.embedJpg(jpg);
        }, function (e) {
            URL.revokeObjectURL(url);
            throw e;
        });
    }

    /** Lê a tag Orientation do EXIF de um JPEG (1 = normal). */
    function orientacaoJpeg(buf) {
        try {
            var v = new DataView(buf instanceof ArrayBuffer ? buf : buf.buffer);
            if (v.getUint16(0) !== 0xFFD8) { return 1; }
            var pos = 2, fim = v.byteLength;
            while (pos + 4 < fim) {
                var marca = v.getUint16(pos);
                var tam = v.getUint16(pos + 2);
                if (marca === 0xFFE1 && v.getUint32(pos + 4) === 0x45786966) { // "Exif"
                    var tiff = pos + 10;
                    var le = v.getUint16(tiff) === 0x4949;
                    var ifd = tiff + v.getUint32(tiff + 4, le);
                    var n = v.getUint16(ifd, le);
                    for (var i = 0; i < n; i++) {
                        var ent = ifd + 2 + i * 12;
                        if (v.getUint16(ent, le) === 0x0112) { return v.getUint16(ent + 8, le); }
                    }
                    return 1;
                }
                if ((marca & 0xFF00) !== 0xFF00 || marca === 0xFFDA) { break; }
                pos += 2 + tam;
            }
        } catch (e) { /* EXIF ilegível: trata como normal */ }
        return 1;
    }

    /* ============================================================== */
    /* Utilidades                                                     */
    /* ============================================================== */

    function carregarLib() {
        if (window.PDFLib) { return Promise.resolve(); }
        if (promessaLib) { return promessaLib; }

        promessaLib = new Promise(function (ok, falha) {
            var s = document.createElement('script');
            s.src = URL_LIB;
            s.async = true;
            s.onload = function () {
                if (window.PDFLib) { ok(); } else { falha(new Error('Biblioteca de PDF não inicializou.')); }
            };
            s.onerror = function () {
                promessaLib = null;
                falha(new Error('Não foi possível carregar a biblioteca de PDF (' + URL_LIB.split('?')[0] + ').'));
            };
            document.head.appendChild(s);
        });
        return promessaLib;
    }

    /** Monta a URL do anexo codificando cada trecho do caminho. */
    function urlAnexo(rel) {
        return String(rel).split('/').map(function (p) { return encodeURIComponent(p); }).join('/');
    }

    function baixarBytes(a) {
        return fetch(urlAnexo(a.url || a.rel), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) { throw new Error('arquivo não encontrado no servidor (HTTP ' + r.status + ')'); }
                return r.arrayBuffer();
            }, function () {
                throw new Error('falha de rede ao baixar o arquivo');
            });
    }

    /** "Protocolo 1234 - Título da tarefa - Compilado.pdf" */
    function nomeArquivo(t) {
        /*
         * O nome sai sem acentos e só com caracteres ASCII: alguns navegadores
         * descartam o atributo "download" quando o nome tem caracteres
         * especiais e salvam o arquivo como "download", sem extensão.
         */
        var titulo = String(t.titulo || '')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[–—−]/g, '-')
            .replace(/[ºª°]/g, 'o')
            .replace(/[^\x20-\x7E]+/g, ' ')
            .replace(/[\\\/:*?"<>|]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        if (titulo.length > 90) { titulo = titulo.slice(0, 90).trim(); }
        titulo = titulo.replace(/[.\s]+$/, '');
        return 'Protocolo ' + t.id + (titulo ? ' - ' + titulo : '') + ' - Compilado.pdf';
    }

    function baixar(bytes, nome) {
        var blob = new Blob([bytes], { type: 'application/pdf' });

        if (window.navigator && window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveOrOpenBlob(blob, nome);
            return;
        }

        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nome;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 4000);
    }

    return {
        botao: botao,
        painel: painel,
        ligar: ligar,
        compilavel: compilavel,
        nomeArquivo: nomeArquivo
    };
}(jQuery));
