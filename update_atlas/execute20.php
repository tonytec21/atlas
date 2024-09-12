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
"REPLACE INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1338, '181', 'CNJ', 'Altera o Provimento Nº 149, de 30/08/2023, do Conselho Nacional de Justiça, que institui o Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça - Foro Extrajudicial (CNN/CN/CNJ-Extra)', '2024-09-11', 'anexo/CNJ/Provimento/2024/181.pdf', 'ADMIN', '2024-09-12 17:29:37', 'Ativo', 'Provimento', 'O CORREGEDOR NACIONAL DE JUSTIÇA, no uso de suas atribuições constitucionais, legais e regimentais, CONSIDERANDO os avanços advindos da execução de atividades à distância, implementadas durante vigência das medidas de prevenção ao contágio da Covid-19, proporcionando modernização tecnológica e inúmeras facilidades de acesso ao usuário dos serviços extrajudiciais; CONSIDERANDO a possibilidade de conferir a esses avanços caráter perene, evitando o retrocesso na prestação dos serviços delegados; CONSIDERANDO que o Sistema de Atos Notariais Eletrônicos, e-Notariado, é uma plataforma que propicia a evolução do serviço público e a inclusão digital de todas as pessoas que dela necessitem; CONSIDERANDO que a ampliação da prestação do serviço eletrônico trouxe eficiência e celeridade ao cidadão, com a mesma garantia da segurança jurídica que o serviço prestado de modo presencial e físico; CONSIDERANDO todos os benefícios já alcançados com a revolução tecnológica ocorrida nos cartórios, com uma prestação célere, segura, eficiente e acessível; CONSIDERANDO a viabilidade econômica e o baixo custo financeiro atribuído ao tabelião para a manutenção da plataforma; CONSIDERANDO a necessidade de ampliar o acesso ao serviço notarial eletrônico a todo o território nacional; CONSIDERANDO a ampla aprovação das Corregedorias-Gerais de Justiça, conforme manifestações contidas nos autos do Pedido de Providências n. 0002227-50.2024.2.00.0000 RESOLVE: Art. 1º. O artigo 284 do Código Nacional de Normas da Corregedoria Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, passa a vigorar com a seguinte alteração: Art. 284. ................................................................................... Parágrafo único. Todos os tabeliães de notas deverão prestar o serviço de que trata esta Seção. (NR). Art. 2º. Este Provimento entra em vigor 30 dias após a data de sua publicação. Ministro MAURO CAMPBELL MARQUES Corregedor Nacional de Justiça');"
];

// Executa a criação de todas as tabelas
foreach ($tabelas as $query) {
    criarTabelaSeNecessario($conn, $query);
}

echo "Execute1 concluído com sucesso.<br>";

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
