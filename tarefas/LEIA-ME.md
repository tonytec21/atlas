# Atlas · Módulo de Tarefas — versão 2

Refatoração completa do módulo, com o acervo existente preservado.

---

## 1. Instalação em 3 passos

### Passo 1 — substituir os arquivos

Faça uma cópia da pasta atual antes de qualquer coisa:

```
xcopy C:\xampp\htdocs\atlas\tarefas C:\backup\tarefas_v1 /E /I /H
```

Depois copie o conteúdo deste ZIP por cima de `C:\xampp\htdocs\atlas\tarefas`.

> A pasta `arquivos/` **não vem no ZIP** justamente para não sobrescrever os anexos
> já enviados. Ela continua onde está.

Como o Apache está com OPcache ligado, reinicie o serviço depois de copiar:

```
C:\xampp\apache_stop.bat
C:\xampp\apache_start.bat
```

### Passo 2 — rodar a migração

Abra no navegador, logado como administrador:

```
http://localhost/atlas/tarefas/migracao_v2.php
```

A migração **só acrescenta**: cria 6 tabelas novas, 8 colunas novas em `tarefas`
e 6 índices. Não há nenhum `DROP`, nenhum `UPDATE` em dado existente e nenhuma
alteração de coluna já usada. Rodar de novo é seguro — cada passo confere antes
se já foi aplicado.

O painel mostra ao final quantas tarefas e comentários existem na base. Confira
que os números batem com o que você tinha antes.

### Passo 3 — configurar a IA (opcional)

```
http://localhost/atlas/tarefas/configuracoes-ia.php
```

Cole a chave obtida em **aistudio.google.com → Get API key**, escolha o modelo
padrão e clique em **Testar conexão**. Enquanto não houver chave, o módulo
funciona normalmente — os botões de IA simplesmente não aparecem.

---

## 2. Sobre os modelos Gemini

O catálogo já vem semeado com a linha 3.x:

| Identificador de API | Situação inicial | Observação |
|---|---|---|
| `gemini-3.5-flash` | ativo, favorito | equilíbrio para o uso diário |
| `gemini-3.1-flash-lite` | ativo | mais barato, para classificação e textos curtos |
| `gemini-3.1-pro-preview` | ativo | raciocínio mais profundo, análise de documentos |
| `gemini-3.6-flash` | desativado | identificador da geração seguinte |
| `gemini-3.5-flash-lite` | desativado | idem |

**Atenção a um detalhe que gera erro 404:** o Gemini 3.1 Pro é publicado na
Developer API como `gemini-3.1-pro-preview`. Cadastrar `gemini-3.1-pro` puro
retorna "modelo não encontrado".

Os dois últimos entram desativados de propósito: podem ainda não estar
liberados para a sua chave. Clique em **Sincronizar modelos** — o sistema
consulta a API, marca quais identificadores a sua chave realmente enxerga e
cadastra os que faltarem. Aí é só ativar.

A família 2.5 foi deixada de fora deliberadamente: o Google anunciou o
desligamento dela.

**O catálogo é seu.** Cadastre, edite, ative, favorite ou exclua modelos à
vontade conforme o Google atualizar a linha. A única trava: o modelo definido
como padrão não pode ser excluído nem desativado, para o módulo nunca ficar sem
modelo utilizável. Troque o padrão primeiro, depois mexa no antigo.

---

## 3. O que mudou na tela

**Cinco visões** no lugar da lista única, alternadas no topo e memorizadas entre
sessões:

- **Painel** — indicadores clicáveis (vencidas, vencem hoje, próximos 7 dias,
  minhas, concluídas no mês, tempo médio de conclusão, taxa de cumprimento de
  prazo), gráficos por status/responsável/categoria/prioridade, movimentação dos
  últimos 30 dias e a lista "precisam de atenção".
- **Cards** — como antes, mas com selo de prazo, contadores de anexos,
  comentários e subtarefas, e seleção em lote.
