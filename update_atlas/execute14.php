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
    "REPLACE INTO `provimentos` (`id`, `numero_provimento`, `origem`, `descricao`, `data_provimento`, `caminho_anexo`, `funcionario`, `data_cadastro`, `status`, `tipo`, `conteudo_anexo`) VALUES (2, '5', 'CGJ/MA', 'Regulamenta o disposto no § 2º, do Art. 144-a, da Lei complementar nº 14/91 (Código de Divisão e Organização Judiciárias do Maranhão), estabelecendo critérios à designação de interinos para as Serventias Extrajudiciais do Estado do Maranhão e outras providências. Publicado no DJE do dia 05/02/2016.', '2016-02-03', 'anexo/CGJ_MA/Provimento/2016/5.pdf', 'ADMIN', '2024-08-14 17:12:07', 'Ativo', 'Provimento', 'PROV - 52016 Código de validação: 199169E871 REGULAMENTA O DISPOSTO NO § 2º, DO ART. 144-A, DA LEI COMPLEMENTAR Nº 14/91 (CÓDIGO DE DIVISÃO E ORGANIZAÇÃO JUDICIÁRIAS DO MARANHÃO), ESTABELECENDO CRITÉRIOS À DESIGNAÇÃO DE INTERINOS PARA AS SERVENTIAS EXTRAJUDICIAIS DO ESTADO DO MARANHÃO E OUTRAS PROVIDÊNCIAS. A CORREGEDORA-GERAL DA JUSTIÇA DO ESTADO DO MARANHÃO, no uso de suas atribuições legais, CONSIDERANDO o disposto no art. 236, caput, da Constituição Federal que estabelece que os serviços notariais e de registro são exercidos em caráter privado, por delegação do Poder Público; CONSIDERANDO o disposto no art. 4º, da Lei nº 8.935, de 18/11/1994 c/c o dispositivo constitucional acima estabelecer a obrigatoriedade de que os prestadores de serviço notarial e de registro exerçam suas atribuições de modo eficiente e adequado, os quais são fiscalizados pelo Poder Judiciário; CONSIDERANDO que cabe ao Corregedor-Geral da Justiça o controle e fiscalização da cobrança de custas e emolumentos e, da mesma forma, em caráter geral e permanente, o controle da atividade dos serviços extrajudiciais, tendo a competência para determinar abertura de procedimento investigatório contra delegatários e propor a perda da delegação, nos termos do art. 6º, XXIII, XXIV, XXV, XXXIV e XXXVIII, do Código de Normas da CGJ (Provimento nº 11/2013); CONSIDERANDO o disposto na Resolução nº 80/2009- CNJ quanto à natureza multitudinária das controvérsias sobre serventias extrajudiciais e o interesse público de que o entendimento amplamente predominante seja aplicável de maneira uniforme para todas as questões resolvendo a matéria, dando-se ao tema a natureza objetiva, evitando-se contradições geradoras de insegurança jurídica; CONSIDERANDO as centenas de serventias extrajudiciais no Estado do Maranhão, as quais, muitas vezes, requerem a designação de interinos para as unidades dos serviços vagos e a inexistência de normas que estabeleçam critérios objetivos para referidas designações; CONSIDERANDO a necessidade contínua de apresentar soluções ao alcance da excelência na prestação dos serviços extrajudiciais e, por consequência ao jurisdicionado, usuários destes serviços; CONSIDERANDO o disposto no art. 144-A, § 2º, da Lei Complementar nº 14/91 (acrescentado pela Lei Complementar nº 157, de 17/10/2013) e em atenção aos Princípios da Moralidade e da Impessoalidade, RESOLVE: Art. 1º. A designação de interinos para as serventias vagas no Estado do Maranhão, além de atender ao disposto no art. 3º, § 2º, da Resolução nº 80/09-CNJ, recairá preferencialmente sobre delegatário de serviço notarial ou de registro de igual natureza e do mesmo município em que instalada a serventia vaga, observando-se, ainda, os seguintes critérios: I – não esteja com obrigações pendentes junto ao Fundo Especial de Modernização e Reaparelhamento do Poder Judiciário – FERJ; II – não pode ter sido condenado por decisão judicial ou administrativa relacionada ao exercício da função, mesmo que esteja sob efeito suspensivo, tendo em vista que a designação de interinidade se trata de atividade em confiança do Poder Público delegante; III – a designação de interinidade se limitará a apenas uma serventia, além da que o delegatário é titular. § 1º. Caso não existam delegatários aptos à designação para interinidade, conforme os requisitos constantes do caput e incisos deste artigo, ou, caso preencham mas não manifestem interesse, a escolha recairá sobre titular de serventia extrajudicial dentro do mesmo município, ainda que com natureza diversa da serventia vaga. § 2º. Em não havendo delegatário apto nos termos do caput, incisos e parágrafo primeiro deste artigo, a designação recairá sobre titular de serventia extrajudicial distante até 300 (trezentos) quilômetros, apurados por via de acesso terrestre (estrada), observando-se a seguinte ordem preferencial: I – município diverso da serventia vaga, desde que de natureza idêntica; II – município diverso da serventia vaga, ainda que de natureza diversa. § 3º. Persistindo a impossibilidade de designação de delegatário, ainda que observado o disposto no parágrafo anterior, a escolha deverá ser feita a critério de conveniência e oportunidade da Corregedora-Geral da Justiça. § 4º. A designação promovida nos termos dos parágrafos anteriores não exime que os interinos preencham todos os requisitos constantes do caput e incisos do presente artigo. Art. 2º. Preenchidos os requisitos e demais critérios previstos no caput, incisos e § 1º e 2º, do artigo anterior, por 2 (dois) ou mais delegatários, o desempate será resolvido na seguinte ordem de prioridade: Estado do Maranhão Poder Judiciário CORREGEDORIA GERAL DA JUSTIÇA _ 2 I - quantidade de qualificações em cursos de pós-graduações relacionadas à natureza do serviço; II - quantidade de cursos de atualização relacionadas à natureza do serviço; III - quantidade de publicações em revistas especializadas na matéria; IV - antiguidade na atividade notarial e/ou registral. Art. 3º. Os casos omissos serão decididos, motivadamente, pela Corregedora-Geral de Justiça do Estado do Maranhão. Art. 4º. Este Provimento entra em vigor na data de sua publicação, cabendo à Corregedoria Geral de Justiça adequar as atuais designações aos termos deste instrumento, no prazo máximo de 180 (cento e oitenta) dias. Registre-se. Publique-se. Cumpra-se. Gabinete da Corregedora-Geral da Justiça, ao 2º dia do mês de fevereiro de 2016. Desembargadora ANILDES DE JESUS BERNARDES CHAVES CRUZ Corregedora-geral da Justiça Matrícula 3640 Documento assinado. SÃO LUÍS - TRIBUNAL DE JUSTIÇA, 03/02/2016 11:11 (ANILDES DE JESUS BERNARDES CHAVES CRUZ ) Estado do Maranhão Poder Judiciário CORREGEDORIA GERAL DA JUSTIÇA _ 3');"
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
