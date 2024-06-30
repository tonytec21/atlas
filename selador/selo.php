<?php
include 'db_connection.php';

// Configurações da API
$authUrl = "https://selador.ma.portalselo.com.br:9443/auth";
$seloBaseUrl = "https://selador.ma.portalselo.com.br:9443";
$username = "homologacao";
$password = "a907438c85f0";
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
        die('Erro ao obter token de acesso: ' . curl_error($ch));
    }

    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['access_token'];
}

// Função para mapear código de ato para URL
function getAtoUrl($codAto) {
    $map = [
        '13.10.5.2' => '/selo/notas/testamento',
        '13.11' => '/selo/notas/ato-declarado/sem-valor',
        '13.11.1' => '/selo/notas/ato-declarado/sem-valor',
        '13.12.1' => '/selo/notas/certidao',
        '13.12.3' => '/selo/notas/certidao',
        '13.13.1' => '/selo/notas/certidao',
        '13.13.2' => '/selo/notas/certidao',
        '13.13.3' => '/selo/notas/certidao',
        '13.13.4' => '/selo/notas/certidao',
        '13.13.5' => '/selo/notas/certidao',
        '13.13.6' => '/selo/notas/certidao',
        '13.13.7' => '/selo/notas/certidao',
        '13.13.8' => '/selo/notas/certidao',
        '13.14.1' => '/selo/notas/certidao',
        '13.14.2' => '/selo/notas/certidao',
        '13.15' => '/selo/notas/certidao',
        '13.16' => '/selo/notas/certidao',
        '13.17.1' => '/selo/notas/reconhecimento-firma',
        '13.17.2' => '/selo/notas/reconhecimento-firma',
        '13.17.3' => '/selo/notas/reconhecimento-firma',
        '13.18' => '/selo/notas/autenticacao',
        '13.19' => '/selo/notas/ato-declarado/sem-valor',
        '13.21' => '/selo/notas/certidao',
        '13.21.1' => '/selo/notas/certidao',
        '13.21.2' => '/selo/notas/certidao',
        '13.21.3' => '/selo/notas/certidao',
        '13.23' => '/selo/notas/apostila-haia',
        '13.28' => '/selo/notas/atos-em-geral',
        '13.2' => '/selo/notas/ato-declarado/sem-valor',
        '13.9.2' => '/selo/notas/procuracao/poderes-especificos',
        '13.9.5' => '/selo/notas/substabelecimento',
        '13.22' => '/selo/notas/comunicacao-transferencia-veiculo',
        '13.1' => '/selo/notas/ato-declarado/com-valor',
        '13.3' => '/selo/notas/ato-declarado/com-valor',
        '13.8' => '/selo/notas/ato-declarado/com-valor',
        '13.9.1' => '/selo/notas/ato-declarado/com-valor',
        '13.14.3' => '/selo/notas/ato-declarado/com-valor',
        '13.16.1' => '/selo/notas/ato-declarado/com-valor',
        '13.2' => '/selo/notas/ato-declarado/com-valor',
        '13.17.4' => '/selo/notas/reconhecimento-firma',
        '13.9.4' => '/selo/notas/procuracao/simples',
        '13.9.6' => '/selo/notas/revogacao-procuracao',
        '13.10.1' => '/selo/notas/testamento',
        '13.10.2' => '/selo/notas/testamento',
        '13.10.3' => '/selo/notas/testamento',
        '13.10.4' => '/selo/notas/testamento',
        '13.10.5.1' => '/selo/notas/testamento',
        '13.29' => '/selo/notas/atos-em-geral',
        '13.7' => '/selo/notas/ato-declarado/sem-valor',
        '13.9.3' => '/selo/notas/procuracao/simples',
        '13.30' => '/selo/notas/atos-em-geral',
        '13.12.4' => '/selo/notas/certidao',
        // Adicione todas as outras entradas do mapeamento aqui
    ];

    return isset($map[$codAto]) ? $map[$codAto] : null;
}

$token = getAccessToken($authUrl, $username, $password, $client_id, $grant_type);