- **Kanban** — arrastar e soltar entre colunas de status, gravando na hora.
- **Lista** — tabela ordenável por qualquer coluna.
- **Calendário** — implementação própria, **sem CDN**.

**Modal de detalhe em 6 abas**: visão geral, anexos, checklist, andamento,
assistente de IA e histórico de alterações. Toda a barra de ações antiga
continua lá: protocolo geral, guia de recebimento, recibo de entrega, vincular
ofício, criar ofício, subtarefa, arquivar ato, editar e excluir.

**Ações em lote**: selecione vários cards e altere status, responsável ou
prioridade de uma vez. Tudo fica registrado no histórico de cada tarefa.

**Exportação CSV** dos resultados filtrados, com BOM UTF-8 e separador `;` —
abre direto no Excel em português, sem assistente de importação.

**Atalhos de teclado**: `/` foca a busca, `n` abre nova tarefa.

---

## 4. O que a IA faz

Dentro de cada tarefa:

- **Resumir** — sintetiza descrição, comentários e situação (fica em cache).
- **Próximos passos** — lista o que falta fazer, marcando o que é urgente.
- **Redigir texto** — 6 tipos (andamento, despacho, nota de exigência, e-mail,
  WhatsApp, texto de conclusão) × 3 níveis de linguagem (formal de serventia,
  simples para o cidadão, técnica registral). O texto gerado abre num editor
  antes de ir para a linha do tempo.
- **Analisar anexo** — lê PDF ou imagem e resume o conteúdo.
- **Perguntar** — chat sobre a tarefa, com a conversa preservada.
- **Sugerir checklist** — cria as etapas de conferência.

Na criação da tarefa: **Sugerir classificação** propõe categoria, origem,
prioridade, prazo e etiquetas a partir do título e da descrição.

Na busca: **Buscar com IA** interpreta linguagem natural — "escrituras atrasadas
da Maria" vira o conjunto de filtros correspondente.

Todo texto gerado vem com aviso de conferência. A IA não altera nada sozinha:
sempre há um passo de confirmação.

---

## 5. Compatibilidade — nada quebra

**Os quatro arquivos duplicados viraram atalhos de uma linha.** `index.php`,
`index_tarefa.php`, `consulta-tarefas.php` e `consulta-tarefas-sub.php` eram
quase idênticos entre si (cerca de 14 mil linhas repetidas). Agora os três
últimos apenas encaminham para a tela única. Qualquer link de outro módulo
continua funcionando, inclusive com `?token=` e `?id=`.

**Os 22 endpoints antigos foram mantidos** com exatamente o mesmo contrato de
entrada e saída: `search_tasks.php`, `view_task.php`, `save_task.php`,
`save_task_edit.php`, `save_sub_task.php`, `update_status.php`,
`delete_task.php`, `add_comment.php`, `edit_comment.php`,
`delete_attachment.php`, `delete_comment_attachment.php`,
`vincular_oficio.php`, `get_user_access.php`, `get_token.php`,
`get_sub_tasks.php`, `get_tarefa_principal.php`, `get_task_info.php`,
`load_categories.php`, `load_origins.php`, `load_employees.php`,
`db_connection.php` e `session_check.php`. Se algum outro sistema seu chama
esses endereços, continua funcionando.

**Os pares hífen/underscore foram preservados intactos.** Confirmei que
`protocolo-geral.php` / `protocolo_geral.php`, `recibo-entrega.php` /
`recibo_entrega.php`, `guia-recebimento.php` / `guia_recebimento.php` e
`ver-oficio.php` / `ver_oficio.php` **não são duplicatas**: o JavaScript lê
`../style/configuracao.json` e escolhe pelo campo `timbrado` — `S` usa o arquivo
com sublinhado (papel já timbrado), `N` usa o com hífen (o PDF desenha o
cabeçalho). Essa lógica está preservada em `assets/js/tarefas-documentos.js`.

**A regra de acesso é a mesma.** Administrador ou quem tem "Controle de Tarefas"
em `acesso_adicional` vê tudo; os demais veem as próprias (como responsável,
revisor ou autor) mais as concluídas. E a exceção histórica continua: qualquer
usuário localiza **uma** tarefa buscando pelo número exato do protocolo geral.

