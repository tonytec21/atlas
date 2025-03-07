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
"REPLACE INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1361, '188', 'CNJ', 'Altera o Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial(CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, para revogar o Provimento n. 39/2014 e dispor sobre o funcionamento da Central Nacional de Indisponibilidade de Bens (CNIB) 2.0, destinada ao cadastramento de ordens de indisponibilidade de bens específicos ou do patrimônio indistinto, bem como das ordens para cancelamento de indisponibilidade.', '2024-12-04', 'anexo/CNJ/Provimento/2024/188.pdf', 'ADMIN', '2025-03-07 20:41:57', 'Ativo', 'Provimento', 'O CORREGEDOR NACIONAL DE JUSTIÇA, no uso de suas atribuições constitucionais, legais e regimentais, CONSIDERANDO o poder de fiscalização e de normatização do Poder Judiciário em relação aos atos praticados por seus órgãos (art. 103- B, § 4º, I, II e III, da Constituição Federal de 1988); CONSIDERANDO a competência do Poder Judiciário para fiscalizar os serviços notariais e de registro (arts. 103-B, § 4º, I e III, e 236, § 1º, da Constituição Federal); CONSIDERANDO a atribuição da Corregedoria Nacional Justiça de expedir provimentos e outros atos normativos destinados ao aperfeiçoamento das atividades dos serviços notariais e de registro (art. 8º, X, do Regimento Interno do Conselho Nacional de Justiça); CONSIDERANDO, nos termos do art. 76 da Lei nº 13.465, de 11/07/2017, caber ao Operador Nacional do Sistema de Registro Eletrônico de Imóveis (ONR) a implementação e operação do sistema de Registro Eletrônico de Imóveis; CONSIDERANDO a necessidade de haver a padronização no âmbito do território nacional do intercâmbio eletrônico de dados estruturados para o atendimento ao princípio da eficiência insculpida no art. 37 da Constituição Federal; e CONSIDERANDO as previsões constitucionais e legislativas para a imposição de indisponibilidades de bens e a necessidade de lhes dar publicidade (CF, art. 37, § 4º; Lei 6.024/1974, art. 36; Lei 8.397/1992, art. 4º; CTN, art. 185-A; Lei 8.429/1992, art. 7º e 16; Lei 11.101/2005, art. 82, § 2º e art. 154, § 5º; CLT, art. 889; Lei 9.656/1998, art. 23, §4.º, e art. 24-A; Lei 8.443/1992, art. 44, § 2º; Lei Complementar 109/2001, art. 59, §§ 1º e 2º, art. 60 e art. 61, § 2º, II; e Decreto 4.942/2003, art. 101; Lei Federal 13.097/2015, art. 54; Lei Federal 13.105/2015 (Código de Processo Civil), artigos 805, 828 e 854; Lei Federal 13.260/2016, art. 12; Lei Federal 13.465/2017, artigos 74, e Decreto Federal 9.310/2018, art. 91), RESOLVE: Art. 1° O Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, passa a vigorar com as seguintes alterações: “Art. 320. A Central Nacional de Indisponibilidade de Bens (CNIB) é administrada e mantida pelo Operador Nacional do Sistema de Registro Eletrônico de Imóveis (ONR), cuja operação será acompanhada e fiscalizada pela Corregedoria do Conselho Nacional de Justiça, pelas Corregedorias Gerais da Justiça dos Estados e do Distrito Federal e pelas Corregedorias Permanentes dos serviços extrajudiciais de notas e de registros, no âmbito de suas respectivas competências.\" (NR) Art. 320-A. A CNIB tem por finalidade o cadastramento de ordens de indisponibilidade de bens específicos ou do patrimônio indistinto, bem como das ordens para cancelamento de indisponibilidade. § 1º O cadastramento das ordens será realizado pelo número de inscrição no Cadastro de Pessoas Físicas (CPF) ou do número de inscrição no Cadastro Nacional da Pessoa Jurídica (CNPJ), com propósito de afastar risco de homonímia. § 2º Terão acesso à CNIB todas as autoridades judiciárias e administrativas autorizadas em lei a decretarem a indisponibilidade de bens. Art. 320-B. O acesso para inclusão das ordens de indisponibilidade, de cancelamento de indisponibilidade e de consultas circunstanciadas será realizado com o uso de certificado ICP-Brasil e, quando a plataforma estiver no ambiente do SERP (Sistema Eletrônico de Registros Públicos), o acesso será realizado nas formas de autenticação autorizadas pela plataforma. § 1º Ressalvadas as hipóteses relacionadas a processos que tramitem em segredo de justiça, a pessoa sujeita à indisponibilidade de bens poderá consultar os dados de origem das ordens cadastradas em seu nome, desde que vigentes, e obter relatório circunstanciado, com uso de assinatura eletrônica avançada. § 2º O relatório mencionado no parágrafo anterior será gratuito para a pessoa sujeita à ordem de indisponibilidade que acesse o sistema com assinatura eletrônica avançada ou qualificada, ou que compareça, pessoalmente, ao serviço extrajudicial para obter a informação. § 3º Os Órgãos do Poder Judiciário, de qualquer instância, terão acesso livre e integral aos dados e informações constantes na CNIB, inclusive das indisponibilidades canceladas. § 4º O cadastramento de membros e servidores do Ministério Público e/ou membros e servidores de órgãos públicos com legítimo interesse decorrente da natureza do serviço prestado, para fins de consulta, inclusive das ordens canceladas, dar-se-á mediante habilitação, a ser solicitada diretamente no sítio eletrônico do ONR, visando credenciamento com perfil de \"usuário qualificado”. Art. 320-C. A ordem judicial para cancelamento de indisponibilidade deverá indicar se a pessoa atingida é beneficiária da Justiça Gratuita e, nessa situação, a averbação deverá ser efetivada pelo oficial do registro de imóveis sem ônus para os que ocupem ou que tenham ocupado posições de partes processuais, no âmbito das Justiças Comum ou Especial. Parágrafo único. Excetuadas situações abrangidas por isenções e imunidades previstas em Lei, ou ordem judicial em contrário, os emolumentos devidos pelo ato de indisponibilidade serão pagos conjuntamente com os de seu cancelamento, quando praticado sem a exigência da antecipação, pelo interessado que fizer o pedido de cancelamento ao oficial de registro de imóveis. Art. 320-D. Cadastrada na CNIB a autorização de cancelamento da ordem de indisponibilidade, o Oficial de Registro de Imóveis fica obrigado a averbar o seu cancelamento, independentemente de mandado judicial, desde que pagos os emolumentos, quando cabíveis. Art. 320-E. Todas as ordens de indisponibilidade e de cancelamento deverão ser encaminhadas aos oficiais de registro de imóveis, exclusivamente, por intermédio da CNIB, vedada a utilização de quaisquer outros meios, tais como mandados, ofícios, malotes digitais e mensagens eletrônicas. Parágrafo único. As ordens de indisponibilidade e de cancelamento com cadastramento incompleto serão exibidas na tela inicial da autoridade responsável, para a devida complementação, no prazo de 90 (noventa) dias, sob pena de exclusão. Art. 320-F. A consulta ao banco de dados da CNIB será obrigatória para todos os notários e registradores de imóveis, no desempenho de suas atividades, bem como para a prática dos atos de ofício, nos termos da Lei e das normas regulamentares, devendo o resultado da consulta ser consignado no ato notarial. Parágrafo único. A existência de ordem de indisponibilidade não impede a lavratura de escritura pública, mas obriga que as partes sejam cientificadas, bem como que a circunstância seja consignada no ato notarial. Artigo 320-G. No caso de arrematação, alienação ou adjudicação, a autoridade judicial que determinou tais medidas deverá, expressamente, prever o cancelamento das demais constrições oriundas de outros processos, arcando o interessado com os emolumentos devidos. Art. 320-H. A retificação administrativa, a unificação, o desdobro, o desmembramento, a divisão, a estremação, a REURB, salvo na hipótese do art. 74 da Lei n. 13.465/2017, de imóvel com indisponibilidade averbada, independem de autorização da autoridade ordenadora. § 1º A indisponibilidade, nos casos descritos no caput, será transportada para as matrículas abertas e o Oficial de Registro de Imóveis comunicará a providência à autoridade ordenadora. § 2º É dispensada a consulta à CNIB em relação ao adquirente. Art. 320-I. Os oficiais de registro de imóveis deverão consultar, diariamente, a CNIB e prenotar as ordens de indisponibilidade específicas relativas aos imóveis matriculados em suas serventias, bem como devem lançar as indisponibilidades sobre o patrimônio indistinto na base de dados utilizada para o controle da tramitação de títulos representativos de direitos contraditórios. § 1º Ficam dispensadas da verificação diária prevista no caput deste artigo as serventias extrajudiciais que adotarem solução de comunicação com a CNIB via API (Application Programming Interface). § 2º Verificada a existência de bens no nome cadastrado, a indisponibilidade será prenotada e averbada na matrícula ou transcrição do imóvel. Se o imóvel houver passado para outra circunscrição de registro de imóveis, certidão deverá ser encaminhada ao atual registrador, acompanhada de comunicado sobre a ordem de indisponibilidade. Não sendo possível a abertura da matrícula na circunscrição atual, a averbação será realizada na serventia de origem. § 3º A superveniência de ordem de indisponibilidade impede o registro de títulos, ainda que anteriormente prenotados, salvo exista na ordem judicial previsão em contrário. Art. 320-J. Em caso de aquisição de imóvel por pessoa cujos bens foram atingidos por ordem de indisponibilidade, deverá o oficial de registro de imóveis, imediatamente após o registro do título aquisitivo na matrícula, promover a averbação da indisponibilidade, independentemente de prévia consulta ao adquirente, inclusive nos casos em que a aquisição envolver contratos garantidos por alienação fiduciária, recaindo sobre os direitos do devedor fiduciante ou do credor fiduciário. Parágrafo único. Imediatamente após a averbação da indisponibilidade na matrícula ou transcrição do imóvel, o registrador comunicará à autoridade ordenadora a sua efetivação. Art. 320-K. Os titulares de direitos reais sobre bens imóveis poderão eleger um ou mais imóveis, dentre os de sua titularidade, sobre os quais pretendem que recaiam, preferencialmente, eventuais ordens de indisponibilidade, formando uma base indicativa disponível para consulta no momento de cadastramento de ordens, conforme previsão em manual operacional do ONR. Parágrafo único. A indicação mencionada no caput deste artigo: I - tornar-se-á sem efeito com sua revogação ou com a alteração do proprietário ou titular de direito, salvo se decorrer de constituição de propriedade resolúvel por alienação fiduciária em garantia; II – não vincula os órgãos do Poder Judiciário ou as autoridades administrativas, que poderão determinar a indisponibilidade de bens imóveis não integrantes daquela base indicativa. Art. 320-L. O acesso à CNIB pelos órgãos públicos, notários e registradores, bem como a consulta do interessado sobre cadastramentos em seu próprio nome será realizada de forma gratuita. Parágrafo único. O acesso de terceiros, entidades de proteção de crédito e demais interessados será realizado mediante identificação e custeio do respectivo serviço. Art. 320-M. O contínuo acompanhamento, controle gerencial e fiscalização pela Corregedoria Nacional de Justiça, Corregedorias-Gerais de Justiça dos Estados e do Distrito Federal e Corregedorias Permanentes dos serviços extrajudiciais de notas e de registros será realizado por módulo de geração de relatórios (correição on-line) e de estatísticas, disponibilizado pelo ONR. Art. 320-N. A apresentação da página na internet, a forma de preenchimento de formulários, os formatos dos dados, o cadastramento de autoridades e dos demais usuários, os métodos de identificação, a gestão do acesso, a usabilidade, a interoperabilidade, os requisitos do sistema e questões técnicas relativas ao uso da tecnologia constarão do manual operacional elaborado pelo ONR.” Art. 2º Este Provimento entra em vigor 30 (trinta) dias após a sua publicação, momento a partir do qual ficará revogado o Provimento n. 39, de 25 de julho de 2014. Ministro MAURO CAMPBELL MARQUES');",
"REPLACE INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (1360, '187', 'CNJ', 'Altera o Código Nacional de Normas da Corregedoria Nacional de Justiçado Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra),instituído pelo Provimento n. 149, de 30 de agosto de 2023, para esclarecera dispensa de escritura pública nos contratos ou termos administrativos dedesapropriação extrajudicial.', '2024-12-03', 'anexo/CNJ/Provimento/2024/187.pdf', 'ADMIN', '2025-03-07 20:39:44', 'Ativo', 'Provimento', 'O CORREGEDOR NACIONAL DE JUSTIÇA, no uso de suas atribuições constitucionais, legais e regimentais, CONSIDERANDO o poder de fiscalização e de normatização do Poder Judiciário em relação aos atos praticados por seus órgãos (art. 103-B, § 4º, I, II e III, da Constituição Federal de 1988); CONSIDERANDO a competência do Poder Judiciário para fiscalizar os serviços notariais e de registro (arts. 103-B, § 4º, I e III, e 236, § 1º,da Constituição Federal); CONSIDERANDO a atribuição do Corregedor Nacional de Justiça de expedir provimentos e outros atos normativos destinados aoaperfeiçoamento das atividades dos serviços notariais e de registro (art. 8º, X, do Regimento Interno do Conselho Nacional de Justiça); CONSIDERANDO que os arts. 23 e 24 da Lei de Introdução às Normas do Direito Brasileiro (Decreto n. 4.657, de 4 de setembro de 1942) recomendam que, em nome da segurança jurídica, sejam protegidos os terceiros de boa-fé que se ampararam em interpretações jurídicas razoáveis; CONSIDERANDO que existem entendimentos divergentes acerca da possibilidade de registro de contratos e termos administrativos de que trata o inciso VI do art. 221 da Lei n. 6.015/1973; CONSIDERANDO o requerimento formulado no Pedido de Providências n. 0004044-86.2023.2.00.0000, RESOLVE: Art. 1º O Título Único do Livro III da Parte Especial do Código Nacional de Normas da Corregedoria Nacional de Justiça do Conselho Nacional de Justiça – Foro Extrajudicial (CNN/CN/CNJ-Extra), instituído pelo Provimento n. 149, de 30 de agosto de 2023, passa a vigorar acrescido do seguinte Capítulo VIII: “CAPÍTULO VIII DA DESAPROPRIAÇÃO Seção I Das Disposições Gerais Art. 440-AP. Os contratos e termos administrativos de que trata o inciso VI do art. 221 da Lei n. 6.015/1973 dispensam escritura pública para ingresso no Cartório de Registro de Imóveis, exigido, nesse caso, o reconhecimento de firma.” Art. 2º Este Provimento entra em vigor na data de sua publicação, revogando-se as disposições em contrário. Ministro MAURO CAMPBELL MARQUES');"
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
