/*!
 * GuiaOS — passos do módulo O.S. (Atlas)
 * ------------------------------------------------------------------
 * Os seletores apontam para elementos que JÁ existem nas telas
 * (index.php, criar_os.php e visualizar_os.php), portanto nenhuma
 * alteração no HTML atual é necessária.
 *
 * Para editar os textos, mexa apenas neste arquivo.
 */
(function () {
    'use strict';

    if (!window.GuiaOS) { return; }

    var pagina = (window.location.pathname.split('/').pop() || 'index.php').toLowerCase();

    /* ==================================================================
     * 1) TELA DE PESQUISA — index.php
     * ================================================================ */
    var passosPesquisa = [
        {
            alvo: '.filter-card',
            titulo: 'Bem-vindo ao módulo O.S.',
            texto: 'Esta é a tela de <b>Pesquisar Ordens de Serviço</b>. Aqui você localiza uma O.S. '
                 + 'já existente pelos filtros (número, apresentante, CPF/CNPJ, período, situação) '
                 + 'e também cria uma nova.',
            posicao: 'baixo'
        },
        {
            alvo: '#tabelaResultados',
            titulo: 'Resultados da pesquisa',
            texto: 'Cada linha traz o valor total, o depósito prévio, o valor liquidado e a situação da O.S. '
                 + 'Na coluna <b>Ações</b> ficam os botões de <b>visualizar</b>, <b>imprimir</b> e <b>anexos</b>.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="tabela_de_emolumentos"]',
            titulo: 'Tabela de Emolumentos',
            texto: 'Em caso de dúvida sobre o código de um ato, consulte a tabela vigente por aqui.',
            opcional: true
        },
        {
            alvo: 'a[href*="liberar_os"]',
            titulo: 'Desfazer Liquidações',
            texto: 'Liquidou um ato por engano? Nesta tela é possível desfazer as liquidações '
                 + '<b>feitas hoje</b> em uma O.S. Ela tem o próprio guia, no botão “?”.',
            opcional: true
        },
        {
            alvo: 'a[href*="modelos_orcamento"]',
            titulo: 'Modelos O.S',
            texto: 'Aqui ficam os <b>modelos de O.S.</b>: conjuntos de atos já montados para os serviços '
                 + 'repetitivos do cartório. Depois de criado, o modelo é carregado com um clique na tela '
                 + 'de criação da O.S. Abra esta tela e use o botão “?” para ver o guia dos modelos.',
            opcional: true
        },
        {
            alvo: 'button[onclick*="criar_os.php"]',
            titulo: 'Criar uma nova O.S.',
            texto: 'Clique neste botão para abrir a tela de cadastro. O guia continua automaticamente na próxima tela.',
            avancarEm: { evento: 'click', dica: 'Clique no botão destacado para continuar.' },
            irPara: 'criar_os.php',
            retomar: { guia: 'criar-os', indice: 0 }
        }
    ];

    /* ==================================================================
     * 2) CRIAÇÃO DA O.S. — criar_os.php
     * ================================================================ */
    var passosCriar = [
        {
            alvo: '#modelo_orcamento',
            titulo: 'Modelo de O.S. (opcional)',
            texto: 'Se o serviço for repetitivo, escolha um modelo pronto: todos os atos são lançados de uma vez. '
                 + 'Você ainda poderá acrescentar ou remover atos depois.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cliente',
            titulo: 'Apresentante (obrigatório)',
            texto: 'Digite o nome de quem está solicitando o serviço. <b>Sem este campo o botão SALVAR OS '
                 + 'continua desativado.</b>',
            posicao: 'baixo'
        },
        {
            alvo: '#cpf_cliente',
            titulo: 'CPF/CNPJ do apresentante',
            texto: 'Digite apenas os números. Ao sair do campo o sistema formata e valida o documento — '
                 + 'se for inválido, o campo é limpo e um aviso é exibido.',
            posicao: 'baixo'
        },
        {
            alvo: '#base_calculo',
            titulo: 'Base de cálculo',
            texto: 'Informe o valor do negócio jurídico quando o ato for cobrado por faixa de valor '
                 + '(escrituras, registros com valor declarado etc.).',
            posicao: 'baixo'
        },
        {
            alvo: '#total_os',
            titulo: 'Valor total da O.S.',
            texto: 'Campo apenas de leitura: é a <b>soma automática</b> dos itens lançados. '
                 + 'Ele se atualiza a cada ato adicionado, editado ou removido.',
            posicao: 'baixo'
        },
        {
            alvo: '#descricao_os',
            titulo: 'Título da O.S.',
            texto: 'Uma descrição curta do serviço, por exemplo <code>Certidão de Inteiro Teor — Matrícula 4.821</code>. '
                 + 'Facilita localizar a O.S. depois, na tela de pesquisa.',
            posicao: 'baixo'
        },
        {
            alvo: '#ato',
            titulo: 'Código do ato',
            texto: 'Agora começa o lançamento dos atos, um a um. Informe o código conforme a Tabela de Emolumentos '
                 + '(somente números e ponto).',
            posicao: 'baixo'
        },
        {
            alvo: '#quantidade',
            titulo: 'Quantidade',
            texto: 'Quantas vezes o ato será cobrado. O mínimo é 1.',
            posicao: 'baixo'
        },
        {
            alvo: '#desconto_legal',
            titulo: 'Desconto legal (%)',
            texto: 'Percentual de desconto previsto em lei, quando houver. Aceita de 0 a 100.',
            posicao: 'baixo'
        },
        {
            alvo: 'button[onclick*="buscarAto"]',
            titulo: 'Buscar Ato',
            texto: 'Clique aqui para consultar a tabela vigente. A descrição e todos os valores '
                 + '(emolumentos, FERC, FADEP, FEMP, FERRFIS e total) são preenchidos automaticamente.',
            posicao: 'baixo',
            avancarEm: { evento: 'click', atraso: 900, dica: 'Clique em “Buscar Ato” para continuar.' }
        },
        {
            alvo: '#emolumentos',
            subir: '.form-row',
            titulo: 'Valores calculados',
            texto: 'Estes campos vêm da Tabela de Emolumentos e ficam bloqueados.<ul>'
                 + '<li>Mudou a <b>quantidade</b> ou o <b>desconto</b>? Os valores são recalculados na hora.</li>'
                 + '<li>Mudou o <b>código do ato</b>? O cálculo é descartado: clique em “Buscar Ato” de novo.</li></ul>',
            posicao: 'topo'
        },
        {
            alvo: 'button[onclick*="adicionarAtoManual"]',
            titulo: 'Ato fora da tabela',
            texto: 'Use apenas quando o serviço não tiver código na tabela. O código passa a ser <code>0</code> '
                 + 'e os campos de valor ficam liberados para digitação.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#osForm button[type="submit"]',
            titulo: 'Adicionar à OS',
            texto: 'Confira os valores e clique para lançar o ato na lista de itens. Os campos são limpos em seguida, '
                 + 'prontos para o próximo ato. <b>Repita o ciclo para cada ato da O.S.</b>',
            posicao: 'topo',
            avancarEm: { evento: 'click', atraso: 700, dica: 'Clique em “Adicionar à OS” para continuar.' }
        },
        {
            alvo: '#osItens',
            titulo: 'Itens da Ordem de Serviço',
            texto: 'Aqui ficam os atos já lançados. Nesta lista você pode:<ul>'
                 + '<li>clicar na <b>quantidade</b> ou na <b>descrição</b> para editar direto na linha;</li>'
                 + '<li><b>arrastar</b> a linha para reordenar (a numeração se refaz sozinha);</li>'
                 + '<li>usar <b>Ato Isento</b> para zerar os valores do item;</li>'
                 + '<li>usar a <b>lixeira</b> para remover o item.</li></ul>'
                 + 'Se o ISS estiver ativado na configuração, aparece ainda uma linha “ISS”, calculada pelo sistema '
                 + 'e que não pode ser editada nem removida.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#observacoes',
            titulo: 'Observações',
            texto: 'Campo livre para informações do atendimento: prazo de entrega, exigências, quem vai retirar o documento.',
            posicao: 'topo'
        },
        {
            alvo: '#btnSalvarOS',
            titulo: 'Salvar a O.S.',
            texto: 'O botão só é liberado quando existir o <b>apresentante</b> e <b>pelo menos um ato</b> lançado. '
                 + 'Ao salvar, o sistema grava a O.S. e abre a tela dela, já com o número gerado.',
            posicao: 'topo'
        }
    ];

    /* ==================================================================
     * 3) O.S. JÁ CRIADA — visualizar_os.php
     * ================================================================ */
    var passosVisualizar = [
        {
            alvo: '.header-actions',
            titulo: 'O que fazer agora',
            texto: 'Esta é a barra de ações da O.S.: imprimir, assinar digitalmente, lançar pagamentos, '
                 + 'anexar documentos, editar, cancelar e criar tarefa.',
            posicao: 'baixo'
        },
        {
            alvo: '[data-target="#pagamentoModal"]',
            titulo: 'Pagamentos',
            texto: 'Lance aqui o depósito prévio. Para formas como PIX, transferência, boleto ou cheque, '
                 + 'lembre-se de anexar o comprovante.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#osItens',
            titulo: 'Itens e liquidação',
            texto: 'Depois de lançado o pagamento, cada item pode ser liquidado individualmente — '
                 + 'ou todos de uma vez, pelo botão <b>Liquidar Tudo</b>.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="assinarDocOS"]',
            titulo: 'Assinar digitalmente',
            texto: 'Abre, em outra aba, a tela de assinatura ICP-Brasil pelo Assinador SERPRO. '
                 + 'O botão <b>Assinar A4</b>, no grupo dos recibos, leva à mesma tela — muda apenas o '
                 + 'documento assinado. Lá existe o próprio guia, no botão “?”.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="editarOS"]',
            titulo: 'Editar a O.S.',
            texto: 'Esqueceu um ato ou precisa corrigir a quantidade? Abra <b>Editar OS</b> — lá dá para '
                 + 'incluir e remover atos, ajustar quantidades e corrigir os dados do apresentante. '
                 + 'Use o botão “?” naquela tela para ver o guia da edição.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="imprimirOS"]',
            titulo: 'Entrega ao apresentante',
            texto: 'Imprima a O.S./orçamento para entregar ao apresentante. Se o cartório usa assinatura digital, '
                 + 'use antes o botão <b>Assinar OS</b>.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    /* ==================================================================
     * 4) MODELOS DE O.S. — modelos_orcamento.php
     * ================================================================ */
    var passosModelos = [
        {
            alvo: '.page-hero',
            titulo: 'Para que servem os modelos',
            texto: 'Um <b>modelo de O.S.</b> guarda um conjunto de atos usado com frequência '
                 + '(por exemplo: “Registro de imóvel padrão” ou “Escritura + certidões”). '
                 + 'Na hora de criar uma Ordem de Serviço, basta escolher o modelo e todos os atos '
                 + 'são lançados de uma só vez.',
            posicao: 'baixo'
        },
        {
            alvo: '#nome_modelo',
            titulo: 'Nome do modelo (obrigatório)',
            texto: 'Use um nome que o balcão reconheça de imediato, como <code>Registro de Imóvel Padrão</code>. '
                 + 'É por ele que o modelo aparecerá na lista da tela de criação da O.S.',
            posicao: 'baixo'
        },
        {
            alvo: '#descricao_modelo',
            titulo: 'Descrição (opcional)',
            texto: 'Explique quando este modelo deve ser usado — ajuda quem está começando no atendimento.',
            posicao: 'baixo'
        },
        {
            alvo: '#ato',
            titulo: 'Código do ato',
            texto: 'Agora monte a lista de atos do modelo, um a um. Informe o código conforme a '
                 + 'Tabela de Emolumentos.',
            posicao: 'baixo'
        },
        {
            alvo: '#quantidade',
            titulo: 'Quantidade',
            texto: 'Quantas vezes este ato entra no modelo. Pode ser alterado depois, na O.S. gerada.',
            posicao: 'baixo'
        },
        {
            alvo: '#desconto_legal',
            titulo: 'Desconto (%)',
            texto: 'Percentual de desconto legal, quando o ato tiver previsão. Deixe 0 se não houver.',
            posicao: 'baixo'
        },
        {
            alvo: 'button[onclick*="buscarAto"]',
            titulo: 'Buscar Ato',
            texto: 'Clique para consultar a tabela vigente: a descrição e os valores do ato são '
                 + 'preenchidos automaticamente.',
            posicao: 'baixo',
            avancarEm: { evento: 'click', atraso: 900, dica: 'Clique em “Buscar Ato” para continuar.' }
        },
        {
            alvo: '#descricao_item',
            titulo: 'Descrição do item',
            texto: 'Vem preenchida pela tabela e fica bloqueada. Ela só se torna editável no modo '
                 + '<b>Adicionar Manualmente</b>.',
            posicao: 'baixo'
        },
        {
            alvo: '#emolumentos',
            subir: '.row',
            titulo: 'Valores do ato',
            texto: 'Emolumentos, FERC, FADEP, FEMP, FERRFIS e total, conforme a tabela vigente. '
                 + 'O <b>Total precisa ser maior que zero</b> para o item ser aceito.',
            posicao: 'topo'
        },
        {
            alvo: 'button[onclick*="adicionarAtoManual"]',
            titulo: 'Ato fora da tabela',
            texto: 'Use apenas quando o serviço não tiver código na Tabela de Emolumentos: a descrição '
                 + 'e os valores passam a ser digitados por você.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="adicionarItemTabela"]',
            titulo: 'Adicionar Item',
            texto: 'Clique para incluir o ato na lista do modelo. Depois é só repetir o ciclo '
                 + '(código → Buscar Ato → Adicionar Item) para cada ato que o modelo deve conter.',
            posicao: 'topo',
            avancarEm: { evento: 'click', atraso: 700, dica: 'Clique em “Adicionar Item” para continuar.' }
        },
        {
            alvo: '#tabelaItensModelo',
            subir: '.table-wrapper',
            titulo: 'Itens do modelo',
            texto: 'Confira aqui os atos já incluídos. Para tirar algum, use o botão de <b>lixeira</b> '
                 + 'na coluna Ações. Os valores são apenas uma referência: ao gerar a O.S., o sistema '
                 + 'recalcula tudo pela tabela vigente na data.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'button[onclick*="salvarModelo"]',
            titulo: 'Salvar Modelo',
            texto: 'Grava o modelo. São exigidos o <b>nome</b> e <b>ao menos um item</b> — sem isso o '
                 + 'sistema avisa e não salva.',
            posicao: 'topo'
        },
        {
            alvo: '#listaModelos',
            subir: '.card',
            titulo: 'Modelos cadastrados',
            texto: 'A lista abaixo mostra tudo o que já existe. Em cada modelo você tem:<ul>'
                 + '<li><b>Visualizar</b> — abre os itens do modelo em uma janela;</li>'
                 + '<li><b>Editar</b> — carrega o modelo no formulário acima (o título muda para '
                 + '“Editar Modelo” e o botão para “Atualizar Modelo”);</li>'
                 + '<li><b>Excluir</b> — remove o modelo, mediante confirmação.</li></ul>',
            posicao: 'topo',
            aguardar: 4000,
            opcional: true
        },
        {
            titulo: 'Pronto — como usar o modelo',
            texto: 'Na tela <b>Criar Ordem de Serviço</b>, escolha o modelo no campo '
                 + '<b>“Carregar Modelo de O.S.”</b>, no topo da página: todos os atos são lançados '
                 + 'de uma vez e você ainda pode acrescentar ou remover o que precisar.'
        }
    ];

    /* ==================================================================
     * 5) EDIÇÃO DA O.S. — editar_os.php
     * ================================================================ */
    var passosEditar = [
        {
            alvo: '.page-hero',
            titulo: 'Editando uma O.S. já gravada',
            texto: 'Atenção a uma diferença importante desta tela:<ul>'
                 + '<li>as alterações nos <b>itens</b> (incluir, alterar quantidade, isentar, remover) '
                 + 'valem <b>na hora</b>, assim que confirmadas;</li>'
                 + '<li>os dados do <b>cabeçalho</b> (apresentante, CPF/CNPJ, base de cálculo, título e '
                 + 'observações) só são gravados ao clicar em <b>SALVAR OS</b>.</li></ul>'
                 + 'Toda edição fica registrada no log da O.S., com o usuário que a fez.',
            posicao: 'baixo'
        },
        {
            alvo: '#cliente',
            titulo: 'Apresentante',
            texto: 'Corrija aqui o nome de quem apresentou o serviço, se tiver sido digitado errado.',
            posicao: 'baixo'
        },
        {
            alvo: '#cpf_cliente',
            titulo: 'CPF/CNPJ do apresentante',
            texto: 'Mesma validação da tela de criação: ao sair do campo o documento é formatado e conferido.',
            posicao: 'baixo'
        },
        {
            alvo: '#base_calculo',
            titulo: 'Base de cálculo',
            texto: 'Ajuste quando o valor do negócio jurídico tiver mudado. Lembre-se de conferir depois '
                 + 'os atos cobrados por faixa de valor.',
            posicao: 'baixo'
        },
        {
            alvo: '#total_os',
            titulo: 'Total da O.S.',
            texto: 'Campo somente leitura: é recalculado automaticamente a cada item incluído, alterado, '
                 + 'isentado ou removido.',
            posicao: 'baixo'
        },
        {
            alvo: '#descricao_os',
            titulo: 'Título da O.S.',
            texto: 'Pode ser ajustado livremente — é o texto que aparece na tela de pesquisa.',
            posicao: 'baixo'
        },
        {
            alvo: '#ato',
            titulo: 'Incluir um novo ato',
            texto: 'Para acrescentar um ato à O.S., o caminho é o mesmo da criação: informe o código, '
                 + 'a quantidade e o desconto legal.',
            posicao: 'baixo'
        },
        {
            alvo: 'button[onclick*="buscarAto"]',
            titulo: 'Buscar Ato',
            texto: 'Consulta a tabela vigente e preenche a descrição e os valores do ato.',
            posicao: 'baixo',
            avancarEm: { evento: 'click', atraso: 900, dica: 'Clique em “Buscar Ato” para continuar.' }
        },
        {
            alvo: '#emolumentos',
            subir: '.form-row',
            titulo: 'Valores do novo ato',
            texto: 'Conferidos pela tabela e bloqueados. Se o ato não existir na tabela, use '
                 + '<b>Adicionar Ato Manualmente</b> para digitar descrição e valores.',
            posicao: 'topo'
        },
        {
            alvo: '#osForm button[onclick*="adicionarItem"]',
            titulo: 'Adicionar à OS',
            texto: 'Inclui o ato na Ordem de Serviço. Diferente da tela de criação, aqui a inclusão é '
                 + 'gravada imediatamente e o total da O.S. já é atualizado.',
            posicao: 'topo',
            avancarEm: { evento: 'click', atraso: 800, dica: 'Clique em “Adicionar à OS” para continuar.' }
        },
        {
            alvo: '#tabelaItensOS',
            titulo: 'Itens da Ordem de Serviço',
            texto: 'Além das colunas de valores, esta lista traz a <b>Qtd Liquidada</b> — quanto do item '
                 + 'já foi liquidado. É ela que define o que ainda pode ser alterado.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'button[onclick*="alterarQuantidade"]',
            titulo: 'Alterar quantidade',
            texto: 'Abre a janela <b>Alterar Quantidade</b>. A nova quantidade <b>não pode ser menor que a '
                 + 'quantidade já liquidada</b> do item; os valores são recalculados pela tabela ao confirmar.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="marcarItemIsento"]',
            titulo: 'Marcar como isento',
            texto: 'Zera os valores do ato e acrescenta “(isento)” ao código, mediante confirmação. '
                 + 'A linha de ISS nunca pode ser isentada.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="removerItem"]',
            titulo: 'Remover item',
            texto: 'Exclui o ato da O.S., com confirmação. <b>Item parcialmente liquidado não pode ser '
                 + 'removido</b> — o sistema bloqueia a operação.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#observacoes',
            titulo: 'Observações',
            texto: 'Atualize as informações do atendimento — este campo é gravado junto com o botão abaixo.',
            posicao: 'topo'
        },
        {
            alvo: 'button[onclick*="salvarOS"]',
            titulo: 'SALVAR OS',
            texto: 'Grava as alterações do cabeçalho e devolve você à tela da Ordem de Serviço, '
                 + 'já com os valores atualizados.',
            posicao: 'topo'
        }
    ];

    /* ==================================================================
     * 5b) PAGAMENTOS — janela "Efetuar Pagamento" (visualizar_os.php)
     * ================================================================ */
    var passosPagamento = [
        {
            alvo: '#total_os_modal',
            subir: '.row',
            titulo: 'Painel de pagamentos',
            texto: 'No topo ficam os números da O.S.: <b>Valor Total</b>, <b>Valor Pago</b>, '
                 + '<b>Valor Liquidado</b>, <b>Saldo</b> e, quando houver, <b>Valor Devolvido</b> e '
                 + '<b>Repasse Credor</b>. Eles se atualizam a cada lançamento.',
            posicao: 'baixo',
            aguardar: 3000
        },
        {
            alvo: '#saldo_modal',
            titulo: 'Saldo',
            texto: 'É a diferença entre o que foi pago e o total da O.S. Saldo negativo significa que '
                 + 'ainda falta receber; positivo, que há valor a devolver ao apresentante.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#forma_pagamento',
            titulo: 'Forma de pagamento',
            texto: 'Escolha como o valor foi recebido: espécie, crédito, débito, PIX, centrais '
                 + 'eletrônicas, transferência, depósito, boleto ou cheque.<br><b>Atenção:</b> em PIX, '
                 + 'transferência, boleto e cheque o sistema passa a cobrar o <b>comprovante anexado</b> — '
                 + 'na tela de pesquisa a O.S. fica marcada em vermelho enquanto o anexo não existir.',
            posicao: 'baixo'
        },
        {
            alvo: '#valor_pagamento',
            titulo: 'Valor do pagamento',
            texto: 'Digite o valor recebido. Pode ser pago em partes: basta lançar um pagamento de cada vez.'
                 + '<br>Em <b>espécie</b>, os centavos precisam terminar em <b>0 ou 5</b> — é a regra do '
                 + 'troco em moeda corrente, e o sistema recusa outros valores.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnAdicionarPagamento',
            titulo: 'Adicionar Pagamento',
            texto: 'Registra o pagamento. Se já existir um lançamento igual (mesma forma e mesmo valor), '
                 + 'o sistema pede confirmação, para evitar duplicidade por engano.',
            posicao: 'topo',
            avancarEm: { evento: 'click', atraso: 900, dica: 'Clique em “Adicionar Pagamento” para continuar.' }
        },
        {
            alvo: '#tabelaIPagamentoOS',
            titulo: 'Pagamentos lançados',
            texto: 'A lista mostra forma, valor, data e o funcionário que registrou. Em cada linha há '
                 + 'o botão do <b>comprovante</b> (com o número de anexos) e o de <b>remover</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#btnIsentoPagamento',
            titulo: 'Isentar o pagamento',
            texto: 'Para os casos previstos em lei (gratuidade), registre a isenção por aqui em vez de '
                 + 'lançar um valor. A O.S. passa a constar como <b>Isento</b> na pesquisa.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="abrirDevolucaoModal"]',
            titulo: 'Devolução e repasse',
            texto: 'Recebeu a mais? Use <b>Devolução</b> para devolver a diferença ao apresentante. '
                 + '<b>Repasse Credor</b> registra valores repassados a terceiros.',
            posicao: 'topo',
            opcional: true
        },
        {
            titulo: 'Próximo passo: liquidar',
            texto: 'Com o pagamento registrado, feche esta janela e <b>liquide os atos</b> na lista de '
                 + 'itens — um a um ou com o botão <b>Liquidar Tudo</b>. Só depois de liquidados a O.S. '
                 + 'aparece como concluída na pesquisa.'
        }
    ];

    /* ==================================================================
     * 5c) ANEXOS — janela "Anexos" (visualizar_os.php)
     * ================================================================ */
    var passosAnexo = [
        {
            alvo: '.upload-card',
            titulo: 'Anexos da Ordem de Serviço',
            texto: 'Aqui ficam guardados os documentos digitalizados da O.S.: comprovantes de pagamento, '
                 + 'requerimentos, procurações, documentos do apresentante.<br><b>Importante:</b> quando o '
                 + 'pagamento é por PIX, transferência, boleto ou cheque, o comprovante deve ser anexado — '
                 + 'enquanto não estiver, a O.S. fica sinalizada em vermelho na tela de pesquisa.',
            posicao: 'baixo',
            aguardar: 3000
        },
        {
            alvo: '#novo_anexo',
            subir: '.custom-file',
            titulo: 'Escolher os arquivos',
            texto: 'Clique para abrir o seletor de arquivos. É possível <b>marcar vários de uma vez</b> '
                 + '(segurando Ctrl); o nome dos selecionados aparece no próprio campo.',
            posicao: 'baixo'
        },
        {
            alvo: '#formAnexos button[onclick*="salvarAnexo"]',
            titulo: 'Anexar Arquivos',
            texto: 'Envia os arquivos escolhidos e vincula-os a esta O.S. O sistema confirma o envio e '
                 + 'atualiza a lista logo abaixo.',
            posicao: 'topo',
            avancarEm: { evento: 'click', atraso: 1200, dica: 'Clique em “Anexar Arquivos” para continuar.' }
        },
        {
            alvo: '#anexosTable',
            titulo: 'Anexos adicionados',
            texto: 'A lista mostra tudo o que já foi anexado. O botão do <b>olho</b>, na coluna Ações, abre '
                 + 'o arquivo para conferência. Havendo muitos anexos, use a busca e a paginação da tabela.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            titulo: 'Pronto',
            texto: 'Feche a janela para voltar à Ordem de Serviço. Na tela de pesquisa, o botão de anexos '
                 + 'da O.S. passa a listar os documentos vinculados — e deixa de ficar em vermelho quando '
                 + 'o comprovante exigido já estiver lá.'
        }
    ];

    /* ==================================================================
     * 5d) ASSINATURA DIGITAL — assinar-os.php
     *     A mesma tela assina a O.S./orçamento (tipo=os) e o Recibo A4
     *     (tipo=recibo_a4); por isso um único guia atende aos dois casos.
     * ================================================================ */
    var passosAssinar = [
        {
            alvo: '.hero',
            titulo: 'Assinatura digital do documento',
            texto: 'Esta é a tela de assinatura <b>ICP-Brasil (PAdES / AD-RB)</b> pelo Assinador SERPRO. '
                 + 'Ela é a mesma para a <b>Ordem de Serviço/orçamento</b> e para o <b>Recibo A4</b> — '
                 + 'muda apenas o documento carregado, indicado no título acima.',
            posicao: 'baixo'
        },
        {
            alvo: '#serproChip',
            titulo: 'Situação do Assinador',
            texto: 'Este selo mostra se o sistema conseguiu falar com o Assinador SERPRO instalado na '
                 + 'máquina. Enquanto ele não estiver <b>conectado</b>, o botão de assinar continua desligado.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#sAstat',
            titulo: 'Conectando o token',
            texto: 'O painel do Assinador explica o que fazer em cada situação:<ul>'
                 + '<li><b>Não está em execução</b> — abra o Assinador SERPRO e clique em <b>Reconectar</b>;</li>'
                 + '<li><b>Autorização pendente</b> — clique em <b>Autorizar</b>, libere o acesso e reconecte;</li>'
                 + '<li><b>Assinador conectado</b> — o token está pronto.</li></ul>'
                 + 'Confira também se o certificado A3 está espetado na máquina.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnAutorizarAssinador',
            titulo: 'Autorizar o Assinador',
            texto: 'Se o estado for <b>“Autorização pendente”</b>, é porque o Assinador ainda não liberou '
                 + 'o acesso do sistema ao token. Clique aqui: a permissão abre <b>dentro desta tela</b> e, '
                 + 'assim que você autorizar, o sistema reconecta sozinho.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnReconnect',
            titulo: 'Reconectar',
            texto: 'Use quando o Assinador foi aberto (ou o token foi espetado) depois desta tela. '
                 + 'Ele refaz a verificação sem precisar recarregar a página.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#pages',
            titulo: 'Onde a assinatura vai aparecer',
            texto: 'A pré-visualização mostra o documento. <b>Clique no ponto da página</b> em que o selo de '
                 + 'assinatura deve ficar — depois é possível <b>arrastá-lo</b> para ajustar a posição.',
            posicao: 'dir',
            aguardar: 5000,
            opcional: true
        },
        {
            alvo: '#sealW',
            titulo: 'Tamanho do selo',
            texto: 'Ajuste a largura do selo (de 28% a 72% da página) para não cobrir informações do documento. '
                 + 'O valor escolhido aparece ao lado do controle.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnAssinar',
            titulo: 'Assinar com o token',
            texto: 'Só fica habilitado com o Assinador conectado e a posição do selo definida. Ao clicar, o '
                 + 'token pede o <b>PIN</b> e o documento assinado é gravado no lugar do original.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'a[href*="view_signed_os.php"]',
            titulo: 'Documento já assinado',
            texto: 'Quando o documento já possui assinatura, a tela mostra o PDF assinado. Use '
                 + '<b>Abrir PDF assinado</b> para visualizá-lo ou baixá-lo.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'a[href*="resign=1"]',
            titulo: 'Assinar novamente',
            texto: 'Editou a O.S. depois de assinar? Use <b>Assinar novamente</b>: a nova versão assinada '
                 + '<b>substitui</b> a anterior.',
            posicao: 'baixo',
            opcional: true
        },
        {
            titulo: 'Terminou',
            texto: 'Esta tela abre em uma aba separada: basta fechá-la para voltar à Ordem de Serviço. '
                 + 'O procedimento do <b>Recibo A4</b> é idêntico — use o botão <b>Assinar A4</b> na tela da O.S.'
        }
    ];

    /* ==================================================================
     * 5e) AUTORIZAÇÃO DO ASSINADOR — janela aberta em assinar-os.php
     * ================================================================ */
    var passosAutorizar = [
        {
            alvo: '#btnLiberarCertificado',
            titulo: 'Passo 1 — liberar o certificado',
            texto: 'O Assinador funciona por um endereço seguro na sua própria máquina '
                 + '(<code>127.0.0.1</code>) e usa um certificado que o navegador ainda não conhece. '
                 + 'Clique neste botão: abre uma <b>janelinha</b> com o aviso de segurança — escolha '
                 + '<b>Avançado</b> e depois <b>“Ir para 127.0.0.1 (não seguro)”</b>. Pode confiar: o '
                 + 'endereço é o seu próprio computador.<br>A janelinha <b>fecha sozinha</b> e o sistema '
                 + 'segue para o passo 2 automaticamente. Só é preciso fazer isso uma vez por navegador — '
                 + 'e nunca mais, se a T.I. instalar o certificado na máquina.',
            posicao: 'topo',
            quando: function () {
                var j = document.querySelector('.aa-janela');
                return !!(j && j.classList.contains('aa-janela--certificado'));
            },
            opcional: true
        },
        {
            alvo: '.aa-janela',
            titulo: 'Passo 2 — permissão do Assinador',
            texto: 'Com o certificado liberado, a tela de permissão do próprio Assinador aparece aqui '
                 + 'dentro — <b>clique no botão de autorizar que surge nela</b>.',
            posicao: 'topo',
            aguardar: 3000
        },
        {
            alvo: '.aa-status',
            titulo: 'O sistema cuida do resto',
            texto: 'Enquanto você autoriza, o sistema fica tentando reconectar sozinho. Quando conseguir, '
                 + 'esta mensagem fica verde e a janela se fecha automaticamente — sem precisar recarregar '
                 + 'a página nem clicar em mais nada.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '.aa-acoes',
            titulo: 'Se algo não sair como esperado',
            texto: '<ul><li><b>Já autorizei — reconectar</b>: força uma nova tentativa na hora;</li>'
                 + '<li><b>Abrir em outra aba</b>: use se a janelinha não carregar (o Assinador SERPRO '
                 + 'precisa estar aberto na bandeja do Windows);</li>'
                 + '<li><b>Fechar</b>: volta à tela de assinatura.</li></ul>'
                 + 'Lembre-se de conferir se o token/certificado A3 está espetado na máquina.',
            posicao: 'topo',
            opcional: true
        }
    ];

    /* ==================================================================
     * 5f) ATLAS SIGNUM — assinatura de PDFs avulsos (signum/index.php)
     * ================================================================ */
    var passosSignum = [
        {
            alvo: '.sg-hero',
            titulo: 'Atlas Signum',
            texto: 'Este módulo assina <b>qualquer PDF</b> com o seu certificado digital, no padrão '
                 + '<b>PAdES (ICP-Brasil)</b> — requerimentos, ofícios, declarações, documentos '
                 + 'digitalizados. O caminho é sempre o mesmo: anexar o PDF, posicionar o carimbo e assinar.',
            posicao: 'baixo'
        },
        {
            alvo: '#topChip',
            titulo: 'Situação do Assinador',
            texto: 'Mostra se o sistema está falando com o <b>Assinador SERPRO</b> da sua máquina. '
                 + 'No método A1 (certificado no servidor) este indicador não aparece.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#dz',
            titulo: 'Anexar o PDF',
            texto: 'Arraste o arquivo para esta área ou clique para escolher (até 30 MB). '
                 + 'Assim que o PDF é enviado, a pré-visualização abre logo abaixo.',
            posicao: 'baixo'
        },
        {
            alvo: '#pages',
            titulo: 'Posicionar o carimbo',
            texto: 'Clique no ponto da página em que a assinatura deve aparecer — depois é possível '
                 + '<b>arrastar</b> o carimbo para ajustar. Escolha um espaço livre, para não cobrir '
                 + 'o texto do documento.',
            posicao: 'dir',
            aguardar: 4000,
            opcional: true
        },
        {
            alvo: '#sealW',
            titulo: 'Tamanho do carimbo',
            texto: 'A régua ajusta a largura do carimbo (de 16% a 50% da página); os botões '
                 + '<b>−</b> e <b>+</b> fazem o ajuste fino, e o valor aparece ao lado.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#sAstat',
            titulo: 'Assinador SERPRO',
            texto: 'Aqui está o estado da conexão com o token. Enquanto não estiver <b>online</b>, '
                 + 'os botões de autenticar e assinar continuam desligados.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnAutorizarAssinador',
            titulo: 'Autorizar o Assinador',
            texto: 'Se aparecer <b>“Autorização pendente”</b>, clique aqui: a permissão é resolvida '
                 + 'em uma janela do próprio sistema e a reconexão acontece sozinha.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnReconnect',
            titulo: 'Reconectar',
            texto: 'Use quando o Assinador foi aberto (ou o token espetado) depois desta tela — '
                 + 'refaz a verificação sem recarregar a página.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnAuth',
            titulo: 'Autenticar o certificado',
            texto: 'Lê o seu certificado no token: o PIN é solicitado uma vez e o sistema passa a '
                 + 'conhecer <b>nome e CPF</b> do assinante, que vão impressos no carimbo.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#st1',
            subir: 'ul',
            titulo: 'O roteiro da tela',
            texto: 'Esta listinha acompanha o seu progresso: <b>conectar o token → autenticar o '
                 + 'certificado → posicionar o carimbo → assinar</b>. O passo atual fica destacado '
                 + 'e os concluídos ficam marcados.',
            posicao: 'esq',
            opcional: true
        },
        {
            alvo: '#btnAssinar',
            titulo: 'Assinar o documento',
            texto: 'Só habilita quando tudo acima estiver pronto. O token pede a confirmação e a '
                 + 'assinatura é gravada dentro do próprio PDF (PAdES), preservando a validade jurídica.',
            posicao: 'topo'
        },
        {
            alvo: '#fq',
            subir: '.sg-card',
            titulo: 'Documentos assinados',
            texto: 'Tudo o que já foi assinado fica listado aqui, com filtros por texto, método e '
                 + 'período. Cada linha traz o <b>código de verificação</b> do documento.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#docBody .tbtn',
            titulo: 'Ações de cada documento',
            texto: 'Na coluna Ações: o <b>olho</b> abre o PDF assinado, a <b>seta</b> baixa o arquivo '
                 + 'e a <b>lixeira</b> exclui o registro.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'a[href*="configurar.php"]',
            titulo: 'Configurar',
            texto: 'Em <b>Configurar</b> ficam o método de assinatura (A3 com token ou A1 com arquivo), '
                 + 'os dados do assinante, a logomarca e os textos do carimbo. Vale conferir antes da '
                 + 'primeira assinatura — aquela tela também tem o próprio guia.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    /* ==================================================================
     * 5g) SIGNUM — configurações (signum/configurar.php)
     * ================================================================ */
    var passosSignumConfig = [
        {
            alvo: '.sg-hero',
            titulo: 'Configurações do Signum',
            texto: 'Aqui você define <b>como assina</b> e <b>como o carimbo aparece</b> nos PDFs. '
                 + 'O método de assinatura é uma preferência <b>individual</b> (cada usuário tem a sua); '
                 + 'já a logomarca é compartilhada pelo cartório.',
            posicao: 'baixo'
        },
        {
            alvo: '.methods',
            titulo: 'Método de assinatura',
            texto: '<b>A3 (token/cartão)</b> — o padrão do cartório: você assina na hora, com o token '
                 + 'conectado e o Assinador SERPRO aberto. Não exige configuração.<br>'
                 + '<b>A1 (arquivo)</b> — você envia o seu <code>.pfx</code> e a senha; a assinatura é '
                 + 'feita direto no servidor, sem token.<br>Clique em um dos cartões: os campos abaixo '
                 + 'mudam conforme a escolha.',
            posicao: 'baixo'
        },
        {
            alvo: '#cfgAssBadge',
            titulo: 'Situação do Assinador (A3)',
            texto: 'Este selo indica se o Assinador SERPRO está <b>online</b>. Ele é verificado sozinho '
                 + 'ao escolher o método A3.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cfgTestar',
            titulo: 'Testar',
            texto: 'Refaz a verificação na hora — útil depois de abrir o Assinador ou espetar o token. '
                 + 'Se der <b>offline</b>, o sistema explica o que fazer.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#btnAutorizarAssinador',
            titulo: 'Autorizar o Assinador',
            texto: 'Quando o Assinador está aberto mas ainda não liberou o acesso do navegador, use este '
                 + 'botão: a permissão é resolvida em uma janela do próprio sistema, que reconecta e '
                 + 'fecha sozinha.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#certInput',
            subir: '.card-blk',
            titulo: 'Certificado A1 (.pfx)',
            texto: 'Só aparece no método A1. Envie o arquivo <code>.pfx</code>/<code>.p12</code> e informe '
                 + 'a <b>senha</b> — o arquivo fica guardado apenas para o seu login, em pasta protegida, '
                 + 'e a senha é gravada criptografada.<br>Já havendo um certificado enviado, deixe a senha '
                 + 'em branco para mantê-la. O selo acima mostra o titular e a <b>validade</b> — fique de '
                 + 'olho quando estiver perto de expirar.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#logoPrev',
            subir: '.card-blk',
            titulo: 'Logomarca do carimbo',
            texto: 'PNG ou JPG do brasão/logomarca do cartório, que aparece no carimbo. A prévia à '
                 + 'esquerda atualiza assim que você escolhe o arquivo. Esta imagem é <b>compartilhada</b> '
                 + 'por todos os usuários.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'input[name="usar_cn_titular"]',
            subir: '.field',
            titulo: 'Nome do assinante',
            texto: 'Marcado, o nome impresso no carimbo é lido do <b>próprio certificado</b> — a opção '
                 + 'mais segura, porque não depende de digitação. Desmarcado, valem o nome e o CPF que '
                 + 'você informar nos campos abaixo.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cpfInp',
            titulo: 'CPF no carimbo',
            texto: 'Detalhe importante: no <b>A1</b> o nome e o CPF são lidos automaticamente do '
                 + 'certificado. No <b>A3 (token)</b> é preciso informar aqui o CPF, porque o Assinador '
                 + 'só revela o certificado no instante da assinatura. O campo já formata sozinho.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="assinante_cargo"]',
            subir: '.row2',
            titulo: 'Cargo e local',
            texto: 'Cargo/função (Tabelião, Oficial, Escrevente…) e a cidade/UF que acompanham o nome '
                 + 'no carimbo.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="carimbo_titulo"]',
            subir: '.row2',
            titulo: 'Textos do carimbo',
            texto: 'O <b>título</b> encabeça o carimbo (por padrão “Assinado digitalmente”). O '
                 + '<b>motivo</b> não é impresso: ele vai nos metadados da assinatura dentro do PDF, '
                 + 'visível nos leitores que exibem as propriedades da assinatura.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#cfgForm button[type="submit"]',
            titulo: 'Salvar configurações',
            texto: 'Grava tudo e recarrega a tela. As mudanças valem para as <b>próximas</b> assinaturas — '
                 + 'documentos já assinados permanecem como estão.',
            posicao: 'topo'
        },
        {
            titulo: 'Tudo pronto',
            texto: 'Volte ao <b>Atlas Signum</b> pelo botão no topo e comece a assinar: anexe o PDF, '
                 + 'posicione o carimbo e assine. Aquela tela também tem o próprio guia, no botão “?”.'
        }
    ];

    /* ==================================================================
     * 5h) ATLAS FORJA — ferramentas de PDF (forja/index.php)
     *     Cada ferramenta é uma aba com o seu próprio guia; o botão “?”
     *     acompanha a aba aberta.
     * ================================================================ */

    /* Passo inicial de cada ferramenta: garante que a aba esteja aberta
       antes de destacar os campos do painel. */
    function abaDaFerramenta(chave, titulo, texto) {
        return {
            alvo: '.tab[data-tab="' + chave + '"]',
            titulo: titulo,
            texto: texto,
            posicao: 'baixo',
            aoEntrar: function (el) { if (el && !el.classList.contains('active')) { el.click(); } }
        };
    }

    function envioDaFerramenta(chave, titulo, texto) {
        return {
            alvo: '#panel-' + chave + ' .dz',
            titulo: titulo,
            texto: texto,
            posicao: 'baixo',
            aguardar: 2000
        };
    }

    var passosForja = [
        {
            alvo: '.fj-hero',
            titulo: 'Atlas Forja',
            texto: 'A caixa de ferramentas de PDF do Atlas: comprimir, converter para imagens, montar PDF '
                 + 'a partir de imagens, juntar, dividir e converter de/para Word. Nada sai do servidor '
                 + 'do cartório — tudo é processado localmente.',
            posicao: 'baixo'
        },
        {
            alvo: '.fj-actions .chip',
            titulo: 'Ferramentas externas',
            texto: 'Este selo mostra se os programas auxiliares foram encontrados. <b>Comprimir</b> e '
                 + '<b>PDF → Imagens</b> precisam do Ghostscript; <b>Word ↔ PDF</b> precisa do LibreOffice. '
                 + 'Já <b>Imagens → PDF</b>, <b>Juntar</b> e <b>Dividir</b> funcionam só com o PHP.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '.tabs',
            titulo: 'As oito ferramentas',
            texto: '<ul><li><b>Comprimir PDF</b> — reduz o tamanho do arquivo, com quatro níveis e '
                 + 'prévia para conferir a qualidade;</li>'
                 + '<li><b>PDF → Imagens</b> — uma imagem por página, em ZIP;</li>'
                 + '<li><b>Imagens → PDF</b> — várias fotos/digitalizações viram um PDF;</li>'
                 + '<li><b>Juntar PDFs</b> — vários arquivos em um só;</li>'
                 + '<li><b>União múltipla</b> — combina um documento comum com vários outros, em lote;</li>'
                 + '<li><b>Dividir PDF</b> — reparte um PDF em várias partes;</li>'
                 + '<li><b>Word → PDF</b> e <b>PDF → Word</b> — conversão de formato.</li></ul>',
            posicao: 'baixo'
        },
        {
            alvo: '.tab.active',
            titulo: 'Um guia para cada ferramenta',
            texto: 'Ao trocar de aba, o botão <b>“?”</b> no canto passa a oferecer o guia <b>daquela</b> '
                 + 'ferramenta — com o passo a passo específico dela. Abra a aba desejada e clique no “?”.',
            posicao: 'baixo'
        },
        {
            titulo: 'Como funciona, em geral',
            texto: 'Todas as ferramentas seguem o mesmo ritmo: <b>enviar o arquivo</b> (arrastando ou '
                 + 'clicando na área tracejada) → <b>ajustar as opções</b> → clicar no botão de ação → '
                 + '<b>baixar o resultado</b> pelo link que aparece. Respeite o limite de tamanho '
                 + 'indicado na própria área de envio.'
        }
    ];

    var passosForjaComprimir = [
        abaDaFerramenta('comprimir', 'Comprimir PDF',
            'Reduz o tamanho de um PDF sem sacrificar a leitura — para caber no limite do ONR, do '
            + 'Malote Digital, do e-mail ou do protocolo eletrônico. Precisa do <b>Ghostscript</b> '
            + '(versão 9.50 ou mais nova) no servidor.'),
        envioDaFerramenta('comprimir', 'Envie o PDF',
            'Arraste o arquivo para esta área ou clique para escolher — um arquivo por vez, até 200 MB.'),
        {
            alvo: '#panel-comprimir [data-list]',
            titulo: 'Arquivo selecionado',
            texto: 'Mostra o nome e o tamanho atual. O <b>×</b> remove o arquivo, caso tenha escolhido o errado.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#nivelComp',
            titulo: 'Nível de compressão',
            texto: 'Cada nível define para quantos <b>dpi</b> as imagens são reamostradas:<ul>'
                 + '<li><b>Alta qualidade (300 dpi)</b> — para documentos que ainda serão impressos ou '
                 + 'passarão por OCR;</li>'
                 + '<li><b>Recomendada (200 dpi)</b> — o padrão: texto nítido na tela e na impressão '
                 + 'comum, melhor escolha para digitalizações de cartório;</li>'
                 + '<li><b>Máxima compressão legível (150 dpi)</b> — reduz de 85% a 90% e o texto '
                 + 'continua perfeitamente legível;</li>'
                 + '<li><b>Compressão extrema (120 dpi)</b> — reduz de 90% a 95%, para limites de '
                 + 'upload apertados; não indicada para reimpressão.</li></ul>',
            posicao: 'baixo'
        },
        {
            alvo: '#cinzaComp',
            titulo: 'Cores',
            texto: '<b>Automático</b> — o sistema mede a cobertura de cor de cada página e, se ela não '
                 + 'tiver cor real (o caso das digitalizações de documento preto e branco), converte '
                 + 'para cinza sem perda visível.<br>'
                 + '<b>Converter em tons de cinza</b> — força a conversão: em digitalizações coloridas '
                 + 'reduz de 30% a 60% a mais.<br>'
                 + '<b>Preservar as cores</b> — use quando a cor for parte do documento (mapas, '
                 + 'plantas, selos coloridos, fotos).',
            posicao: 'baixo'
        },
        {
            alvo: '#hintComp',
            titulo: 'A dica muda com a sua escolha',
            texto: 'Esta linha explica, na hora, o que o nível e a opção de cores escolhidos farão com o '
                 + 'documento — vale ler antes de comprimir.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#btnComprimir',
            titulo: 'Comprimir',
            texto: 'O sistema não usa um ajuste único: ele tenta uma sequência de configurações até '
                 + 'atingir a meta do nível escolhido, sempre <b>dentro do piso de legibilidade</b> '
                 + 'daquele nível. Em arquivos grandes, essa busca é feita antes em uma amostra de '
                 + 'páginas, para não processar o documento inteiro várias vezes.<br>'
                 + 'E há uma garantia: <b>nunca devolve um arquivo maior que o original</b> — se não '
                 + 'houver o que ganhar, ele devolve o próprio original e avisa.',
            posicao: 'topo'
        },
        {
            alvo: '#panel-comprimir .badges',
            titulo: 'O que aconteceu com o arquivo',
            texto: 'Os selos do resultado mostram a <b>redução obtida</b>, o nível aplicado, os <b>dpi</b> '
                 + 'finais, se houve conversão para tons de cinza (e se foi automática) e o número de '
                 + 'páginas. Ao lado fica o botão <b>Baixar</b>.',
            posicao: 'topo',
            aguardar: 4000,
            opcional: true
        },
        {
            alvo: '#panel-comprimir [data-pv]',
            titulo: 'Confira antes de baixar',
            texto: 'A prévia abre o <b>PDF de verdade</b> no visualizador do navegador — o que você vê é '
                 + 'exatamente o arquivo que será baixado. São três modos: <b>Comprimido</b>, '
                 + '<b>Original</b> e <b>Lado a lado</b>, com os dois abertos ao mesmo tempo para '
                 + 'comparar a nitidez do texto.',
            posicao: 'topo',
            aguardar: 4000,
            opcional: true
        },
        {
            titulo: 'Se ainda estiver grande',
            texto: 'Repita a compressão em um nível mais forte — <b>Máxima compressão legível</b> ou '
                 + '<b>Compressão extrema</b> — e confira o resultado na prévia lado a lado antes de '
                 + 'usar o arquivo. Se aparecer a mensagem de que o PDF <b>já está otimizado</b> para o '
                 + 'nível escolhido, é justamente esse o caminho: subir o nível.'
        }
    ];

    var passosForjaPdf2Img = [
        abaDaFerramenta('pdf2img', 'PDF → Imagens',
            'Transforma cada página do PDF em uma imagem. Útil para inserir páginas em outro documento, '
            + 'publicar um trecho ou anexar em sistemas que só aceitam imagem. Precisa do <b>Ghostscript</b>.'),
        envioDaFerramenta('pdf2img', 'Envie o PDF', 'Arraste ou clique para escolher o arquivo.'),
        {
            alvo: '#fmtImg',
            titulo: 'Formato',
            texto: '<b>PNG</b> — sem perda de qualidade, arquivos maiores (melhor para documentos e '
                 + 'texto).<br><b>JPG</b> — arquivos bem menores, com leve perda (bom para fotos).',
            posicao: 'baixo'
        },
        {
            alvo: '#dpiImg',
            titulo: 'Resolução',
            texto: '<b>100 DPI</b> para visualização em tela, <b>150 DPI</b> para o uso comum e '
                 + '<b>300 DPI</b> quando a imagem for impressa ou passar por leitura/OCR. Quanto maior '
                 + 'o DPI, maior o arquivo e mais demorada a conversão.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnPdf2Img',
            titulo: 'Converter',
            texto: 'Gera uma imagem por página e entrega tudo em um <b>arquivo ZIP</b> — as imagens saem '
                 + 'numeradas na ordem das páginas.',
            posicao: 'topo'
        }
    ];

    var passosForjaImg2Pdf = [
        abaDaFerramenta('img2pdf', 'Imagens → PDF',
            'Junta várias imagens (PNG ou JPG) em um único PDF — o caminho natural para fotos de '
            + 'documentos e digitalizações que chegaram soltas. Funciona sem programa externo.'),
        envioDaFerramenta('img2pdf', 'Envie as imagens',
            'Pode selecionar <b>várias de uma vez</b> (segurando Ctrl) ou arrastar todas juntas.'),
        {
            alvo: '#panel-img2pdf [data-list]',
            titulo: 'A ordem das páginas',
            texto: 'A ordem da lista é a ordem das páginas no PDF. Use as setas <b>↑</b> e <b>↓</b> de '
                 + 'cada item para reordenar e o <b>×</b> para remover.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#modoImg2Pdf',
            titulo: 'Tamanho da página',
            texto: '<b>Tamanho da imagem</b> — cada página fica do tamanho exato da foto (sem margens '
                 + 'nem cortes).<br><b>Ajustar em A4</b> — tudo em folha A4, melhor quando o PDF for '
                 + 'impresso ou juntado a outros documentos.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnImg2Pdf',
            titulo: 'Gerar PDF',
            texto: 'Monta o PDF na ordem definida e disponibiliza o link para download.',
            posicao: 'topo'
        }
    ];

    var passosForjaJuntar = [
        abaDaFerramenta('juntar', 'Juntar PDFs',
            'Combina vários PDFs em um só, na ordem que você definir — por exemplo, requerimento + '
            + 'documentos + comprovante em um único arquivo. Funciona sem programa externo.'),
        envioDaFerramenta('juntar', 'Envie os PDFs',
            'Selecione ou arraste <b>todos os arquivos</b> que farão parte do documento final.'),
        {
            alvo: '#panel-juntar [data-list]',
            titulo: 'Ordem dos documentos',
            texto: 'O PDF final segue exatamente esta ordem. Ajuste com as setas <b>↑</b> e <b>↓</b>; '
                 + 'o <b>×</b> tira um arquivo da lista.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#btnJuntar',
            titulo: 'Juntar',
            texto: 'Gera o arquivo único. Vale conferir o resultado antes de descartar os originais.',
            posicao: 'topo'
        }
    ];

    var passosForjaMultiplo = [
        abaDaFerramenta('multiplo', 'União múltipla (em lote)',
            'Esta é a ferramenta que economiza mais tempo: ela combina um documento <b>comum</b> '
            + '(Lado A) com <b>cada</b> arquivo de uma lista (Lado B), gerando um PDF por item — sem '
            + 'repetir a mesma junção dezenas de vezes.'),
        {
            alvo: '#panel-multiplo [data-dz="ladoA"]',
            titulo: 'Lado A — o documento comum',
            texto: 'O arquivo que se repete em todos os resultados: uma portaria, um ofício-circular, '
                 + 'um termo padrão. Pode ter mais de um PDF.',
            posicao: 'baixo',
            aguardar: 2000
        },
        {
            alvo: '#panel-multiplo [data-dz="ladoB"]',
            titulo: 'Lado B — um resultado para cada',
            texto: 'Aqui vão os arquivos que variam. Cada um deles gera um PDF próprio, formado por '
                 + '<b>Lado A + aquele arquivo</b>.',
            posicao: 'baixo',
            aguardar: 2000
        },
        {
            alvo: '#posMultiplo',
            titulo: 'Ordem da junção',
            texto: 'Define se o Lado A entra <b>antes</b> (capa, portaria) ou <b>depois</b> (anexo, '
                 + 'encerramento) de cada arquivo do Lado B.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnMultiplo',
            titulo: 'Gerar em lote',
            texto: 'Produz todos os PDFs de uma vez e entrega em um <b>ZIP</b>: são tantos arquivos '
                 + 'quantos forem os do Lado B.',
            posicao: 'topo'
        }
    ];

    var passosForjaDividir = [
        abaDaFerramenta('dividir', 'Dividir PDF',
            'Reparte um PDF grande em vários menores — para enviar em partes ou separar um lote '
            + 'digitalizado de uma vez só. Funciona sem programa externo.'),
        envioDaFerramenta('dividir', 'Envie o PDF', 'Arraste ou clique para escolher o arquivo.'),
        {
            alvo: '#modoDividir',
            titulo: 'Critério da divisão',
            texto: '<b>Número de partes</b> — o sistema reparte o total de páginas igualmente entre as '
                 + 'partes.<br><b>Páginas por parte</b> — você define quantas páginas cada arquivo terá '
                 + '(ex.: 1 para separar página por página).',
            posicao: 'baixo'
        },
        {
            alvo: '#valDividir',
            titulo: 'Quantidade',
            texto: 'O número que acompanha o critério escolhido — o rótulo ao lado muda conforme a opção, '
                 + 'para não haver confusão.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnDividir',
            titulo: 'Dividir',
            texto: 'Gera as partes e entrega tudo em um <b>ZIP</b>, com os arquivos numerados na ordem.',
            posicao: 'topo'
        }
    ];

    var passosForjaWord2Pdf = [
        abaDaFerramenta('word2pdf', 'Word → PDF',
            'Converte documentos do Word (e formatos parecidos) em PDF, direto no servidor — sem '
            + 'depender do Office na máquina do usuário. Precisa do <b>LibreOffice</b> configurado.'),
        envioDaFerramenta('word2pdf', 'Envie o documento',
            'Aceita <b>.docx</b>, <b>.doc</b>, <b>.odt</b>, <b>.rtf</b> e <b>.txt</b>.'),
        {
            alvo: '#btnWord2Pdf',
            titulo: 'Converter para PDF',
            texto: 'A conversão preserva o layout do documento. Fontes muito incomuns podem ser '
                 + 'substituídas — vale conferir o resultado antes de assinar ou arquivar.',
            posicao: 'topo'
        }
    ];

    var passosForjaPdf2Word = [
        abaDaFerramenta('pdf2word', 'PDF → Word',
            'Transforma um PDF em documento editável (.docx) — útil para reaproveitar um texto que só '
            + 'chegou em PDF. Precisa do <b>LibreOffice</b> configurado.'),
        envioDaFerramenta('pdf2word', 'Envie o PDF', 'Arraste ou clique para escolher o arquivo.'),
        {
            alvo: '#modoPdf2Word',
            titulo: 'Modo da conversão',
            texto: '<b>Fiel</b> — mantém imagens e layout, mas o texto costuma vir em caixas, mais '
                 + 'difícil de editar.<br><b>Texto fluido</b> — texto limpo e fácil de editar, sem as '
                 + 'imagens.<br><b>Texto simples</b> — só o conteúdo, sem formatação.<br>'
                 + 'Para reescrever um documento, prefira o texto fluido.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnPdf2Word',
            titulo: 'Converter para Word',
            texto: 'PDFs digitalizados (imagem pura) não têm texto para extrair — nesses casos o '
                 + 'resultado sai vazio ou só com a imagem. Aí o caminho é o OCR.',
            posicao: 'topo'
        }
    ];

    var passosForjaConfig = [
        {
            alvo: '.fj-hero',
            titulo: 'Configurar a Forja',
            texto: 'Tela de administrador: indica onde estão os programas auxiliares e liga/desliga o '
                 + 'módulo. Os usuários comuns não precisam mexer aqui.',
            posicao: 'baixo'
        },
        {
            alvo: '#statusFerramentas',
            titulo: 'O que foi encontrado',
            texto: 'Mostra quais ferramentas o servidor localizou. Ferramenta ausente significa que as '
                 + 'operações que dependem dela ficam indisponíveis.',
            posicao: 'baixo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#btnTestar',
            titulo: 'Testar ferramentas',
            texto: 'Refaz a checagem na hora — use depois de instalar um programa ou de corrigir um caminho.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="gs_path"]',
            subir: '.field',
            titulo: 'Caminho do Ghostscript',
            texto: 'Necessário para <b>comprimir</b> e <b>PDF → imagens</b>. Deixe <b>em branco</b> para '
                 + 'o sistema procurar sozinho nos lugares habituais; preencha apenas se a instalação '
                 + 'estiver em pasta fora do padrão.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="magick_path"]',
            subir: '.field',
            titulo: 'ImageMagick (opcional)',
            texto: 'Alternativa ao Ghostscript para algumas operações. Pode ficar em branco.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="lo_path"]',
            subir: '.field',
            titulo: 'LibreOffice',
            texto: 'Necessário para <b>Word → PDF</b> e <b>PDF → Word</b>. Aponta para o '
                 + '<code>soffice.exe</code>; em branco, o sistema tenta localizar sozinho.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'input[name="forja_ativo"]',
            subir: '.field',
            titulo: 'Módulo ativo',
            texto: 'Controla se o card da Forja aparece na tela inicial do Atlas. Desmarcado, o módulo '
                 + 'fica oculto para todos.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#loUrl',
            subir: '.field',
            titulo: 'Instalar o LibreOffice sozinho',
            texto: 'Para não instalar o LibreOffice servidor por servidor: informe a URL de um pacote '
                 + '(<code>.zip</code> portátil ou <code>.msi</code>) e clique em <b>Baixar e instalar</b>. '
                 + 'O módulo baixa, extrai em <code>forja/libreoffice/</code> e configura o caminho sozinho — '
                 + 'sem instalação nem privilégio de administrador na máquina.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#btnInstalarLO',
            titulo: 'Baixar e instalar',
            texto: 'Executa o download e a instalação portátil. Pode levar alguns minutos, conforme o '
                 + 'tamanho do pacote e a velocidade da rede — acompanhe a mensagem na tela.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#cfgForm button[type="submit"]',
            titulo: 'Salvar',
            texto: 'Grava os caminhos e a ativação do módulo. Depois de salvar, use <b>Testar '
                 + 'ferramentas</b> para confirmar que tudo foi reconhecido.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var FERRAMENTAS_FORJA = [
        ['comprimir', 'forja-comprimir', 'Como comprimir PDF', passosForjaComprimir],
        ['pdf2img', 'forja-pdf2img', 'Como converter PDF em imagens', passosForjaPdf2Img],
        ['img2pdf', 'forja-img2pdf', 'Como gerar PDF de imagens', passosForjaImg2Pdf],
        ['juntar', 'forja-juntar', 'Como juntar PDFs', passosForjaJuntar],
        ['multiplo', 'forja-multiplo', 'Como usar a união múltipla', passosForjaMultiplo],
        ['dividir', 'forja-dividir', 'Como dividir um PDF', passosForjaDividir],
        ['word2pdf', 'forja-word2pdf', 'Como converter Word em PDF', passosForjaWord2Pdf],
        ['pdf2word', 'forja-pdf2word', 'Como converter PDF em Word', passosForjaPdf2Word]
    ];

    /* ==================================================================
     * 5i) FLUXO DE CAIXA — caixa/index.php
     *     Quase tudo acontece em janelas (modais); cada uma tem o seu guia,
     *     e o botão “?” acompanha a janela aberta.
     * ================================================================ */
    var passosCaixa = [
        {
            alvo: '#pesquisarForm',
            titulo: 'Fluxo de Caixa',
            texto: 'Esta tela reúne o movimento financeiro do cartório por <b>funcionário</b> e por '
                 + '<b>dia</b>: o que foi recebido nos atos, as saídas, os depósitos e o saldo que passa '
                 + 'de um dia para o outro. Comece pelos filtros.',
            posicao: 'baixo'
        },
        {
            alvo: '#funcionario',
            titulo: 'Funcionário',
            texto: 'Escolha de quem é o caixa. A opção <b>Todos</b> consolida os funcionários no mesmo '
                 + 'card — é o chamado <b>caixa unificado</b>, usado no fechamento geral do dia.',
            posicao: 'baixo'
        },
        {
            alvo: '#periodo',
            titulo: 'Período',
            texto: 'Atalhos para hoje, ontem, a semana ou o mês. Para um intervalo específico, use as '
                 + 'datas ao lado.',
            posicao: 'baixo'
        },
        {
            alvo: '#data_inicial',
            titulo: 'Datas',
            texto: 'Data inicial e final do intervalo pesquisado. Cada dia vira um card no resultado.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#modo_visualizacao',
            subir: '.form-check',
            titulo: 'Modo de visualização',
            texto: 'Marcando esta opção, todos os dias do período são consolidados em <b>um único caixa</b> '
                 + '(com o funcionário “Todos”, também consolida as pessoas), abrindo o card, a janela de '
                 + 'detalhes e o relatório unificados.<br><b>É apenas consulta:</b> nesse modo não há '
                 + 'cadastro de depósitos, saídas nem fechamento.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#pesquisarForm button[type="submit"]',
            titulo: 'Pesquisar',
            texto: 'Aplica os filtros e monta os cards do resultado.',
            posicao: 'topo'
        },
        {
            alvo: '#cardsResultados',
            titulo: 'Os cards do resultado',
            texto: 'Cada card é um caixa (funcionário + dia) com o resumo dos valores e a cor indicando a '
                 + 'situação — aberto ou fechado. <b>Clique no corpo do card</b> para abrir a janela de '
                 + 'detalhes, com todo o movimento daquele caixa.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#cardsResultados .btn-icon',
            titulo: 'Ações de cada caixa',
            texto: 'Os botõezinhos no rodapé do card abrem as ações sem entrar nos detalhes:<ul>'
                 + '<li><b>Saídas e Despesas</b> — lança o que saiu do caixa;</li>'
                 + '<li><b>Depósito do Caixa</b> — registra depósitos e fecha o caixa com transporte do saldo;</li>'
                 + '<li><b>Imprimir Fechamento</b> — abre o relatório do caixa em outra aba;</li>'
                 + '<li><b>Ver Depósitos do Caixa</b> — no caixa unificado, lista os depósitos de todos;</li>'
                 + '<li><b>Fechar caixa</b> (cadeado) — encerra o caixa do dia.</li></ul>'
                 + 'Cada janela dessas tem o seu próprio guia: abra a janela e clique no <b>“?”</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'button[onclick*="analiticos"]',
            titulo: 'Analíticos',
            texto: 'Leva aos relatórios analíticos, com os números consolidados por período — útil para '
                 + 'conferência e prestação de contas.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCaixaDetalhes = [
        {
            alvo: '#modalStatusPill',
            titulo: 'Situação do caixa',
            texto: 'Indica se este caixa está <b>aberto</b> ou <b>fechado</b>. Depois de fechado, o '
                 + 'movimento fica apenas para consulta.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#cardSaldoInicial',
            titulo: 'Saldo inicial',
            texto: 'O dinheiro que já estava em caixa quando o dia começou — normalmente o saldo '
                 + 'transportado do fechamento anterior.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#cardTotalRecebido',
            titulo: 'O que entrou',
            texto: 'Os cards do topo separam a origem do dinheiro: <b>atos liquidados</b>, <b>atos '
                 + 'manuais</b>, <b>atos isentos</b> (que não geram valor) e o recebido <b>em conta</b> '
                 + 'e <b>em espécie</b>. O “Total Recebido” é a soma das entradas do dia.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cardSaidasDespesas',
            titulo: 'O que saiu',
            texto: '<b>Saídas/Despesas</b> são os pagamentos feitos com o dinheiro do caixa; '
                 + '<b>Devoluções</b> são valores devolvidos a apresentantes; e <b>Depósito do Caixa</b> '
                 + 'é o que foi recolhido para o banco.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cardTotalSelos',
            titulo: 'Selos e repasses',
            texto: '<b>Selos (à vista)</b> e <b>Selos diferidos</b> mostram a parcela dos emolumentos '
                 + 'destinada ao selo digital; <b>Repasse a credores</b> reúne valores de terceiros que '
                 + 'passaram pelo caixa.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cardTotalEmCaixa',
            titulo: 'Total em caixa',
            texto: 'O resultado de tudo: saldo inicial + entradas − saídas − devoluções − depósitos. '
                 + 'É este valor que deve bater com o dinheiro conferido na gaveta no fim do dia.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#filtrosAtosLiquidados',
            titulo: 'Filtrar os atos',
            texto: 'Nas listas detalhadas dá para filtrar por funcionário, ato, apresentante ou número da '
                 + 'O.S. — útil para localizar um lançamento específico na conferência. O botão '
                 + '<b>Limpar</b> volta à lista completa.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#tabelaAtos',
            titulo: 'As listas detalhadas',
            texto: 'Abaixo dos cards vêm as listas que compõem cada número: atos liquidados, atos '
                 + 'manuais, isentos, selos, pagamentos por tipo, devoluções, saídas, depósitos e saldo '
                 + 'transportado — cada uma com o seu total ao final.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosCaixaSaida = [
        {
            alvo: '#titulo',
            titulo: 'Título da saída',
            texto: 'Descreva o gasto de forma reconhecível — <i>combustível</i>, <i>material de '
                 + 'escritório</i>, <i>correio</i>. É o que aparece no fechamento.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#valor_saida',
            titulo: 'Valor',
            texto: 'Quanto saiu do caixa. O valor é descontado do total em caixa assim que a saída é salva.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#forma_de_saida',
            titulo: 'Forma de saída',
            texto: 'Como o pagamento foi feito (espécie, cartão, transferência…). Ajuda na conferência '
                 + 'entre o dinheiro em gaveta e o extrato bancário.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#anexo',
            subir: '.custom-file',
            titulo: 'Anexo',
            texto: 'Anexe o cupom, a nota ou o comprovante do gasto. Fica guardado junto do lançamento e '
                 + 'pode ser consultado depois pelo ícone do olho.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#formCadastroSaida button[type="submit"]',
            titulo: 'Cadastrar a saída',
            texto: 'Grava o lançamento e atualiza o caixa na hora.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#tabelaSaidasCadastradas',
            titulo: 'Saídas já cadastradas',
            texto: 'A lista traz o que já foi lançado neste caixa, com o botão de <b>visualizar</b> o '
                 + 'anexo e o de <b>remover</b> — a remoção só é possível enquanto o caixa estiver aberto.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosCaixaDeposito = [
        {
            alvo: '#total_em_caixa',
            titulo: 'Quanto há para depositar',
            texto: 'O topo da janela mostra o <b>total em caixa</b>, o que já foi depositado e o saldo '
                 + 'transportado — os números que orientam o valor do depósito.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#valor_deposito',
            titulo: 'Valor do depósito',
            texto: 'O quanto está sendo recolhido do caixa para o banco.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#tipo_deposito',
            titulo: 'Tipo',
            texto: 'Depósito bancário, espécie ou transferência — conforme a forma como o dinheiro saiu '
                 + 'do caixa.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#comprovante_deposito',
            subir: '.custom-file',
            titulo: 'Comprovante',
            texto: 'Anexe o comprovante do depósito. Não havendo comprovante no momento, marque '
                 + '<b>Sem comprovante</b> — mas registre-o depois, pelo clipe na lista de depósitos.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#btnAdicionarDeposito',
            titulo: 'Adicionar depósito',
            texto: 'Grava o depósito e abate o valor do total em caixa. Pode lançar vários depósitos no '
                 + 'mesmo dia.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#tabelaDepositosRegistrados',
            titulo: 'Depósitos registrados',
            texto: 'Lista os depósitos deste caixa, com <b>visualizar</b>, <b>anexar comprovante</b> '
                 + '(clipe) e <b>remover</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#btnTransportarSaldo',
            titulo: 'Transportar saldo e fechar o caixa',
            texto: 'Encerra o caixa do dia e leva o <b>total em caixa</b> restante para o caixa seguinte, '
                 + 'como saldo inicial. O sistema pede confirmação mostrando o valor que será '
                 + 'transportado — confira o dinheiro em gaveta antes, porque depois de fechado o caixa '
                 + 'não aceita novos lançamentos.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCaixaAnexar = [
        {
            alvo: '#arquivo_comprovante',
            subir: '.custom-file',
            titulo: 'Escolher o comprovante',
            texto: 'Selecione o arquivo do comprovante daquele depósito — imagem ou PDF.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#formAnexarComprovante button[type="submit"]',
            titulo: 'Enviar',
            texto: 'Vincula o comprovante ao depósito. Depois disso, o ícone do olho passa a exibi-lo na '
                 + 'lista — é assim que se regulariza um depósito lançado como “sem comprovante”.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCaixaDepositosUnificado = [
        {
            alvo: '#tabelaDepositosCaixaUnificado',
            titulo: 'Depósitos do caixa unificado',
            texto: 'Reúne os depósitos de <b>todos os funcionários</b> naquela data, com funcionário, '
                 + 'valor, tipo e comprovante. É a visão usada na conferência do fechamento geral do dia.',
            posicao: 'topo',
            aguardar: 3000
        }
    ];

    var passosCaixaAbrir = [
        {
            alvo: '#saldo_inicial',
            titulo: 'Saldo inicial do dia',
            texto: 'Ao entrar sem um caixa aberto, o sistema pede a abertura. Informe o dinheiro que já '
                 + 'está na gaveta — normalmente o saldo transportado do dia anterior, que costuma vir '
                 + 'preenchido. Confira antes de confirmar: é a base de todo o fechamento.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#formAbrirCaixa button[type="submit"]',
            titulo: 'Abrir caixa',
            texto: 'Abre o caixa do dia e libera os lançamentos.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: 'button[onclick*="pularAberturaCaixa"]',
            titulo: 'Pular por enquanto',
            texto: 'Fecha o aviso e deixa a abertura para depois — útil quando você entrou só para '
                 + 'consultar. Sem o caixa aberto, porém, não é possível lançar saídas nem depósitos.',
            posicao: 'topo',
            opcional: true
        }
    ];

    /* ==================================================================
     * 5j) CONTAS A PAGAR — contas_a_pagar/
     *     Painel, janelas (conta, pagamento, anexos, configurações),
     *     extrato das contas virtuais e relatórios.
     * ================================================================ */
    var passosCap = [
        {
            alvo: '.kpi-grid',
            titulo: 'Contas a Pagar',
            texto: 'O módulo de despesas do cartório: cadastre contas avulsas ou recorrentes, acompanhe '
                 + 'vencimentos, registre pagamentos e receba avisos por e-mail.<br>Os quatro cartões do '
                 + 'topo resumem a situação: <b>Em aberto</b>, <b>Vencidas</b>, <b>A vencer</b> (dentro '
                 + 'do prazo de aviso configurado) e <b>Pago no mês</b>.',
            posicao: 'baixo'
        },
        {
            alvo: '.vconta',
            titulo: 'Contas virtuais',
            texto: 'Duas “caixinhas” acompanham o dinheiro do cartório: <b>Banco</b> e <b>Espécie</b>. '
                 + 'Cada pagamento sai de uma delas conforme a forma escolhida, e o saldo mostra entradas '
                 + 'menos saídas — em vermelho quando fica negativo. O botão <b>Extrato</b> abre o '
                 + 'movimento detalhado daquela conta.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#chartStatus',
            subir: '.row',
            titulo: 'Os gráficos',
            texto: '<b>Situação (em aberto)</b>, <b>Em aberto por categoria</b> e <b>Pagamentos (6 meses)</b> — '
                 + 'úteis para enxergar concentração de despesas e a evolução do que já foi pago.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#searchForm',
            titulo: 'Filtros',
            texto: 'Busque por texto e refine por <b>categoria</b>, <b>recorrência</b>, <b>mês de '
                 + 'vencimento</b> e <b>situação</b> (em aberto, vencidas, pagas…). A lista e os '
                 + 'gráficos acompanham o filtro.',
            posicao: 'baixo'
        },
        {
            alvo: '#tabelaContas',
            titulo: 'A lista de contas',
            texto: 'Cada linha é uma conta, com vencimento, valor, categoria e situação. Os botões da '
                 + 'coluna de ações abrem as janelas do módulo:<ul>'
                 + '<li><b>✓ verde</b> — registrar o pagamento;</li>'
                 + '<li><b>lápis</b> — editar a conta;</li>'
                 + '<li><b>clipe</b> — anexos (boleto, nota, comprovante);</li>'
                 + '<li><b>lixeira</b> — excluir.</li></ul>'
                 + 'Cada janela dessas tem o seu guia: abra e clique no <b>“?”</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'button[data-bs-target="#contaModal"]',
            titulo: 'Nova conta',
            texto: 'Cadastra uma despesa — avulsa, recorrente ou parcelada.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#btnSyncFundos',
            titulo: 'Sincronizar fundos',
            texto: 'Recalcula automaticamente as contas de <b>FERJ, FERC, FEMP, FADEP e FERRFIS</b> a '
                 + 'partir dos selos lançados — assim os valores devidos a esses fundos entram no '
                 + 'contas a pagar sem digitação manual.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'a[href="extrato.php"]',
            titulo: 'Extrato e Relatórios',
            texto: '<b>Extrato</b> mostra o movimento das contas virtuais e permite transferir valores '
                 + 'entre elas. <b>Relatórios</b> consolida as contas por período, com exportação em CSV. '
                 + 'As duas telas têm guia próprio.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'button[data-bs-target="#configModal"]',
            titulo: 'Configurações',
            texto: 'Define o e-mail que recebe os alertas de vencimento, com quantos dias de antecedência '
                 + 'avisar e os dados do servidor de envio.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    var passosCapConta = [
        {
            alvo: '#c_titulo',
            titulo: 'Título da conta',
            texto: 'Como a despesa será reconhecida na lista — <i>Energia elétrica</i>, <i>Aluguel</i>, '
                 + '<i>Internet</i>. Campo obrigatório.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#c_valor',
            titulo: 'Valor e vencimento',
            texto: 'O valor da conta e a data em que ela vence. São esses campos que alimentam os '
                 + 'cartões de <b>vencidas</b> e <b>a vencer</b> e disparam os alertas por e-mail.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#c_categoria',
            titulo: 'Categoria',
            texto: 'Classifica a despesa para os gráficos e relatórios (por exemplo: tributos, aluguel, '
                 + 'pessoal, material). Vale manter um padrão para os números fazerem sentido depois.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#c_recorrencia',
            titulo: 'Recorrência',
            texto: 'Para contas que se repetem (mensal, anual…), o sistema já prepara a próxima ocorrência '
                 + '— evita recadastrar a mesma despesa todo mês.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#c_parc_on',
            subir: '.form-check',
            titulo: 'Parcelamento',
            texto: 'Ligue esta chave para dividir a despesa em parcelas. Aparecem então o <b>número de '
                 + 'parcelas</b> e a escolha entre <b>valor total</b> (o sistema divide) ou <b>valor de '
                 + 'cada parcela</b> (o sistema multiplica) — a prévia mostra como ficará antes de salvar.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#c_fornecedor',
            titulo: 'Fornecedor, nota e observações',
            texto: 'Campos opcionais, mas que ajudam bastante na conferência e aparecem no relatório: '
                 + 'quem prestou o serviço, o número da nota fiscal e o que mais for útil registrar.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#contaSalvarBtn',
            titulo: 'Salvar',
            texto: 'Grava a conta. Sendo parcelada, todas as parcelas são criadas de uma vez, com os '
                 + 'vencimentos já distribuídos.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCapPagar = [
        {
            alvo: '#pg_valor_fmt',
            titulo: 'Registrar o pagamento',
            texto: 'A janela já traz o <b>valor da conta</b>. Confira antes de confirmar — é este valor '
                 + 'que sairá da conta virtual escolhida.',
            posicao: 'baixo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#pg_forma',
            titulo: 'Forma de pagamento',
            texto: 'Além de registrar como a conta foi paga, a forma define <b>de qual conta virtual o '
                 + 'valor sai</b>: apenas <b>Espécie</b> debita o dinheiro em espécie; PIX, '
                 + 'transferência, boleto, débito automático e cartões debitam o <b>Banco</b>.',
            posicao: 'baixo'
        },
        {
            alvo: '#pg_data',
            titulo: 'Data do pagamento',
            texto: 'A data em que o pagamento aconteceu de fato — é ela que posiciona o lançamento no '
                 + 'extrato e no relatório.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#pg_saldo_box',
            titulo: 'Saldo da conta escolhida',
            texto: 'Mostra o saldo disponível na conta virtual que será debitada, para você perceber na '
                 + 'hora se o pagamento vai deixá-la negativa.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#pgConfirmBtn',
            titulo: 'Confirmar pagamento',
            texto: 'Marca a conta como paga, lança a saída na conta virtual e atualiza os cartões do '
                 + 'painel e o extrato.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCapAnexos = [
        {
            alvo: '#axDz',
            titulo: 'Anexar documentos',
            texto: 'Arraste os arquivos para esta área ou clique para escolher — boleto, nota fiscal, '
                 + 'comprovante de pagamento. Vários de uma vez, se precisar.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#axDesc',
            titulo: 'Descrição',
            texto: 'Um rótulo curto para identificar o anexo depois (“boleto”, “comprovante PIX”, “NF '
                 + '1234”). Ajuda quando a conta acumula vários arquivos.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#axList',
            titulo: 'Arquivos anexados',
            texto: 'A lista traz tudo o que já está vinculado à conta, com os botões para <b>visualizar</b>, '
                 + '<b>baixar</b> e <b>excluir</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#axViewerBody',
            titulo: 'Visualizador',
            texto: 'Ao abrir um anexo, ele é exibido aqui mesmo. Use <b>Abrir em nova aba</b> ou '
                 + '<b>Baixar</b> quando precisar do arquivo, e <b>Voltar</b> para a lista.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosCapConfig = [
        {
            alvo: '#cfg_email',
            titulo: 'E-mail dos alertas',
            texto: 'Endereço que recebe os avisos de contas a vencer e vencidas. Pode ser a conta '
                 + 'administrativa do cartório.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#cfg_dias',
            titulo: 'Antecedência do aviso',
            texto: 'Quantos dias antes do vencimento o alerta é enviado. Esse mesmo número define o '
                 + 'cartão <b>A vencer</b> no painel.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#cfg_ativo',
            subir: '.form-check',
            titulo: 'Ativar os alertas',
            texto: 'Liga ou desliga o envio automático, sem perder as configurações.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#configForm',
            titulo: 'Servidor de envio (SMTP)',
            texto: 'Host, porta, tipo de segurança, usuário, senha e o remetente. São os dados da conta '
                 + 'de e-mail que o sistema usa para enviar — normalmente fornecidos pelo provedor.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#cfgTestBtn',
            titulo: 'Testar o envio',
            texto: 'Dispara um e-mail de teste com os dados preenchidos. Faça isso antes de confiar nos '
                 + 'alertas — é a forma de saber que a configuração está correta.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCapExtrato = [
        {
            alvo: '.hero, #main h1',
            titulo: 'Extrato da conta virtual',
            texto: 'Mostra, lançamento a lançamento, o que entrou e o que saiu de cada “caixinha” do '
                 + 'cartório — <b>Banco</b> e <b>Espécie</b>. Use os botões acima para alternar entre elas.',
            posicao: 'baixo'
        },
        {
            alvo: 'input[name="de"]',
            subir: '.filter-card',
            titulo: 'Período',
            texto: 'Delimite o intervalo do extrato e clique em filtrar. Os totais acompanham o período '
                 + 'escolhido.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#tabelaContas',
            titulo: 'Os movimentos',
            texto: 'Data, descrição, observação e valor de cada lançamento — pagamentos de contas, '
                 + 'transferências entre as contas virtuais e, no Banco, os recebimentos de O.S. que não '
                 + 'foram em espécie.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#btnTransferir',
            titulo: 'Transferir entre contas',
            texto: 'Abre a janela de transferência — usada quando o dinheiro em espécie é depositado no '
                 + 'banco, ou quando se faz um saque para o caixa. A janela tem o seu próprio guia.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '.js-estornar',
            titulo: 'Estornar uma transferência',
            texto: 'Transferências lançadas por engano podem ser estornadas pelo botão vermelho da linha, '
                 + 'que desfaz o movimento nas duas contas.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosCapTransferir = [
        {
            alvo: '#tr_origem',
            titulo: 'Origem e destino',
            texto: 'De qual conta virtual o valor sai e para qual ele vai. O botão de <b>inverter</b>, '
                 + 'entre os dois campos, troca a direção com um clique.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#tr_valor',
            titulo: 'Valor',
            texto: 'Quanto será transferido. O atalho <b>“Usar todo o saldo disponível”</b> preenche com '
                 + 'o saldo inteiro da origem — prático ao depositar toda a espécie no banco.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#tr_data',
            titulo: 'Data e observação',
            texto: 'A data do movimento e um texto livre para identificá-lo no extrato (“depósito do dia”, '
                 + '“saque para troco”).',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#tr_saldo_box',
            titulo: 'Saldo da origem',
            texto: 'Fica visível para você conferir se a transferência deixa a conta de origem negativa.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#trConfirmBtn',
            titulo: 'Transferir',
            texto: 'Lança a saída na origem e a entrada no destino, na mesma data. Se errar, dá para '
                 + 'estornar depois pela própria linha do extrato.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosCapRelatorios = [
        {
            alvo: '.filter-card',
            titulo: 'Relatórios de contas',
            texto: 'Consolida as contas por período. Escolha o intervalo e a <b>base da data</b> — se o '
                 + 'relatório considera o <b>vencimento</b> ou a <b>data de pagamento</b>; a diferença '
                 + 'muda bastante o resultado no fechamento do mês.',
            posicao: 'baixo'
        },
        {
            alvo: 'select[name="categoria"]',
            titulo: 'Categoria e situação',
            texto: 'Refina por categoria de despesa e por situação (pagas, em aberto, vencidas).',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#chartCat',
            subir: '.row',
            titulo: 'Os gráficos',
            texto: 'Distribuição <b>por categoria</b> e evolução <b>por mês</b>, sempre conforme os '
                 + 'filtros aplicados.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#tabelaContas',
            titulo: 'A tabela detalhada',
            texto: 'Vencimento, título, categoria, fornecedor, nota fiscal, valor, situação, data e forma '
                 + 'de pagamento — e o botão de <b>anexos</b> para conferir os comprovantes sem sair daqui.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'a[href*="export=csv"]',
            titulo: 'Exportar CSV',
            texto: 'Baixa exatamente o que está filtrado, para abrir no Excel — útil para a contabilidade '
                 + 'e para conferências fora do sistema.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    /* ==================================================================
     * 5k) ARQUIVAMENTO DIGITAL — arquivamento/
     * ================================================================ */
    var passosArq = [
        {
            alvo: '#kpi-total',
            subir: '.arq-kpis, .row, section',
            titulo: 'Arquivamento Digital',
            texto: 'O acervo digital da serventia: cada registro guarda os dados do ato, as partes '
                 + 'envolvidas e os documentos digitalizados. Os indicadores do topo mostram o total de '
                 + 'arquivamentos, os do mês, a quantidade de anexos e o espaço ocupado.',
            posicao: 'baixo',
            aguardar: 3000
        },
        {
            alvo: '#arq-q',
            titulo: 'Busca rápida',
            texto: 'Procura em tudo de uma vez — nome de parte, CPF/CNPJ, livro, folha, termo, protocolo, '
                 + 'matrícula e descrição. É o caminho mais curto quando você já sabe o que procura.',
            posicao: 'baixo'
        },
        {
            alvo: '#arq-periodo',
            titulo: 'Período',
            texto: 'Fatias rápidas de tempo — hoje, 7 dias, 30 dias, o ano ou tudo. O acervo abre nos '
                 + 'últimos 30 dias; para consultas antigas, escolha <b>Tudo</b>.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-alternar-filtros',
            titulo: 'Filtros avançados',
            texto: 'Abre a busca campo a campo: atribuição, categoria, nome, CPF/CNPJ, livro, folha, '
                 + 'termo, protocolo, matrícula, descrição, intervalo de datas e presença de anexo. '
                 + 'Use quando a busca rápida trouxer resultados demais.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-visao',
            titulo: 'Fichas ou tabela',
            texto: 'Alterna a apresentação dos resultados: <b>fichas</b> mostram mais contexto de cada '
                 + 'registro; a <b>tabela</b> é melhor para conferir muitos de uma vez.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-ordenar',
            titulo: 'Ordenação',
            texto: 'Define a ordem da listagem — por data do ato ou de cadastro, do mais novo ao mais '
                 + 'antigo e vice-versa.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-resultados',
            titulo: 'Os resultados',
            texto: 'Clique em um registro para abrir o <b>painel de detalhe</b>, com os dados completos, '
                 + 'os anexos, a capa e o histórico. Cada registro tem uma caixinha de seleção — é por '
                 + 'ela que se trabalha com vários ao mesmo tempo.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#arq-selecao',
            titulo: 'Trabalhando com vários',
            texto: 'Ao marcar registros, aparece esta barra flutuante:<ul>'
                 + '<li><b>Compilar</b> — junta os anexos de todos em um PDF único, com capa e índice;</li>'
                 + '<li><b>ZIP</b> — baixa os arquivos originais compactados;</li>'
                 + '<li><b>CSV</b> — exporta os dados dos registros para planilha;</li>'
                 + '<li><b>Selecionar todos</b> e <b>limpar seleção</b> completam a barra.</li></ul>',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'a[href="cadastro.php"]',
            titulo: 'Novo arquivamento',
            texto: 'Abre o cadastro em quatro passos: dados do ato, partes, anexos e selos.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'a[href="categorias.php"]',
            titulo: 'Categorias e Lixeira',
            texto: '<b>Categorias</b> organiza o acervo por tipo de ato — renomear uma categoria '
                 + 'reclassifica sozinho todos os registros que a usavam. <b>Lixeira</b> guarda o que '
                 + 'foi excluído, por um prazo, permitindo restaurar. As duas telas têm guia próprio.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    var passosArqDetalhe = [
        {
            alvo: '#arq-detalhe-corpo',
            titulo: 'Detalhe do arquivamento',
            texto: 'Reúne tudo do registro: dados do ato, partes qualificadas, anexos, selos emitidos e '
                 + 'os últimos eventos de <b>auditoria</b> — quem cadastrou, editou ou excluiu, e quando.',
            posicao: 'topo',
            aguardar: 3000
        },
        {
            alvo: '#arq-detalhe-capa',
            titulo: 'Capa do arquivamento',
            texto: 'Gera a capa para impressão, com uma moldura por selo emitido (com QR, texto e '
                 + 'funcionário) — ou uma moldura em branco, para colar o selo depois, quando ainda '
                 + 'não houver emissão.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-detalhe-compilar',
            titulo: 'Compilar o dossiê',
            texto: 'Junta os anexos deste registro em um PDF único, com capa e índice. A janela de '
                 + 'compilação tem o seu próprio guia.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-detalhe-editar',
            titulo: 'Editar',
            texto: 'Abre o cadastro com os dados carregados — é também por aqui que se chega ao passo de '
                 + '<b>solicitar selo</b>, que só existe para arquivamentos já gravados.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqVisor = [
        {
            alvo: '#arq-visor-palco',
            titulo: 'Visualizador de documentos',
            texto: 'PDFs, imagens e textos abrem aqui mesmo. Os demais formatos — planilhas, XML, DWG — '
                 + 'são baixados, porque o navegador não os exibe.',
            posicao: 'topo',
            aguardar: 3000
        },
        {
            alvo: '#arq-visor-aba',
            titulo: 'Abrir em nova aba e baixar',
            texto: 'Para ver em tela cheia ou guardar o arquivo original, use estes dois botões — o '
                 + 'nome baixado é sempre o nome original do documento.',
            posicao: 'baixo',
            opcional: true
        }
    ];

    var passosArqCompilar = [
        {
            alvo: '#arq-compilar-resumo',
            titulo: 'Compilar dossiê',
            texto: 'Junta imagens e PDFs em um documento único, com <b>capa</b> e <b>índice</b>. O resumo '
                 + 'mostra o que entrará na compilação.',
            posicao: 'baixo',
            aguardar: 3000
        },
        {
            alvo: '#arq-pilha',
            titulo: 'A ordem dos documentos',
            texto: 'A bandeja lista os anexos na ordem em que serão juntados — <b>arraste</b> para '
                 + 'reordenar antes de gerar, e desmarque o que não deve entrar.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-carimbar',
            subir: 'label, .arq-campo',
            titulo: 'Carimbo de folhas',
            texto: 'Ligado, cada página recebe o carimbo <b>fl. N/M</b> — a numeração contínua que se '
                 + 'espera de um dossiê arquivado.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-compilar-zip',
            subir: 'label, .arq-campo',
            titulo: 'Baixar em ZIP',
            texto: 'Em vez do PDF único, baixa os arquivos originais compactados — útil quando é preciso '
                 + 'entregar os documentos exatamente como foram arquivados.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-compilar-gerar',
            titulo: 'Gerar',
            texto: 'A junção acontece <b>no próprio navegador</b>: os anexos são baixados, as páginas '
                 + 'contadas e o PDF é montado aqui. Por isso a barra de progresso — em dossiês grandes '
                 + 'leva alguns segundos, e a aba precisa ficar aberta até o fim.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqCadastro = [
        {
            alvo: '#atribuicao',
            titulo: 'Passo 01 — dados do ato',
            texto: '<b>Atribuição</b> e <b>categoria</b> classificam o arquivamento e são obrigatórias, '
                 + 'junto com a <b>data do ato</b>. O botão ao lado da categoria cria uma nova sem sair '
                 + 'da tela.',
            posicao: 'baixo',
            aguardar: 2500
        },
        {
            alvo: '#livro',
            titulo: 'Localização do ato',
            texto: 'Livro, folha, termo/ordem, protocolo e matrícula — preencha o que se aplicar ao seu '
                 + 'tipo de ato. São justamente esses campos que a busca do acervo pesquisa depois.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#p-cpf',
            titulo: 'Passo 02 — partes',
            texto: 'Informe CPF/CNPJ, nome e a <b>qualificação</b> (outorgante, outorgado, requerente…) '
                 + 'e clique em <b>adicionar</b>. Repita para cada pessoa — são elas que tornam o '
                 + 'registro localizável por nome ou documento.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#solta',
            titulo: 'Passo 03 — anexos',
            texto: 'Arraste os documentos digitalizados para esta área ou clique para escolher. O acervo '
                 + 'aceita <b>qualquer formato</b> (PDF, imagens, planilhas, XML…); arquivos que o '
                 + 'servidor poderia executar são neutralizados na gravação, mantendo o nome original '
                 + 'para você.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#bloco-selos',
            titulo: 'Passo 04 — selos',
            texto: 'Este passo aparece quando o arquivamento <b>já está gravado</b>, porque o selo precisa '
                 + 'do número do registro. Livro, folha, termo, escrevente e partes vão preenchidos do '
                 + 'que já está na tela.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#ato',
            titulo: 'Dados do selo',
            texto: 'Escolha o <b>ato</b> e a <b>tabela de custas</b>, informe a quantidade de folhas e, '
                 + 'sendo o caso, marque <b>isento</b> e descreva o motivo. Depois clique em '
                 + '<b>solicitar selo</b>.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#salvar',
            titulo: 'Salvar',
            texto: 'Grava o arquivamento e o devolve ao acervo. Os anexos são enviados junto — em '
                 + 'documentos grandes, aguarde a barra concluir antes de sair da tela.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqCategorias = [
        {
            alvo: '#nova',
            titulo: 'Criar categoria',
            texto: 'Digite o nome e clique em criar. As categorias organizam o acervo e alimentam os '
                 + 'filtros da tela principal — vale combinar um padrão de nomes com a equipe.',
            posicao: 'baixo'
        },
        {
            alvo: '#lista',
            titulo: 'Renomear e excluir',
            texto: '<b>Renomear</b> reclassifica sozinho todos os registros que usavam o nome antigo — '
                 + 'nenhum arquivamento fica órfão.<br><b>Excluir</b> só é permitido quando a categoria '
                 + 'não estiver em uso por nenhum registro.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosArqLixeira = [
        {
            alvo: '#busca',
            titulo: 'Lixeira',
            texto: 'Tudo o que é excluído no acervo vem para cá e fica disponível por um prazo, antes do '
                 + 'descarte definitivo. Use a busca para localizar o registro.',
            posicao: 'baixo'
        },
        {
            alvo: '#lista',
            titulo: 'Restaurar ou expurgar',
            texto: 'A lista mostra quem excluiu e o <b>prazo</b> restante.<ul>'
                 + '<li><b>Restaurar</b> devolve o registro ao acervo, com os anexos;</li>'
                 + '<li><b>Excluir definitivamente</b> (expurgo) é irreversível: exige perfil autorizado '
                 + 'e a digitação do número do arquivamento como confirmação.</li></ul>',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    /* ==================================================================
     * 5k) ARQUIVAMENTO DIGITAL — arquivamento/
     *     Acervo (busca, seleção, compilação), cadastro em 3 passos,
     *     categorias e lixeira. As janelas têm guia próprio.
     * ================================================================ */
    var passosArq = [
        {
            alvo: '.arq-kpis, #kpi-total',
            subir: '.arq-kpis',
            titulo: 'Arquivamento Digital',
            texto: 'O acervo digital da serventia: aqui ficam guardados, indexados e pesquisáveis os '
                 + 'documentos arquivados. Os indicadores do topo mostram o total de arquivamentos, os '
                 + 'do mês, a quantidade de documentos e o espaço ocupado.',
            posicao: 'baixo'
        },
        {
            alvo: '#arq-q',
            titulo: 'Busca rápida',
            texto: 'Procura em todo o registro de uma vez — número, nome das partes, CPF/CNPJ, livro, '
                 + 'folha, descrição. É por onde começa a maioria das consultas do balcão.',
            posicao: 'baixo'
        },
        {
            alvo: '#arq-periodo',
            titulo: 'Recorte de período',
            texto: 'Atalhos por <b>hoje</b>, <b>7 dias</b>, <b>30 dias</b>, <b>ano</b> ou <b>tudo</b>. '
                 + 'O padrão são 30 dias; para consultas antigas, lembre-se de trocar para “tudo”.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-alternar-filtros',
            titulo: 'Filtros avançados',
            texto: 'Abre a busca detalhada: atribuição, categoria, nome da parte, CPF/CNPJ, livro, folha, '
                 + 'termo, protocolo, matrícula, descrição, data exata, intervalo e presença de '
                 + 'documentos anexados. Use quando a busca rápida trouxer resultado demais.',
            posicao: 'baixo',
            aoEntrar: function (el) {
                if (el && el.getAttribute('aria-expanded') === 'false') { el.click(); }
            }
        },
        {
            alvo: '#arq-f-atribuicao',
            titulo: 'Combinando filtros',
            texto: 'Os campos se somam: atribuição + categoria + período, por exemplo, isolam '
                 + 'rapidamente um conjunto de atos. Os filtros ativos ficam listados logo abaixo, e '
                 + 'podem ser removidos um a um.',
            posicao: 'baixo',
            aguardar: 2000,
            opcional: true
        },
        {
            alvo: '#arq-visao',
            titulo: 'Fichas ou tabela',
            texto: 'Alterne entre a visão em <b>fichas</b> (melhor para leitura) e em <b>tabela</b> '
                 + '(melhor para conferir muitos registros de uma vez). A ordenação fica ao lado.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#arq-resultados',
            titulo: 'Os resultados',
            texto: 'Clique em um registro para abrir o <b>detalhe</b>, com as partes, os documentos '
                 + 'anexados, os selos vinculados e o histórico de quem consultou ou alterou aquele '
                 + 'arquivamento.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '#arq-selecao',
            titulo: 'Seleção múltipla',
            texto: 'Marcando vários registros aparece a barra flutuante com as ações em lote: '
                 + '<b>Compilar</b> (dossiê único em PDF), <b>ZIP</b> (originais + manifesto), '
                 + '<b>CSV</b> (planilha do resultado) e <b>limpar seleção</b>.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'a[href="cadastro.php"]',
            titulo: 'Novo arquivamento',
            texto: 'Abre o formulário de cadastro, em três passos: dados do ato, partes envolvidas e '
                 + 'documentos. Aquela tela tem o seu próprio guia.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: 'a[href="categorias.php"]',
            titulo: 'Categorias e Lixeira',
            texto: '<b>Categorias</b> organiza a classificação do acervo — renomear uma categoria '
                 + 'reclassifica sozinho todos os registros que a usavam. <b>Lixeira</b> guarda o que '
                 + 'foi excluído, pelo prazo de retenção, antes do descarte definitivo.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqDetalhe = [
        {
            alvo: '#arq-detalhe-corpo',
            titulo: 'Detalhe do arquivamento',
            texto: 'Reúne tudo o que existe sobre o registro: dados do ato (livro, folha, termo, '
                 + 'protocolo, matrícula), as <b>partes</b>, os <b>documentos anexados</b> e os '
                 + '<b>selos digitais</b> vinculados. Clique em um documento para vê-lo sem sair da tela.',
            posicao: 'topo',
            aguardar: 3000
        },
        {
            alvo: '#arq-detalhe-capa',
            titulo: 'Capa de arquivamento',
            texto: 'Gera a capa em PDF para a juntada física do processo — com os dados do ato e os '
                 + 'selos vinculados.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-detalhe-compilar',
            titulo: 'Compilar este dossiê',
            texto: 'Junta os documentos deste arquivamento em um <b>PDF único</b>, com capa e índice.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-detalhe-editar',
            titulo: 'Editar',
            texto: 'Abre o registro no formulário de cadastro para corrigir dados, incluir partes ou '
                 + 'anexar novos documentos. Toda alteração fica registrada na trilha de auditoria.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqCompilar = [
        {
            alvo: '#arq-compilar-resumo',
            titulo: 'Compilar dossiê',
            texto: 'Junta os documentos selecionados em um <b>PDF único</b>, com capa e índice — e o '
                 + 'índice já sai com o intervalo de folhas de cada documento (1–3, 4, 5–12).<br>'
                 + 'A junção acontece <b>no seu navegador</b>: não pesa no servidor e dá conta de '
                 + 'dossiês grandes.',
            posicao: 'baixo',
            aguardar: 3000
        },
        {
            alvo: '#arq-pilha',
            titulo: 'A ordem dos documentos',
            texto: 'A bandeja mostra a pilha na ordem em que os documentos entrarão no PDF. '
                 + '<b>Arraste</b> para reordenar antes de gerar.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-carimbar',
            subir: 'label',
            titulo: 'Numeração das folhas',
            texto: 'Com esta opção ligada, cada folha do corpo recebe o carimbo <b>fl. N/M</b> — '
                 + 'inclusive nas páginas giradas, respeitando a orientação.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-compilar-gerar',
            titulo: 'Gerar o PDF',
            texto: 'Monta capa, índice e corpo e entrega o arquivo. Formatos que não entram no PDF '
                 + '(DOCX, XLSX, TXT, P7S) são listados na capa como <b>não incorporados</b> — para '
                 + 'esses, use o ZIP.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#arq-compilar-zip',
            titulo: 'Baixar em ZIP',
            texto: 'Alternativa que preserva os <b>originais</b>, acompanhados de um '
                 + '<b>MANIFESTO.txt</b> com o SHA-256 de cada arquivo — serve para provar que o '
                 + 'documento entregue é bit a bit o que está no acervo.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqVisor = [
        {
            alvo: '#arq-visor-palco',
            titulo: 'Visualizador de documentos',
            texto: 'Exibe o documento sem sair do acervo. A entrega passa sempre por rota autenticada — '
                 + 'e cada consulta fica registrada na trilha de auditoria.',
            posicao: 'topo',
            aguardar: 3000
        },
        {
            alvo: '#arq-visor-baixar',
            titulo: 'Abrir em aba e baixar',
            texto: 'Use <b>abrir em nova aba</b> para conferir em tela cheia ou <b>baixar</b> quando '
                 + 'precisar do arquivo original.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqCadastro = [
        {
            alvo: '#atribuicao',
            titulo: 'Passo 1 — dados do ato',
            texto: 'Comece pela <b>atribuição</b> e pela <b>categoria</b> (o botão <b>+</b> ao lado cria '
                 + 'uma categoria nova sem sair daqui) e pela <b>data do ato</b>. Os três são '
                 + 'obrigatórios.',
            posicao: 'baixo',
            aguardar: 2000
        },
        {
            alvo: '#livro',
            titulo: 'Localização do ato',
            texto: '<b>Livro</b>, <b>folha</b>, <b>termo/ordem</b>, <b>protocolo</b> e <b>matrícula</b>: '
                 + 'preencha o que existir. São esses campos que permitem reencontrar o documento anos '
                 + 'depois, e todos são pesquisáveis no acervo.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#descricao',
            titulo: 'Descrição',
            texto: 'Um resumo do que está sendo arquivado. Vale caprichar: é o texto que aparece na '
                 + 'busca e ajuda quem for procurar depois sem saber o número.',
            posicao: 'topo',
            opcional: true
        },
        {
            alvo: '#p-cpf',
            titulo: 'Passo 2 — partes',
            texto: 'Informe <b>CPF/CNPJ</b>, <b>nome completo</b> e a <b>qualificação</b> (outorgante, '
                 + 'outorgado, requerente…) e clique em adicionar. Repita para cada pessoa envolvida — '
                 + 'a busca por nome e por documento depende disso.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#partes',
            titulo: 'Partes adicionadas',
            texto: 'A lista mostra quem já foi incluído; dá para remover antes de salvar.',
            posicao: 'topo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#solta',
            titulo: 'Passo 3 — documentos',
            texto: 'Arraste os arquivos para esta área ou clique para escolher. Qualquer formato é '
                 + 'aceito — é um acervo de cartório —, mas arquivos executáveis no servidor são '
                 + 'neutralizados na gravação, por segurança.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#fila',
            titulo: 'Fila de envio',
            texto: 'Acompanhe aqui o progresso de cada anexo. Ao editar um arquivamento, os documentos '
                 + 'já existentes aparecem acima e podem ser removidos.',
            posicao: 'topo',
            aguardar: 2500,
            opcional: true
        },
        {
            alvo: '#btnAddSelo',
            titulo: 'Selos digitais',
            texto: 'Vincule o selo do ato: informe <b>ato</b>, <b>tabela de custas</b> e <b>quantidade '
                 + 'de folhas</b>; havendo <b>isenção</b>, marque a opção e informe o motivo. Os selos '
                 + 'vinculados saem na capa e no dossiê compilado.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#salvar',
            titulo: 'Salvar o arquivamento',
            texto: 'Grava o registro com as partes, os anexos e os selos. Depois de salvo, ele já '
                 + 'aparece no acervo e pode ser compilado, ter capa gerada ou ser editado.',
            posicao: 'topo',
            opcional: true
        }
    ];

    var passosArqCategorias = [
        {
            alvo: '#nova',
            titulo: 'Criar categoria',
            texto: 'Digite o nome e clique em criar. As categorias organizam o acervo e alimentam os '
                 + 'filtros da busca — vale combinar um padrão com a equipe antes de sair criando.',
            posicao: 'baixo'
        },
        {
            alvo: '#lista',
            titulo: 'Renomear e excluir',
            texto: '<b>Renomear</b> reclassifica automaticamente todos os registros que usavam o nome '
                 + 'antigo — nada fica órfão. Já a <b>exclusão</b> só é permitida quando a categoria '
                 + 'não estiver em uso por nenhum arquivamento.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    var passosArqLixeira = [
        {
            alvo: '#busca',
            titulo: 'Lixeira',
            texto: 'Arquivamentos excluídos não somem na hora: ficam aqui pelo prazo de retenção '
                 + 'configurado, e podem ser localizados pela busca.',
            posicao: 'baixo'
        },
        {
            alvo: '#lista',
            titulo: 'Restaurar ou expurgar',
            texto: '<b>Restaurar</b> devolve o registro ao acervo com tudo o que ele tinha. '
                 + 'O <b>expurgo</b> é definitivo e por isso é protegido: exige perfil autorizado e '
                 + 'digitar o número do arquivamento para confirmar. Não há como desfazer.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        }
    ];

    /* ==================================================================
     * 6) TABELA DE EMOLUMENTOS — tabela_de_emolumentos.php
     * ================================================================ */
    var passosEmolumentos = [
        {
            alvo: '.page-hero',
            titulo: 'Tabela de Emolumentos',
            texto: 'Esta é a consulta da <b>tabela vigente</b>: códigos dos atos, descrição e os valores '
                 + 'que o sistema usa nos cálculos (emolumentos, FERC, FADEP, FEMP, FERRFIS e total). '
                 + 'Use-a sempre que tiver dúvida sobre qual código lançar na O.S.',
            posicao: 'baixo'
        },
        {
            alvo: '.stat-card',
            titulo: 'Totais da consulta',
            texto: 'Os cartões mostram quantos registros a pesquisa retornou e a soma de cada fundo — '
                 + 'os números acompanham os filtros aplicados.',
            posicao: 'baixo',
            opcional: true
        },
        {
            alvo: '#ato',
            titulo: 'Filtrar por código',
            texto: 'Digite o código, inteiro ou em parte (ex.: <code>16</code> traz todos os atos de '
                 + 'Registro de Imóveis que começam por 16).',
            posicao: 'baixo'
        },
        {
            alvo: '#descricao',
            titulo: 'Filtrar por descrição',
            texto: 'Quando não souber o código, procure por uma palavra do ato — por exemplo '
                 + '<code>certidão</code> ou <code>autenticação</code>.',
            posicao: 'baixo'
        },
        {
            alvo: '#atribuicao',
            titulo: 'Filtrar por atribuição',
            texto: 'Restringe a busca à atribuição desejada: Notas, Registro Civil, Títulos e Documentos '
                 + 'e Pessoas Jurídicas, Registro de Imóveis, Protesto ou Contratos Marítimos.',
            posicao: 'baixo'
        },
        {
            alvo: '#pesquisarForm button[type="submit"]',
            titulo: 'Pesquisar',
            texto: 'Aplica os filtros. Para voltar à tabela completa, use <b>Limpar</b> ao lado.',
            posicao: 'topo'
        },
        {
            alvo: '#resultadosTabela',
            titulo: 'Resultado da consulta',
            texto: 'Cada linha traz o ato, a descrição e a composição do valor. É exatamente esse total '
                 + 'que o sistema lança quando você usa o botão <b>Buscar Ato</b> na Ordem de Serviço. '
                 + 'A tabela tem busca rápida, ordenação por coluna e paginação.',
            posicao: 'topo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: '.dt-buttons',
            titulo: 'Exportar',
            texto: 'Dá para levar o resultado para <b>Excel</b>, <b>CSV</b> ou copiar para a área de '
                 + 'transferência — útil para conferências e para afixar a tabela no balcão.',
            posicao: 'baixo',
            aguardar: 3000,
            opcional: true
        },
        {
            alvo: 'button[onclick*="window.print"]',
            titulo: 'Imprimir',
            texto: 'Gera a versão impressa da consulta atual, já com os filtros aplicados.',
            posicao: 'baixo',
            opcional: true
        },
        {
            titulo: 'Voltando para a O.S.',
            texto: 'Anotou o código? Volte à Ordem de Serviço, digite-o em <b>Código do Ato</b> e clique '
                 + 'em <b>Buscar Ato</b> — os valores vêm daqui automaticamente. Quando esta tela é aberta '
                 + 'pelo botão da O.S., ela abre em outra aba: nada do que você digitou se perde.'
        }
    ];

    /* ==================================================================
     * 7) DESFAZER LIQUIDAÇÕES — liberar_os.php
     * ================================================================ */
    var passosLiberar = [
        {
            alvo: '.page-hero',
            titulo: 'Desfazer liquidação de uma O.S.',
            texto: 'Esta tela serve para corrigir um engano de liquidação. Ela apaga <b>somente os atos '
                 + 'liquidados hoje</b> da Ordem de Serviço e limpa o status desses itens, devolvendo-os '
                 + 'à condição de pendentes.<ul>'
                 + '<li>Liquidações de <b>dias anteriores</b> não são tocadas — e bloqueiam a operação.</li>'
                 + '<li>A ação <b>não pode ser desfeita</b> e fica registrada em log.</li></ul>',
            posicao: 'baixo'
        },
        {
            alvo: '#os_id',
            titulo: 'Número da O.S.',
            texto: 'Informe o número da Ordem de Serviço que precisa ser liberada.',
            posicao: 'baixo'
        },
        {
            alvo: '#btnResumo',
            titulo: 'Ver Resumo',
            texto: 'Antes de qualquer coisa, consulte o resumo: ele mostra o que exatamente será apagado. '
                 + 'Nada é alterado neste passo.',
            posicao: 'baixo',
            avancarEm: { evento: 'click', atraso: 1200, dica: 'Clique em “Ver Resumo” para continuar.' }
        },
        {
            alvo: '#resumoWrap',
            titulo: 'O que será apagado',
            texto: 'O resumo traz os atos <b>liquidados hoje</b>, os <b>lançamentos manuais de hoje</b>, '
                 + 'os <b>registros anteriores</b> e quantos itens da O.S. possuem liquidação.',
            posicao: 'topo',
            aguardar: 5000,
            opcional: true
        },
        {
            alvo: '#sum_anteriores',
            titulo: 'A regra do bloqueio',
            texto: 'Se este número for maior que zero, aparece o aviso <b>“Bloqueado por registros '
                 + 'anteriores”</b> e o botão de desfazer continua desabilitado — a liberação só é '
                 + 'permitida quando toda a liquidação da O.S. foi feita hoje.',
            posicao: 'topo',
            aguardar: 5000,
            opcional: true
        },
        {
            alvo: '#btnLiberar',
            titulo: 'Desfazer Liquidação',
            texto: 'Habilitado apenas quando o resumo está liberado. O sistema pede uma confirmação '
                 + 'final antes de apagar os atos liquidados hoje — leia com atenção, pois '
                 + '<b>não há como reverter</b>.',
            posicao: 'topo'
        },
        {
            alvo: '#tabelaLogs',
            titulo: 'Histórico de liberações',
            texto: 'Toda liberação fica registrada com data e hora, número da O.S., usuário, IP e o que '
                 + 'havia antes e foi removido. É a trilha de auditoria da operação.',
            posicao: 'topo',
            aguardar: 4000,
            opcional: true
        },
        {
            titulo: 'Depois de liberar',
            texto: 'Com os itens de volta à condição de pendentes, abra a Ordem de Serviço e refaça a '
                 + 'liquidação corretamente. Se o erro tiver sido no pagamento, ajuste-o antes em '
                 + '<b>Pagamentos</b>, na tela da O.S.'
        }
    ];

    /* ==================================================================
     * Utilitário: trocar de guia (e o rótulo do botão “?”) quando um modal
     * abre, e retomar o guia anterior quando ele fecha.
     * ================================================================ */
    var guiaAnterior = null;

    function ligarModalGuia(seletor, nomeGuia, rotulo, nomeBase, rotuloBase) {
        var ligar = function () {
            if (ligar.feito) { return true; }
            ligar.feito = window.GuiaOS.aoAbrirModal(seletor,
                function () {
                    window.GuiaOS.definirBotaoAjuda(nomeGuia, rotulo);
                    var atual = window.GuiaOS.emExecucao();
                    if (atual && atual.nome !== nomeGuia) { guiaAnterior = atual; }
                    if (guiaAnterior || !window.GuiaOS.jaConcluido(nomeGuia)) {
                        window.GuiaOS.iniciar(nomeGuia, { reiniciar: true });
                    }
                },
                function () {
                    window.GuiaOS.definirBotaoAjuda(nomeBase, rotuloBase);
                    var atual = window.GuiaOS.emExecucao();
                    if (atual && atual.nome === nomeGuia) { window.GuiaOS.parar(); }
                    if (guiaAnterior) {
                        var voltar = guiaAnterior;
                        guiaAnterior = null;
                        window.setTimeout(function () {
                            window.GuiaOS.iniciar(voltar.nome, { indice: voltar.indice, reiniciar: true });
                        }, 250);
                    }
                });
            return ligar.feito;
        };

        // O modal pode ser criado por outro script (carregado depois): insiste um pouco.
        if (!ligar()) {
            var tentativas = 0;
            var relogio = window.setInterval(function () {
                if (ligar() || ++tentativas > 25) { window.clearInterval(relogio); }
            }, 400);
        }
    }

    /* Na Forja, o botão “?” segue a aba aberta: cada ferramenta tem o seu guia.
       Se o usuário já estiver dentro de um guia da Forja, ele troca junto. */
    function acompanharAbasForja() {
        var porChave = {};
        for (var i = 0; i < FERRAMENTAS_FORJA.length; i++) {
            porChave[FERRAMENTAS_FORJA[i][0]] = FERRAMENTAS_FORJA[i];
        }

        function aplicar(chave, trocarGuiaEmExecucao) {
            var f = porChave[chave];
            if (!f) { return; }
            window.GuiaOS.definirBotaoAjuda(f[1], f[2]);
            if (!trocarGuiaEmExecucao) { return; }
            var atual = window.GuiaOS.emExecucao();
            if (!atual || atual.nome === f[1]) { return; }   // nada a trocar (evita reinício à toa)
            if (/^forja/.test(atual.nome)) {                 // já estava num guia da Forja: troca junto
                window.GuiaOS.iniciar(f[1], { reiniciar: true });
            }
        }

        function ligar() {
            var abas = document.querySelectorAll('.tab[data-tab]');
            if (!abas.length) { return false; }
            for (var i = 0; i < abas.length; i++) {
                (function (aba) {
                    aba.addEventListener('click', function () {
                        window.setTimeout(function () { aplicar(aba.getAttribute('data-tab'), true); }, 60);
                    });
                })(abas[i]);
            }
            return true;   // o rótulo inicial continua sendo o do guia geral da Forja
        }

        if (!ligar()) {
            var tentativas = 0;
            var relogio = window.setInterval(function () {
                if (ligar() || ++tentativas > 20) { window.clearInterval(relogio); }
            }, 300);
        }
    }

    /* ==================================================================
     * Registro conforme a tela aberta
     * ================================================================ */
    var caminho = (window.location.pathname || '').toLowerCase();
    var emSignum = caminho.indexOf('/signum/') >= 0;
    var emForja = caminho.indexOf('/forja/') >= 0;
    var emCaixa = caminho.indexOf('/caixa/') >= 0;
    var emCap = caminho.indexOf('/contas_a_pagar/') >= 0;
    var emArq = caminho.indexOf('/arquivamento/') >= 0;
    var emArq = caminho.indexOf('/arquivamento/') >= 0;

    if (emArq) {
        if (/^cadastro/.test(pagina)) {
            window.GuiaOS.registrar('arq-cadastro', passosArqCadastro, {
                rotuloAjuda: 'Como cadastrar um arquivamento',
                mensagemFinal: 'Arquivamento pronto! Ele já aparece no acervo, pesquisável por parte, livro e protocolo.'
            });
            window.GuiaOS.autoIniciar('arq-cadastro');
        } else if (/^categorias/.test(pagina)) {
            window.GuiaOS.registrar('arq-categorias', passosArqCategorias, {
                rotuloAjuda: 'Guia das categorias'
            });
        } else if (/^lixeira/.test(pagina)) {
            window.GuiaOS.registrar('arq-lixeira', passosArqLixeira, {
                rotuloAjuda: 'Guia da lixeira'
            });
        } else {
            var ROTULO_ARQ = 'Guia do Arquivamento';
            window.GuiaOS.registrar('arq', passosArq, {
                rotuloAjuda: ROTULO_ARQ,
                mensagemFinal: 'Cada painel do módulo tem o seu guia: abra o painel e clique no “?”.'
            });
            var JANELAS_ARQ = [
                ['#arq-dlg-detalhe', 'arq-detalhe', 'Como ler o detalhe do registro', passosArqDetalhe],
                ['#arq-dlg-visor', 'arq-visor', 'Como usar o visualizador', passosArqVisor],
                ['#arq-dlg-compilar', 'arq-compilar', 'Como compilar o dossiê', passosArqCompilar]
            ];
            for (var iA = 0; iA < JANELAS_ARQ.length; iA++) {
                (function (j) {
                    window.GuiaOS.registrar(j[1], j[3], {
                        botaoAjuda: false,
                        rotuloAjuda: j[2],
                        mensagemFinal: 'Feche o painel para voltar ao acervo.'
                    });
                    ligarModalGuia(j[0], j[1], j[2], 'arq', ROTULO_ARQ);
                })(JANELAS_ARQ[iA]);
            }
            window.GuiaOS.autoIniciar('arq');
        }
    } else if (emCap) {
        if (/^extrato/.test(pagina)) {
            window.GuiaOS.registrar('cap-extrato', passosCapExtrato, {
                rotuloAjuda: 'Guia do extrato',
                mensagemFinal: 'Extrato conferido! Volte ao painel pelo botão “Contas a pagar”.'
            });
            window.GuiaOS.registrar('cap-transferir', passosCapTransferir, {
                botaoAjuda: false,
                rotuloAjuda: 'Como transferir entre contas',
                mensagemFinal: 'Transferência registrada — confira no extrato das duas contas.'
            });
            ligarModalGuia('#transferirModal', 'cap-transferir', 'Como transferir entre contas',
                           'cap-extrato', 'Guia do extrato');
            window.GuiaOS.autoIniciar('cap-extrato');
        } else if (/^relatorios/.test(pagina)) {
            window.GuiaOS.registrar('cap-relatorios', passosCapRelatorios, {
                rotuloAjuda: 'Guia dos relatórios',
                mensagemFinal: 'Pronto! Use o CSV quando precisar dos números fora do sistema.'
            });
            window.GuiaOS.autoIniciar('cap-relatorios');
        } else {
            var ROTULO_CAP = 'Guia do Contas a Pagar';
            window.GuiaOS.registrar('cap', passosCap, {
                rotuloAjuda: ROTULO_CAP,
                mensagemFinal: 'Cada janela do módulo tem o seu guia: abra a janela e clique no “?”.'
            });
            var JANELAS_CAP = [
                ['#contaModal', 'cap-conta', 'Como cadastrar uma conta', passosCapConta],
                ['#pagarModal', 'cap-pagar', 'Como registrar o pagamento', passosCapPagar],
                ['#anexosModal', 'cap-anexos', 'Como anexar documentos', passosCapAnexos],
                ['#configModal', 'cap-config', 'Como configurar os alertas', passosCapConfig]
            ];
            for (var iP = 0; iP < JANELAS_CAP.length; iP++) {
                (function (j) {
                    window.GuiaOS.registrar(j[1], j[3], {
                        botaoAjuda: false,
                        rotuloAjuda: j[2],
                        mensagemFinal: 'Feche a janela para voltar ao painel de contas.'
                    });
                    ligarModalGuia(j[0], j[1], j[2], 'cap', ROTULO_CAP);
                })(JANELAS_CAP[iP]);
            }
            window.GuiaOS.autoIniciar('cap');
        }
    } else if (emCaixa) {
        var ROTULO_CAIXA = 'Guia do Fluxo de Caixa';
        window.GuiaOS.registrar('caixa', passosCaixa, {
            rotuloAjuda: ROTULO_CAIXA,
            mensagemFinal: 'Cada janela do módulo tem o seu guia: abra a janela e clique no “?”.'
        });
        var JANELAS_CAIXA = [
            ['#detalhesModal', 'caixa-detalhes', 'Como ler os detalhes do caixa', passosCaixaDetalhes],
            ['#cadastroSaidaModal', 'caixa-saida', 'Como lançar uma saída', passosCaixaSaida],
            ['#cadastroDepositoModal', 'caixa-deposito', 'Como depositar e fechar o caixa', passosCaixaDeposito],
            ['#anexarComprovanteModal', 'caixa-anexar', 'Como anexar o comprovante', passosCaixaAnexar],
            ['#verDepositosCaixaModal', 'caixa-depositos', 'Como conferir os depósitos', passosCaixaDepositosUnificado],
            ['#abrirCaixaModal', 'caixa-abrir', 'Como abrir o caixa do dia', passosCaixaAbrir]
        ];
        for (var iC = 0; iC < JANELAS_CAIXA.length; iC++) {
            (function (j) {
                window.GuiaOS.registrar(j[1], j[3], {
                    botaoAjuda: false,          // o botão existente é reaproveitado
                    rotuloAjuda: j[2],
                    mensagemFinal: 'Feche a janela para voltar ao fluxo de caixa.'
                });
                ligarModalGuia(j[0], j[1], j[2], 'caixa', ROTULO_CAIXA);
            })(JANELAS_CAIXA[iC]);
        }
        window.GuiaOS.autoIniciar('caixa');
    } else if (emForja && /^configurar/.test(pagina)) {
        window.GuiaOS.registrar('forja-config', passosForjaConfig, {
            rotuloAjuda: 'Guia das configurações',
            mensagemFinal: 'Configurado! Volte à Forja e use as ferramentas.'
        });
    } else if (emForja) {
        window.GuiaOS.registrar('forja', passosForja, {
            rotuloAjuda: 'Guia da Atlas Forja',
            mensagemFinal: 'Cada aba tem o seu próprio passo a passo: abra a ferramenta e clique no “?”.'
        });
        for (var iF = 0; iF < FERRAMENTAS_FORJA.length; iF++) {
            (function (f) {
                window.GuiaOS.registrar(f[1], f[3], {
                    botaoAjuda: false,           // o botão existente é reaproveitado
                    rotuloAjuda: f[2],
                    mensagemFinal: 'Pronto! Para outra ferramenta, troque de aba — o guia acompanha.'
                });
            })(FERRAMENTAS_FORJA[iF]);
        }
        window.GuiaOS.autoIniciar('forja');
        acompanharAbasForja();
    } else if (emSignum && /^configurar/.test(pagina)) {
        window.GuiaOS.registrar('signum-config', passosSignumConfig, {
            rotuloAjuda: 'Guia das configurações',
            mensagemFinal: 'Configurações prontas. Volte ao Signum para assinar seus PDFs.'
        });
        window.GuiaOS.registrar('autorizar-assinador', passosAutorizar, {
            botaoAjuda: false,
            rotuloAjuda: 'Como autorizar o Assinador',
            mensagemFinal: 'Assinador liberado! O selo acima já deve indicar “online”.'
        });
        window.GuiaOS.autoIniciar('signum-config');
        ligarModalGuia('#modalAutorizarAssinador', 'autorizar-assinador', 'Como autorizar o Assinador',
                       'signum-config', 'Guia das configurações');
    } else if (emSignum) {
        window.GuiaOS.registrar('signum', passosSignum, {
            rotuloAjuda: 'Guia do Atlas Signum',
            mensagemFinal: 'É isso! Anexe o PDF, posicione o carimbo e assine. '
                         + 'O botão “?” traz este passo a passo quando precisar.'
        });
        window.GuiaOS.registrar('autorizar-assinador', passosAutorizar, {
            botaoAjuda: false,
            rotuloAjuda: 'Como autorizar o Assinador',
            mensagemFinal: 'Assinador liberado! Agora é só autenticar o certificado e assinar.'
        });
        window.GuiaOS.autoIniciar('signum');
        ligarModalGuia('#modalAutorizarAssinador', 'autorizar-assinador', 'Como autorizar o Assinador',
                       'signum', 'Guia do Atlas Signum');
    } else if (/^(criar_os|demo-criar-os)/.test(pagina)) {
        window.GuiaOS.registrar('criar-os', passosCriar, {
            rotuloAjuda: 'Como criar uma O.S.',
            mensagemFinal: 'Pronto! Agora é só preencher o apresentante, lançar os atos e clicar em SALVAR OS. '
                         + 'Para rever este passo a passo, use o botão “?” no canto inferior direito.'
        });
        // Abre sozinho na primeira vez que o usuário entra nesta tela.
        window.GuiaOS.autoIniciar('criar-os');
    } else if (/^assinar-os/.test(pagina)) {
        window.GuiaOS.registrar('assinar-os', passosAssinar, {
            rotuloAjuda: 'Como assinar o documento',
            mensagemFinal: 'Documento assinado? Feche esta aba para voltar à Ordem de Serviço.'
        });
        window.GuiaOS.registrar('autorizar-assinador', passosAutorizar, {
            botaoAjuda: false,
            rotuloAjuda: 'Como autorizar o Assinador',
            mensagemFinal: 'Assinador liberado! Agora é só posicionar o selo e assinar.'
        });
        window.GuiaOS.autoIniciar('assinar-os');

        /* A janela de autorização (criada pelo assinador-autorizar.js) troca o guia. */
        ligarModalGuia('#modalAutorizarAssinador', 'autorizar-assinador', 'Como autorizar o Assinador',
                       'assinar-os', 'Como assinar o documento');

    } else if (/^modelos_orcamento/.test(pagina)) {
        window.GuiaOS.registrar('modelos-os', passosModelos, {
            rotuloAjuda: 'Como criar um modelo de O.S.',
            mensagemFinal: 'Modelo pronto! Ele já aparece em “Carregar Modelo de O.S.” na tela de criação da Ordem de Serviço.'
        });
        window.GuiaOS.autoIniciar('modelos-os');
    } else if (/^visualizar_os/.test(pagina)) {
        window.GuiaOS.registrar('os-criada', passosVisualizar, {
            rotuloAjuda: 'O que fazer com esta O.S.',
            mensagemFinal: 'Guia concluído. Use o botão “?” sempre que precisar rever.'
        });
        window.GuiaOS.registrar('pagamento-os', passosPagamento, {
            botaoAjuda: false,          // não cria um segundo botão: o existente é reaproveitado
            rotuloAjuda: 'Como adicionar pagamento',
            mensagemFinal: 'Pagamento registrado! Agora feche a janela e liquide os atos da O.S.'
        });
        window.GuiaOS.registrar('anexo-os', passosAnexo, {
            botaoAjuda: false,
            rotuloAjuda: 'Como adicionar anexo',
            mensagemFinal: 'Anexo enviado! Feche a janela para voltar à Ordem de Serviço.'
        });

        /* Ao abrir uma janela (Pagamentos ou Anexos), o guia — e o rótulo do botão “?” —
           passam a ser os daquela janela; ao fechá-la, o guia anterior é retomado. */
        var ROTULO_OS = 'O que fazer com esta O.S.';
        ligarModalGuia('#pagamentoModal', 'pagamento-os', 'Como adicionar pagamento', 'os-criada', ROTULO_OS);
        ligarModalGuia('#anexoModal', 'anexo-os', 'Como adicionar anexo', 'os-criada', ROTULO_OS);

    } else if (/^tabela_de_emolumentos/.test(pagina)) {
        window.GuiaOS.registrar('tabela-emolumentos', passosEmolumentos, {
            rotuloAjuda: 'Como consultar a tabela',
            mensagemFinal: 'Pronto! Use o código encontrado no campo “Código do Ato” da Ordem de Serviço.'
        });
    } else if (/^liberar_os/.test(pagina)) {
        window.GuiaOS.registrar('liberar-os', passosLiberar, {
            rotuloAjuda: 'Como desfazer uma liquidação',
            mensagemFinal: 'Guia concluído. Lembre-se: só as liquidações feitas hoje são desfeitas, e a ação é registrada em log.'
        });
        window.GuiaOS.autoIniciar('liberar-os');
    } else if (/^editar_os/.test(pagina)) {
        window.GuiaOS.registrar('editar-os', passosEditar, {
            rotuloAjuda: 'Como editar esta O.S.',
            mensagemFinal: 'Alterações concluídas! Lembre-se: os itens valem na hora; o cabeçalho só depois de SALVAR OS.'
        });
        window.GuiaOS.autoIniciar('editar-os');
    } else if (/^modelos_orcamento/.test(pagina)) {
        window.GuiaOS.registrar('modelos-os', passosModelos, {
            rotuloAjuda: 'Como criar um modelo de O.S.',
            mensagemFinal: 'Modelo pronto! Ele já aparece em “Carregar Modelo de O.S.” na tela de criação da Ordem de Serviço.'
        });
        window.GuiaOS.autoIniciar('modelos-os');
    } else if (/^visualizar_os/.test(pagina)) {
        window.GuiaOS.registrar('os-criada', passosVisualizar, {
            rotuloAjuda: 'O que fazer com esta O.S.',
            mensagemFinal: 'Guia concluído. Use o botão “?” sempre que precisar rever.'
        });
        window.GuiaOS.registrar('pagamento-os', passosPagamento, {
            botaoAjuda: false,          // não cria um segundo botão: o existente é reaproveitado
            rotuloAjuda: 'Como adicionar pagamento',
            mensagemFinal: 'Pagamento registrado! Agora feche a janela e liquide os atos da O.S.'
        });
        window.GuiaOS.registrar('anexo-os', passosAnexo, {
            botaoAjuda: false,
            rotuloAjuda: 'Como adicionar anexo',
            mensagemFinal: 'Anexo enviado! Feche a janela para voltar à Ordem de Serviço.'
        });

        /* Ao abrir uma janela (Pagamentos ou Anexos), o guia — e o rótulo do botão “?” —
           passam a ser os daquela janela; ao fechá-la, o guia anterior é retomado no ponto
           em que estava e o botão volta ao guia da O.S. */
        var ROTULO_OS = 'O que fazer com esta O.S.';
        var guiaAnterior = null;

        var ligarGuiaDeModal = function (seletor, nomeGuia, rotulo) {
            return window.GuiaOS.aoAbrirModal(seletor,
                function () {
                    window.GuiaOS.definirBotaoAjuda(nomeGuia, rotulo);
                    var atual = window.GuiaOS.emExecucao();
                    if (atual && atual.nome !== nomeGuia) { guiaAnterior = atual; }
                    if (guiaAnterior || !window.GuiaOS.jaConcluido(nomeGuia)) {
                        window.GuiaOS.iniciar(nomeGuia, { reiniciar: true });
                    }
                },
                function () {
                    window.GuiaOS.definirBotaoAjuda('os-criada', ROTULO_OS);
                    var atual = window.GuiaOS.emExecucao();
                    if (atual && atual.nome === nomeGuia) { window.GuiaOS.parar(); }
                    if (guiaAnterior) {
                        var voltar = guiaAnterior;
                        guiaAnterior = null;
                        window.setTimeout(function () {
                            window.GuiaOS.iniciar(voltar.nome, { indice: voltar.indice, reiniciar: true });
                        }, 250);
                    }
                });
        };

        var ligarGuiasDeModais = function () {
            if (ligarGuiasDeModais.feito) { return; }
            var a = ligarGuiaDeModal('#pagamentoModal', 'pagamento-os', 'Como adicionar pagamento');
            var b = ligarGuiaDeModal('#anexoModal', 'anexo-os', 'Como adicionar anexo');
            ligarGuiasDeModais.feito = a || b;
        };

        document.addEventListener('DOMContentLoaded', ligarGuiasDeModais);
        if (document.readyState !== 'loading') { ligarGuiasDeModais(); }

    } else {
        window.GuiaOS.registrar('pesquisa-os', passosPesquisa, {
            rotuloAjuda: 'Guia do módulo O.S.'
        });
    }
})();