**Os modais antigos foram mantidos com os mesmos IDs de campo** (guia, recibo,
subtarefa, ofício, arquivamento), para o back-end e o módulo de Arquivamento
continuarem recebendo exatamente o que esperam. Só o JavaScript que os controla
foi reescrito.

**Versões originais preservadas** em `legado/*_v1.php.txt`, para consulta.

---

## 6. Correções de segurança e de bugs

Coisas que encontrei durante a refatoração e corrigi:

**Segurança**

- `search_tasks.php` concatenava os filtros direto na SQL. Agora é preparada.
- `get_user_access.php` interpolava o nome de usuário na consulta. Idem.
- Uploads não validavam extensão — era possível enviar um `.php` para dentro de
  `arquivos/` e executá-lo pelo navegador. Agora há lista de extensões
  permitidas, limite de tamanho, nome higienizado e um `.htaccess` que desliga a
  execução de PHP na pasta.
- `delete_attachment.php` montava o caminho a partir do POST e chamava `unlink()`
  direto, permitindo apagar arquivos fora da pasta. Agora o caminho é
  normalizado e conferido contra `arquivos/` antes de qualquer remoção.
- `delete_task.php` não checava permissão: qualquer usuário logado excluía
  qualquer tarefa chamando o endereço direto. Agora exige administrador.
- `edit_comment.php` não checava autoria. Agora só o autor ou um administrador
  edita.
- As ações da tela nova exigem token CSRF.

**Bugs**

- `get_task_info.php` consultava a coluna `hash_tarefa` na tabela `tarefas` —
  ela não existe ali (é da tabela `comentarios`). A consulta falhava em silêncio.
  O campo correto é `token`.
- `save_task_edit.php` imprimia o JSON de resposta **antes** de processar os
  anexos, o que às vezes produzia texto solto depois do JSON e quebrava o parse
  no navegador. Agora a resposta sai uma vez só, no fim.
- `search_tasks.php` fazia uma consulta de comentários por tarefa dentro do laço
  (N+1). Agora busca todos de uma vez.
- O FullCalendar vinha do CDN jsdelivr — as máquinas de atendimento ficavam sem
  calendário quando a internet caía. Substituído por implementação própria.
- `SHOW TABLES LIKE ?` não funciona com prepared statement nativo do
  MySQL/MariaDB; as checagens de esquema retornavam sempre falso. Trocado por
  `information_schema`.
- Filtros de `status = 'ativo'` agora são insensíveis a maiúsculas, já que os
  dados antigos têm caixa mista.

---

## 6b. Correções da revisão 2.0.1

Cinco defeitos encontrados depois da primeira entrega, todos reproduzidos e
corrigidos com teste de regressão:

**`Maximum call stack size exceeded` ao anexar arquivo.** O `<input type="file">`
ficava dentro da área clicável de upload. Clicar no input fazia o evento subir
até a área, que disparava outro clique no input, e assim indefinidamente. Agora
o handler ignora o clique originado do próprio input. Afetava as três telas com
anexo: criar tarefa, editar tarefa e a aba de anexos do modal.

**Modal sem cliques e sem rolagem, fechando a qualquer toque.** Faltava a classe
`modal-content` no modal de detalhe. O Bootstrap aplica `pointer-events: none`
em `.modal-dialog` e só devolve os cliques em `.modal-content` — sem ela, todo
clique atravessava para o fundo e acionava o fechamento pelo backdrop.

**Kanban em branco.** Causa raiz: a função de busca tinha uma trava booleana que
*descartava* a chamada nova enquanto outra estivesse no ar. Ao abrir a tela e
clicar em Kanban antes de a primeira consulta voltar, a requisição do Kanban
nunca era enviada; a resposta atrasada (de Cards) chegava depois, já com a visão
trocada, e o quadro era montado com `colunas` indefinido — daí a tela vazia.
A trava foi substituída por um número de sequência: a chamada nova sempre é
enviada e só a resposta mais recente, e da visão correta, é desenhada.

