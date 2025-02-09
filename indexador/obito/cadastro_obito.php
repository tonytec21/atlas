<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Cadastro de Óbito</title>  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">  
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.min.css" rel="stylesheet">  
    <style>  
        .cidade-input {  
            background-color: #f8f9fa;  
            cursor: pointer;  
        }  
        #resultadoCidades {  
            max-height: 300px;  
            overflow-y: auto;  
        }  
        .loading-spinner {  
            display: none;  
            margin-left: 10px;  
        }  
        .required-label::after {  
            content: "*";  
            color: red;  
            margin-left: 4px;  
        }  

        .drop-zone {  
            max-width: 100%;  
            height: 200px;  
            padding: 25px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            text-align: center;  
            font-size: 1.2rem;  
            font-weight: 500;  
            cursor: pointer;  
            color: #777;  
            border: 2px dashed #ddd;  
            border-radius: 10px;  
            background-color: #f8f9fa;  
            transition: all 0.3s ease;  
        }  

        .drop-zone:hover {  
            border-color: #0d6efd;  
            color: #0d6efd;  
            background-color: #f1f7ff;  
        }  

        .drop-zone.drop-zone--over {  
            border-style: solid;  
            background-color: #e9f2ff;  
        }  

        .drop-zone__input {  
            display: none;  
        }  

        .drop-zone__thumb {  
            width: auto;  
            height: 100%;  
            border-radius: 10px;  
            overflow: hidden;  
            background-color: #fff;  
            background-size: cover;  
            position: relative;  
            display: flex;  
            align-items: center;  
            margin: 5px;  
        }  

        .drop-zone__files {  
            display: flex;  
            flex-wrap: wrap;  
            gap: 10px;  
            margin-top: 10px;  
        }  

        .drop-zone__file {  
            display: flex;  
            align-items: center;  
            padding: 8px 12px;  
            background: #fff;  
            border: 1px solid #ddd;  
            border-radius: 5px;  
            font-size: 0.9rem;  
        }  

        .drop-zone__file-icon {  
            color: #dc3545;  
            margin-right: 8px;  
        }  

        .drop-zone__file-name {  
            max-width: 200px;  
            overflow: hidden;  
            text-overflow: ellipsis;  
            white-space: nowrap;  
        }  

        .drop-zone__file-remove {  
            margin-left: 8px;  
            color: #dc3545;  
            cursor: pointer;  
            border: none;  
            background: none;  
            padding: 0 4px;  
        }  

        .drop-zone__prompt {  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            gap: 10px;  
        }  

        .drop-zone__prompt i {  
            font-size: 2.5rem;  
            color: #0d6efd;  
        }
    </style>  
