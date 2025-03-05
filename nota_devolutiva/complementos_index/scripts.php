<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>  
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>  
<script>  
    $(document).ready(function() {  
        $('#tabelaResultados').DataTable({  
            "language": { "url": "../style/Portuguese-Brasil.json" },  
            "order": [[1, 'desc']],  
            "pageLength": 25,  
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]  
        });  
        
        // Configuração de máscara CPF/CNPJ com detecção automática  
        var cpfCnpjOptions = {  
            onKeyPress: function(input, e, field, options) {  
                var masks = ['000.000.000-000', '00.000.000/0000-00'];  
                var mask = (input.length > 14) ? masks[1] : masks[0];  
                $('#cpf_cnpj').mask(mask, options);  
            }  
        };  

        $('#cpf_cnpj').mask('000.000.000-000', cpfCnpjOptions);  

        // Verificar valor inicial para aplicar a máscara correta  
        var cpfCnpjInicial = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
        if (cpfCnpjInicial.length > 11) {  
            $('#cpf_cnpj').mask('00.000.000/0000-00');  
        } else if (cpfCnpjInicial.length > 0) {  
            $('#cpf_cnpj').mask('000.000.000-000');  
        }
        
        // Corrigir botões de fechar do modal  
        $(document).on('click', '.btn-close, [data-dismiss="modal"]', function() {  
            $('#viewNotaModal').modal('hide');  
        });  
        
        // Função para aplicar a cor do status ao select  
        function applyStatusColor(status) {  
            // Remover todas as classes de status  
            $('#statusSelect').removeClass(function (index, className) {  
                return (className.match(/(^|\s)select-\S+/g) || []).join(' ');  
            });  
            
            // Adicionar a classe correta baseada no status  
            let selectClass = '';  
            switch (status) {  
                case 'Exigência Cumprida':  
                    selectClass = 'select-exigencia-cumprida';  
                    break;  
                case 'Exigência Não Cumprida':  
                    selectClass = 'select-exigencia-nao-cumprida';  
                    break;  
                case 'Prazo Expirado':  
                    selectClass = 'select-prazo-expirado';  
                    break;  
                case 'Em Análise':  
                    selectClass = 'select-em-analise';  
                    break;  
                case 'Cancelada':  
                    selectClass = 'select-cancelada';  
                    break;  
                case 'Aguardando Documentação':  
                    selectClass = 'select-aguardando-documentacao';  
                    break;  
                default:  
                    selectClass = 'select-pendente';  
            }  
            
            $('#statusSelect').addClass(selectClass);  
        }  
        
        // Atualizar a cor do select quando o status é alterado  
        $('#statusSelect').on('change', function() {  
            applyStatusColor($(this).val());  
        });  
        
        // Atualizar o status quando clicar no botão  
        $('#btnUpdateStatus').on('click', function() {  
            const numero = $('#viewNotaModalLabel').text().replace('Nota Devolutiva ', '');  
            const novoStatus = $('#statusSelect').val();  
            const statusAnterior = $('#statusSelect option:selected').text();  
            
            // Mostrar indicador de carregamento com SweetAlert2  
            Swal.fire({  
                title: 'Atualizando Status...',  
                text: `De "${statusAnterior}" para "${novoStatus}"`,  
                didOpen: () => {  
                    Swal.showLoading();  
                },  
                allowOutsideClick: false,  
                allowEscapeKey: false,  
                showConfirmButton: false  
            });  
            
            // Enviar requisição AJAX para atualizar o status  
            $.ajax({  
                url: 'update_nota_status.php',  
                type: 'POST',  
                data: {   
                    numero: numero,  
                    status: novoStatus  
                },  
                dataType: 'json',  
                success: function(response) {  
                    if (response.success) {  
                        // Atualizar o status exibido no select  
                        $('#statusSelect').val(novoStatus);  
                        applyStatusColor(novoStatus);  
                        
                        // Mostrar mensagem de sucesso com SweetAlert2  
                        Swal.fire({  
                            icon: 'success',  
                            title: 'Status Atualizado',  
                            text: `O status da nota foi alterado para "${novoStatus}"`,  
                            confirmButtonColor: '#28a745',  
                            timer: 2000,  
                            timerProgressBar: true  
                        });  
                        
                        // Atualizar o status na tabela sem recarregar a página  
                        $('tr').each(function() {  
                            if ($(this).find('td:first').text() === numero) {  
                                const statusCell = $(this).find('td:nth-child(7)');  // Atualizei o índice para 7 por causa da nova coluna  
                                const statusClassMap = {  
                                    'Pendente': 'status-pendente',  
                                    'Exigência Cumprida': 'status-exigencia-cumprida',  
                                    'Exigência Não Cumprida': 'status-exigencia-nao-cumprida',  
                                    'Prazo Expirado': 'status-prazo-expirado',  
                                    'Em Análise': 'status-em-analise',  
                                    'Cancelada': 'status-cancelada',  
                                    'Aguardando Documentação': 'status-aguardando-documentacao'  
                                };  
                                
                                // Atualizar o span do status  
                                const newStatusClass = statusClassMap[novoStatus] || 'status-pendente';  
                                statusCell.html(`<span class="status-badge ${newStatusClass}">${novoStatus}</span>`);  
                            }  
                        });  
                    } else {  
                        // Mostrar mensagem de erro com SweetAlert2  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro ao Atualizar Status',  
                            text: response.message || 'Ocorreu um erro ao atualizar o status da nota.',  
                            confirmButtonColor: '#dc3545'  
                        });  
                    }  
                },  
                error: function(xhr, status, error) {  
                    console.error('Erro na requisição AJAX:', error);  
                    
                    // Mostrar mensagem de erro com SweetAlert2  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro de Comunicação',  
                        text: 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',  
                        confirmButtonColor: '#dc3545'  
                    });  
                }  
            });  
        });  
    });  

    function viewNota(numero) {  
        // Exibir o modal  
        $('#viewNotaModalLabel').text('Nota Devolutiva ' + numero);  
        $('#notaModalBody').html(`  
            <div class="d-flex justify-content-center">  
                <div class="spinner-border text-primary" role="status">  
                    <span class="visually-hidden">Carregando...</span>  
                </div>  
            </div>  
        `);  
        
        // Resetar o status no modal para o carregamento  
        $('#statusSelect').val('Pendente');  
        
        $('#viewNotaModal').modal('show');  
        
        // Carregar os detalhes da nota devolutiva  
        $.ajax({  
            url: 'get_nota_details.php',  
            type: 'GET',  
            data: { numero: numero },  
            dataType: 'json',  
            success: function(response) {  
                // Formatar a data  
                let dataFormatada = '';  
                if (response.data_formatada) {  
                    dataFormatada = response.data_formatada;  
                } else if (response.data) {  
                    const data = new Date(response.data);  
                    dataFormatada = data.toLocaleDateString('pt-BR');  
                }  
                
                // Formatar a data do protocolo, se existir  
                let dataProtocoloFormatada = '';  
                if (response.data_protocolo) {  
                    const dataProtocolo = new Date(response.data_protocolo);  
                    dataProtocoloFormatada = dataProtocolo.toLocaleDateString('pt-BR');  
                }  
                
                // Construir o HTML para exibir os detalhes da nota  
                let html = `  
                    <div class="nota-content">  
                        <div class="nota-metadata">  
                            <div class="row">  
                                <div class="col-md-6">  
                                    <p><strong>Número:</strong> ${response.numero}</p>  
                                    <p><strong>Data:</strong> ${dataFormatada}</p>  
                                    <p><strong>Apresentante:</strong> ${response.apresentante}</p>  
                                    ${response.cpf_cnpj ? `<p><strong>CPF/CNPJ:</strong> ${response.cpf_cnpj}</p>` : ''}  
                                    <p><strong>Protocolo:</strong> ${response.protocolo || '-'}</p>  
                                    ${response.data_protocolo ? `<p><strong>Data do Protocolo:</strong> ${dataProtocoloFormatada}</p>` : ''}  
                                </div>  
                                <div class="col-md-6">  
                                    <p><strong>Título:</strong> ${response.titulo}</p>  
                                    ${response.origem_titulo ? `<p><strong>Origem do Título:</strong> ${response.origem_titulo}</p>` : ''}  
                                    <p><strong>Assinante:</strong> ${response.assinante}</p>  
                                    <p><strong>Cargo do Assinante:</strong> ${response.cargo_assinante || '-'}</p>  
                                    <p><strong>Processo de Referência:</strong> ${response.processo_referencia || '-'}</p>  
                                </div>  
                            </div>  
                        </div>  
                        <div class="section-title">Motivos da Devolução</div>  
                        <div class="nota-body">  
                            ${response.corpo}  
                        </div>  
                `;  
                
                // Adicionar seção de prazo para cumprimento se existir  
                if (response.prazo_cumprimento && response.prazo_cumprimento.trim() !== '') {  
                    html += `  
                        <div class="nota-prazo-cumprimento">  
                            <div class="section-title">Prazo Para Cumprimento</div>  
                            <div class="nota-prazo-content">  
                                ${response.prazo_cumprimento}  
                            </div>  
                        </div>  
                    `;  
                }  
                
                // Adicionar dados complementares se existirem  
                if (response.dados_complementares) {  
                    html += `  
                        <div class="nota-footer mt-4 pt-3 border-top">  
                            <div class="section-title">Dados Complementares</div>  
                            <p>${response.dados_complementares}</p>  
                        </div>  
                    `;  
                }  
                
                // Fechar a div principal  
                html += `</div>`;  
                
                $('#notaModalBody').html(html);  
                
                // Atualizar o status no select  
                const status = response.status || 'Pendente';  
                $('#statusSelect').val(status);  
                
                // Aplicar cor ao select de acordo com o status  
                applyStatusColor(status);  
            },  
            error: function(xhr, status, error) {  
                console.error('Erro ao buscar detalhes da nota:', error);  
                
                // Mostrar mensagem de erro com SweetAlert2  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro ao Carregar Dados',  
                    text: 'Não foi possível carregar os detalhes da nota devolutiva.',  
                    confirmButtonColor: '#dc3545'  
                });  
                
                $('#notaModalBody').html(`  
                    <div class="alert alert-danger" role="alert">  
                        Ocorreu um erro ao carregar os detalhes da nota devolutiva. Por favor, tente novamente.  
                    </div>  
                `);  
            }  
        });  
    }  

    function editNota(numero) {  
        window.location.href = 'editar-nota-devolutiva.php?numero=' + numero;  
    }  

    // Função para imprimir a nota como PDF  
    $(document).on('click', '#btnPrintNota', function() {  
        const numero = $('#viewNotaModalLabel').text().replace('Nota Devolutiva ', '');  
        window.open('gerar_pdf_nota.php?numero=' + numero, '_blank');  
    });  
    
    // Função para aplicar a cor do status ao select  
    function applyStatusColor(status) {  
        // Remover todas as classes de status  
        $('#statusSelect').removeClass(function (index, className) {  
            return (className.match(/(^|\s)select-\S+/g) || []).join(' ');  
        });  
        
        // Adicionar a classe correta baseada no status  
        let selectClass = '';  
        switch (status) {  
            case 'Exigência Cumprida':  
                selectClass = 'select-exigencia-cumprida';  
                break;  
            case 'Exigência Não Cumprida':  
                selectClass = 'select-exigencia-nao-cumprida';  
                break;  
            case 'Prazo Expirado':  
                selectClass = 'select-prazo-expirado';  
                break;  
            case 'Em Análise':  
                selectClass = 'select-em-analise';  
                break;  
            case 'Cancelada':  
                selectClass = 'select-cancelada';  
                break;  
            case 'Aguardando Documentação':  
                selectClass = 'select-aguardando-documentacao';  
                break;  
            default:  
                selectClass = 'select-pendente';  
        }  
        
        $('#statusSelect').addClass(selectClass);  
    }  
</script> 