**Painel incompleto.** Os três blocos (indicadores, gráficos e prazos próximos)
eram desenhados em sequência; um erro em qualquer um deles interrompia os
seguintes e deixava a área em branco sem aviso. Agora cada bloco é isolado, e a
falha aparece na tela e no console em vez de sumir.

**Dependência de `$conn` nos modais herdados.** O arquivo
`partials/_modais_legado.php` montava quatro listas com `$conn->query()`,
contando com uma conexão mysqli global deixada por outro include. Isso derrubava
a página inteira com erro fatal em qualquer ordem de carregamento diferente.
As quatro consultas passaram a usar os helpers do módulo.

Também retirei o uso de `$.trim`, que o jQuery marcou como obsoleto na 3.5 e
removeu na 4 — o módulo não quebra se o Atlas atualizar a biblioteca.

---

## 6c. Ajustes da revisão 2.0.2

**Botões que são links apareciam sem texto.** "Nova tarefa", "Voltar",
"Configurar agora" e os botões de anexo tinham fundo e fonte da mesma cor; o
texto só surgia ao passar o mouse. Era conflito de especificidade no CSS:
`.tf-app a` (0,1,1) vencia `.tf-btn-primario` (0,1,0), então o link pintava o
texto com a cor primária — a mesma do fundo do botão. No hover valia
`.tf-btn-primario:hover` (0,2,0), e aí o branco aparecia. A regra de link passou
a excluir os botões (`a:not(.tf-btn)`) e cada variante colorida ganhou uma
seleção equivalente para links.

**Conteúdo passando por baixo do menu do sistema.** A barra de navegação fixa do
Atlas (O.S / Caixa / Início / Tarefas / Menu) cobria o que ficasse no rodapé —
no Kanban, engolia justamente a barra de rolagem horizontal. Agora existe uma
medida única, `--tf-menu-inferior` (92px), declarada no topo de
`assets/css/tarefas.css`. Dela sai a `--tf-folga-inferior`, que é respeitada
pelo container do módulo, pela altura das colunas do Kanban, pela barra
flutuante de ações em lote, pelo corpo dos modais e pela rolagem por âncora.
**Se a altura do menu mudar no Atlas, basta alterar esse único valor.**
A barra de rolagem do Kanban também ficou mais grossa, para ser mais fácil de
pegar com o mouse.

**Modal de criar subtarefa redesenhado.** Saiu do visual antigo e entrou no
padrão do módulo: cabeçalho em gradiente mostrando a qual tarefa a subtarefa
será vinculada, campos em grade, blocos separados para descrição e anexos, e
área de arrastar-soltar com lista dos arquivos escolhidos e remoção individual.
A opção de reaproveitar os anexos da tarefa principal agora desativa
visivelmente a área de upload. Todos os IDs e `name` do formulário foram
mantidos, então `save_sub_task.php` e o restante do sistema continuam recebendo
exatamente os mesmos campos. O modal foi movido para
`partials/_modal_subtarefa.php`.

---

## 6d. Correção da revisão 2.0.3

**Não dava para digitar em "Registrar andamento".** O campo abria, mas não
aceitava texto. Causa: conflito entre o modal do Bootstrap e o SweetAlert2. O
Bootstrap mantém um vigia de foco (`_enforceFocus`) que devolve o foco ao modal
sempre que ele escapa para um elemento de fora; o SweetAlert2, por padrão, se
anexa ao `<body>`, ou seja, fora do modal. A cada tecla o foco era arrancado do
campo antes de o caractere ser registrado.

A correção ancora o diálogo no modal aberto (opção `target` do SweetAlert2),
o que faz o campo passar a estar "dentro" na visão do Bootstrap. A aparência
não muda — o contêiner do SweetAlert2 continua fixo, cobrindo a tela toda.
O ajuste é central, em `Tarefas.opcoesDialogo()`, e vale para todos os diálogos
do módulo: registrar e editar andamento, redigir com IA, revisar o texto
gerado, confirmações, avisos e a lista de tarefas do dia no calendário. Quando
não há modal aberto, nada muda.

