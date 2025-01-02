<?php
include 'db_connection.php';

// Função para obter as configurações da API do banco de dados
function getApiConfig($conn) {
    $query = "SELECT url_base, porta, usuario, senha FROM conexao_selador WHERE id = 1";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        die('Erro: Configurações da API não encontradas no banco de dados.');
    }
}

// Obter configurações da API
$apiConfig = getApiConfig($conn);

$authUrl = $apiConfig['url_base'] . ':' . $apiConfig['porta'] . '/auth';
$seloBaseUrl = $apiConfig['url_base'] . ':' . $apiConfig['porta'];
$username = $apiConfig['usuario'];
$password = $apiConfig['senha'];
$client_id = "selador";
$grant_type = "password";

// Função para obter token de acesso usando cURL
function getAccessToken($authUrl, $username, $password, $client_id, $grant_type) {
    $data = [
        'username' => $username,
        'password' => $password,
        'client_id' => $client_id,
        'grant_type' => $grant_type
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $authUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativar verificação do certificado SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Desativar verificação do host SSL

    $response = curl_exec($ch);

    if ($response === false) {
        return false;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? false;
}

// Função para mapear código de ato para URL e obter código de tabela de custas
function getAtoData($codAto) {
    $map = [
        '13.30' => ['/selo/notas/atos-em-geral', '0520250101'],
        '14.12' => ['/selo/civil/atos-em-geral', '0120250101'],
        '15.22' => ['/selo/rtdpj/atos-em-geral', '0420250101'],
        '16.39' => ['/selo/imovel/atos-em-geral', '0220250101'],
        '17.9' => ['/selo/protesto/atos-em-geral', '0320250101'],
        '18.13' => ['/selo/maritimo/atos-em-geral', '0620250101'],
    ];

    return isset($map[$codAto]) ? $map[$codAto] : null;
}

$token = getAccessToken($authUrl, $username, $password, $client_id, $grant_type);

if (!$token) {
    echo json_encode(['error' => 'Erro ao obter token de acesso.']);
    exit;
}

$seloHtml = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ato = $_POST["ato"];
    $escrevente = ($_POST["escrevente"]);
    $partes = $_POST["partes"];
    $quantidade = $_POST["quantidade"];
    $numeroControle = $_POST["numeroControle"] ?? ''; // Adicionando a variável numeroControle
    $livro = $_POST["livro"]; // Livro do formulário
    $folha = $_POST["folha"]; // Folha do formulário
    $termo = $_POST["termo"]; // Termo do formulário

    // Obter URL específica do ato e código de tabela de custas
    $atoData = getAtoData($ato);
    if ($atoData === null) {
        echo json_encode(['error' => 'Código de ato inválido.']);
        exit;
    }

    $fullUrl = $seloBaseUrl . $atoData[0];
    $codigoTabelaCusta = $atoData[1];

    // Obter as partes envolvidas do banco de dados
    $partesEnvolvidas = json_decode($partes, true);
    $parteDocumento = isset($partesEnvolvidas[0]['cpf']) ? $partesEnvolvidas[0]['cpf'] : '06151320301'; // Usar o CPF da primeira parte

    $data = [
        'ato' => [
            'codigo' => $ato,
            'codigoTabelaCusta' => $codigoTabelaCusta
        ],
        'escrevente' => $escrevente,
        'isento' => [
            'motivo' => null,
            'value' => false
        ],
        'partes' => [
            'parteAto' => [
                [
                    'nome' => $partes,
                    'documento' => $parteDocumento // Documento da Parte (11 a 14 caracteres)
                ]
            ]
        ],
        'quantidade' => (int) $quantidade,
        'numeroControle' => $numeroControle // Incluindo numeroControle na requisição
    ];

    // Adicionar campos específicos para o ato 14.12
    if ($ato === '14.12') {
        $data['dadosSelo'] = [
            'versaoTabelaDeCustas' => $codigoTabelaCusta,
            'escrevente' => $escrevente,
            'isento' => false,
            'folha' => $folha, // Pegando valor do formulário
            'livro' => $livro, // Pegando valor do formulário
            'termo' => $termo, // Pegando valor do formulário
        ];
        $data['nomesPartes'] = [$partes];
        $data['codigoAto'] = $ato;
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desativar verificação do certificado SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Desativar verificação do host SSL

    $response = curl_exec($ch);

    if ($response === false) {
        echo json_encode(['error' => 'Erro ao gerar selo: ' . curl_error($ch)]);
        exit;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    // Verificar a resposta para garantir que estamos recebendo os selos corretos
    if (isset($responseData['resumos'][0]['numeroSelo'])) {
        foreach ($responseData['resumos'] as $selo) {
            $numero_selo = $selo['numeroSelo'];
            $texto_selo = $selo['textoSelo'];
            $qr_code = isset($selo['qrCode']) ? $selo['qrCode'] : null;
            $data_geracao = DateTime::createFromFormat('d/m/Y H:i:s', $selo['dataGeracao'])->format('Y-m-d H:i:s');
            $valor_qr_code = $selo['valorQrCode'];
            $retorno_selo = json_encode($selo);

            // Salvar no banco de dados
            $stmt = $conn->prepare("INSERT INTO selos (ato, escrevente, isento, partes, quantidade, numero_selo, texto_selo, qr_code, data_geracao, valor_qr_code, retorno_selo, numero_controle) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssisssssss", $ato, $escrevente, $data['isento']['value'], $partes, $quantidade, $numero_selo, $texto_selo, $qr_code, $data_geracao, $valor_qr_code, $retorno_selo, $numeroControle);
            $stmt->execute();
            $seloId = $stmt->insert_id;
            $stmt->close();

            // Save the seal in the selos_arquivamentos table
            $stmt = $conn->prepare("INSERT INTO selos_arquivamentos (arquivo_id, selo_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $numeroControle, $seloId);
            $stmt->execute();
            $stmt->close();
        }

        // Exibir o selo gerado na página
        $seloHtml = '<div style="border: 1px solid #ddd; padding: 10px; margin-top: 20px;">';
        $seloHtml .= '<p>Enviado ao portal em ' . date('d/m/Y H:i:s') . '</p>';
        $seloHtml .= '<table>';
        $seloHtml .= '<tr><td><img src="data:image/png;base64,' . $qr_code . '" alt="QR Code"></td>';
        $seloHtml .= '<td>';
        $seloHtml .= '<p style="text-align: center;font-size: 14px;"><strong>Poder Judiciário – TJMA</strong><br><strong>Selo: ' . $numero_selo . '</strong></p><p style="text-align: justify;font-size: 14px;margin-top: -12px;">' . $texto_selo . '</p>';
        $seloHtml .= '</td></tr>';
        $seloHtml .= '</table>';
        $seloHtml .= '</div>';

        // Define a JavaScript variable to indicate the seal has been generated
        $seloHtml .= '<script>var seloGerado = true;</script>';
        echo json_encode(['success' => 'Selo solicitado com sucesso.', 'html' => $seloHtml]);
    } else {
        echo json_encode(['error' => 'Não foi possível gerar o selo. Por favor, tente novamente.', 'response' => $responseData]);
    }
}
?>
