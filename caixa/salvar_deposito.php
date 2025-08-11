<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');
ob_start();

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDatabaseConnection();

        $funcionario = trim(str_replace(' ', '', $_POST['funcionario_deposito']));
        $data_caixa = $_POST['data_caixa_deposito'];
        $valor_do_deposito = str_replace(['.', ','], ['', '.'], $_POST['valor_deposito']);
        $tipo_deposito = $_POST['tipo_deposito'];

        // Definindo o diretório alvo
        $target_dir = __DIR__ . "/anexos/" . date('d-m-y', strtotime($data_caixa)) . "/" . $funcionario . "/";
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                throw new Exception("Falha ao criar diretório: " . $target_dir);
            }
        }

        // Decide fluxo: com ou sem comprovante
        $semComprovante = (isset($_POST['sem_comprovante']) && $_POST['sem_comprovante'] === 'on');
        $file_name_to_save = ''; // será usado no INSERT (vazio quando sem comprovante)

        // Se NÃO for "sem comprovante", tenta mover o arquivo enviado
        if (!$semComprovante) {
            if (isset($_FILES["comprovante_deposito"]) && $_FILES["comprovante_deposito"]["error"] === UPLOAD_ERR_OK) {
                $file_name_to_save = basename($_FILES["comprovante_deposito"]["name"]);
                $target_file = $target_dir . $file_name_to_save;

                if (!move_uploaded_file($_FILES["comprovante_deposito"]["tmp_name"], $target_file)) {
                    throw new Exception("Falha ao mover o arquivo carregado para o diretório alvo.");
                }
            } else {
                throw new Exception("Nenhum comprovante foi enviado.");
            }
        }

        // 1) INSERT inicial (para obter o ID)
        $stmt = $conn->prepare('INSERT INTO deposito_caixa (funcionario, data_caixa, valor_do_deposito, tipo_deposito, caminho_anexo) 
                                VALUES (:funcionario, :data_caixa, :valor_do_deposito, :tipo_deposito, :caminho_anexo)');
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->bindParam(':data_caixa', $data_caixa);
        $stmt->bindParam(':valor_do_deposito', $valor_do_deposito);
        $stmt->bindParam(':tipo_deposito', $tipo_deposito);
        $stmt->bindParam(':caminho_anexo', $file_name_to_save);
        if (!$stmt->execute()) {
            throw new Exception("Falha ao executar a consulta no banco de dados.");
        }
        $depositoId = $conn->lastInsertId();

        // 2) Se foi marcado "sem comprovante", cria o placeholder ÚNICO (sem_comprovante_{ID}.php) e atualiza o registro
        if ($semComprovante) {
            $file_name = "sem_comprovante_{$depositoId}.php";
            $file_path = $target_dir . $file_name;

            // Página com formulário para anexar posteriormente (faz POST em caixa/anexar_comprovante_deposito.php)
            $php_content = <<<PHP
        <?php
        include(__DIR__ . '/../../../../caixa/session_check.php');
        checkSession();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Atlas - Anexar Comprovante</title>
            <link rel="stylesheet" href="../../../../style/css/bootstrap.min.css">
            <link rel="icon" href="../../../../style/img/favicon.png" type="image/png">
        </head>
        <body class="light-mode">
        <div class="container" style="max-width:720px; margin-top:60px;">
            <h4 class="mb-3">Depósito sem comprovante</h4>
            <p>Funcionário: <b>{$funcionario}</b><br>Data do Caixa: <b><?php echo date('d/m/Y', strtotime('{$data_caixa}')); ?></b></p>
            <div class="alert alert-warning">Este depósito foi registrado sem comprovante. Anexe o arquivo abaixo quando disponível.</div>

            <form id="formAnexar" enctype="multipart/form-data" method="post">
                <input type="hidden" name="deposito_id_anexo" value="{$depositoId}">
                <div class="custom-file mb-3">
                    <input type="file" class="custom-file-input" id="arquivo_comprovante" name="arquivo_comprovante" required>
                    <label class="custom-file-label" for="arquivo_comprovante">Selecione um arquivo (PDF, JPG, PNG)...</label>
                </div>
                <button type="submit" class="btn btn-primary">Enviar comprovante</button>
                <a href="../../../../caixa/index.php" class="btn btn-secondary">Voltar</a>
            </form>

            <div id="msg" class="mt-3"></div>
        </div>

        <script>
        document.addEventListener('change', function(e){
        if(e.target && e.target.classList.contains('custom-file-input')){
            var fileName = e.target.value.split('\\\\').pop();
            e.target.nextElementSibling.classList.add('selected');
            e.target.nextElementSibling.textContent = fileName || 'Selecione um arquivo...';
        }
        });

        document.getElementById('formAnexar').addEventListener('submit', async function(ev){
        ev.preventDefault();
        const fd = new FormData(this);
        try{
            const resp = await fetch('../../../../caixa/anexar_comprovante_deposito.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if(data && data.success){
            document.getElementById('msg').innerHTML = '<div class="alert alert-success">Comprovante anexado com sucesso! Redirecionando...</div>';
            // o novo arquivo ficará no MESMO diretório deste placeholder -> abrir diretamente:
            if (data.filename) {
                setTimeout(function(){ window.location.href = './' + data.filename; }, 1200);
            } else {
                setTimeout(function(){ window.location.href='../../../../caixa/index.php'; }, 1200);
            }
            } else {
            document.getElementById('msg').innerHTML = '<div class="alert alert-danger">' + (data && data.error ? data.error : 'Falha ao anexar') + '</div>';
            }
        } catch(err){
            document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro inesperado: ' + err + '</div>';
        }
        });
        </script>
        </body>
        </html>
        PHP;

            if (!file_put_contents($file_path, $php_content)) {
                throw new Exception("Falha ao criar o arquivo de placeholder do comprovante.");
            }

            // Atualiza o caminho do anexo no registro
            $up = $conn->prepare('UPDATE deposito_caixa SET caminho_anexo = :anexo WHERE id = :id');
            $up->bindValue(':anexo', $file_name);
            $up->bindValue(':id', $depositoId);
            $up->execute();
        }

        $response['success'] = true;

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
}

ob_end_clean();
echo json_encode($response);
exit;
