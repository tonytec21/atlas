<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['query']);
    $results = [];
    $finalResults = [];

    if (!empty($question)) {
        $keywords = extractKeywords($question);  // Função para extrair palavras-chave e frases exatas
        $conn = getDatabaseConnection();

        // Passo 1: Busca Exata por Frase
        $exactMatchSql = "SELECT id, numero_provimento, origem, descricao, data_provimento, conteudo_anexo
                          FROM provimentos
                          WHERE conteudo_anexo LIKE :exact_phrase";
        $stmt = $conn->prepare($exactMatchSql);
        $stmt->bindValue(':exact_phrase', '%' . $question . '%');
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Se a busca exata não retornar resultados, expanda a busca
        if (empty($results)) {
            // Passo 2: Expansão da Pesquisa por Palavras
            $conditions = [];
            foreach ($keywords as $keyword) {
                $conditions[] = "conteudo_anexo LIKE :keyword_" . md5($keyword);
            }

            $expandedSearchSql = "SELECT id, numero_provimento, origem, descricao, data_provimento, conteudo_anexo
                                  FROM provimentos
                                  WHERE " . implode(' AND ', $conditions);

            $stmt = $conn->prepare($expandedSearchSql);
            foreach ($keywords as $keyword) {
                $stmt->bindValue(':keyword_' . md5($keyword), '%' . $keyword . '%');
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Processar os resultados e extrair o contexto
        foreach ($results as $provimento) {
            $matches = [];
            foreach ($keywords as $keyword) {
                if (stripos($provimento['conteudo_anexo'], $keyword) !== false) {
                    $matches[] = $keyword;
                }
            }

            if (!empty($matches)) {
                // Extrair e destacar os trechos onde as palavras-chave aparecem
                $context = extractContext($provimento['conteudo_anexo'], $matches);

                $finalResults[] = [
                    'id' => $provimento['id'],
                    'numero_provimento' => $provimento['numero_provimento'],
                    'origem' => $provimento['origem'],
                    'descricao' => $provimento['descricao'],
                    'data_provimento' => date('d/m/Y', strtotime($provimento['data_provimento'])),
                    'context' => $context
                ];
            } else {
                // Se não houver correspondência, definir um contexto vazio
                $finalResults[] = [
                    'id' => $provimento['id'],
                    'numero_provimento' => $provimento['numero_provimento'],
                    'origem' => $provimento['origem'],
                    'descricao' => $provimento['descricao'],
                    'data_provimento' => date('d/m/Y', strtotime($provimento['data_provimento'])),
                    'context' => 'Nenhum contexto encontrado'
                ];
            }
        }
    }
}

// Função para extrair palavras-chave e frases exatas
function extractKeywords($question) {
    $stopWords = [
        'qual', 'quais', 'sobre', 'que', 'fala', 'falam', 'a', 'as', 'o', 'os', 'de', 'do', 'dos', 'e',
        'qual?', 'quais?', 'sobre?', 'que?', 'fala?', 'falam?', 'a?', 'as?', 'o?', 'os?', 'de?', 'do?', 'dos?', 'e?',
        'provimento', 'provimentos', 'resolução', 'resoluções',
        'provimento?', 'provimentos?', 'resolução?', 'resoluções?',
        '?'
    ];

    // Regex para capturar frases entre aspas
    preg_match_all('/"([^"]+)"|\S+/', strtolower($question), $matches);
    $words = array_filter($matches[0], function($word) use ($stopWords) {
        return !in_array($word, $stopWords);
    });

    return array_map(function($word) {
        return trim($word, '"');
    }, $words);
}

// Função para extrair o contexto em torno das palavras-chave
function extractContext($content, $keywords) {
    $contextSizeAfter = 20; // Número de palavras depois da palavra-chave
    $contexts = [];

    foreach ($keywords as $keyword) {
        $keywordPos = stripos($content, $keyword);
        if ($keywordPos !== false) {
            // Procurar pela expressão "Art." antes da palavra-chave
            $artPos = strripos(substr($content, 0, $keywordPos), 'Art.');
            if ($artPos !== false) {
                // Começar a partir de "Art."
                $start = $artPos;
            } else {
                // Caso não encontre "Art.", começar do início do contexto padrão
                $start = max(0, $keywordPos - 100);
            }
            // Determinar o final do contexto, incluindo mais algumas palavras após a palavra-chave
            $end = $keywordPos + strlen($keyword);
            $remainingContent = substr($content, $end);
            $additionalWords = implode(' ', array_slice(explode(' ', $remainingContent), 0, $contextSizeAfter));

            // Construir o contexto completo
            $context = substr($content, $start, $end - $start) . $additionalWords;

            // Destacar a expressão "Art." e a palavra-chave, mantendo a codificação correta
            $context = htmlentities($context, ENT_QUOTES, 'UTF-8');
            $context = str_ireplace('Art.', "<strong>Art.</strong>", $context);
            $context = str_ireplace($keyword, "<strong>$keyword</strong>", $context);
            $contexts[] = html_entity_decode($context, ENT_QUOTES, 'UTF-8'); // Decodifica as entidades HTML

        }
    }

    return implode("... ", $contexts);
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provimentos e Resoluções</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Provimentos e Resoluções</h3>
            <hr>
            <form id="pesquisarForm" method="POST">
                <div class="form-group">
                    <label for="query">Pergunta:</label>
                    <input type="text" class="form-control" id="query" name="query" placeholder="Digite sua pergunta aqui...">
                </div>
                <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-primary">
                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar
                </button>
            </form>
            <hr>
            <div id="resultados">
                <h5>Resultados da Pesquisa</h5>
                <?php if (isset($finalResults) && count($finalResults) > 0): ?>
                    <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Origem</th>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Contexto</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finalResults as $result): ?>
                                <tr>
                                    <td><?php echo htmlentities($result['numero_provimento'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlentities($result['origem'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlentities($result['data_provimento'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlentities($result['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $result['context']; ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" title="Visualizar Provimento" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="visualizarProvimento('<?php echo $result['id']; ?>')">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhum provimento encontrado para a busca realizada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização -->
    <div class="modal fade" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header success">
                    <h5 class="modal-title" id="visualizarModalLabel">Provimento</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="tipo_provimento_modal">Tipo:</label>
                                <input type="text" class="form-control" id="tipo_provimento_modal" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="numero_provimento_modal">Número:</label>
                                <input type="text" class="form-control" id="numero_provimento_modal" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="origem_modal">Origem:</label>
                                <input type="text" class="form-control" id="origem_modal" readonly>
                            </div>
                            <div class="form-group col-md_3">
                                <label for="data_provimento_modal">Data:</label>
                                <input type="text" class="form-control" id="data_provimento_modal" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="descricao_modal">Descrição:</label>
                            <textarea class="form-control" id="descricao_modal" rows="3" readonly></textarea>
                        </div>
                        <div class="form-group">
                            <label for="anexo_visualizacao">Conteúdo:</label>
                            <iframe id="anexo_visualizacao" style="width: 100%; height: 800px;" frameborder="0"></iframe>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[2, 'desc']]
            });
        });

        function visualizarProvimento(id) {
            $.ajax({
                url: 'obter_provimento.php',
                type: 'GET',
                data: { id: id },
                success: function(response) {
                    try {
                        var provimento = JSON.parse(response);

                        var numero_provimento_ano = provimento.numero_provimento + '/' + provimento.ano_provimento;
                        $('#tipo_provimento_modal').val(provimento.tipo);
                        $('#numero_provimento_modal').val(numero_provimento_ano);
                        $('#origem_modal').val(provimento.origem);
                        let dataProvimento = new Date(provimento.data_provimento + 'T00:00:00');
                        $('#data_provimento_modal').val(dataProvimento.toLocaleDateString('pt-BR'));
                        $('#descricao_modal').val(provimento.descricao);
                        $('#anexo_visualizacao').attr('src', provimento.caminho_anexo);

                        var modalTitle = provimento.tipo + ' nº: ' + numero_provimento_ano + ' - ' + provimento.origem;
                        $('#visualizarModalLabel').text(modalTitle);

                        $('#visualizarModal').modal('show');
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor.');
                    }
                },
                error: function() {
                    alert('Erro ao obter os dados do provimento.');
                }
            });
        }
    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
