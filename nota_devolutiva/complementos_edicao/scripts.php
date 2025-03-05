<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
<script src="../ckeditor/ckeditor.js"></script>
<script>  
        // Inicializar o editor CKEditor para os campos de texto rico  
        document.addEventListener('DOMContentLoaded', function() {  
            if (typeof CKEDITOR !== 'undefined') {  
                CKEDITOR.replace('corpo', {  
                    height: 300,  
                    removeButtons: 'Cut,Copy,Paste,Undo,Redo,Anchor,Strike,Subscript,Superscript'  
                });  
                
                CKEDITOR.replace('prazo_cumprimento', {  
                    height: 150,  
                    removeButtons: 'Cut,Copy,Paste,Undo,Redo,Anchor,Strike,Subscript,Superscript'  
                });  
                
                CKEDITOR.replace('dados_complementares', {  
                    height: 150,  
                    removeButtons: 'Cut,Copy,Paste,Undo,Redo,Anchor,Strike,Subscript,Superscript'  
                });  
            }  
            
            // Verifica se há mensagem de erro ou sucesso na URL  
            const urlParams = new URLSearchParams(window.location.search);  

            // Verificar mensagens de erro  
            if (urlParams.has('erro')) {  
                const erro = urlParams.get('erro');  
                let msg = "Erro ao processar a solicitação.";  
                
                if (erro === 'falha_atualizacao') {  
                    msg = "Erro ao atualizar a nota devolutiva.";  
                    if (urlParams.has('msg')) {  
                        msg += " Detalhes: " + urlParams.get('msg');  
                    }  
                } else if (erro === 'nota_nao_encontrada') {  
                    msg = "A nota devolutiva solicitada não foi encontrada.";  
                } else if (erro === 'numero_nao_informado') {  
                    msg = "O número da nota devolutiva não foi informado.";  
                } else if (erro === 'campos_obrigatorios') {  
                    msg = "Todos os campos obrigatórios devem ser preenchidos.";  
                }  
                
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro',  
                    text: msg  
                });  
            }   
            // Verificar mensagens de sucesso  
            else if (urlParams.has('sucesso')) {  
                const sucesso = urlParams.get('sucesso');  
                let msg = "Operação realizada com sucesso!";  
                let numeroNota = urlParams.has('numero') ? urlParams.get('numero') : '';  
                
                if (sucesso === 'nota_atualizada') {  
                    msg = `A nota devolutiva ${numeroNota} foi atualizada com sucesso!`;  
                }  
                
                Swal.fire({  
                    icon: 'success',  
                    title: 'Sucesso',  
                    text: msg,  
                    timer: 3000,  
                    showConfirmButton: false  
                });  
            }  
        });  
        
        // Função para consultar CNPJ na API  
        function consultarCnpj() {  
            const cnpj = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
            
            if (cnpj.length !== 14) {  
                Swal.fire({  
                    icon: 'error',  
                    title: 'CNPJ Inválido',  
                    text: 'Por favor, insira um CNPJ válido com 14 dígitos.',  
                    confirmButtonColor: '#3085d6'  
                });  
                return;  
            }  
            
            $('#consultaIndicator').show();  
            
            // Como estamos na mesma página, vamos usar a URL atual com os parâmetros necessários  
            $.ajax({  
                url: window.location.pathname, // URL atual  
                method: 'GET',  
                data: {  
                    action: 'consultar',  
                    documento: cnpj,  
                    numero: $('input[name="numero"]').val() // Mantém o número da nota para não perder o contexto  
                },  
                dataType: 'json',  
                success: function(response) {  
                    $('#consultaIndicator').hide();  
                    
                    if (!response.erro) {  
                        $('#apresentante').val(response.razao_social);  
                        Swal.fire({  
                            icon: 'success',  
                            title: 'CNPJ Consultado',  
                            text: 'Dados preenchidos com sucesso!',  
                            confirmButtonColor: '#3085d6'  
                        });  
                    } else {  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro na Consulta',  
                            text: response.mensagem || 'Não foi possível consultar o CNPJ.',  
                            confirmButtonColor: '#3085d6'  
                        });  
                    }  
                },  
                error: function(xhr, status, error) {  
                    $('#consultaIndicator').hide();  
                    console.error("Erro na consulta:", xhr.responseText);  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro na Consulta',  
                        text: 'Ocorreu um erro ao consultar o CNPJ. Por favor, tente novamente.',  
                        confirmButtonColor: '#3085d6'  
                    });  
                }  
            });  
        }
        
        // Validar CPF/CNPJ  
        function validarCpfCnpj(valor) {  
            valor = valor.replace(/[^\d]+/g, '');  
            
            if (valor.length === 11) {  
                return validarCPF(valor);  
            } else if (valor.length === 14) {  
                return validarCNPJ(valor);  
            }  
            
            return false;  
        }  
        
        function validarCPF(cpf) {  
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;  
            
            var soma = 0;  
            var resto;  
            
            for (var i = 1; i <= 9; i++) {  
                soma = soma + parseInt(cpf.substring(i-1, i)) * (11 - i);  
            }  
            
            resto = (soma * 10) % 11;  
            if (resto === 10 || resto === 11) resto = 0;  
            if (resto !== parseInt(cpf.substring(9, 10))) return false;  
            
            soma = 0;  
            for (var i = 1; i <= 10; i++) {  
                soma = soma + parseInt(cpf.substring(i-1, i)) * (12 - i);  
            }  
            
            resto = (soma * 10) % 11;  
            if (resto === 10 || resto === 11) resto = 0;  
            if (resto !== parseInt(cpf.substring(10, 11))) return false;  
            
            return true;  
        }  
        
        function validarCNPJ(cnpj) {  
            if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;  
            
            var tamanho = cnpj.length - 2;  
            var numeros = cnpj.substring(0, tamanho);  
            var digitos = cnpj.substring(tamanho);  
            var soma = 0;  
            var pos = tamanho - 7;  
            
            for (var i = tamanho; i >= 1; i--) {  
                soma += numeros.charAt(tamanho - i) * pos--;  
                if (pos < 2) pos = 9;  
            }  
            
            var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
            if (resultado !== parseInt(digitos.charAt(0))) return false;  
            
            tamanho = tamanho + 1;  
            numeros = cnpj.substring(0, tamanho);  
            soma = 0;  
            pos = tamanho - 7;  
            
            for (var i = tamanho; i >= 1; i--) {  
                soma += numeros.charAt(tamanho - i) * pos--;  
                if (pos < 2) pos = 9;  
            }  
            
            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
            if (resultado !== parseInt(digitos.charAt(1))) return false;  
            
            return true;  
        }  
        
        $(document).ready(function() {  
            // Máscaras e validações para CPF/CNPJ  
            var cpfMascara = '000.000.000-00';  
            var cnpjMascara = '00.000.000/0000-00';  
            
            var options = {  
                onKeyPress: function(cpf, e, field, options) {  
                    var masks = ['000.000.000-000', '00.000.000/0000-00'];  
                    var mask = (cpf.length > 14) ? masks[1] : masks[0];  
                    $('#cpf_cnpj').mask(mask, options);  
                }  
            };  
            
            // Aplicar máscara apropriada ao carregar a página  
            var cpfCnpjValue = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
            if (cpfCnpjValue.length > 0) {  
                if (cpfCnpjValue.length > 11) {  
                    $('#cpf_cnpj').mask(cnpjMascara, options);  
                } else {  
                    $('#cpf_cnpj').mask(cpfMascara, options);  
                }  
            } else {  
                $('#cpf_cnpj').mask(cpfMascara, options);  
            }  
            
            // Evento para consultar o CNPJ ao clicar no botão  
            $('#consultarCpfCnpj').on('click', function() {  
                let documento = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
                
                // Apenas consultar se for um CNPJ (14 dígitos)  
                if (documento.length === 14) {  
                    consultarCnpj();  
                } else {  
                    Swal.fire({  
                        icon: 'info',  
                        title: 'Atenção',  
                        text: 'A consulta automática está disponível apenas para CNPJ.',  
                        confirmButtonColor: '#3085d6'  
                    });  
                }  
            });  

            // Consultar automaticamente ao sair do campo se for um CNPJ válido  
            $('#cpf_cnpj').on('blur', function() {  
                var valor = $(this).val().replace(/[^\d]/g, '');  
                
                // Se for um CNPJ completo (14 dígitos)  
                if (valor.length === 14) {  
                    if (!validarCNPJ(valor)) {  
                        var campoInput = $(this); // Armazena referência ao campo  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'CNPJ Inválido',  
                            text: 'Por favor, insira um CNPJ válido.',  
                            confirmButtonColor: '#3085d6',  
                            willClose: function() {  
                                // Limpa o campo quando o alerta for fechado  
                                campoInput.val('');  
                            }  
                        });  
                    } else {  
                        // Se for válido e o campo de apresentante estiver vazio, consulta automaticamente  
                        if ($('#apresentante').val() === '') {  
                            consultarCnpj();  
                        }  
                    }  
                }  
            });  
            
            // Validar CPF/CNPJ antes do envio do formulário  
            $('#notaForm').on('submit', function(e) {  
                console.log("Formulário sendo enviado. Validando CPF/CNPJ...");  
                var cpfCnpjValue = $('#cpf_cnpj').val();  
                
                if (cpfCnpjValue && cpfCnpjValue.replace(/[^\d]/g, '').length > 0) {  
                    console.log("Valor do CPF/CNPJ:", cpfCnpjValue);  
                    console.log("Valor após remoção de caracteres não-numéricos:", cpfCnpjValue.replace(/[^\d]/g, ''));  
                    
                    var isValid = validarCpfCnpj(cpfCnpjValue);  
                    console.log("CPF/CNPJ é válido?", isValid);  
                    
                    if (!isValid) {  
                        e.preventDefault();  
                        e.stopPropagation();  
                        console.log("Formulário bloqueado devido a CPF/CNPJ inválido");  
                        
                        Swal.fire({  
                            icon: 'error',  
                            title: 'CPF/CNPJ Inválido',  
                            text: 'Por favor, insira um CPF ou CNPJ válido.',  
                            confirmButtonColor: '#3085d6',  
                            willClose: function() {  
                                // Limpa o campo quando o alerta for fechado  
                                $('#cpf_cnpj').val('');  
                            }  
                        });  
                        return false;  
                    }  
                }  
                
                // Confirmação antes de salvar as alterações  
                e.preventDefault();  
                Swal.fire({  
                    title: 'Confirmar alterações?',  
                    text: "Você está prestes a atualizar esta nota devolutiva.",  
                    icon: 'question',  
                    showCancelButton: true,  
                    confirmButtonColor: '#3085d6',  
                    cancelButtonColor: '#d33',  
                    confirmButtonText: 'Sim, salvar',  
                    cancelButtonText: 'Cancelar'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        // Se confirmado, envie o formulário  
                        this.submit();  
                    }  
                });  
            });  
        });  
        
        // Função para carregar notas devolutivas anteriores  
        let dataTable = null;  
        
        window.carregarNotas = function() {  
            console.log('Iniciando carregamento de notas devolutivas');  
            
            if (dataTable) {  
                dataTable.destroy();  
            }  
            
            $('#notasTableBody').html(`  
                <tr>  
                    <td colspan="5" class="text-center">  
                        <div class="spinner-border text-primary" role="status">  
                            <span class="sr-only">Carregando...</span>  
                        </div>  
                    </td>  
                </tr>  
            `);  
            
            $.ajax({  
                url: 'listar_notas_devolutivas.php',  
                method: 'GET',  
                dataType: 'json',  
                success: function(response) {  
                    console.log('Dados recebidos:', response);  
                    
                    if (!response.data || !Array.isArray(response.data)) {  
                        $('#notasTableBody').html(`  
                            <tr>  
                                <td colspan="5" class="text-center">Nenhuma nota devolutiva encontrada</td>  
                            </tr>  
                        `);  
                        return;  
                    }  
                    
                    let html = '';  
                    response.data.forEach(function(nota) {  
                        const numero = $('<div>').text(nota.numero).html();  
                        const apresentante = $('<div>').text(nota.apresentante).html();  
                        const titulo = $('<div>').text(nota.titulo).html();  
                        
                        html += `  
                            <tr>  
                                <td><div class="cell-content">${numero}</div></td>  
                                <td><div class="cell-content">${nota.data}</div></td>  
                                <td><div class="cell-content" title="${apresentante}">${apresentante.length > 40 ? apresentante.substring(0, 37) + "..." : apresentante}</div></td>  
                                <td><div class="cell-content" title="${titulo}">${titulo.length > 65 ? titulo.substring(0, 62) + "..." : titulo}</div></td>  
                                <td>  
                                    <div class="action-buttons">  
                                        <button class="btn-action btn-view" onclick="verCorpo('${numero}')" title="Visualizar nota">  
                                            <i class="fa fa-eye"></i>  
                                        </button>  
                                        <button class="btn-action btn-use" onclick="usarModelo('${numero}')" title="Usar como modelo">  
                                            <i class="fa fa-copy"></i>  
                                        </button>  
                                    </div>  
                                </td>  
                            </tr>  
                        `;  
                    });  
                    
                    $('#notasTableBody').html(html);  
                    initializeDataTable();  
                },  
                error: function(xhr, status, error) {  
                    console.error('Erro na requisição:', error);  
                    $('#notasTableBody').html(`  
                        <tr>  
                            <td colspan="5" class="text-center text-danger">  
                                <i class="fas fa-exclamation-triangle me-2"></i>  
                                Erro ao carregar notas devolutivas. Por favor, tente novamente.  
                            </td>  
                        </tr>  
                    `);  
                }  
            });  
        };  
        
        function initializeDataTable() {  
            dataTable = $('#notasTable').DataTable({  
                responsive: true,  
                language: {  
                    url: '../style/Portuguese-Brasil.json'  
                },  
                pageLength: 15,  
                order: [[1, 'asc']],  
                columnDefs: [  
                    { orderable: false, targets: 4 }  
                ],  
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +  
                     "<'row'<'col-sm-12'tr>>" +  
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",  
                lengthMenu: [[15, 25, 50, 100], [15, 25, 50, "Todos"]]  
            });  
        }  
        
        window.verCorpo = function(numero) {  
            $.ajax({  
                url: 'get_nota_details.php',  
                method: 'GET',  
                data: { numero: numero },  
                dataType: 'json',  
                beforeSend: function() {  
                    Swal.fire({  
                        title: 'Carregando...',  
                        html: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Carregando...</span></div>',  
                        showConfirmButton: false,  
                        allowOutsideClick: false,  
                        didOpen: () => {  
                            Swal.showLoading();  
                        }  
                    });  
                },  
                success: function(response) {  
                    Swal.fire({  
                        title: `Nota Devolutiva ${numero}`,  
                        html: `<div class="text-start p-3">${response.corpo}</div>`,  
                        width: '80%',  
                        confirmButtonText: 'Fechar',  
                        customClass: {  
                            container: 'swal-large-text'  
                        }  
                    });  
                },  
                error: function() {  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro',  
                        text: 'Não foi possível carregar os detalhes da nota devolutiva'  
                    });  
                }  
            });  
        };  
        
        window.usarModelo = function(numero) {  
            $.ajax({  
                url: 'get_nota_details.php',  
                method: 'GET',  
                data: { numero: numero },  
                dataType: 'json',  
                beforeSend: function() {  
                    Swal.fire({  
                        title: 'Carregando modelo...',  
                        html: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Carregando...</span></div>',  
                        showConfirmButton: false,  
                        allowOutsideClick: false,  
                        allowEscapeKey: false,  
                        didOpen: () => {  
                            Swal.showLoading();  
                        }  
                    });  
                },  
                success: function(response) {  
                    try {  
                        // Substituir apenas os campos específicos do modelo  
                        Swal.fire({  
                            title: 'Substituir campos',  
                            text: "Você deseja substituir os campos da nota atual pelo modelo selecionado?",  
                            icon: 'question',  
                            showCancelButton: true,  
                            confirmButtonColor: '#3085d6',  
                            cancelButtonColor: '#d33',  
                            confirmButtonText: 'Sim, usar modelo',  
                            cancelButtonText: 'Cancelar'  
                        }).then((result) => {  
                            if (result.isConfirmed) {  
                                // Preencher apenas os campos necessários  
                                $('#titulo').val(response.titulo || '');  
                                $('#origem_titulo').val(response.origem_titulo || '');  
                                
                                if (typeof CKEDITOR !== 'undefined') {  
                                    if (CKEDITOR.instances.corpo) {  
                                        CKEDITOR.instances.corpo.setData(response.corpo || '');  
                                    }  
                                    
                                    if (CKEDITOR.instances.prazo_cumprimento) {  
                                        CKEDITOR.instances.prazo_cumprimento.setData(response.prazo_cumprimento || '');  
                                    }  
                                }  
                                
                                // Fecha o modal do bootstrap  
                                $('#notasAnterioresModal').modal('hide');  
                                
                                Swal.fire({  
                                    icon: 'success',  
                                    title: 'Modelo carregado!',  
                                    text: 'O conteúdo do modelo foi aplicado à nota atual.',  
                                    timer: 1500,  
                                    showConfirmButton: false  
                                });  
                            } else {  
                                Swal.close();  
                            }  
                        });  
                    } catch (error) {  
                        console.error('Erro ao preencher campos:', error);  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro',  
                            text: 'Erro ao preencher os campos do formulário: ' + error.message  
                        });  
                    }  
                },  
                error: function(xhr, status, error) {  
                    console.error('Erro na requisição:', error);  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro',  
                        text: 'Não foi possível carregar o modelo da nota devolutiva: ' + error  
                    });  
                }  
            });  
        };  
    </script>