---

## 6e. Correção da revisão 2.0.4

**A barra de ações em lote aparecia sem nada selecionado, e sobre o menu.**
Ela era escondida por deslocamento — `translateY(140%)` — contando que a própria
altura a jogasse para fora da tela. Isso funcionava enquanto ela ficava colada
no rodapé; quando o `bottom` passou a reservar os 112px de folga do menu do
sistema (revisão 2.0.2), o empurrão de 64px deixou de ser suficiente e a barra
parava a 48px do rodapé — bem no meio do menu, e visível o tempo todo.

Agora ela some por `visibility` e `opacity`, o que independe da altura que a
barra tenha ou de onde esteja ancorada. Também deixou de interceptar cliques
enquanto oculta (`pointer-events: none`), o que antes podia bloquear cliques
numa faixa invisível da tela. O deslocamento ficou só para a animação de
entrada.

---

## 6f. Correção da revisão 2.0.5

**Botões coloridos sumiam ao passar o mouse.** "Redigir com IA", "Buscar com
IA", "Excluir" e os botões de remover da tela de configurações ficavam brancos
com fonte branca no hover. Esse defeito existia desde a primeira versão — não
veio dos ajustes recentes.

A causa é ordem e especificidade: `.tf-btn:hover` e `.tf-btn-ia:hover` têm o
mesmo peso (0,2,0), e o hover neutro vem antes no arquivo. Como as variantes
coloridas só redeclaravam `filter` e `color` no hover, o `background`
continuava vindo do hover neutro — fundo claro do tema com fonte branca por
cima, contraste de 1,12:1. Atingia só os `<button>`; nos `<a>` a regra de link
tinha peso maior e o texto saía escuro, legível ainda que fora do padrão.

Agora o hover neutro exclui explicitamente as variantes, e cada variante
declara o próprio fundo no hover.

**Contraste do botão principal no tema escuro.** Verificando o conserto acima,
apareceu um segundo problema: `--tf-primaria` vale `#60a5fa` no tema escuro
porque também serve de cor de texto para links, e precisa ser clara ali. Só que
ela era usada como fundo do botão principal, do cabeçalho dos modais, da página
ativa e das marcas da linha do tempo — branco sobre azul-claro, contraste de
2,54:1. Criei `--tf-primaria-solida` (`#1e40af` no claro, `#2563eb` no escuro)
para todos os preenchimentos com texto branco, deixando `--tf-primaria` só para
texto.

A verificação virou teste: `legado/testes_contraste.js.txt` resolve a cascata do
CSS por conta própria — casamento de seletor, especificidade, ordem e expansão
do atalho `background` — e mede a razão de contraste de cada variante de botão,
nos dois temas e nos dois estados, exigindo no mínimo 3:1. São 40 combinações.
Ele foi escrito assim porque o jsdom não resolve corretamente o atalho
`background` sobrescrevendo `background-image` entre regras, que é exatamente o
mecanismo do defeito.

---

## 6g. Revisão 2.0.6

**Barras do painel apareciam vazias.** `.tf-barra-preench` e `.tf-barra-trilho`
são elementos `<span>`, e em elemento inline as propriedades `width` e `height`
simplesmente não se aplicam — o preenchimento nascia com altura zero, e só o
trilho cinza aparecia. O trilho escapava do problema por ser filho direto de um
flex container, que o converte em bloco automaticamente; o preenchimento, um
nível abaixo, não tinha essa sorte. Os dois passaram a declarar
`display: block`.

**Percentual nas barras.** A largura e o número exibido são calculados agora
sobre o TOTAL do grupo, não sobre o maior item — assim o tamanho da barra e o
percentual ao lado dizem a mesma coisa. Cada linha mostra a contagem e a fatia,
o cabeçalho traz o total do grupo, e passar o mouse detalha "X de Y (Z%)".

