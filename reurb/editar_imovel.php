<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Obtém o ID do imóvel a ser editado
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID do imóvel não fornecido.";
    exit;
}

// Busca os dados do imóvel no banco
$query = $conn->prepare("SELECT * FROM cadastro_de_imoveis WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "Imóvel não encontrado.";
    exit;
}

$imovel = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Imóvel</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <style>
        .form-group {
            margin-bottom: 1rem;
        }

        .hidden {
            display: none;
        }

        .custom-container {
            max-width: 1000px;
        }

        .w-100 {
            margin-bottom: 31px;
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

<body>
    <?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Editar Imóvel</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='busca_imoveis.php'">
                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar</button>
                </button>
            </div>
        </div>
        <hr>
        <form id="editarImovelForm">
            <input type="hidden" name="id" value="<?= $imovel['id'] ?>">

            <!-- Endereço -->
            <h5>Endereço</h5>
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="tipoLogradouro">Tipo de Logradouro</label>
                    <select class="form-control" id="tipoLogradouro" name="tipoLogradouro" required>
                        <option value="">Selecione</option>
                        <?php
                        $jsonFile = file_get_contents(__DIR__ . '/tipo_logradouro.json');
                        $tiposLogradouro = json_decode($jsonFile, true);

                        if ($tiposLogradouro && is_array($tiposLogradouro)) {
                            foreach ($tiposLogradouro as $tipo) {
                                if (!empty($tipo['ID']) && !empty($tipo['NOME'])) {
                                    $selected = ($tipo['ID'] == $imovel['tipo_logradouro']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($tipo['ID'], ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($tipo['NOME'], ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                            }
                        } else {
                            echo '<option value="">Erro ao carregar os tipos de logradouro</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-md-4">
                    <label for="cep">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" value="<?= $imovel['cep'] ?>" maxlength="9" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="logradouro">Logradouro</label>
                    <input type="text" class="form-control" id="logradouro" name="logradouro" value="<?= $imovel['logradouro'] ?>" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="quadra">Quadra</label>
                    <input type="text" class="form-control" id="quadra" name="quadra" value="<?= $imovel['quadra'] ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="numero">Número</label>
                    <input type="text" class="form-control" id="numero" name="numero" value="<?= $imovel['numero'] ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="bairro">Bairro</label>
                    <input type="text" class="form-control" id="bairro" name="bairro" value="<?= $imovel['bairro'] ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="cidade">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" value="<?= $imovel['cidade'] ?>" required>
                </div>
                
            </div>

            <!-- Dados do Imóvel -->
            <h5>Dados do Imóvel</h5>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="areaDoLote">Área do Lote (m²)</label>
                    <input type="number" class="form-control" id="areaDoLote" name="area_do_lote" step="0.01" value="<?= $imovel['area_do_lote'] ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="perimetro">Perímetro (m)</label>
                    <input type="number" class="form-control" id="perimetro" name="perimetro" step="0.01" value="<?= $imovel['perimetro'] ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="areaConstruida">Área Construída (m²)</label>
                    <input type="number" class="form-control" id="areaConstruida" name="areaConstruida" step="0.01" value="<?= $imovel['area_construida'] ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="processoAdm">Processo Administrativo</label>
                    <select class="form-control" id="processoAdm" name="processoAdm" required>
                        <option value="">Selecione um processo</option>
                        <?php
                        $query = "SELECT processo_adm FROM cadastro_de_processo_adm";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $selected = ($row['processo_adm'] == $imovel['processo_adm']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($row['processo_adm'], ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($row['processo_adm'], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        } else {
                            echo '<option value="">Nenhum processo encontrado</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group col-md-12">
                    <label for="memorialDescritivo">Memorial Descritivo</label>
                    <textarea class="form-control" id="memorialDescritivo" name="memorialDescritivo" rows="3"><?= $imovel['memorial_descritivo'] ?></textarea>
                </div>
            </div>

            <!-- Proprietário -->
            <h5>Proprietário</h5>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="proprietarioCpf">CPF do Proprietário</label>
                    <input type="text" class="form-control" id="proprietarioCpf" name="proprietarioCpf" maxlength="14" value="<?= $imovel['proprietario_cpf'] ?>" required>
                </div>
                <div class="form-group col-md-8">
                    <label for="proprietarioNome">Nome do Proprietário</label>
                    <input type="text" class="form-control" id="proprietarioNome" name="proprietarioNome" value="<?= $imovel['proprietario_nome'] ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="cpfConjuge">CPF do Cônjuge</label>
                    <input type="text" class="form-control" id="cpfConjuge" name="cpfConjuge" maxlength="14" value="<?= $imovel['cpf_conjuge'] ?>">
                </div>
                <div class="form-group col-md-8">
                    <label for="nomeConjuge">Nome do Cônjuge</label>
                    <input type="text" class="form-control" id="nomeConjuge" name="nomeConjuge" value="<?= $imovel['nome_conjuge'] ?>" readonly>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Salvar Alterações</button>
        </form>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Máscaras para os campos de CPF e CEP
        $('#cep').mask('00000-000');
        $('#proprietarioCpf, #cpfConjuge').mask('000.000.000-00');

        // Habilita/Desabilita o botão Salvar
        function toggleSalvarButton(enable) {
            $('#editarImovelForm button[type="submit"]').prop('disabled', !enable);
        }

        // Consulta CPF do Proprietário
        $('#proprietarioCpf').on('blur', function () {
            const cpf = $(this).val().replace(/\D/g, ''); // Remove formatação

            if (cpf.length !== 11) {
                Swal.fire('Erro!', 'CPF inválido. Verifique e tente novamente.', 'error');
                $('#proprietarioNome').val('');
                $('#conjugeFields').addClass('hidden');
                toggleSalvarButton(false);
                return;
            }

            $.ajax({
                url: 'buscar_proprietario.php',
                method: 'POST',
                data: { cpf },
                success: function (response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;

                        if (!data.success) {
                            Swal.fire('Atenção!', data.message || 'CPF não encontrado.', 'warning');
                            $('#proprietarioNome').val('');
                            $('#conjugeFields').addClass('hidden');
                            toggleSalvarButton(false);
                            return;
                        }

                        // Preenche os dados do proprietário
                        $('#proprietarioNome').val(data.nome);
                        $('#cpfProprietarioFeedback').addClass('hidden');
                        toggleSalvarButton(true);

                        // Verifica se há cônjuge
                        if (data.estado_civil === 'casado') {
                            $('#conjugeFields').removeClass('hidden');
                            $('#nomeConjuge').val(data.conjuge || '');
                            $('#cpfConjuge').val(data.cpfConjuge || '');
                        } else {
                            $('#conjugeFields').addClass('hidden');
                            $('#nomeConjuge, #cpfConjuge').val('');
                        }
                    } catch (error) {
                        console.error('Erro ao processar a resposta:', response, error);
                        Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                        toggleSalvarButton(false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    Swal.fire('Erro!', 'Erro ao buscar os dados.', 'error');
                    toggleSalvarButton(false);
                }
            });
        });

        // Consulta CPF do Cônjuge
        $('#cpfConjuge').on('blur', function () {
            const cpf = $(this).val().replace(/\D/g, ''); // Remove formatação
            if (cpf.length !== 11) {
                Swal.fire('Erro!', 'CPF inválido. Verifique e tente novamente.', 'error');
                $('#nomeConjuge').val('');
                return;
            }

            $.ajax({
                url: 'buscar_proprietario.php',
                method: 'POST',
                data: { cpf },
                success: function (response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;

                        if (!data.success) {
                            Swal.fire('Atenção!', data.message || 'CPF não encontrado.', 'warning');
                            $('#nomeConjuge').val('');
                            return;
                        }

                        $('#cpfConjugeFeedback').addClass('hidden');
                        $('#nomeConjuge').val(data.nome);
                    } catch (error) {
                        console.error('Erro ao processar a resposta:', response, error);
                        Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    Swal.fire('Erro!', 'Erro ao buscar os dados.', 'error');
                }
            });
        });

        // Submissão do formulário de edição
        $('#editarImovelForm').on('submit', function (e) {
            e.preventDefault();

            // Tratamento do campo Memorial Descritivo
            let memorialDescritivo = $('#memorialDescritivo').val()
                .replace(/(\r\n|\n|\r)/gm, ' ')
                .replace(/'/g, '&apos;')   
                .replace(/"/g, '&quot;');  
            $('#memorialDescritivo').val(memorialDescritivo);

            const formData = $(this).serialize();
            $.ajax({
                url: 'atualizar_imovel.php',
                method: 'POST',
                data: formData,
                success: function (response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;

                        if (data.success) {
                            Swal.fire('Sucesso!', 'Imóvel atualizado com sucesso.', 'success').then(() => window.location.href = 'busca_imoveis.php');
                        } else {
                            Swal.fire('Erro!', data.message || 'Erro ao salvar os dados.', 'error');
                        }
                    } catch (error) {
                        console.error('Erro ao processar a resposta:', response, error);
                        Swal.fire('Erro!', 'Erro ao salvar os dados.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao salvar os dados.', 'error');
                }
            });
        });
    });

    // Consulta CEP
    $('#cep').on('blur', function () {
        const cep = $(this).val().replace(/\D/g, '');
        if (cep.length !== 8) {
            $('#cepFeedback').removeClass('hidden').text('CEP inválido.');
            limparCamposEndereco();
            return;
        }

        $.ajax({
            url: `https://viacep.com.br/ws/${cep}/json/`,
            method: 'GET',
            success: function (data) {
                if (data.erro) {
                    $('#cepFeedback').removeClass('hidden').text('CEP não encontrado.');
                    limparCamposEndereco();
                    return;
                }

                $('#logradouro').val(data.logradouro);
                $('#bairro').val(data.bairro);
                $('#cidade').val(`${data.localidade}/${data.uf}`);
                $('#quadra').val('');
                $('#cepFeedback').addClass('hidden');
            },
            error: function () {
                $('#cepFeedback').removeClass('hidden').text('Erro ao consultar o CEP.');
                limparCamposEndereco();
            }
        });
    });
</script>


<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
