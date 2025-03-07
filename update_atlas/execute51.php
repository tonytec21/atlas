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
   "INSERT INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1360, '187', 'CNJ', 'Altera o Código Nacional de Normas da Corregedoria Nacional de Justiçado Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra),instituído pelo Provimento n. 149, de 30 de agosto de 2023, para esclarecera dispensa de escritura pública nos contratos ou termos administrativos dedesapropriação extrajudicial.', '2024-12-03', 'anexo/CNJ/Provimento/2024/187.pdf', 'ADMIN', '2025-03-07 20:39:44', 'Ativo', 'Provimento', 'O CORREGEDOR NACIONAL DE JUSTIÇA, no uso de suas atribuições constitucionais, legais e regimentais, CONSIDERANDO o poder de fiscalização e de normatização do Poder Judiciário em relação aos atos praticados por seus órgãos (art. 103-B, § 4º, I, II e III, da Constituição Federal de 1988); CONSIDERANDO a competência do Poder Judiciário para fiscalizar os serviços notariais e de registro (arts. 103-B, § 4º, I e III, e 236, § 1º,da Constituição Federal); CONSIDERANDO a atribuição do Corregedor Nacional de Justiça de expedir provimentos e outros atos normativos destinados aoaperfeiçoamento das atividades dos serviços notariais e de registro (art. 8º, X, do Regimento Interno do Conselho Nacional de Justiça); CONSIDERANDO que os arts. 23 e 24 da Lei de Introdução às Normas do Direito Brasileiro (Decreto n. 4.657, de 4 de setembro de 1942) recomendam que, em nome da segurança jurídica, sejam protegidos os terceiros de boa-fé que se ampararam em interpretações jurídicas razoáveis; CONSIDERANDO que existem entendimentos divergentes acerca da possibilidade de registro de contratos e termos administrativos de que trata o inciso VI do art. 221 da Lei n. 6.015/1973; CONSIDERANDO o requerimento formulado no Pedido de Providências n. 0004044-86.2023.2.00.0000, RESOLVE: Art. 1º O Título Único do Livro III da Parte Especial do Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, passa a vigorar acrescido do seguinte Capítulo VIII: CAPÍTULO VIII DA DESAPROPRIAÇÃO Seção I Das Disposições Gerais Art. 440-AP. Os contratos e termos administrativos de que trata o inciso VI do art. 221 da Lei n. 6.015/1973 dispensam escritura pública para ingresso no Cartório de Registro de Imóveis, exigido, nesse caso, o reconhecimento de firma. Art. 2º Este Provimento entra em vigor na data de sua publicação, revogando-se as disposições em contrário. Ministro MAURO CAMPBELL MARQUES');",
   "INSERT INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1359, '6', 'CGJ/MA', 'Dispõe sobre a instalação da 3ª Vara da Comarca de Barra do Corda e a redistribuição dos feitos.', '2025-02-10', 'anexo/CGJ_MA/Provimento/2025/6.pdf', 'ADMIN', '2025-03-07 19:51:50', 'Ativo', 'Provimento', 'PROVIMENTO Nº 6, DE 10 DE FEVEREIRO DE 2025. Código de validação: BBC85D7F30 PROV - 62025 ( relativo ao Processo 89052025 ) Dispõe sobre a instalação da 3ª Vara da Comarca de Barra do Corda e a redistribuição dos feitos. O CORREGEDOR-GERAL DA JUSTIÇA DO ESTADO DO MARANHÃO, no uso de suas atribuições legais conferidas pelo art. 32 do Código de Divisão e Organização Judiciárias do Estado do Maranhão (Lei Complementar Estadual nº 14, de 17 de dezembro de 1991) e pelo art. 35 do Regimento Interno do Tribunal de Justiça; CONSIDERANDO a Lei Complementar nº 198, de 7 de novembro de 2017, que alterou a redação e acresceu dispositivos à Lei Complementar nº 14/1991 (Código de Divisão e Organização Judiciárias do Estado do Maranhão); CONSIDERANDO a deliberação do Tribunal de Justiça pela instalação da 3ª Vara da Comarca de Barra do Corda; CONSIDERANDO a necessidade de disciplinar a distribuição de processos para essa unidade, de forma a assegurar o equilíbrio do contingente processual entre as três varas, relativamente à matéria de competência concorrente, sem descurar da observância do princípio do juiz natural, estabelecido conforme as regras de fixação de competência vigentes por ocasião da distribuição da ação; CONSIDERANDO que a competência é determinada no momento do registro ou da distribuição da petição inicial, sendo irrelevantes as modificações do estado de fato ou de direito ocorridas posteriormente (perpetuatio jurisdicionis), salvo quando houver supressão do órgão judiciário ou alteração da competência absoluta, nos termos do artigo 43 do CPC; CONSIDERANDO o disposto no § 6º do art. 2º da Resolução-GP nº 73, de 21 de novembro de 2017, segundo o qual o peso do cargo judicial pode ser utilizado para viabilizar a estipulação de critérios diferenciados de distribuição da carga de trabalho para os órgãos julgadores, em razão de situações excepcionais definidas normativamente ou para correção de desequilíbrios verificados na distribuição dos processos entre magistrados com competências comuns; PROVÊ: Art. 1º Determinar que, a partir da instalação, proceda-se à redistribuição para a 3ª Vara da Comarca de Barra do Corda, criada pela Lei Complementar nº 198, de 7 de novembro de 2017, dos processos relativos às seguintes matérias: crime, família, casamento, sucessões, inventários, partilhas e arrolamentos, alvarás, processamento e julgamento dos crimes de competência do juiz ou juíza singular, processamento e julgamento dos crimes de competência do Tribunal do Júri, presidência do Tribunal do Júri, infância e juventude, Juizado Especial de Violência Doméstica e Familiar contra a Mulher, com a competência prevista no art. 14 combinado com o art. 5º, ambos da Lei Federal nº 11.340, de 7 de agosto de 2006, incluindo o processamento e julgamento dos crimes de competência do Tribunal do Júri e habeas corpus. § 1º Com exceção daqueles arquivados ou pendentes de baixa, todos os processos de competência exclusiva da 3ª Vara deverão ser redistribuídos em conformidade com as regras definidas neste Provimento, incluindo os feitos em fase de cumprimento de sentença. § 2º A redistribuição dos autos eletrônicos de competência exclusiva da 3ª Vara, em tramitação no Sistema Processo Judicial Eletrônico (PJe), será realizada manualmente pela unidade de origem. Art. 2º Estabelecer que não haverá redistribuição para a recém-instalada 3ª Vara da Comarca de Barra do Corda dos processos judiciais de competência comum às unidades (crime, processamento e julgamento dos crimes de competência do juiz ou juíza singular, processamento e julgamento dos crimes de competência do Tribunal do Júri, presidência do Tribunal do Júri, habeas corpus), com jurisdição já firmada por distribuição regular aos juízos da 1ª e 2ª Varas, exceto nas hipóteses legais de modificação de competência mencionadas no art. 1º deste Provimento. § 1º A equivalência do acervo da carga de trabalho do Juízo da 3ª Vara da Comarca de Barra do Corda com os Juízos da 1ª e 2ª Varas, no que se refere à competência concorrente, será alcançada de forma gradual mediante ajustes nos parâmetros de configuração que servem ao algoritmo de distribuição nativo do Sistema Processo Judicial Eletrônico (PJe). TRIBUNAL DE JUSTIÇA DO ESTADO DO MARANHÃO - Praça Dom Pedro II, s/n Centro - CEP 65010-905 - São Luis-MA - Fone: (98) 3198-4300 - www.tjma.jus.br Diário da Justiça Eletrônico - Diretoria Judiciária - Divisão do Diário da Justiça Eletrônico - Fone: (98) 3198-4404 / 3198-4409 - publicacoes@tj.ma.gov.br Página 1 de 2 Art. 3º Caberá à Assessoria de Informática da Corregedoria Geral da Justiça (CGJ-MA) o monitoramento da evolução dos números dos acumuladores de peso dos cargos judiciais das três unidades jurisdicionais. § 1º Quando o número do acumulador de peso do cargo judicial da 3ª Vara da Comarca de Barra do Corda apresentar proporção superior a 95% (noventa e cinco por cento) do peso médio dos acumuladores de peso dos cargos judiciais das outras unidades jurisdicionais, a Diretoria de Informática e Automação deverá ser oficiada para restabelecer os parâmetros de configuração do Sistema Processo Judicial Eletrônico (PJe) que assegurem igualdade na divisão da carga de trabalho entre tais unidades jurisdicionais com competência comum. Art. 4º A configuração, de que trata o artigo 3º, deve ser realizada no prazo máximo de 5 (cinco) dias úteis, a contar da publicação deste normativo. Art. 5º Os casos omissos serão solucionados pela Corregedoria Geral da Justiça, se necessário, com o auxílio da Diretoria de Informática e Automação do Tribunal de Justiça do Estado do Maranhão (TJMA) e da Assessoria de Informática da CGJ-MA. Art. 6º Este Provimento entra em vigor na data de sua publicação. Dê-se ciência. Publique-se. PALÁCIO DA JUSTIÇA \"CLÓVIS BEVILÁCQUA\" DO ESTADO DO MARANHÃO, em São Luís, 10 de fevereiro de 2025. Desembargador JOSÉ LUIZ OLIVEIRA DE ALMEIDA Corregedor-Geral da Justiça Matrícula 16048 Documento assinado. SÃO LUÍS - TRIBUNAL DE JUSTIÇA, 10/02/2025 11:38 (JOSÉ LUIZ OLIVEIRA DE ALMEIDA) Informações de Publicação 25/2025 10/02/2025 às 14:16 11/02/2025 TRIBUNAL DE JUSTIÇA DO ESTADO DO MARANHÃO - Praça Dom Pedro II, s/n Centro - CEP 65010-905 - São Luis-MA - Fone: (98) 3198-4300 - www.tjma.jus.br Diário da Justiça Eletrônico - Diretoria Judiciária - Divisão do Diário da Justiça Eletrônico - Fone: (98) 3198-4404 / 3198-4409 - publicacoes@tj.ma.gov.br Página 2 de 2');"
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