</head>  
<body>  
    <div class="container mt-4">  
        <h2>Cadastro de Óbito</h2>  
        <form id="formObito" method="POST" enctype="multipart/form-data">  
            <div class="row">  
                <div class="col-md-4 mb-3">  
                    <label for="livro" class="form-label required-label">Livro</label>  
                    <input type="text" class="form-control" id="livro" name="livro" required>  
                </div>  
                <div class="col-md-4 mb-3">  
                    <label for="folha" class="form-label required-label">Folha</label>  
                    <input type="text" class="form-control" id="folha" name="folha" required>  
                </div>  
                <div class="col-md-4 mb-3">  
                    <label for="termo" class="form-label required-label">Termo</label>  
                    <input type="text" class="form-control" id="termo" name="termo" required>  
                </div>  
            </div>  

            <div class="row">  
                <div class="col-md-4 mb-3">  
                    <label for="data_registro" class="form-label required-label">Data do Registro</label>  
                    <input type="date" class="form-control" id="data_registro" name="data_registro" required>  
                </div>   
                <div class="col-md-4 mb-3">  
                    <label for="data_obito" class="form-label required-label">Data do Óbito</label>  
                    <input type="date" class="form-control" id="data_obito" name="data_obito" required>  
                </div>  
                <div class="col-md-4 mb-3">  
                    <label for="hora_obito" class="form-label required-label">Hora do Óbito</label>  
                    <input type="time" class="form-control" id="hora_obito" name="hora_obito" required>  
                </div>  
            </div>  

            <div class="row">  
                <div class="col-md-8 mb-3">  
                    <label for="nome_registrado" class="form-label required-label">Nome do Registrado</label>  
                    <input type="text" class="form-control" id="nome_registrado" name="nome_registrado" required>  
                </div>  
                <div class="col-md-4 mb-3">  
                    <label for="data_nascimento" class="form-label required-label">Data de Nascimento</label>  
                    <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" required>  
                </div> 
            </div>  

            <div class="row">  
                <div class="col-md-6 mb-3">  
                    <label for="nome_pai" class="form-label">Nome do Pai</label>  
                    <input type="text" class="form-control" id="nome_pai" name="nome_pai">  
                </div>  
                <div class="col-md-6 mb-3">  
                    <label for="nome_mae" class="form-label">Nome da Mãe</label>  
                    <input type="text" class="form-control" id="nome_mae" name="nome_mae">  
                </div>  
            </div>  

            <div class="row">  
                <div class="col-md-6 mb-3">  
                    <label for="cidade_endereco" class="form-label required-label">Cidade do Endereço</label>  
                    <input type="text" class="form-control cidade-input" id="cidade_endereco"   
                           name="cidade_endereco" placeholder="Clique para pesquisar" readonly required>  
                    <input type="hidden" id="ibge_cidade_endereco" name="ibge_cidade_endereco">  
                </div>  
                <div class="col-md-6 mb-3">  
                    <label for="cidade_obito" class="form-label required-label">Cidade do Óbito</label>  
                    <input type="text" class="form-control cidade-input" id="cidade_obito"   
                           name="cidade_obito" placeholder="Clique para pesquisar" readonly required>  
                    <input type="hidden" id="ibge_cidade_obito" name="ibge_cidade_obito">  
                </div>  
            </div>  

            <div class="row">  
                <div class="col-md-12 mb-3">  
                    <label for="anexos" class="form-label">Anexos (PDF)</label>  
                    <div class="drop-zone">  
                        <span class="drop-zone__prompt">  
                            <i class="fas fa-file-upload"></i>  
                            <span>Arraste arquivos PDF aqui ou clique para selecionar</span>  
                        </span>  
                        <input type="file" name="anexos[]" class="drop-zone__input" id="anexos" multiple accept=".pdf">  
                    </div>  
                    <div class="drop-zone__files"></div>  
                </div>  
            </div>  

            <div class="row mt-3">  
                <div class="col-12">  
                    <button type="submit" class="btn btn-primary">Salvar</button>  
                    <button type="reset" class="btn btn-secondary">Limpar</button>  
                </div>  
            </div>  
        </form>  
    </div>  

    <!-- Modal de Pesquisa de Cidade -->  
    <div class="modal fade" id="cidadeModal" tabindex="-1">  
        <div class="modal-dialog modal-lg">  
            <div class="modal-content">  
                <div class="modal-header">  
                    <h5 class="modal-title">Pesquisar Cidade</h5>  
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>  
                </div>  
                <div class="modal-body">  
                    <div class="input-group mb-3">  
                        <input type="text" class="form-control" id="cidade_pesquisa"   
                               placeholder="Digite o nome da cidade ou UF (mínimo 3 letras)">  
                        <div class="loading-spinner">  
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>  
                        </div>  
                    </div>  
                    <div id="resultadoCidades" class="list-group">  
                        <!-- Resultados serão inseridos aqui -->  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>  

    <script>  
        $(document).ready(function() {  
            let cidadeModal = new bootstrap.Modal(document.getElementById('cidadeModal'));  
            let campoAtual = null;  
            let timeoutId = null;  
            let todasCidades = [];  

            function carregarCidades() {  
                if (todasCidades.length === 0) {  
                    $('.loading-spinner').show();  
                    $.get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios', {  
                        orderBy: "nome"  
                    })  
                    .done(function(cidades) {  
                        todasCidades = cidades;  
                        $('.loading-spinner').hide();  
                    })  
                    .fail(function() {  
                        $('.loading-spinner').hide();  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro!',  
                            text: 'Erro ao carregar lista de cidades.'  
                        });  
                    });  
                }  
            }  

            function removerAcentos(texto) {  
                return texto.normalize('NFD')  
                        .replace(/[\u0300-\u036f]/g, '')  
                        .toLowerCase();  
            }  

            function filtrarCidades(termo) {  
                if (termo.length < 3) {  
                    $('#resultadoCidades').html(`  
                        <div class="list-group-item text-muted">  
                            Digite pelo menos 3 caracteres para pesquisar  
                        </div>  
                    `);  
                    return;  
                }  

                const termoPesquisa = removerAcentos(termo);  

                const resultados = todasCidades.filter(cidade => {  
                    const nomeSemAcento = removerAcentos(cidade.nome);  
                    const ufSemAcento = removerAcentos(cidade.microrregiao.mesorregiao.UF.sigla);  
                    
                    return nomeSemAcento.includes(termoPesquisa) ||   
                        ufSemAcento.includes(termoPesquisa);  
                }).slice(0, 100);  

                $('#resultadoCidades').empty();  
                if (resultados.length > 0) {  
                    resultados.forEach(cidade => {  
                        $('#resultadoCidades').append(`  
                            <a href="#" class="list-group-item list-group-item-action cidade-item"   
                            data-nome="${cidade.nome}/${cidade.microrregiao.mesorregiao.UF.sigla}"  
                            data-ibge="${cidade.id}">  
                                ${cidade.nome}/${cidade.microrregiao.mesorregiao.UF.sigla}  
                            </a>  
                        `);  
                    });  
                } else {  
                    $('#resultadoCidades').append(`  
                        <div class="list-group-item text-muted">  
                            Nenhuma cidade encontrada  
                        </div>  
                    `);  
                }  
            }

            $('.cidade-input').click(function() {  
                campoAtual = $(this).attr('id');  
                $('#cidade_pesquisa').val('');  
                $('#resultadoCidades').empty();  
                cidadeModal.show();  
                carregarCidades();  
            });  

            $('#cidade_pesquisa').on('input', function() {  
                const termo = $(this).val();  
                
                if (timeoutId) {  
                    clearTimeout(timeoutId);  
                }  

                timeoutId = setTimeout(() => {  
                    filtrarCidades(termo);  
                }, 300);  
            });  

            $(document).on('click', '.cidade-item', function(e) {  
                e.preventDefault();  
                const nome = $(this).data('nome');  
                const ibge = $(this).data('ibge');  

                if (campoAtual === 'cidade_endereco') {  
                    $('#cidade_endereco').val(nome);  
                    $('#ibge_cidade_endereco').val(ibge);  
                } else if (campoAtual === 'cidade_obito') {  
                    $('#cidade_obito').val(nome);  
                    $('#ibge_cidade_obito').val(ibge);  
                }  

                cidadeModal.hide();  
            });  

            $('#formObito').on('submit', function(e) {  
                e.preventDefault();  
                
                if (!$('#ibge_cidade_endereco').val() || !$('#ibge_cidade_obito').val()) {  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro!',  
                        text: 'Por favor, selecione as cidades através da pesquisa.'  
                    });  
                    return;  
                }  
                
                let formData = new FormData(this);  
                
                $.ajax({  
                    url: 'salvar_obito.php',  
                    type: 'POST',  
                    data: formData,  
                    processData: false,  
                    contentType: false,  
                    success: function(response) {  
                        let data = JSON.parse(response);  
                        if(data.status === 'success') {  
                            Swal.fire({  
                                icon: 'success',  
                                title: 'Sucesso!',  
                                text: data.message,  
                                showConfirmButton: false,  
                                timer: 1500  
                            }).then(() => {  
                                $('#formObito')[0].reset();  
                                $('#ibge_cidade_endereco').val('');  
                                $('#ibge_cidade_obito').val('');  
                            });  
                        } else {  
                            Swal.fire({  
                                icon: 'error',  
                                title: 'Erro!',  
                                text: data.message  
                            });  
                        }  
                    },  
                    error: function() {  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro!',  
                            text: 'Ocorreu um erro ao processar a requisição.'  
                        });  
                    }  
                });  
            });  
        });  

        // Função para formatar o tamanho do arquivo  
        function formatFileSize(bytes) {  
            if (bytes === 0) return '0 Bytes';  
            const k = 1024;  
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
            const i = Math.floor(Math.log(bytes) / Math.log(k));  
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
        }  

        // Função para atualizar a lista de arquivos  
        function updateFileList(files) {  
            const fileList = $('.drop-zone__files');  
            fileList.empty();  

            Array.from(files).forEach((file, index) => {  
                const fileElement = $(`  
                    <div class="drop-zone__file">  
                        <i class="fas fa-file-pdf drop-zone__file-icon"></i>  
                        <span class="drop-zone__file-name">${file.name}</span>  
                        <span class="text-muted ms-2">(${formatFileSize(file.size)})</span>  
                        <button type="button" class="drop-zone__file-remove" data-index="${index}">  
                            <i class="fas fa-times"></i>  
                        </button>  
                    </div>  
                `);  
                fileList.append(fileElement);  
            });  
        }  

        // Configuração da Drop Zone  
        const dropZone = document.querySelector('.drop-zone');  
        const input = dropZone.querySelector('.drop-zone__input');  

        dropZone.addEventListener('click', () => input.click());  

        input.addEventListener('change', (e) => {  
            if (input.files.length) {  
                updateFileList(input.files);  
            }  
        });  

        dropZone.addEventListener('dragover', (e) => {  
            e.preventDefault();  
            dropZone.classList.add('drop-zone--over');  
        });  

        ['dragleave', 'dragend'].forEach(type => {  
            dropZone.addEventListener(type, (e) => {  
                dropZone.classList.remove('drop-zone--over');  
            });  
        });  

        dropZone.addEventListener('drop', (e) => {  
            e.preventDefault();  
            dropZone.classList.remove('drop-zone--over');  

            if (e.dataTransfer.files.length) {  
                input.files = e.dataTransfer.files;  
                updateFileList(input.files);  
            }  
        });  

        // Remover arquivo da lista  
        $(document).on('click', '.drop-zone__file-remove', function() {  
            const index = $(this).data('index');  
            const dt = new DataTransfer();  
            const input = document.querySelector('.drop-zone__input');  
            const { files } = input;  

            for (let i = 0; i < files.length; i++) {  
                if (i !== index) {  
                    dt.items.add(files[i]);  
                }  
            }  

            input.files = dt.files;  
            updateFileList(input.files);  
        });
    </script>  
</body>  
</html>