// Se o formulário for enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ato = $_POST["ato"];
    $escrevente = $_POST["escrevente"];
    $partes = $_POST["partes"];
    $quantidade = $_POST["quantidade"];
    $isento = isset($_POST["isento"]) ? (bool)$_POST["isento"] : false;
    $motivoIsencao = $_POST["motivo_isencao"] ?? null;
    $naturezaTipo = $_POST["natureza_tipo"] ?? null;

    // Obter URL específica do ato
    $atoUrl = getAtoUrl($ato);
    if ($atoUrl === null) {
        die('Código de ato inválido.');
    }

    $fullUrl = $seloBaseUrl . $atoUrl;

    $data = [
        'ato' => [
            'codigo' => $ato,
            'codigoTabelaCusta' => '0520240101' // Código da Tabela de Custa válido (1 a 10 caracteres)
        ],
        'escrevente' => $escrevente,
        'isento' => [
            'motivo' => $motivoIsencao,
            'value' => $isento
        ],
        'partes' => [
            'parteAto' => [
                [
                    'nome' => $partes,
                    'documento' => '06151320301' // Documento da Parte (11 a 14 caracteres)
                ]
            ]
        ],
        'quantidade' => (int) $quantidade
    ];

    if ($naturezaTipo !== null) {
        $data['natureza'] = ['tipo' => (int)$naturezaTipo];
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
        die('Erro ao gerar selo: ' . curl_error($ch));
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    // Depuração: Verificar os dados enviados e a resposta da API
    echo "<h2>Dados enviados para a API:</h2>";
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
    echo "<h2>Resposta da API:</h2>";
    echo "<pre>" . json_encode($responseData, JSON_PRETTY_PRINT) . "</pre>";

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
            $stmt = $conn->prepare("INSERT INTO selos (ato, escrevente, isento, partes, quantidade, numero_selo, texto_selo, qr_code, data_geracao, valor_qr_code, retorno_selo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissssss", $ato, $escrevente, $data['isento']['value'], $partes, $quantidade, $numero_selo, $texto_selo, $qr_code, $data_geracao, $valor_qr_code, $retorno_selo);
            $stmt->execute();
            $stmt->close();
        }
        
        // Exibir o selo gerado na página
        $seloHtml = '<div style="border: 1px solid #ddd; padding: 10px; margin-top: 20px;">';
        $seloHtml .= '<p>Enviado ao portal em ' . date('d/m/y') . '</p>';
        $seloHtml .= '<p><strong>Poder Judiciário – TJMA</strong></p>';
        $seloHtml .= '<p><strong>Selo: ' . $numero_selo . '</strong></p>';
        $seloHtml .= '<p>' . $texto_selo . '</p>';
        $seloHtml .= '<p><img src="data:image/png;base64,' . $qr_code . '" alt="QR Code"></p>';
        $seloHtml .= '<p>Consulte em <a href="https://selo.tjma.jus.br">https://selo.tjma.jus.br</a></p>';
        $seloHtml .= '</div>';
    } else {
        $seloHtml = '<p>Erro: Não foi possível gerar o selo. Por favor, tente novamente.<br>';
        $seloHtml .= 'Resposta da API: <pre>' . print_r($responseData, true) . '</pre></p>';
    }
} else {
    $seloHtml = '';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geração de Selos</title>
    <style>
        form {
            max-width: 600px;
            margin: 20px auto;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, textarea, button, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .selo-gerado {
            max-width: 600px;
            margin: 20px auto;
            border: 1px solid #ddd;
            padding: 10px;
        }
    </style>
</head>
<body>
    <h1>Gerar Selo para Atos em Geral</h1>
    <form method="post">
        <label for="ato">Ato:</label>
        <input type="text" id="ato" name="ato" required><br><br>

        <label for="escrevente">Escrevente:</label>
        <input type="text" id="escrevente" name="escrevente" required><br><br>

        <label for="partes">Partes:</label>
        <textarea id="partes" name="partes" required></textarea><br><br>

        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" required><br><br>

        <label for="natureza_tipo">Natureza do Ato:</label>
        <select id="natureza_tipo" name="natureza_tipo">
            <option value="">Selecione a natureza do ato</option>
            <option value="0">0 - Reconhecimento de Firma por Autencidade</option>
            <option value="1">1 - Ata Notarial</option>
            <!-- Adicione todas as outras opções conforme necessário -->
        </select><br><br>

        <label for="isento">Isento:</label>
        <input type="checkbox" id="isento" name="isento" value="true"><br><br>

        <div id="motivoIsencao" style="display: none;">
            <label for="motivo_isencao">Motivo da Isenção:</label>
            <textarea id="motivo_isencao" name="motivo_isencao"></textarea><br><br>
        </div>

        <button type="submit">Solicitar Selo</button>
    </form>

    <?php if (!empty($seloHtml)): ?>
        <div class="selo-gerado">
            <?php echo $seloHtml; ?>
        </div>
    <?php endif; ?>

    <script>
        document.getElementById('isento').addEventListener('change', function() {
            document.getElementById('motivoIsencao').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>
