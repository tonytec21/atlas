<?php
// Inicia o buffer de saída
ob_start();

// Define o max_allowed_packet para 2GB
$conn->query("SET GLOBAL max_allowed_packet=2147483648");

// Função para criar a tabela se ela não existir
function criarTabelaSeNecessario($conn, $queryCriarTabela) {
    if ($conn->query($queryCriarTabela) === TRUE) {
        echo "Tabela criada ou verificada com sucesso.<br>";
    } else {
        echo "Erro ao criar/verificar tabela: " . $conn->error . "<br>";
    }
}

$tabelas = [
    "INSERT INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1340, '183', 'CNJ', 'Altera o Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, para dispor sobre o reconhecimento de firma de títulos procedentes de entes coletivos.', '2024-11-12', 'anexo/CNJ/Provimento/2024/183.pdf', 'ADMIN', '2024-11-20 19:00:39', 'Ativo', 'Provimento', 'O CORREGEDOR NACIONAL DE JUSTIÇA, no uso de suas atribuições constitucionais, legais e regimentais, CONSIDERANDO o poder de fiscalização e de normatização do Poder Judiciário em relação aos atos praticados por seus órgãos (art. 103- B, § 4º, I, II e III, da Constituição Federal de 1988); CONSIDERANDO a competência do Poder Judiciário para fiscalizar os serviços notariais e de registro (arts. 103-B, § 4º, I e III, e 236, § 1º, da Constituição Federal); CONSIDERANDO a competência da Corregedoria Nacional de Justiça de expedir provimentos e outros atos normativos destinados ao aperfeiçoamento das atividades dos serviços notariais e de registro (art. 8º, X, do Regimento Interno do Conselho Nacional de Justiça); CONSIDERANDO que há Cartórios de Registros de Imóveis que exigem o reconhecimento de firma de todos os condôminos para qualquer registro relativo aos condomínios edilício, de lotes, em multipropriedade e outros especiais com base no art. 222, II, da Lei nº 6.015/1973; CONSIDERANDO que essa prática acaba por inviabilizar diversos atos condominiais, especialmente diante da existência de supercondomínios, que chegam a ter centenas de condôminos; CONSIDERANDO que os quóruns exigidos nas assembleias condominiais destinam-se apenas a autorizar o condomínio, por seu representante, a praticar um ato jurídico e, portanto, não representam a prática direta de atos por parte dos condôminos, mas apenas um ato do próprio condomínio; CONSIDERANDO que o ato de instituição ou de cancelamento da instituição do condomínio especial, por implicar a mutação do direito real de propriedade, e a convenção, por força da exigência legal de subscrição dos condôminos (ex.: art. 1.333 do Código Civil), representam atos diretos dos próprios condôminos, e não um ato do próprio condomínio; CONSIDERANDO que a situação acima se aproxima de outros entes coletivos, como os envolvendo pessoas jurídicas; CONSIDERANDO que todas as especialidades são submetidas, potencialmente, a lidar com a situação acima, RESOLVE: Art. 1º A Parte Geral do Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, passa a vigorar acrescido do seguinte Livro VI: “LIVRO VI DE OUTRAS REGRAS COMUNS ÀS ESPECIALIDADES TÍTULO I DOS TÍTULOS CAPÍTULO I DOS TÍTULOS PROCEDENTES DE ENTES COLETIVOS Seção I Das Disposições Gerais Art. 353-A. Quando a lei exigir reconhecimento de firma no título (como no caso do art. 221, II, da Lei n. 6.015/1973) e este proceder de ente coletivo (pessoa jurídica ou ente despersonalizado), será exigido o reconhecimento de firma apenas do representante do ente, ainda que o ato decorra de deliberação de qualquer de seus órgãos colegiados. § 1º No caso de condomínio especial (edilício, de lotes, em multipropriedade e urbano simples), observar-se-á o seguinte: I - o síndico é o representante; II - as atas de assembleias que alteram a convenção ou que versam sobre outras questões do condomínio especial enquadram-se no disposto no caput deste artigo; III - o título de instituição ou de cancelamento da instituição do condomínio especial e a convenção não se sujeitam ao disposto no caput deste artigo. § 2º O reconhecimento de firma de que trata o caput deste artigo poderá ser pela modalidade de reconhecimento de assinatura eletrônica, na forma do art. 306, III, deste Código.” Art. 2º Este provimento entra em vigor na data de sua publicação, revogando-se as disposições em contrário. Ministro MAURO CAMPBELL MARQUES');"
];

// Executa a criação de todas as tabelas
foreach ($tabelas as $query) {
    criarTabelaSeNecessario($conn, $query);
}

echo "Execute concluído com sucesso.<br>";

// Captura e armazena a saída gerada
$output = ob_get_clean();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="style/css/toastr.min.css">
</head>
<body>

    <script src="script/jquery-3.6.0.min.js"></script>
    <script src="script/toastr.min.js"></script>

    <script>
        // Configuração básica do Toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-bottom-left",
            "preventDuplicates": true,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Função para verificar as atualizações
        function verificarAtualizacoes() {
            // Exibe a mensagem inicial de verificação
            toastr.info('Verificando atualizações...');

            // Simula o retorno da mensagem de verificação após 2 segundos
            setTimeout(() => {
                const mensagem = "<?php echo $mensagem; ?>";
                if (mensagem.includes('sucesso')) {
                    toastr.success(mensagem);
                } else if (mensagem.includes('Erro')) {
                    toastr.error(mensagem);
                } else {
                    toastr.info(mensagem);
                }
            }, 2000);
        }

        // Chama a função de verificação ao carregar a página
        window.onload = verificarAtualizacoes;
    </script>
</body>
</html>
