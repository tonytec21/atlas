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

// Função para mapear código de ato para URL e obter código de tabela de custas
function getAtoData($codAto) {
    $map = [
        '13.30' => ['/selo/notas/atos-em-geral', '0520240101'],
        '14.12' => ['/selo/civil/atos-em-geral', '0120240101'],
        '15.22' => ['/selo/rtdpj/atos-em-geral', '0420240101'],
        '16.39' => ['/selo/imovel/atos-em-geral', '0220240101'],
        '17.9' => ['/selo/protesto/atos-em-geral', '0320240101'],
        '18.13' => ['/selo/maritimo/atos-em-geral', '0620240101'],
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
    $numeroControle = $_POST["numeroControle"] ?? ''; // Adicionando a variável numeroControle

    // Obter URL específica do ato e código de tabela de custas
    $atoData = getAtoData($ato);
    if ($atoData === null) {
        die('Código de ato inválido.');
    }

    $fullUrl = $seloBaseUrl . $atoData[0];
    $codigoTabelaCusta = $atoData[1];

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
                    'documento' => '06151320301' // Documento da Parte (11 a 14 caracteres)
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
            'folha' => '1', // Exemplos de valores, ajuste conforme necessário
            'livro' => 'A',
            'termo' => '1',
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
        die('Erro ao gerar selo: ' . curl_error($ch));
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
    } else {
        $seloHtml = '<p>Erro: Não foi possível gerar o selo. Por favor, tente novamente.<br>';
        $seloHtml .= 'Resposta da API: <pre>' . print_r($responseData, true) . '</pre></p>';
    }
} else {
    $seloHtml = '';
}
?>
