/**
 * Atlas - Table Helper para CKEditor 4
 * - Inserção de tabelas com modelos predefinidos
 * - Edição de tabelas existentes (ajuste de largura de colunas via sliders)
 * - Redimensionamento de colunas por drag direto no editor
 * - Normalização do HTML para renderização perfeita no TCPDF/PDF
 * - Nenhuma dependência de plugins externos do CKEditor
 */
(function () {
    'use strict';

    // Referência global
    var TH = window.AtlasTableHelper = {};

    /* ==============================================================
       INICIALIZAÇÃO DO CKEDITOR
       ============================================================== */

    TH.initEditor = function (elementId, extraCfg) {
        var cfg = {
            extraPlugins: 'htmlwriter',
            allowedContent: true,
            filebrowserUploadUrl: '/uploader/upload.php',
            filebrowserUploadMethod: 'form',
            scayt_autoStartup: true,
            scayt_sLang: 'pt_BR',
            language: 'pt-br',
            toolbar: [
                { name: 'clipboard', items: ['Undo', 'Redo'] },
                { name: 'basic', items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat'] },
                { name: 'para', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
                { name: 'styles', items: ['Format', 'FontSize'] },
                { name: 'insert', items: ['Table', 'HorizontalRule', 'SpecialChar'] },
                { name: 'tools', items: ['Maximize', 'Source'] }
            ],
            on: {
                instanceReady: function (e) {
                    var ed = e.editor;
                    // CSS de tabelas no iframe
                    var d = ed.document.$;
                    var s = d.createElement('style');
                    s.textContent = _editorCSS();
                    d.head.appendChild(s);
                    // Ativar resize por drag
                    _attachDragResize(ed);
                    ed.on('afterPaste', function () { _attachDragResize(ed); });
                    ed.on('change', function () {
                        clearTimeout(ed._ath);
                        ed._ath = setTimeout(function () { _attachDragResize(ed); }, 500);
                    });
                },
                getData: function (e) {
                    e.data.dataValue = TH.normalizeHTML(e.data.dataValue);
                }
            }
        };
        if (extraCfg) { for (var k in extraCfg) cfg[k] = extraCfg[k]; }
        return CKEDITOR.replace(elementId, cfg);
    };

    /* ==============================================================
       MODAL GENÉRICO (criar / fechar)
       ============================================================== */

    function _createModal(id, title, bodyHTML, footerHTML) {
        // Remove anterior se existir
        var old = document.getElementById(id);
        if (old) old.parentNode.removeChild(old);

        var overlay = document.createElement('div');
        overlay.id = id;
        overlay.className = 'atlas-tbl-overlay';

        overlay.innerHTML =
            '<div class="atlas-tbl-box">' +
                '<div class="atlas-tbl-header">' +
                    '<h5><i class="fa fa-table"></i> ' + title + '</h5>' +
                    '<button type="button" class="atlas-tbl-close" data-atlas-close="1">&times;</button>' +
                '</div>' +
                '<div class="atlas-tbl-body">' + bodyHTML + '</div>' +
                '<div class="atlas-tbl-footer">' + footerHTML + '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        // ---- Fechar: clique no overlay (fora da box) ----
        overlay.addEventListener('mousedown', function (ev) {
            if (ev.target === overlay) _closeModal(id);
        });

        // ---- Fechar: botão X ----
        var closeBtn = overlay.querySelector('[data-atlas-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                _closeModal(id);
            });
        }

        // ---- Fechar: Escape ----
        overlay._escHandler = function (ev) {
            if (ev.key === 'Escape' || ev.keyCode === 27) _closeModal(id);
        };
        document.addEventListener('keydown', overlay._escHandler);

        return overlay;
    }

    function _closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el._escHandler) document.removeEventListener('keydown', el._escHandler);
        el.parentNode.removeChild(el);
    }

    /* ==============================================================
       INSERIR TABELA NOVA
       ============================================================== */

    TH.openInsert = function (editorInstance) {
        var body =
            '<div class="atlas-tbl-row">' +
                '<div class="atlas-tbl-field"><label>Linhas:</label>' +
                    '<input type="number" id="_atRows" min="1" max="50" value="3"></div>' +
                '<div class="atlas-tbl-field"><label>Colunas:</label>' +
                    '<input type="number" id="_atCols" min="1" max="15" value="3"></div>' +
                '<div class="atlas-tbl-field">' +
                    '<label class="atlas-tbl-chk"><input type="checkbox" id="_atHead" checked> Cabeçalho</label>' +
                '</div>' +
            '</div>' +
            '<div class="atlas-tbl-row">' +
                '<div class="atlas-tbl-field" style="flex:1;"><label>Modelo:</label>' +
                    '<select id="_atTpl">' +
                        '<option value="">Tabela em branco</option>' +
                        '<option value="2col">2 Colunas (30% / 70%)</option>' +
                        '<option value="3col">3 Colunas iguais</option>' +
                        '<option value="lista">Lista: Item / Descrição / Valor</option>' +
                        '<option value="dados">Dados: Nº / Nome / Cargo / Setor</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="atlas-tbl-preview-wrap"><label>Pré-visualização:</label>' +
                '<div id="_atPreview" class="atlas-tbl-preview"></div>' +
            '</div>' +
            '<p class="atlas-tbl-tip"><i class="fa fa-info-circle"></i> Após inserir, arraste as <strong>barras azuis</strong> entre colunas no editor para ajustar larguras, ou use o botão <strong>Editar Tabela</strong>.</p>';

        var footer =
            '<button type="button" class="btn btn-secondary" id="_atCancelBtn">Cancelar</button>' +
            '<button type="button" class="btn btn-primary" id="_atInsertBtn"><i class="fa fa-check"></i> Inserir</button>';

        var modal = _createModal('atlasInsertModal', 'Inserir Tabela', body, footer);
        modal._editor = editorInstance;

        // Eventos
        var rowsEl = document.getElementById('_atRows');
        var colsEl = document.getElementById('_atCols');
        var headEl = document.getElementById('_atHead');
        var tplEl  = document.getElementById('_atTpl');

        function refresh() { _updateInsertPreview(); }
        rowsEl.addEventListener('input', refresh);
        colsEl.addEventListener('input', refresh);
        headEl.addEventListener('change', refresh);
        tplEl.addEventListener('change', function () {
            var t = tplEl.value;
            if (t === '2col')  { colsEl.value = 2; rowsEl.value = 5; headEl.checked = true; }
            if (t === '3col')  { colsEl.value = 3; rowsEl.value = 5; headEl.checked = true; }
            if (t === 'lista') { colsEl.value = 3; rowsEl.value = 6; headEl.checked = true; }
            if (t === 'dados') { colsEl.value = 4; rowsEl.value = 8; headEl.checked = true; }
            refresh();
        });

        document.getElementById('_atCancelBtn').addEventListener('click', function () { _closeModal('atlasInsertModal'); });
        document.getElementById('_atInsertBtn').addEventListener('click', function () { _doInsert(); });

        refresh();
    };

    function _updateInsertPreview() {
        var rows = _clamp(parseInt(document.getElementById('_atRows').value) || 3, 1, 50);
        var cols = _clamp(parseInt(document.getElementById('_atCols').value) || 3, 1, 15);
        var head = document.getElementById('_atHead').checked;
        var tpl  = document.getElementById('_atTpl').value;
        var cw = _colWidths(cols, tpl);
        var hl = _colLabels(cols, tpl);
        var h = '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
        if (head) {
            h += '<thead><tr>';
            for (var i = 0; i < cols; i++)
                h += '<th style="border:1px solid #999;background:#e9ecef;padding:4px 6px;width:' + cw[i] + '%;">' + hl[i] + '</th>';
            h += '</tr></thead>';
        }
        h += '<tbody>';
        var br = head ? Math.max(1, rows - 1) : rows;
        for (var r = 0; r < br; r++) {
            h += '<tr>';
            for (var c = 0; c < cols; c++)
                h += '<td style="border:1px solid #ccc;padding:4px 6px;width:' + cw[c] + '%;">&nbsp;</td>';
            h += '</tr>';
        }
        h += '</tbody></table>';
        document.getElementById('_atPreview').innerHTML = h;
    }

    function _doInsert() {
        var modal = document.getElementById('atlasInsertModal');
        var editor = modal ? modal._editor : null;
        if (!editor) return;

        var rows = _clamp(parseInt(document.getElementById('_atRows').value) || 3, 1, 50);
        var cols = _clamp(parseInt(document.getElementById('_atCols').value) || 3, 1, 15);
        var head = document.getElementById('_atHead').checked;
        var tpl  = document.getElementById('_atTpl').value;
        var cw = _colWidths(cols, tpl);
        var hl = _colLabels(cols, tpl);

        var html = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; width:100%; table-layout:fixed;">';
        if (head) {
            html += '<thead><tr>';
            for (var i = 0; i < cols; i++)
                html += '<th style="border:1px solid #333;background-color:#f0f0f0;padding:6px 8px;font-weight:bold;text-align:center;width:' + cw[i] + '%;">' + hl[i] + '</th>';
            html += '</tr></thead>';
        }
        html += '<tbody>';
        var br = head ? Math.max(1, rows - 1) : rows;
        for (var r = 0; r < br; r++) {
            html += '<tr>';
            for (var c = 0; c < cols; c++)
                html += '<td style="border:1px solid #333;padding:6px 8px;width:' + cw[c] + '%;">&nbsp;</td>';
            html += '</tr>';
        }
        html += '</tbody></table><p>&nbsp;</p>';

        editor.insertHtml(html);
        _closeModal('atlasInsertModal');

        setTimeout(function () { _attachDragResize(editor); }, 400);
    }

    /* ==============================================================
       EDITAR TABELA EXISTENTE
       ============================================================== */

    TH.openEdit = function (editorInstance) {
        var doc = editorInstance.document;
        if (!doc) return;

        // Encontrar todas as tabelas no editor
        var iDoc = doc.$;
        var tables = iDoc.querySelectorAll('table');

        if (!tables.length) {
            _showEditNoTable();
            return;
        }

        // Se só tem uma tabela, editar direto; se tem várias, mostrar seleção
        if (tables.length === 1) {
            _openEditForTable(editorInstance, tables[0], 0);
        } else {
            _openEditSelector(editorInstance, tables);
        }
    };

    function _showEditNoTable() {
        var body = '<div class="atlas-no-table-msg"><i class="fa fa-info-circle"></i>Nenhuma tabela encontrada no corpo do ofício.<br>Insira uma tabela primeiro.</div>';
        var footer = '<button type="button" class="btn btn-secondary" id="_atEditCloseBtn">Fechar</button>';
        _createModal('atlasEditModal', 'Editar Tabela', body, footer);
        document.getElementById('_atEditCloseBtn').addEventListener('click', function () { _closeModal('atlasEditModal'); });
    }

    function _openEditSelector(editor, tables) {
        var body = '<p style="margin-bottom:12px;font-size:0.88rem;color:#555;">Foram encontradas <strong>' + tables.length + '</strong> tabelas. Selecione qual deseja editar:</p>';

        for (var i = 0; i < tables.length; i++) {
            var t = tables[i];
            var r = t.querySelectorAll('tr').length;
            var c = t.querySelector('tr') ? t.querySelector('tr').querySelectorAll('td,th').length : 0;
            // Pegar texto da primeira célula como referência
            var firstCell = t.querySelector('td, th');
            var preview = firstCell ? firstCell.textContent.substring(0, 30).trim() : '';
            if (!preview || preview === '\u00a0') preview = '(tabela em branco)';

            body += '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:6px;background:#f8f9fa;border-radius:6px;cursor:pointer;border:1px solid #e0e0e0;transition:all 0.15s;" ' +
                    'onmouseenter="this.style.borderColor=\'#0d6efd\';this.style.background=\'#f0f4ff\'" ' +
                    'onmouseleave="this.style.borderColor=\'#e0e0e0\';this.style.background=\'#f8f9fa\'" ' +
                    'data-tbl-idx="' + i + '">' +
                    '<i class="fa fa-table" style="font-size:1.2rem;color:#0d6efd;"></i>' +
                    '<div><strong>Tabela ' + (i + 1) + '</strong> &mdash; ' + r + ' linhas, ' + c + ' colunas<br>' +
                    '<small style="color:#888;">' + _escHtml(preview) + '</small></div></div>';
        }

        var footer = '<button type="button" class="btn btn-secondary" id="_atSelCloseBtn">Cancelar</button>';
        var modal = _createModal('atlasEditModal', 'Selecionar Tabela', body, footer);
        modal._editor = editor;
        modal._tables = tables;

        document.getElementById('_atSelCloseBtn').addEventListener('click', function () { _closeModal('atlasEditModal'); });

        // Clique em cada item da lista
        var items = modal.querySelectorAll('[data-tbl-idx]');
        for (var j = 0; j < items.length; j++) {
            items[j].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-tbl-idx'));
                var m = document.getElementById('atlasEditModal');
                _closeModal('atlasEditModal');
                _openEditForTable(m._editor, m._tables[idx], idx);
            });
        }
    }

    function _openEditForTable(editor, table, tableIdx) {
        var firstRow = table.querySelector('tr');
        if (!firstRow) return;
        var cells = firstRow.querySelectorAll('td, th');
        var numCols = cells.length;
        if (!numCols) return;

        // Ler larguras atuais
        var tableW = table.offsetWidth || 640;
        var widths = [];
        for (var i = 0; i < numCols; i++) {
            var s = cells[i].style.width;
            if (s && s.indexOf('%') !== -1) {
                widths.push(parseFloat(s));
            } else if (cells[i].offsetWidth) {
                widths.push(Math.round((cells[i].offsetWidth / tableW) * 100));
            } else {
                widths.push(Math.floor(100 / numCols));
            }
        }
        // Normalizar para somar 100
        var sum = widths.reduce(function (a, b) { return a + b; }, 0);
        if (Math.abs(sum - 100) > 1) {
            for (var n = 0; n < widths.length; n++) widths[n] = Math.round((widths[n] / sum) * 100);
        }

        // Detectar nomes das colunas (do cabeçalho se existir)
        var colNames = [];
        for (var ci = 0; ci < numCols; ci++) {
            var txt = cells[ci].textContent.trim();
            colNames.push(txt && txt !== '\u00a0' ? txt.substring(0, 20) : 'Coluna ' + (ci + 1));
        }

        var body = '<p style="font-size:0.88rem;color:#555;margin-bottom:4px;">Ajuste a largura de cada coluna com os controles abaixo:</p>';
        body += '<div class="atlas-col-editor" id="_atColEditor">';

        for (var j = 0; j < numCols; j++) {
            body += '<div class="atlas-col-row">' +
                        '<span class="atlas-col-label">' + _escHtml(colNames[j]) + '</span>' +
                        '<input type="range" class="atlas-col-slider" min="5" max="90" value="' + widths[j] + '" data-col="' + j + '">' +
                        '<span class="atlas-col-pct" id="_atPct' + j + '">' + widths[j] + '%</span>' +
                    '</div>';
        }

        body += '</div>';
        body += '<div style="margin-top:10px;">' +
                    '<label style="font-size:0.82rem;font-weight:600;color:#555;">Visualização das proporções:</label>' +
                    '<div id="_atBarPreview" style="display:flex;gap:2px;margin-top:6px;height:28px;border-radius:4px;overflow:hidden;"></div>' +
                '</div>';
        body += '<p class="atlas-tbl-tip" style="margin-top:14px;"><i class="fa fa-info-circle"></i> A soma das larguras será normalizada automaticamente para 100%.</p>';

        var footer =
            '<button type="button" class="btn btn-secondary" id="_atEditCancelBtn">Cancelar</button>' +
            '<button type="button" class="btn btn-primary" id="_atEditApplyBtn"><i class="fa fa-check"></i> Aplicar</button>';

        var modal = _createModal('atlasEditModal2', 'Editar Tabela ' + (tableIdx + 1), body, footer);
        modal._editor = editor;
        modal._table = table;
        modal._numCols = numCols;

        // Evento dos sliders
        var sliders = modal.querySelectorAll('.atlas-col-slider');
        for (var si = 0; si < sliders.length; si++) {
            sliders[si].addEventListener('input', _onSliderChange);
        }

        // Atualizar barra visual
        _updateBarPreview(widths);

        document.getElementById('_atEditCancelBtn').addEventListener('click', function () { _closeModal('atlasEditModal2'); });
        document.getElementById('_atEditApplyBtn').addEventListener('click', function () { _applyEdit(); });
    }

    function _onSliderChange() {
        var modal = document.getElementById('atlasEditModal2');
        if (!modal) return;
        var sliders = modal.querySelectorAll('.atlas-col-slider');
        var widths = [];
        for (var i = 0; i < sliders.length; i++) {
            widths.push(parseInt(sliders[i].value));
            document.getElementById('_atPct' + i).textContent = sliders[i].value + '%';
        }
        _updateBarPreview(widths);
    }

    function _updateBarPreview(widths) {
        var container = document.getElementById('_atBarPreview');
        if (!container) return;
        var colors = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#dc3545', '#20c997', '#ffc107', '#6610f2', '#d63384', '#0dcaf0', '#adb5bd', '#343a40', '#6c757d', '#e9ecef', '#495057'];
        var sum = widths.reduce(function (a, b) { return a + b; }, 0) || 1;
        var html = '';
        for (var i = 0; i < widths.length; i++) {
            var pct = Math.round((widths[i] / sum) * 100);
            html += '<div style="flex:' + widths[i] + ';background:' + colors[i % colors.length] + ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.72rem;font-weight:700;">' + pct + '%</div>';
        }
        container.innerHTML = html;
    }

    function _applyEdit() {
        var modal = document.getElementById('atlasEditModal2');
        if (!modal) return;
        var editor = modal._editor;
        var table = modal._table;
        var numCols = modal._numCols;

        // Ler valores dos sliders
        var sliders = modal.querySelectorAll('.atlas-col-slider');
        var rawWidths = [];
        for (var i = 0; i < sliders.length; i++) rawWidths.push(parseInt(sliders[i].value));

        // Normalizar para somar 100%
        var sum = rawWidths.reduce(function (a, b) { return a + b; }, 0) || 1;
        var pcts = [];
        for (var j = 0; j < rawWidths.length; j++) pcts.push(Math.round((rawWidths[j] / sum) * 100));

        // Ajustar último para bater exatamente 100
        var pctSum = pcts.reduce(function (a, b) { return a + b; }, 0);
        if (pctSum !== 100) pcts[pcts.length - 1] += (100 - pctSum);

        // Aplicar a todas as linhas da tabela
        var rows = table.querySelectorAll('tr');
        for (var r = 0; r < rows.length; r++) {
            var rowCells = rows[r].querySelectorAll('td, th');
            for (var c = 0; c < rowCells.length && c < pcts.length; c++) {
                rowCells[c].style.width = pcts[c] + '%';
            }
        }

        // Notificar editor
        editor.fire('change');
        _closeModal('atlasEditModal2');

        // Re-ativar handles
        table._atlasResizeInit = false;
        setTimeout(function () { _attachDragResize(editor); }, 200);
    }

    /* ==============================================================
       REDIMENSIONAMENTO POR DRAG NO EDITOR
       ============================================================== */

    function _attachDragResize(editor) {
        var doc = editor.document;
        if (!doc) return;
        var iDoc = doc.$;
        var tables = iDoc.querySelectorAll('table');

        for (var t = 0; t < tables.length; t++) {
            var table = tables[t];
            if (table._atlasResizeInit) continue;
            table._atlasResizeInit = true;

            table.style.tableLayout = 'fixed';
            if (!table.style.width) table.style.width = '100%';

            _initWidths(table);
            _buildHandles(table, iDoc, editor);
        }
    }

    function _initWidths(table) {
        var fr = table.querySelector('tr');
        if (!fr) return;
        var cells = fr.querySelectorAll('td, th');
        if (!cells.length) return;

        var any = false;
        for (var i = 0; i < cells.length; i++) {
            if (cells[i].style.width) { any = true; break; }
        }
        if (!any) {
            var p = Math.floor(100 / cells.length);
            for (var j = 0; j < cells.length; j++) {
                cells[j].style.width = (j === cells.length - 1 ? 100 - p * (cells.length - 1) : p) + '%';
            }
        }
        // Propagar
        var ws = [];
        for (var k = 0; k < cells.length; k++) ws.push(cells[k].style.width);
        var rows = table.querySelectorAll('tr');
        for (var r = 0; r < rows.length; r++) {
            var rc = rows[r].querySelectorAll('td, th');
            for (var c = 0; c < rc.length && c < ws.length; c++) rc[c].style.width = ws[c];
        }
    }

    function _buildHandles(table, iDoc, editor) {
        // Wrapper
        var wrap = table.parentElement;
        if (!wrap || !wrap.classList.contains('atlas-tw')) {
            wrap = iDoc.createElement('div');
            wrap.className = 'atlas-tw';
            wrap.style.cssText = 'position:relative;width:100%;margin:10px 0;';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        }
        // Limpar handles antigos
        var old = wrap.querySelectorAll('.atlas-rh');
        for (var o = 0; o < old.length; o++) old[o].parentNode.removeChild(old[o]);

        var fr = table.querySelector('tr');
        if (!fr) return;
        var cells = fr.querySelectorAll('td, th');
        if (cells.length < 2) return;

        for (var i = 0; i < cells.length - 1; i++) _oneHandle(i, cells, table, wrap, iDoc, editor);
    }

    function _oneHandle(idx, cells, table, wrap, iDoc, editor) {
        var h = iDoc.createElement('div');
        h.className = 'atlas-rh';

        var cr = cells[idx].getBoundingClientRect();
        var tr = table.getBoundingClientRect();

        h.style.cssText =
            'position:absolute;top:0;width:7px;cursor:col-resize;background:transparent;z-index:10;' +
            'left:' + (cr.right - tr.left - 3) + 'px;height:' + table.offsetHeight + 'px;';

        h.addEventListener('mouseenter', function () { this.style.background = 'rgba(13,110,253,0.35)'; });
        h.addEventListener('mouseleave', function () { if (!this._d) this.style.background = 'transparent'; });

        h.addEventListener('mousedown', function (e) {
            e.preventDefault(); e.stopPropagation();
            var me = this; me._d = true;
            me.style.background = 'rgba(13,110,253,0.55)';

            var sx = e.clientX, tw = table.offsetWidth;
            var lc = cells[idx], rc = cells[idx + 1];
            var slw = lc.offsetWidth, srw = rc.offsetWidth;

            var allR = table.querySelectorAll('tr'), lArr = [], rArr = [];
            for (var i = 0; i < allR.length; i++) {
                var rc2 = allR[i].querySelectorAll('td,th');
                if (rc2[idx]) lArr.push(rc2[idx]);
                if (rc2[idx + 1]) rArr.push(rc2[idx + 1]);
            }

            function mv(ev) {
                var dx = ev.clientX - sx;
                var nl = Math.max(25, slw + dx), nr = Math.max(25, srw - dx);
                if (slw + dx < 25) { nl = 25; nr = slw + srw - 25; }
                if (srw - dx < 25) { nr = 25; nl = slw + srw - 25; }

                var lp = (nl / tw * 100).toFixed(1), rp = (nr / tw * 100).toFixed(1);
                for (var a = 0; a < lArr.length; a++) lArr[a].style.width = lp + '%';
                for (var b = 0; b < rArr.length; b++) rArr[b].style.width = rp + '%';

                var nr2 = lc.getBoundingClientRect();
                var ntr = table.getBoundingClientRect();
                me.style.left = (nr2.right - ntr.left - 3) + 'px';
            }

            function up() {
                me._d = false; me.style.background = 'transparent';
                iDoc.removeEventListener('mousemove', mv);
                iDoc.removeEventListener('mouseup', up);
                editor.fire('change');
            }
            iDoc.addEventListener('mousemove', mv);
            iDoc.addEventListener('mouseup', up);
        });

        wrap.appendChild(h);
    }

    /* ==============================================================
       CSS DO EDITOR (iframe)
       ============================================================== */

    function _editorCSS() {
        return 'table{border-collapse:collapse!important;width:100%!important;margin:10px 0!important;font-size:12px;table-layout:fixed}' +
            'td,th{border:1px solid #333!important;padding:6px 8px!important;vertical-align:middle;word-wrap:break-word;overflow:hidden}' +
            'th{background:#f0f0f0!important;font-weight:bold!important;text-align:center}' +
            '.atlas-tw{position:relative;width:100%}';
    }

    /* ==============================================================
       NORMALIZAÇÃO HTML PARA TCPDF/PDF
       ============================================================== */

    TH.normalizeHTML = function (html) {
        if (!html || html.indexOf('<table') === -1) return html;

        var div = document.createElement('div');
        div.innerHTML = html;

        var tables = div.querySelectorAll('table');
        for (var t = 0; t < tables.length; t++) {
            var tbl = tables[t];

            // Desembrulhar wrapper de resize
            var wr = tbl.parentElement;
            if (wr && wr.classList.contains('atlas-tw')) {
                var hs = wr.querySelectorAll('.atlas-rh');
                for (var hh = 0; hh < hs.length; hh++) hs[hh].parentNode.removeChild(hs[hh]);
                wr.parentNode.insertBefore(tbl, wr);
                wr.parentNode.removeChild(wr);
            }

            // Atributos da tabela
            tbl.setAttribute('border', '1');
            tbl.setAttribute('cellpadding', '4');
            tbl.setAttribute('cellspacing', '0');
            tbl.removeAttribute('class');

            var ts = tbl.getAttribute('style') || '';
            ts = ts.replace(/table-layout:\s*fixed;?\s*/gi, '');
            ts = ts.replace(/width:\s*[\d.]+px/gi, 'width: 100%');
            if (ts.indexOf('border-collapse') === -1) ts += ';border-collapse:collapse;';
            if (ts.indexOf('width') === -1) ts += ';width:100%;';
            tbl.setAttribute('style', _cleanStyle(ts));

            // Largura ref para conversão px -> %
            var twPx = 640;
            var twm = ts.match(/width:\s*(\d+)px/); if (twm) twPx = parseInt(twm[1]);

            // Células
            var cells = tbl.querySelectorAll('td, th');
            for (var ci = 0; ci < cells.length; ci++) {
                var c = cells[ci];
                var cs = c.getAttribute('style') || '';
                // px -> %
                var wm = cs.match(/width:\s*(\d+(?:\.\d+)?)px/);
                if (wm) {
                    var pct = _clamp(Math.round(parseFloat(wm[1]) / twPx * 100), 5, 95);
                    cs = cs.replace(/width:\s*\d+(?:\.\d+)?px/gi, 'width:' + pct + '%');
                }
                if (cs.indexOf('border') === -1) cs += ';border:1px solid #333;';
                if (cs.indexOf('padding') === -1) cs += ';padding:4px 6px;';
                cs = cs.replace(/overflow:[^;]+;?\s*/gi, '').replace(/word-wrap:[^;]+;?\s*/gi, '');
                c.setAttribute('style', _cleanStyle(cs));
                c.removeAttribute('class');
            }

            // Remover colgroup/col
            var cgs = tbl.querySelectorAll('colgroup, col');
            for (var cg = 0; cg < cgs.length; cg++) cgs[cg].parentNode.removeChild(cgs[cg]);
        }

        return div.innerHTML;
    };

    /* ==============================================================
       UTILIDADES
       ============================================================== */

    function _clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

    function _cleanStyle(s) {
        return s.replace(/^[;\s]+/, '').replace(/[;\s]+$/, '').replace(/;\s*;/g, ';').replace(/^\s*;\s*/, '');
    }

    function _escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function _colWidths(cols, tpl) {
        if (tpl === '2col' && cols === 2) return [30, 70];
        if (tpl === 'lista' && cols === 3) return [30, 45, 25];
        if (tpl === 'dados' && cols === 4) return [10, 35, 25, 30];
        var w = Math.floor(100 / cols), a = [];
        for (var i = 0; i < cols; i++) a.push(w);
        a[cols - 1] += (100 - w * cols);
        return a;
    }

    function _colLabels(cols, tpl) {
        if (tpl === '2col' && cols === 2) return ['Campo', 'Valor'];
        if (tpl === 'lista' && cols === 3) return ['Item', 'Descri\u00e7\u00e3o', 'Valor'];
        if (tpl === 'dados' && cols === 4) return ['N\u00ba', 'Nome', 'Cargo', 'Setor'];
        var l = [];
        for (var i = 0; i < cols; i++) l.push('Coluna ' + (i + 1));
        return l;
    }

})();
