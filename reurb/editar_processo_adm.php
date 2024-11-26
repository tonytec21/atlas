<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se o ID do processo foi enviado
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID do processo não fornecido.";
    exit;
}

// Busca os dados do processo administrativo pelo ID
$query = $conn->prepare("SELECT * FROM cadastro_de_processo_adm WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();
$processo = $result->fetch_assoc();

if (!$processo) {
    echo "Processo não encontrado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Processo Administrativo</title>
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
            <h3 class="mb-0">Editar Processo Administrativo</h3>
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

        <form id="editarProcessoForm">
            <input type="hidden" name="id" value="<?= $processo['id'] ?>">

            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="processo_adm">Número do Processo</label>
                    <input type="text" class="form-control" id="processo_adm" name="processo_adm" value="<?= htmlspecialchars($processo['processo_adm']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="data_de_publicacao">Data de Publicação</label>
                    <input type="date" class="form-control" id="data_de_publicacao" name="data_de_publicacao" value="<?= htmlspecialchars($processo['data_de_publicacao']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="classificacao_individual">Classificação Individual</label>
                    <input type="text" class="form-control" id="classificacao_individual" name="classificacao_individual" value="<?= htmlspecialchars($processo['classificacao_individual']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="direito_real_outorgado">Direito Real Outorgado</label>
                    <input type="text" class="form-control" id="direito_real_outorgado" name="direito_real_outorgado" value="<?= htmlspecialchars($processo['direito_real_outorgado']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="municipio">Município</label>
                    <input type="text" class="form-control" id="municipio" name="municipio" value="<?= htmlspecialchars($processo['municipio']) ?>" required>
                </div>
                <div class="form-group col-md-9">
                    <label for="qualificacao_municipio">Qualificação do Município</label>
                    <input type="text" class="form-control" id="qualificacao_municipio" name="qualificacao_municipio" value="<?= htmlspecialchars($processo['qualificacao_municipio']) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="representante">Representante</label>
                    <input type="text" class="form-control" id="representante" name="representante" value="<?= htmlspecialchars($processo['representante']) ?>">
                </div>
                <div class="form-group col-md-8">
                    <label for="qualificacao_representante">Qualificação do Representante</label>
                    <input type="text" class="form-control" id="qualificacao_representante" name="qualificacao_representante" value="<?= htmlspecialchars($processo['qualificacao_representante']) ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="responsavel_tecnico">Responsável Técnico</label>
                    <input type="text" class="form-control" id="responsavel_tecnico" name="responsavel_tecnico" value="<?= htmlspecialchars($processo['responsavel_tecnico']) ?>" required>
                </div>
                <div class="form-group col-md-8">
                    <label for="qualificacao_responsavel_tecnico">Qualificação do Responsável Técnico</label>
                    <input type="text" class="form-control" id="qualificacao_responsavel_tecnico" name="qualificacao_responsavel_tecnico" value="<?= htmlspecialchars($processo['qualificacao_responsavel_tecnico']) ?>" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="edital">Edital</label>
                    <input type="text" class="form-control" id="edital" name="edital" value="<?= htmlspecialchars($processo['edital']) ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="data_edital">Data do Edital</label>
                    <input type="date" class="form-control" id="data_edital" name="data_edital" value="<?= htmlspecialchars($processo['data_edital']) ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="matricula_mae">Matrícula Mãe</label>
                    <input type="text" class="form-control" id="matricula_mae" name="matricula_mae" value="<?= htmlspecialchars($processo['matricula_mae']) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="oficial_do_registro">Oficial do Registro</label>
                    <input type="text" class="form-control" id="oficial_do_registro" name="oficial_do_registro" value="<?= htmlspecialchars($processo['oficial_do_registro']) ?>" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="cargo_oficial">Cargo do Oficial</label>
                    <input type="text" class="form-control" id="cargo_oficial" name="cargo_oficial" value="<?= htmlspecialchars($processo['cargo_oficial']) ?>" required>
                </div>
            </div>


            <button type="submit" class="btn btn-primary w-100">Salvar Alterações</button>
        </form>
    </div>

</div>

    <script>
        $(document).ready(function () {
            $('#editarProcessoForm').on('submit', function (e) {
                e.preventDefault();

                const formData = $(this).serialize();
                $.ajax({
                    url: 'update_processo_adm.php',
                    method: 'POST',
                    data: formData,
                    success: function (response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;

                            if (data.success) {
                                Swal.fire('Sucesso!', data.message || 'Processo atualizado com sucesso.', 'success').then(() => {
                                    window.location.href = 'busca_processo_adm.php';
                                });
                            } else {
                                Swal.fire('Erro!', data.message || 'Erro ao atualizar o processo.', 'error');
                            }
                        } catch (error) {
                            console.error('Erro ao processar a resposta:', error);
                            Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro!', 'Erro ao atualizar o processo.', 'error');
                    }
                });
            });
        });
    </script>
</body>

</html>
