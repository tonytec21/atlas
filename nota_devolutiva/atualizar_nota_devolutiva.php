<?php  
// Ativar exibição de erros para debug (remover em produção)  
ini_set('display_errors', 1);  
ini_set('display_startup_errors', 1);  
error_reporting(E_ALL);  

// Criar arquivo de log para esta execução  
$log_file = __DIR__ . '/logs/atualizar_nota_log_' . date('Y-m-d') . '.txt';  
$log_message = date('[Y-m-d H:i:s] ') . "Iniciando processamento de atualização\n";  
file_put_contents($log_file, $log_message, FILE_APPEND);  

// Incluir arquivos necessários  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');  

// Verificar se é uma requisição POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
    $log_message = date('[Y-m-d H:i:s] ') . "Método inválido: " . $_SERVER['REQUEST_METHOD'] . "\n";  
    file_put_contents($log_file, $log_message, FILE_APPEND);  
    header('Location: index.php');  
    exit;  
}  

// Registrar os dados recebidos no log  
$log_message = date('[Y-m-d H:i:s] ') . "Dados recebidos: " . print_r($_POST, true) . "\n";  
file_put_contents($log_file, $log_message, FILE_APPEND);  

// Verificar se o número da nota foi fornecido  
if (!isset($_POST['numero']) || empty($_POST['numero'])) {  
    $log_message = date('[Y-m-d H:i:s] ') . "Número não informado\n";  
    file_put_contents($log_file, $log_message, FILE_APPEND);  
    header('Location: index.php');  
    exit;  
}  

// Obter os dados do formulário  
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;  
$numero = trim($_POST['numero']);  
$apresentante = isset($_POST['apresentante']) ? trim($_POST['apresentante']) : '';  
$cpf_cnpj = isset($_POST['cpf_cnpj']) ? trim($_POST['cpf_cnpj']) : '';  
$protocolo = isset($_POST['protocolo']) ? trim($_POST['protocolo']) : '';  
$data_protocolo = isset($_POST['data_protocolo']) ? trim($_POST['data_protocolo']) : '';  
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';  
$origem_titulo = isset($_POST['origem_titulo']) ? trim($_POST['origem_titulo']) : '';  
$corpo = isset($_POST['corpo']) ? $_POST['corpo'] : '';  
$prazo_cumprimento = isset($_POST['prazo_cumprimento']) ? $_POST['prazo_cumprimento'] : '';  
$assinante = isset($_POST['assinante']) ? trim($_POST['assinante']) : '';  
$cargo_assinante = isset($_POST['cargo_assinante']) ? trim($_POST['cargo_assinante']) : '';  
$data = isset($_POST['data']) ? trim($_POST['data']) : '';  
$tratamento = isset($_POST['tratamento']) ? trim($_POST['tratamento']) : '';  
$dados_complementares = isset($_POST['dados_complementares']) ? $_POST['dados_complementares'] : '';  
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Pendente';  
$processo_referencia = isset($_POST['processo_referencia']) ? trim($_POST['processo_referencia']) : '';  

// Variáveis para controlar o resultado da operação  
$operacao_sucesso = false;  
$mensagem_erro = "";  

// Verificar se a nota existe  
$check_query = "SELECT * FROM notas_devolutivas WHERE numero = ?";  
$check_stmt = $conn->prepare($check_query);  

