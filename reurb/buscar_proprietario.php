<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json'); // Garante que a resposta será JSON

include(__DIR__ . '/db_connection.php');

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? ''); // Remove formatação
$response = ['success' => false, 'message' => 'CPF não encontrado.'];

try {
    if ($cpf) {
        // Verifica se o CPF já existe na tabela cadastro_de_imoveis
        $stmtImovel = $conn->prepare("SELECT proprietario_nome FROM cadastro_de_imoveis WHERE proprietario_cpf = ? OR cpf_conjuge = ?");
        if (!$stmtImovel) {
            throw new Exception('Erro ao preparar consulta para imóveis: ' . $conn->error);
        }

        $stmtImovel->bind_param("ss", $cpf, $cpf);
        $stmtImovel->execute();
        $stmtImovel->bind_result($proprietarioNome);

        if ($stmtImovel->fetch()) {
            $response = [
                'success' => false,
                'message' => "Já existe um imóvel cadastrado para o CPF informado ($cpf) como proprietário $proprietarioNome."
            ];
            $stmtImovel->close();
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            $conn->close();
            exit;
        }

        $stmtImovel->close();

        // Consulta o proprietário na tabela cadastro_de_pessoas
        $stmt = $conn->prepare("SELECT nome, estado_civil, conjuge_cpf FROM cadastro_de_pessoas WHERE cpf = ?");
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta: ' . $conn->error);
        }

        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $stmt->bind_result($nome, $estadoCivil, $conjugeCpf);

        if ($stmt->fetch()) {
            $response = [
                'success' => true,
                'nome' => $nome,
                'estado_civil' => $estadoCivil,
                'conjuge' => null,
                'cpfConjuge' => null
            ];

            // Verifica se há CPF do cônjuge
            if ($estadoCivil === 'casado' && $conjugeCpf) {
                $stmtConjuge = $conn->prepare("SELECT nome FROM cadastro_de_pessoas WHERE cpf = ?");
                if (!$stmtConjuge) {
                    throw new Exception('Erro ao preparar consulta para o cônjuge: ' . $conn->error);
                }

                $stmtConjuge->bind_param("s", $conjugeCpf);
                $stmtConjuge->execute();
                $stmtConjuge->bind_result($conjugeNome);

                if ($stmtConjuge->fetch()) {
                    $response['conjuge'] = $conjugeNome;
                    $response['cpfConjuge'] = $conjugeCpf;
                }
                $stmtConjuge->close();
            }
        }

        $stmt->close();
    }
} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
