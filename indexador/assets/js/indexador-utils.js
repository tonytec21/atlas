/**
 * ============================================================================
 * INDEXADOR - JAVASCRIPT COMPONENTS
 * Funções compartilhadas para Nascimento, Casamento e Óbito
 * ============================================================================
 */

const IndexadorUtils = {
    // ========================================================================
    // DATE FORMATTING
    // ========================================================================
    formatDate: function(date) {
        if (!date) return '-';
        const [year, month, day] = date.split('-');
        return `${day}/${month}/${year}`;
    },
    
    parseDate: function(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }
        return new Date(dateStr);
    },
    
    isValidDateBR: function(str) {
        const regex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        if (!regex.test(str)) return false;
        const [, d, m, y] = str.match(regex);
        const date = new Date(y, m - 1, d);
        return date.getDate() == d && date.getMonth() == m - 1 && date.getFullYear() == y;
    },
    
    // ========================================================================
    // TEXT UTILITIES
    // ========================================================================
    removeDiacritics: function(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    },
    
    sanitizeText: function(str) {
        return str.toUpperCase().trim();
    },
    
    formatSexo: function(sexo) {
        const map = { 'M': 'Masculino', 'F': 'Feminino', 'I': 'Ignorado' };
        return map[sexo] || '-';
    },
    
    // ========================================================================
    // SWEET ALERT HELPERS
    // ========================================================================
    showLoading: function(title = 'Carregando...') {
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });
    },
    
    hideLoading: function() {
        Swal.close();
    },
    
    showSuccess: function(message, callback) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: message,
            timer: 2000,
            showConfirmButton: false
        }).then(callback);
    },
    
    showError: function(message) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: message,
            confirmButtonColor: '#2563eb'
        });
    },
    
    showWarning: function(message) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: message,
            confirmButtonColor: '#2563eb'
        });
    },
    
    showConfirm: function(title, text, confirmText = 'Confirmar') {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar'
        });
    },
    
    // ========================================================================
    // DROPZONE SETUP
    // ========================================================================
    setupDropzone: function(dropzoneSelector, inputSelector, onFilesCallback) {
        const $dropzone = $(dropzoneSelector);
        const $input = $(inputSelector);
        
        $dropzone.on('click', function(e) {
            if (!$(e.target).is('button')) {
                $input.click();
            }
        });
        
        $dropzone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.addClass('dragover');
        });
        
        $dropzone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('dragover');
        });
        
        $dropzone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                onFilesCallback(files);
            }
        });
        
        $input.on('change', function() {
            if (this.files.length > 0) {
                onFilesCallback(this.files);
                $input.val('');
            }
        });
    },
    
    // ========================================================================
    // ATTACHMENT ITEM TEMPLATE
    // ========================================================================
    createAttachmentItem: function(options) {
        const { id, name, size, path, showRemove = true, isActive = false } = options;
        
        const sizeStr = size ? `${(size / 1024).toFixed(1)} KB` : '';
        const activeClass = isActive ? 'active' : '';
        
        return `
            <div class="idx-attachment-item ${activeClass}" data-id="${id || ''}" data-src="${path || ''}" data-name="${name}">
                <div class="idx-attachment-icon">
                    <i class="fa fa-file-pdf"></i>
                </div>
                <div class="idx-attachment-info">
                    <div class="idx-attachment-name">${name}</div>
                    ${sizeStr ? `<div class="idx-attachment-size">${sizeStr}</div>` : ''}
                </div>
                ${showRemove ? `
                <button type="button" class="idx-attachment-remove btn-remove-attachment" data-id="${id || ''}" data-name="${name}">
                    <i class="fa fa-trash"></i>
                </button>
                ` : ''}
            </div>
        `;
    },
    
    // ========================================================================
    // EMPTY STATE TEMPLATE
    // ========================================================================
    createEmptyState: function(icon, text) {
        return `
            <div class="idx-empty-state">
                <div class="idx-empty-icon">
                    <i class="fa fa-${icon}"></i>
                </div>
                <p class="idx-empty-text">${text}</p>
            </div>
        `;
    },
    
    // ========================================================================
    // MOBILE CARD TEMPLATE
    // ========================================================================
    createMobileCard: function(options) {
        const { id, badge, title, items, actions } = options;
        
        const itemsHtml = items.map(item => `
            <div class="idx-mobile-card-item ${item.fullWidth ? 'full-width' : ''}">
                <span class="idx-mobile-card-label">${item.label}</span>
                <span class="idx-mobile-card-value">${item.value}</span>
            </div>
        `).join('');
        
        const actionsHtml = actions.map(action => `
            <button class="idx-btn idx-btn-${action.variant} idx-btn-sm ${action.class}" data-id="${id}">
                <i class="fa fa-${action.icon}"></i> ${action.label || ''}
            </button>
        `).join('');
        
        return `
            <div class="idx-mobile-card idx-animate-fade-in">
                <div class="idx-mobile-card-header">
                    <div class="idx-mobile-card-badge">
                        <i class="fa fa-${badge.icon}"></i> ${badge.text}
                    </div>
                </div>
                <h4 class="idx-mobile-card-title">${title}</h4>
                <div class="idx-mobile-card-grid">
                    ${itemsHtml}
                </div>
                <div class="idx-mobile-card-actions">
                    ${actionsHtml}
                </div>
            </div>
        `;
    },
    
    // ========================================================================
    // TABLE ACTION BUTTONS
    // ========================================================================
    createActionButtons: function(id, options = {}) {
        const { showView = true, showEdit = true, showDelete = false } = options;
        
        let html = '<div class="idx-action-group">';
        
        if (showView) {
            html += `
                <button class="idx-action-btn view btn-view" data-id="${id}" title="Visualizar">
                    <i class="fa fa-eye"></i>
                </button>
            `;
        }
        
        if (showEdit) {
            html += `
                <button class="idx-action-btn edit btn-edit" data-id="${id}" title="Editar">
                    <i class="fa fa-pencil"></i>
                </button>
            `;
        }
        
        if (showDelete) {
            html += `
                <button class="idx-action-btn delete btn-delete" data-id="${id}" title="Excluir">
                    <i class="fa fa-trash"></i>
                </button>
            `;
        }
        
        html += '</div>';
        return html;
    },
    
    // ========================================================================
    // DATATABLE INITIALIZATION
    // ========================================================================
    initDataTable: function(tableId, options = {}) {
        const defaultOptions = {
            language: {
                url: "../../style/Portuguese-Brasil.json"
            },
            pageLength: 10,
            order: [[0, 'desc']],
            responsive: true,
            destroy: true
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        
        return $(tableId).DataTable(mergedOptions);
    },
    
    // ========================================================================
    // MODAL Z-INDEX FIX
    // ========================================================================
    fixModalZIndex: function() {
        $(document).on('show.bs.modal', '.modal', function() {
            const zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack')
                    .css('z-index', zIndex - 1)
                    .addClass('modal-stack');
            }, 0);
        });
    },
    
    // ========================================================================
    // DATE VALIDATION
    // ========================================================================
    validateDateNotFuture: function(inputSelector) {
        const currentYear = new Date().getFullYear();
        
        $(inputSelector).on('change', function() {
            const selectedDate = new Date($(this).val());
            if (selectedDate.getFullYear() > currentYear) {
                IndexadorUtils.showWarning('O ano não pode ser maior que o ano atual.');
                $(this).val('');
            }
        });
    },
    
    // ========================================================================
    // CITY SEARCH (IBGE API)
    // ========================================================================
    initCitySearch: function(searchInputId, resultsTableId, onSelect) {
        let searchTimeout = null;
        
        $(searchInputId).on('input', function() {
            const query = $(this).val();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (query.length < 3) {
                $(resultsTableId).empty();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios',
                    method: 'GET',
                    success: function(data) {
                        const results = $(resultsTableId);
                        results.empty();
                        
                        const queryNormalized = IndexadorUtils.removeDiacritics(query.toLowerCase());
                        const filtered = data.filter(function(city) {
                            return IndexadorUtils.removeDiacritics(city.nome.toLowerCase()).includes(queryNormalized);
                        }).slice(0, 50);
                        
                        filtered.forEach(function(city) {
                            const fullName = `${city.nome}/${city.microrregiao.mesorregiao.UF.sigla}`;
                            results.append(`
                                <tr>
                                    <td>${city.nome}</td>
                                    <td>${city.microrregiao.mesorregiao.UF.nome} (${city.microrregiao.mesorregiao.UF.sigla})</td>
                                    <td><code>${city.id}</code></td>
                                    <td>
                                        <button type="button" class="idx-btn idx-btn-primary idx-btn-sm btn-select-city" 
                                                data-id="${city.id}" 
                                                data-name="${fullName}">
                                            Selecionar
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                        
                        if (filtered.length === 0) {
                            results.append(`
                                <tr>
                                    <td colspan="4" class="idx-text-center idx-text-muted">
                                        Nenhuma cidade encontrada
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function() {
                        IndexadorUtils.showError('Erro ao buscar cidades.');
                    }
                });
            }, 300);
        });
        
        $(document).on('click', '.btn-select-city', function() {
            const cityName = $(this).data('name');
            const cityId = $(this).data('id');
            onSelect(cityId, cityName);
        });
    },
    
    // ========================================================================
    // FILTER TOGGLE (Mobile)
    // ========================================================================
    initFilterToggle: function() {
        window.toggleFilters = function() {
            const $grid = $('#filter-grid');
            const $icon = $('#filter-toggle-icon');
            $grid.toggleClass('collapsed');
            $icon.toggleClass('fa-chevron-down fa-chevron-up');
        };
    }
};

// Initialize modal z-index fix on document ready
$(document).ready(function() {
    IndexadorUtils.fixModalZIndex();
    IndexadorUtils.initFilterToggle();
});