if (!$check_stmt) {  
    $log_message = date('[Y-m-d H:i:s] ') . "Erro ao preparar query de verificação: " . $conn->error . "\n";  
    file_put_contents($log_file, $log_message, FILE_APPEND);  
    $mensagem_erro = "Erro ao preparar consulta: " . $conn->error;  
    $operacao_sucesso = false;  
}  
else {  
    $check_stmt->bind_param("s", $numero);  
    $check_executed = $check_stmt->execute();  

    if (!$check_executed) {  
        $log_message = date('[Y-m-d H:i:s] ') . "Erro ao executar query de verificação: " . $check_stmt->error . "\n";  
        file_put_contents($log_file, $log_message, FILE_APPEND);  
        $mensagem_erro = "Erro ao executar consulta: " . $check_stmt->error;  
        $operacao_sucesso = false;  
    }  
    else {  
        $check_result = $check_stmt->get_result();  

        if ($check_result->num_rows === 0) {  
            $log_message = date('[Y-m-d H:i:s] ') . "Nota não encontrada: $numero\n";  
            file_put_contents($log_file, $log_message, FILE_APPEND);  
            $mensagem_erro = "Nota não encontrada: $numero";  
            $operacao_sucesso = false;  
        }  
        else {  
            $nota_original = $check_result->fetch_assoc();  
            $check_stmt->close();  

            // Inserir log antes da atualização  
            $usuario = $_SESSION['username'];  
            $log_insert_query = "INSERT INTO logs_notas_devolutivas (  
                nota_id, numero, apresentante, cpf_cnpj, titulo, origem_titulo,   
                corpo, prazo_cumprimento, assinante, data, tratamento, protocolo,   
                data_protocolo, cargo_assinante, dados_complementares, status,   
                processo_referencia, created_at, data_atualizacao,   
                usuario_log, acao_log  
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ANTES_ATUALIZAÇÃO')";  

            $log_insert_stmt = $conn->prepare($log_insert_query);  

            if (!$log_insert_stmt) {  
                $log_message = date('[Y-m-d H:i:s] ') . "Erro ao preparar query de log: " . $conn->error . "\n";  
                file_put_contents($log_file, $log_message, FILE_APPEND);  
                $mensagem_erro = "Erro ao preparar log: " . $conn->error;  
                $operacao_sucesso = false;  
            }  
            else {  
                // CORRIGIDO: Adicionado um 's' para corresponder ao número correto de parâmetros (21 no total)  
                $log_insert_stmt->bind_param(  
                    "isssssssssssssssssss", // 1i + 20s = 21 parâmetros  
                    $nota_original['id'],   
                    $nota_original['numero'],   
                    $nota_original['apresentante'],   
                    $nota_original['cpf_cnpj'],  
                    $nota_original['titulo'],   
                    $nota_original['origem_titulo'],   
                    $nota_original['corpo'],   
                    $nota_original['prazo_cumprimento'],  
                    $nota_original['assinante'],   
                    $nota_original['data'],   
                    $nota_original['tratamento'],   
                    $nota_original['protocolo'],  
                    $nota_original['data_protocolo'],   
                    $nota_original['cargo_assinante'],   
                    $nota_original['dados_complementares'],  
                    $nota_original['status'],   
                    $nota_original['processo_referencia'],   
                    $nota_original['created_at'],  
                    $nota_original['data_atualizacao'],   
                    $usuario  
                );  

                $log_executed = $log_insert_stmt->execute();  

                if (!$log_executed) {  
                    $log_message = date('[Y-m-d H:i:s] ') . "Erro ao inserir log: " . $log_insert_stmt->error . "\n";  
                    file_put_contents($log_file, $log_message, FILE_APPEND);  
                    $mensagem_erro = "Erro ao salvar log: " . $log_insert_stmt->error;  
                    $operacao_sucesso = false;  
                }  
                else {  
                    $log_insert_stmt->close();  

                    // Atualizar os dados da nota devolutiva  
                    $update_query = "UPDATE notas_devolutivas SET   
                                    apresentante = ?,  
                                    cpf_cnpj = ?,  
                                    protocolo = ?,  
                                    data_protocolo = ?,  
                                    titulo = ?,  
                                    origem_titulo = ?,  
                                    corpo = ?,  
                                    prazo_cumprimento = ?,  
                                    assinante = ?,  
                                    cargo_assinante = ?,  
                                    data = ?,  
                                    tratamento = ?,  
                                    dados_complementares = ?,  
                                    status = ?,  
                                    processo_referencia = ?,  
                                    data_atualizacao = NOW()  
                                    WHERE numero = ?";  

                    $update_stmt = $conn->prepare($update_query);  

                    if (!$update_stmt) {  
                        $log_message = date('[Y-m-d H:i:s] ') . "Erro ao preparar query de atualização: " . $conn->error . "\n";  
                        file_put_contents($log_file, $log_message, FILE_APPEND);  
                        $mensagem_erro = "Erro ao preparar atualização: " . $conn->error;  
                        $operacao_sucesso = false;  
                    }  
                    else {  
                        $update_stmt->bind_param(  
                            "ssssssssssssssss",  
                            $apresentante,  
                            $cpf_cnpj,  
                            $protocolo,  
                            $data_protocolo,  
                            $titulo,  
                            $origem_titulo,  
                            $corpo,  
                            $prazo_cumprimento,  
                            $assinante,  
                            $cargo_assinante,  
                            $data,  
                            $tratamento,  
                            $dados_complementares,  
                            $status,  
                            $processo_referencia,  
                            $numero  
                        );  

                        $update_executed = $update_stmt->execute();  

                        if (!$update_executed) {  
                            $log_message = date('[Y-m-d H:i:s] ') . "Erro ao executar atualização: " . $update_stmt->error . "\n";  
                            file_put_contents($log_file, $log_message, FILE_APPEND);  
                            $mensagem_erro = "Erro na atualização: " . $update_stmt->error;  
                            $operacao_sucesso = false;  
                        }  
                        else {  
                            $affected_rows = $update_stmt->affected_rows;  
                            $update_stmt->close();  

                            $log_message = date('[Y-m-d H:i:s] ') . "Atualização executada com sucesso. Linhas afetadas: $affected_rows\n";  
                            file_put_contents($log_file, $log_message, FILE_APPEND);  

                            // Inserir log após atualização  
                            $log_sistema_query = "INSERT INTO logs_sistema (usuario, acao, tabela_afetada, id_registro, data_hora)   
                                                VALUES (?, ?, 'notas_devolutivas', ?, NOW())";  

                            $log_sistema_stmt = $conn->prepare($log_sistema_query);  

                            if ($log_sistema_stmt) {  
                                $acao = "Atualização de nota devolutiva nº $numero";  
                                $log_sistema_stmt->bind_param("sss", $usuario, $acao, $numero);  
                                $log_sistema_executed = $log_sistema_stmt->execute();  

                                if (!$log_sistema_executed) {  
                                    $log_message = date('[Y-m-d H:i:s] ') . "Erro ao inserir log sistema: " . $log_sistema_stmt->error . "\n";  
                                    file_put_contents($log_file, $log_message, FILE_APPEND);  
                                    // Não bloqueamos o fluxo por erro no log do sistema  
                                }  
                                $log_sistema_stmt->close();  
                            }  
                            
                            // Marcar operação como bem-sucedida  
                            $operacao_sucesso = true;  
                        }  
                    }  
                }  
            }  
        }  
    }  
}  

