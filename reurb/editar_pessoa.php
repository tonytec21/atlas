<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID não informado.");
}

// Consulta os dados da pessoa pelo ID
$stmt = $conn->prepare("SELECT * FROM cadastro_de_pessoas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pessoa = $result->fetch_assoc();

if (!$pessoa) {
    die("Pessoa não encontrada.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Pessoa</title>
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
            max-width: 900px;
        }

        .w-100 {
            margin-bottom: 31px;
            margin-top: 0px;
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Editar Pessoa</h3>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='busca_pessoas.php'">
                <i class="fa fa-search" aria-hidden="true"></i> Pesquisar</button>
            </button>
        </div>
        <hr>
        <form id="editarPessoaForm">
            <input type="hidden" name="id" value="<?php echo $pessoa['id']; ?>">
            <!-- Identificação Pessoal -->
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="cpf">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo $pessoa['cpf']; ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="rg">RG</label>
                    <input type="text" class="form-control" id="rg" name="rg" value="<?php echo $pessoa['rg']; ?>" maxlength="20" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="dataEmissaoRg">Data de Emissão do RG</label>
                    <input type="date" class="form-control" id="dataEmissaoRg" name="dataEmissaoRg" value="<?php echo $pessoa['data_emissao_rg']; ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="orgaoEmissorRg">Órgão Emissor do RG</label>
                    <input type="text" class="form-control" id="orgaoEmissorRg" name="orgaoEmissorRg" maxlength="100" value="<?php echo $pessoa['orgao_emissor_rg']; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="nome">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" style="text-transform: uppercase;" value="<?php echo $pessoa['nome']; ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="dataNascimento">Data de Nascimento</label>
                    <input type="date" class="form-control" id="dataNascimento" name="dataNascimento" value="<?php echo $pessoa['data_de_nascimento']; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="nacionalidade">Nacionalidade</label>
                    <input type="text" class="form-control" id="nacionalidade" name="nacionalidade" value="<?php echo $pessoa['nacionalidade']; ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="naturalidade">Naturalidade</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="naturalidade" name="naturalidade" value="<?php echo htmlspecialchars($pessoa['naturalidade']); ?>" readonly>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" id="btnBuscarNaturalidade">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filiação -->
            <div class="form-row">
                <?php
                $filiacao = explode(';', $pessoa['filiacao']);
                $nomeMae = $filiacao[0] ?? '';
                $nomePai = $filiacao[1] ?? '';
                ?>
                <div class="form-group col-md-6">
                    <label for="nomeMae">Nome da Mãe</label>
                    <input type="text" class="form-control" id="nomeMae" name="nomeMae" value="<?php echo htmlspecialchars($nomeMae); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="nomePai">Nome do Pai</label>
                    <input type="text" class="form-control" id="nomePai" name="nomePai" value="<?php echo htmlspecialchars($nomePai); ?>">
                </div>
            </div>

            <input type="hidden" id="filiacao" name="filiacao">

            <!-- Profissão -->
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="profissao">Profissão</label>
                    <input type="text" class="form-control" id="profissao" name="profissao" value="<?php echo $pessoa['profissao']; ?>">
                </div>

                <div class="form-group col-md-6">
                    <label for="estadoCivil">Estado Civil</label>
                    <select class="form-control" id="estadoCivil" name="estadoCivil">
                        <option value="">Selecione</option>
                        <option value="solteiro" <?php echo $pessoa['estado_civil'] === 'solteiro' ? 'selected' : ''; ?>>Solteiro(a)</option>
                        <option value="casado" <?php echo $pessoa['estado_civil'] === 'casado' ? 'selected' : ''; ?>>Casado(a)</option>
                        <option value="viuvo" <?php echo $pessoa['estado_civil'] === 'viuvo' ? 'selected' : ''; ?>>Viúvo(a)</option>
                        <option value="divorciado" <?php echo $pessoa['estado_civil'] === 'divorciado' ? 'selected' : ''; ?>>Divorciado(a)</option>
                        <option value="outro" <?php echo $pessoa['estado_civil'] === 'outro' ? 'selected' : ''; ?>>Outro</option>
                    </select>
                </div>
                <div class="form-group col-md-12 hidden" id="regimeBensGroup">
                    <label for="regimeBens">Regime de Bens</label>
                    <select class="form-control" id="regimeBens" name="regimeBens">
                        <option value="">Selecione</option>
                        <option value="comunhao_universal" <?php echo $pessoa['regime_de_bens'] === 'comunhao_universal' ? 'selected' : ''; ?>>Comunhão Universal</option>
                        <option value="comunhao_parcial" <?php echo $pessoa['regime_de_bens'] === 'comunhao_parcial' ? 'selected' : ''; ?>>Comunhão Parcial</option>
                        <option value="separacao_total" <?php echo $pessoa['regime_de_bens'] === 'separacao_total' ? 'selected' : ''; ?>>Separação Total</option>
                        <option value="participacao_final" <?php echo $pessoa['regime_de_bens'] === 'participacao_final' ? 'selected' : ''; ?>>Participação Final</option>
                    </select>
                </div>
            </div>

            <!-- Endereço -->
            <h5>Endereço</h5>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="cep">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" value="<?php echo $pessoa['cep']; ?>" maxlength="9">
                    <small id="cepFeedback" class="form-text text-danger hidden">CEP inválido ou não encontrado.</small>
                </div>
                <div class="form-group col-md-8">
                    <label for="logradouro">Logradouro</label>
                    <input type="text" class="form-control" id="logradouro" name="logradouro" value="<?php echo $pessoa['logradouro']; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="numero">Número</label>
                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo $pessoa['numero']; ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="bairro">Bairro</label>
                    <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo $pessoa['bairro']; ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="cidade">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo $pessoa['cidade']; ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="quadra">Quadra</label>
                    <input type="text" class="form-control" id="quadra" name="quadra" value="<?php echo $pessoa['quadra']; ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="btnSalvar">Salvar</button>
        </form>
    </div>
</div>

    <!-- Modal para Busca de Naturalidade -->
    <div class="modal fade" id="modalNaturalidade" tabindex="-1" role="dialog" aria-labelledby="modalNaturalidadeLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNaturalidadeLabel">Buscar Naturalidade</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="inputBuscaNaturalidade" placeholder="Digite o nome da cidade...">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tableNaturalidade">
                            <thead>
                                <tr>
                                    <th>Cidade</th>
                                    <th>UF</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="resultadosNaturalidade">
                                <!-- Resultados da busca serão carregados aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function () {
            // Máscaras
            $('#cpf').mask('000.000.000-00');
            $('#cep').mask('00000-000');

            // Mostrar ou ocultar campo de Regime de Bens
            $('#estadoCivil').on('change', function () {
                if ($(this).val() === 'casado') {
                    $('#regimeBensGroup').removeClass('hidden');
                } else {
                    $('#regimeBensGroup').addClass('hidden');
                }
            });

            // Consultar Naturalidade
            $('#btnBuscarNaturalidade, #naturalidade').on('click', function () {
                $('#modalNaturalidade').modal('show');
            });

            $('#naturalidade').on('focus', function () {
                $('#modalNaturalidade').modal('show');
            });

            $('#inputBuscaNaturalidade').on('input', function () {
                const query = $(this).val();
                if (query.length < 3) {
                    $('#resultadosNaturalidade').html('');
                    return;
                }

                $.ajax({
                    url: 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios',
                    method: 'GET',
                    success: function (data) {
                        const resultados = data.filter(cidade => cidade.nome.toLowerCase().includes(query.toLowerCase()));
                        let html = '';
                        resultados.forEach(cidade => {
                            const nomeCidade = cidade.nome;
                            const siglaUF = cidade.microrregiao.mesorregiao.UF.sigla;
                            const codigoIbge = cidade.id;
                            html += `
                                <tr>
                                    <td>${nomeCidade}</td>
                                    <td>${siglaUF}</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="selecionarNaturalidade('${nomeCidade}', '${siglaUF}', '${codigoIbge}')">Selecionar</button>
                                    </td>
                                </tr>`;
                        });
                        $('#resultadosNaturalidade').html(html);
                    }
                });
            });

            // Selecionar Naturalidade
            window.selecionarNaturalidade = function (nome, uf, codigoIbge) {
                const cidadeUf = `${nome}/${uf}`;
                $('#naturalidade').val(cidadeUf);
                $('#codigoIbge').val(codigoIbge);
                $('#modalNaturalidade').modal('hide');
            };

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


            function limparCamposEndereco() {
                $('#logradouro, #bairro, #cidade, #quadra').val('');
            }

            // $('#cpf').on('blur', function () {
            //     const cpf = $(this).val(); 
            //     const cpfSemFormatacao = cpf.replace(/\D/g, ''); 

            //     if (!validarCPF(cpfSemFormatacao)) {
            //         Swal.fire({
            //             title: 'Erro!',
            //             text: 'CPF inválido. Por favor, corrija.',
            //             icon: 'error'
            //         });
            //         bloquearFormulario(true);
            //         $('#cpfFeedback').removeClass('hidden');
            //         return;
            //     }

            //     $('#cpfFeedback').addClass('hidden');
            //     $.ajax({
            //         url: 'verificar_cpf.php',
            //         method: 'POST',
            //         data: { cpf }, 
            //         success: function (response) {
            //             const data = JSON.parse(response);
            //             if (data.existe) {
            //                 Swal.fire({
            //                     title: 'Atenção!',
            //                     text: `CPF já cadastrado. Nome: ${data.nome}`,
            //                     icon: 'warning'
            //                 });
            //                 bloquearFormulario(true);
            //             } else {
            //                 desbloquearFormulario();
            //             }
            //         }
            //     });
            // });


            function validarCPF(cpf) {
                cpf = cpf.replace(/\D/g, '');
                if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;

                let soma = 0, resto;
                for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                if (resto !== parseInt(cpf.substring(9, 10))) return false;

                soma = 0;
                for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                if (resto !== parseInt(cpf.substring(10, 11))) return false;

                return true;
            }

            function bloquearFormulario() {
                $('#editarPessoaForm input, #editarPessoaForm select, #btnSalvar').prop('disabled', true);
                $('#cpf').prop('disabled', false); // CPF permanece editável
            }

            function desbloquearFormulario() {
                $('#editarPessoaForm input, #editarPessoaForm select, #btnSalvar').prop('disabled', false);
            }
        });

        $('#editarPessoaForm').on('submit', function (e) {
            e.preventDefault();

            // Concatena os nomes da mãe e do pai no formato esperado
            const nomeMae = $('#nomeMae').val().trim();
            const nomePai = $('#nomePai').val().trim();
            const filiacao = `${nomeMae};${nomePai}`;

            // Adiciona o valor concatenado ao campo oculto "filiacao"
            $('#filiacao').val(filiacao);

            // Serializa e envia o formulário
            const formData = $(this).serialize();
            $.ajax({
                url: 'atualizar_pessoa_action.php',
                method: 'POST',
                data: formData,
                success: function () {
                    Swal.fire('Sucesso!', 'Cadastro atualizado com sucesso.', 'success').then(() => {
                            window.location.href = 'busca_pessoas.php';
                        });
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao salvar os dados.', 'error');
                }
            });
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>