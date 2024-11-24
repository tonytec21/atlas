<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Processo Administrativo</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <style>
        .custom-container {
            max-width: 800px;
            margin: auto;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .btn-central {
            background: #6c927c;
            border: #6c927c;
            color: #fff;
        }
        .btn-central:hover {
            background: #638873;
            border: #638873;
            color: #fff;
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Cadastro de Processo Administrativo</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='busca_processo_adm.php'">
                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar
                </button>
            </div>
        </div>
        <hr>
        <form id="cadastroProcessoAdm">
            <!-- Primeira Linha -->
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="processoAdm">Número do Processo</label>
                    <input type="text" class="form-control" id="processoAdm" name="processoAdm" placeholder="Ex.: 1234/2024" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="dataDePublicacao">Data de Publicação</label>
                    <input type="date" class="form-control" id="dataDePublicacao" name="dataDePublicacao" required>
                </div>

                <div class="form-group col-md-3">
                    <label for="classificacaoIndividual">Classificação Individual</label>
                    <input type="text" class="form-control" id="classificacaoIndividual" name="classificacaoIndividual" placeholder="Ex.: Residencial" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="direitoRealOutorgado">Direito Real Outorgado</label>
                    <input type="text" class="form-control" id="direitoRealOutorgado" name="direitoRealOutorgado" placeholder="Ex.: Uso especial para moradia" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="municipio">Município</label>
                    <input type="text" class="form-control" id="municipio" name="municipio" placeholder="Ex.: São Luís" required>
                </div>
                <div class="form-group col-md-9">
                    <label for="qualificacaoMunicipio">Qualificação do Município</label>
                    <input type="text" class="form-control" id="qualificacaoMunicipio" name="qualificacaoMunicipio" rows="3" placeholder="Ex.: Informações detalhadas do município" required></input>
                </div>
                <div class="form-group col-md-4">
                    <label for="representante">Representante</label>
                    <input type="text" class="form-control" id="representante" name="representante" placeholder="Nome completo" required>
                </div>
                <div class="form-group col-md-8">
                    <label for="qualificacaoRepresentante">Qualificação do Representante</label>
                    <input type="text"  class="form-control" id="qualificacaoRepresentante" name="qualificacaoRepresentante" rows="3" placeholder="Ex.: CPF, RG, Endereço" required></input>
                </div>
                <div class="form-group col-md-4">
                    <label for="responsavelTecnico">Responsável Técnico</label>
                    <input type="text" class="form-control" id="responsavelTecnico" name="responsavelTecnico" placeholder="Ex.: Nome completo" required>
                </div>
                <div class="form-group col-md-8">
                    <label for="qualificacaoResponsavelTecnico">Qualificação do Responsável Técnico</label>
                    <input type="text" class="form-control" id="qualificacaoResponsavelTecnico" name="qualificacaoResponsavelTecnico" rows="3" placeholder="Ex.: Informações detalhadas" required></input>
                </div>
                <div class="form-group col-md-2">
                    <label for="edital">Número do Edital</label>
                    <input type="text" class="form-control" id="edital" name="edital" placeholder="Ex.: 2024/001" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="dataEdital">Data do Edital</label>
                    <input type="date" class="form-control" id="dataEdital" name="dataEdital" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="matriculaMae">Matrícula Mãe</label>
                    <input type="text" class="form-control" id="matriculaMae" name="matriculaMae" placeholder="Ex.: Matrícula de origem" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="oficialDoRegistro">Oficial do Registro</label>
                    <input type="text" class="form-control" id="oficialDoRegistro" name="oficialDoRegistro" placeholder="Ex.: Nome completo do oficial" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="cargoOficial">Cargo do Oficial</label>
                    <input type="text" class="form-control" id="cargoOficial" name="cargoOficial" placeholder="Ex.: Oficial de Registro" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Salvar</button>
        </form>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#cadastroProcessoAdm').on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                url: 'salvar_processo_adm.php',
                method: 'POST',
                data: formData,
                success: function (response) {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        Swal.fire('Sucesso!', 'Processo administrativo cadastrado com sucesso.', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Erro!', data.message || 'Erro ao salvar o processo administrativo.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro na requisição.', 'error');
                }
            });
        });
    });
</script>
</body>
</html>