**Guia de recebimento, recibo de entrega e arquivar ato redesenhados.** Os três
saíram do visual antigo e entraram no padrão do módulo, com cabeçalho em
gradiente, campos em grade e blocos temáticos. No arquivamento, os anexos da
tarefa viraram cartões marcáveis e a escolha de documentos novos ganhou área de
arrastar-soltar com lista e remoção individual. Todos os IDs e `name` foram
mantidos — 6, 6 e 17 respectivamente, conferidos na página renderizada — então
`save_guia_recebimento.php`, `save_recibo_entrega.php` e
`../arquivamento/save_ato.php` continuam recebendo exatamente os mesmos campos.
Os três foram para `partials/_modais_documentos.php`.

Nota preservada da versão original: os campos de observação da guia e do recibo
compartilham o mesmo id (`observacoes`). Como isso está gravado no
comportamento dos endpoints, o id foi mantido e o JavaScript continua lendo
esses campos sempre com o formulário no seletor.

---

## 6h. Correção da revisão 2.0.7

**Guia, recibo e arquivamento não abriam.** Ao mover esses três modais para o
arquivo novo na revisão 2.0.6, o include foi parar dentro do modal de histórico
de guias. Um modal aninhado dentro de outro nunca aparece: enquanto o externo
está fechado ele fica com `display: none` e leva os filhos junto. O include
voltou para o nível raiz do arquivo.

**Modais empilhados.** Guia, recibo, subtarefa e arquivamento são abertos de
dentro do modal de detalhe — um modal por cima do outro, situação que o
Bootstrap não gerencia sozinho. Agora cada modal aberto recebe um z-index acima
do anterior, com o fundo escurecido logo abaixo dele, e o `modal-open` é
devolvido ao `<body>` enquanto ainda houver algum modal aberto, para o de baixo
não perder a rolagem quando o de cima fecha.

O teste `legado/testes_modais.js.txt` passou a conferir, na página renderizada,
que nenhum modal está aninhado em outro e que todos têm a estrutura mínima que o
Bootstrap exige, além do empilhamento.

---

## 6i. Ajuste da revisão 2.0.8

**Modais maiores.** O de detalhe passou de 1140px (teto do `modal-xl` do
Bootstrap) para `min(1560px, 95vw)`, e os demais para `min(1040px, 95vw)`.

Na altura a mudança foi estrutural, não um número maior. O corpo do modal tinha
altura calculada na mão, subtraindo uma estimativa fixa para cabeçalho, barra de
ações e abas — estimativa conservadora, que sempre deixava espaço sobrando. Agora
o conteúdo do modal é uma coluna flexível com teto em `100vh` menos a folga do
menu: as partes fixas ficam com a altura que precisarem e o corpo estica no que
sobrar. Numa tela de 1080px, a área útil passou de 708 para 770 pixels, e se
ajusta sozinha caso a barra de ações ganhe ou perca botões.

Detalhe que exigiu atenção: nos modais de guia, recibo e arquivamento o `<form>`
envolve o corpo e o rodapé. Sem repassar a flexibilidade a ele, o corpo não
teria como esticar nem rolar e o conteúdo seria cortado pelo `overflow: hidden`
do contêiner.

No celular os modais passam a ocupar praticamente a tela inteira, com meio rem
de respiro nas laterais.

---

## 6j. Correção da revisão 2.0.9

**O botão "Filtros" não abria nada.** O JavaScript procurava `#tfFiltros` e o
elemento chama-se `#tfFormFiltros`. O seletor não casava com nada, e o clique
simplesmente não fazia efeito. O mesmo id errado estava na busca com IA, que
deveria abrir o painel ao aplicar os filtros interpretados.

Havia um segundo defeito escondido atrás do primeiro: o painel era aberto com
`slideToggle()`, e a animação do jQuery termina devolvendo `display: block` ao
elemento — o que desmontaria a grade de colunas dos campos. Agora a abertura é
por classe, preservando o `display: grid`, com a entrada animada por opacidade e
deslocamento. Deliberadamente não usei transição de altura: em telas estreitas
os campos empilham e um teto de altura cortaria parte do formulário.

