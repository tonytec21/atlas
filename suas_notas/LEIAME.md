# Anotações (Atlas) — versão modernizada

Reformulação do módulo de anotações com:

## Aparência
- **Page-hero** no padrão dos demais módulos (ícone + título "Anotações" + subtítulo).
- **Post-its realistas e elegantes**: papel com textura sutil, fita adesiva no topo,
  leve rotação (endireita ao passar o mouse), sombras em camadas, orelha dobrada,
  título em fonte manuscrita (Caveat) e corpo em Kalam. Grade **masonry responsiva**.
- Tema claro/escuro acompanha o `body.dark-mode` do sistema.
- Busca instantânea e abas.

## Recursos
- **Criar / editar** anotação (modal), com **9 cores** de post-it.
- **Compartilhar** com um usuário específico (opção "pode editar"), e **descompartilhar**.
- Aba **"Compartilhadas"**: mostra as anotações que outros usuários compartilharam
  com você (identificando quem compartilhou). O dono controla cor/compartilhamento;
  o destinatário pode ver (e editar, se autorizado) e "remover da minha lista".
- Visualização em modal, exclusão (vai para a lixeira, como antes).

## Armazenamento
- As **notas continuam em arquivos** `lembretes/{usuario}/{id}.txt` (+ `{id}.json`
  para a cor) — as anotações que você já tem **aparecem automaticamente**.
- O **compartilhamento** é registrado na tabela `notas_compartilhadas` (criada
  automaticamente). Cada linha liga dono + nota + destinatário.

## Arquivos
`index.php` (novo), `helpers.php` (núcleo), e os endpoints
`nota_criar.php`, `nota_salvar.php`, `nota_cor.php`, `nota_excluir.php`,
`nota_ler.php`, `nota_usuarios.php`, `nota_compartilhar.php`,
`nota_descompartilhar.php`, `nota_compartilhamentos.php`.
Mantidos: `db_connection.php`, `session_check.php`.
Os arquivos antigos (criar_lembrete.php, save_note.php, etc.) podem ser removidos.

## Requisito
O seletor de usuários usa a tabela `funcionarios` (coluna `usuario` e `nome`).

## Atualização — categorias, texto formatado e editor

- **Categorias + arrastar**: chips de categoria na aba "Minhas". Clique num chip para
  filtrar; **arraste um post-it até um chip** para movê-lo para aquela categoria (ou
  até "Todas" para remover a categoria). Reordene arrastando os cards entre si.
  Criar/renomear/excluir categorias pelos próprios chips (excluir não apaga notas).
  A organização fica em `lembretes/{usuario}/_org.json`.
- **Texto formatado**: o editor tem **negrito, itálico e sublinhado** (Ctrl+B/I/U também).
  O conteúdo é salvo como HTML **sanitizado** (só b/i/u/br; scripts e atributos são removidos).
- **Fonte do editor**: agora é a fonte padrão (Inter), legível. Os cards mantêm o
  visual manuscrito (título em Caveat), mas o negrito/itálico/sublinhado aparecem.
- Requer **SortableJS** (carregado via CDN) para o arrastar-e-soltar.

Novos endpoints: `org_salvar.php`, `cat_criar.php`, `cat_renomear.php`, `cat_excluir.php`.

## Migração automática das categorias antigas

Na primeira vez que o módulo abre, as categorias da versão antiga (os "grupos"
salvos em `lembretes/{usuario}/order.json`) são convertidas automaticamente para
o novo formato (`_org.json`): cada grupo vira uma categoria e as notas mantêm sua
associação; o grupo "Novos" (padrão) vira "sem categoria". O `order.json` é
preservado e a migração roda só uma vez (não sobrescreve organização nova).