// Log da conclusão do processo  
$log_message = date('[Y-m-d H:i:s] ') . "Processo concluído. Sucesso: " . ($operacao_sucesso ? "Sim" : "Não") . "\n";  
file_put_contents($log_file, $log_message, FILE_APPEND);  
?>  

<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Processando Nota Devolutiva</title>  
    <?php include(__DIR__ . '/complementos_edicao/links.php'); ?> 
    <?php include(__DIR__ . '/style_nota.php'); ?>
</head>  
<body>  
<?php include(__DIR__ . '/complementos_edicao/scripts.php'); ?>
    <script>  
        document.addEventListener('DOMContentLoaded', function() {  
            <?php if ($operacao_sucesso): ?>  
                Swal.fire({  
                    icon: 'success',  
                    title: 'Sucesso!',  
                    text: 'A nota devolutiva <?php echo htmlspecialchars($numero); ?> foi atualizada com sucesso!',  
                    confirmButtonColor: '#3085d6',  
                    confirmButtonText: 'OK'  
                }).then((result) => {  
                    // Redirecionar após o usuário clicar em OK  
                    window.location.href = 'index.php?sucesso=nota_atualizada&numero=<?php echo urlencode($numero); ?>';  
                });  
            <?php else: ?>  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro!',  
                    text: '<?php echo htmlspecialchars($mensagem_erro); ?>',  
                    confirmButtonColor: '#d33',  
                    confirmButtonText: 'OK'  
                }).then((result) => {  
                    // Redirecionar após o usuário clicar em OK  
                    window.location.href = 'editar-nota-devolutiva.php?numero=<?php echo urlencode($numero); ?>&erro=falha_atualizacao&msg=<?php echo urlencode($mensagem_erro); ?>';  
                });  
            <?php endif; ?>  
        });  
    </script>  
</body>  
</html>