O teste `legado/testes_filtros.js.txt` confere o comportamento do painel e, além
disso, varre os quatro arquivos JS coletando todo `#id` do módulo que eles usam
e conferindo se existe na página renderizada — 65 ids hoje. É essa varredura que
pega a classe de erro que passou despercebida aqui.

---

## 7. Estrutura da pasta

```
tarefas/
├── core/                    núcleo compartilhado
│   ├── config.php           credenciais, catálogo de status e prioridades
│   ├── bootstrap.php        conexão, sessão, CSRF, resposta JSON
│   ├── helpers.php          datas, anexos, uploads, histórico, visibilidade
│   └── gemini.php           integração e catálogo de modelos
├── api/                     endpoints da tela nova
│   ├── tarefas.php          consulta (cards, kanban, calendário, lista)
│   ├── tarefa.php           detalhe completo
│   ├── acoes.php            todas as ações de escrita
│   ├── dashboard.php        indicadores do painel
│   ├── ia.php               recursos de IA
│   ├── modelos.php          catálogo de modelos
│   └── exportar.php         CSV
├── assets/
│   ├── css/tarefas.css
│   └── js/                  core, calendário, detalhe, documentos
├── partials/
│   ├── _modais_legado.php   modais herdados, IDs preservados
│   ├── _modal_subtarefa.php  modal de subtarefa no padrão v2
│   └── _modais_documentos.php  guia, recibo e arquivamento no padrão v2
├── legado/                  versões originais, para consulta
├── arquivos/                anexos (não vem no ZIP)
├── index.php                tela principal
├── criar-tarefa.php
├── edit_task.php
├── categorias.php
├── configuracoes-ia.php
├── migracao_v2.php
└── [22 endpoints de compatibilidade]
```

---

## 8. Testes executados

O módulo foi testado contra um MariaDB 10.11 real, com o esquema das 11 tabelas
e dados representativos (tarefas vencidas, no prazo, concluída, sem responsável,
subtarefa, comentário com anexo, status legado fora do catálogo).

**103 verificações no back-end e 19 de regressão no front-end, todas passando.** Cobrem: migração e sua idempotência,
preservação do acervo, visibilidade por perfil (admin, acesso adicional, usuário
comum), a exceção do protocolo exato, todos os filtros, as cinco visões,
paginação e ordenação, tentativa de injeção de SQL, CSRF, permissões de edição e
exclusão, ações em lote, checklist, histórico, trava do modelo padrão,
tratamento de erro da API sem chave, os 11 endpoints legados e a exportação CSV.

As verificações de front-end rodam o JavaScript real num DOM completo (jsdom),
com as respostas verdadeiras das APIs, e cobrem os cinco defeitos da revisão
2.0.1 — inclusive reproduzindo o estouro de pilha e a corrida entre requisições
para confirmar que os cenários antigos de fato falhavam.

---

## 9. Solução de problemas

**"Migração pendente" continua aparecendo na tela**
A migração não chegou a rodar. Abra `migracao_v2.php` logado como administrador
e confira se o painel mostra 0 erros.

**Erro 404 ao usar o Gemini 3.1 Pro**
O identificador correto é `gemini-3.1-pro-preview`. Use Sincronizar modelos.

**"O servidor devolveu uma página de erro em vez de dados"**
É um erro de PHP dentro do endpoint. Veja `C:\xampp\apache\logs\error.log` —
todas as falhas do módulo são registradas com o prefixo `[tarefas]`.

**Anexos antigos aparecem como "arquivo não encontrado"**
O caminho está no banco mas o arquivo não está em `arquivos/`. O módulo
normaliza os três formatos históricos de caminho (`arquivos/x.pdf`,
`arquivos\x.pdf` e `x.pdf`), então provavelmente o arquivo foi mesmo removido do
disco em algum momento.

**A tela abre sem as tarefas concluídas**
É o comportamento de origem: sem filtro, a lista mostra só a fila de trabalho.
Filtre por status "Concluída" ou use o Kanban, que traz todas as colunas.
