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
"CREATE TABLE IF NOT EXISTS `manuais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caminho_video` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('ativo','inativo') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `ordem` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES
	(1, 'Como criar arquivamentos', 'Neste vídeo, você aprenderá passo a passo como criar arquivamentos dentro do Sistema Atlas. Vamos guiá-lo através do processo, desde o início até a finalização, garantindo que você compreenda todas as funcionalidades e opções disponíveis. Este tutorial é ideal para novos usuários que desejam dominar a ferramenta de arquivamento no sistema ou para quem precisa de uma revisão rápida sobre o assunto. Assista e descubra como otimizar o gerenciamento de arquivos no Sistema Atlas!', 'Arquivamento Digital', 'anexos/Arquivamento Digital/COMO CRIAR ARQUIVAMENTOS NO SISTEMA ATLAS.mp4', '2024-09-03 12:03:22', 'ativo', 1),
	(2, 'Como criar tarefas', 'Neste vídeo tutorial, você aprenderá como criar tarefas de forma eficiente no Sistema Atlas. Acompanhe as instruções detalhadas que mostram cada etapa do processo, a criação de uma nova tarefa com a configuração de suas especificações e atribuição para membros da equipe. Este guia é perfeito para quem deseja melhorar sua produtividade e organização utilizando as ferramentas oferecidas pelo Sistema Atlas. Não perca essa oportunidade de maximizar o uso do sistema em suas atividades diárias!', 'Tarefas', 'anexos/Tarefas/COMO CRIAR TAREFAS NO SISTEMA ATLAS.mp4', '2024-09-03 12:10:16', 'ativo', 1),
	(3, 'Como utilizar os filtros de pesquisas', 'Aprenda a utilizar os filtros de pesquisa da ferramenta Controle de Tarefas no Sistema Atlas de forma eficaz. Neste vídeo, mostramos como você pode refinar suas buscas para encontrar rapidamente as tarefas específicas que procura, seja por data, status, responsável ou outros critérios disponíveis. Este tutorial é essencial para quem deseja dominar as funcionalidades de pesquisa e garantir uma gestão de tarefas mais organizada e produtiva. Assista e descubra como otimizar suas operações no Sistema Atlas!', 'Tarefas', 'anexos/Tarefas/COMO UTILIZAR OS FILTROS DE PESQUISA DA FERRAMENTA CONTROLE DE TAREFAS.mp4', '2024-09-03 12:11:54', 'ativo', 2),
	(4, 'Como editar tarefas', 'Neste vídeo, você aprenderá como editar tarefas no Sistema Atlas de maneira rápida e simples. Vamos guiá-lo através do processo de atualização de informações de tarefas, desde a alteração de descrições e datas até a mudança de título. Ideal para usuários que precisam manter suas tarefas sempre atualizadas e alinhadas com as demandas do dia a dia. Assista para garantir que suas tarefas estejam sempre sob controle e refletindo as necessidades atuais do seu projeto!', 'Tarefas', 'anexos/Tarefas/COMO EDITAR TAREFAS NO SISTEMA ATLAS.mp4', '2024-09-03 12:15:22', 'ativo', 3),
	(5, 'Como finalizar as tarefas e emitir o recibo de entrega de documentos', 'Neste vídeo tutorial, você aprenderá como finalizar tarefas no Sistema Atlas e emitir o recibo de entrega de documentos de forma eficiente. Vamos mostrar passo a passo como concluir suas tarefas, garantindo que todas as etapas sejam devidamente registradas, e como gerar e imprimir o recibo de entrega dos documentos relacionados. Este guia é essencial para usuários que precisam formalizar a conclusão de tarefas e documentar a entrega de materiais de forma organizada e profissional. Assista e simplifique seus processos de finalização de tarefas e emissão de recibos!', 'Tarefas', 'anexos/Tarefas/COMO FINALIZAR AS TAREFAS E EMITIR O RECIBO DE ENTREGA DE DOCUMENTOS.mp4', '2024-09-03 12:16:26', 'ativo', 5),
	(6, 'Como visualizar as tarefas, fazer a mudança de status e adicionar comentários', 'Neste vídeo, você aprenderá como visualizar tarefas, alterar seus status e adicionar comentários no Sistema Atlas. Vamos guiá-lo através do processo de monitoramento das suas tarefas, mostrando como ajustar o status conforme o progresso e adicionar comentários para manter todos os envolvidos informados. Este tutorial é ideal para quem deseja manter um controle eficiente das tarefas e garantir uma comunicação clara e contínua dentro da equipe. Assista e veja como essas funcionalidades podem melhorar a gestão de tarefas no seu dia a dia!', 'Tarefas', 'anexos/Tarefas/COMO VISUALIZAR AS TAREFAS, FAZER AS MUDANÇAS DE STATUS E ADICIONAR COMENTÁRIOS.mp4', '2024-09-03 12:17:58', 'ativo', 4);"
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
