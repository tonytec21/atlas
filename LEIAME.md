# GuiaOS — guia interativo do módulo O.S. (Atlas)

Tour guiado que roda **dentro do próprio sistema**: escurece a tela, destaca o campo/botão
da vez com um anel pulsante, explica o que fazer e avança passo a passo — inclusive
esperando o usuário clicar de verdade em “Buscar Ato” e “Adicionar à OS”.

- JavaScript puro, **sem dependências** e **sem CDN** (usa SweetAlert2 apenas se já estiver na página).
- **Nenhuma alteração no HTML atual**: os passos apontam para IDs e atributos que já existem
  (`#cliente`, `#ato`, `#btnSalvarOS`, `button[onclick*="buscarAto"]` etc.).
- Acompanha o tema claro/escuro do sistema (`body.dark-mode`) e o menu inferior.

---

## 1. Instalação (2 minutos)

1. Copie a pasta `guia/` para dentro do módulo O.S.:

   ```
   atlas/os/guia/guia-os.css
   atlas/os/guia/guia-os.js
   atlas/os/guia/guia-os-passos.js
   atlas/os/guia/guia.php
   atlas/os/guia/demo/demo-criar-os.html
   ```

2. Acrescente a linha abaixo logo antes de `</body>` (depois dos demais `<script>`).

   Nas telas do módulo — `os/index.php`, `os/criar_os.php`, `os/editar_os.php`,
   `os/modelos_orcamento.php`, `os/tabela_de_emolumentos.php`, `os/visualizar_os.php`
   e `os/assinar-os.php`:

   ```php
   <?php include(__DIR__ . '/guia/guia.php'); ?>
   ```

   Em `atlas/liberar_os.php`, que fica um nível acima:

   ```php
   <?php include(__DIR__ . '/os/guia/guia.php'); ?>
   ```

   Nos módulos **Atlas Signum** (`atlas/signum/index.php` e `configurar.php`),
   **Atlas Forja** (`atlas/forja/index.php` e `configurar.php`) e
   **Fluxo de Caixa** (`atlas/caixa/index.php`):

   ```php
   <?php include(__DIR__ . '/../os/guia/guia.php'); ?>
   ```

   A URL dos arquivos estáticos é calculada a partir do `DOCUMENT_ROOT`, então o include
   funciona de qualquer pasta. Se precisar forçar um caminho, defina `$guiaBaseUrl` antes
   do include (ex.: `$guiaBaseUrl = '/atlas/os/guia';`).

Pronto. Não é preciso reiniciar o Apache (são arquivos estáticos; o `?v=` usa o `filemtime`
para furar o cache do navegador a cada atualização).

### Testar antes de instalar

Abra `guia/demo/demo-criar-os.html` direto no navegador: é uma cópia simplificada da tela
`criar_os.php`, com os mesmos IDs, só para ver o guia funcionando.

---

## 2. Como o usuário usa

| Situação | O que acontece |
|---|---|
| Primeira vez que abre `criar_os.php`, `editar_os.php`, `modelos_orcamento.php`, `liberar_os.php`, `assinar-os.php`, `signum/index.php` ou `signum/configurar.php` | O guia daquela tela inicia sozinho, após 0,7 s |
| `tabela_de_emolumentos.php` | Não abre sozinho (a tela costuma ser aberta em outra aba, no meio de um atendimento) — só pelo botão “?” |
| Depois disso | Botão flutuante **“?”**, no canto inferior direito (acima do menu) |
| No meio do guia | `→` / `Enter` avança, `←` volta, `Esc` sai |
| Passos “Buscar Ato” e “Adicionar à OS” | O botão *Próximo* fica apagado: o guia só avança quando o usuário clica de verdade no botão do sistema |
| Passo final da tela de pesquisa | Ao clicar em *Criar Ordem de Serviço*, o guia **continua sozinho** na tela seguinte |
| Abrir qualquer janela do **Fluxo de Caixa** | O guia troca para o daquela janela (detalhes, saída, depósito, comprovante, depósitos unificados, abertura) e volta ao guia do módulo ao fechar |
| Trocar de aba na **Forja** | O botão “?” passa a oferecer o guia daquela ferramenta (“Como comprimir PDF”, “Como dividir um PDF”…). Se o usuário já estiver dentro de um guia da Forja, ele **troca junto** |
| Abrir as janelas **Pagamentos** ou **Anexos** na tela da O.S. | O guia **troca** para o daquela janela e o botão “?” passa a exibir **“Como adicionar pagamento”** / **“Como adicionar anexo”**; ao fechar, o guia anterior é retomado no mesmo passo e o botão volta a **“O que fazer com esta O.S.”** |
| Ao sair pelo *Sair do guia* | Não abre mais automaticamente, mas continua disponível no botão “?” |

> **A página continua totalmente utilizável com o guia aberto**: o usuário pode digitar nos
> campos, clicar em botões e rolar a tela normalmente — o guia é apenas visual. As setas
> `←`/`→` só navegam pelos passos quando o foco **não** está em um campo de digitação.

Guias registrados:

| Guia | Tela | Conteúdo |
|---|---|---|
| `pesquisa-os` | `index.php` | filtros, resultados, botões de ação e caminho para criar a O.S. |
| `criar-os` | `criar_os.php` | passo a passo completo de criação da Ordem de Serviço |
| `editar-os` | `editar_os.php` | alterar quantidade, isentar, remover e incluir atos numa O.S. gravada |
| `modelos-os` | `modelos_orcamento.php` | como montar, salvar, editar e excluir modelos de O.S. |
| `os-criada` | `visualizar_os.php` | o que fazer com a O.S. já gravada (imprimir, pagar, liquidar) |
| `pagamento-os` | janela “Efetuar Pagamento” | forma, valor, comprovante, isenção, devolução e repasse |
| `anexo-os` | janela “Anexos” | escolher arquivos, enviar e conferir os documentos da O.S. |
| `assinar-os` | `assinar-os.php` | assinatura ICP-Brasil pelo Assinador SERPRO — **serve para a O.S. e para o Recibo A4** |
| `autorizar-assinador` | janela “Liberar o Assinador SERPRO” | permissão do token, reconexão automática e o que fazer se falhar (vale para `assinar-os.php` **e** para o Signum) |
| `signum` | `signum/index.php` | anexar o PDF, posicionar o carimbo, autenticar o token, assinar e consultar os assinados |
| `signum-config` | `signum/configurar.php` | método A3/A1, teste e autorização do Assinador, certificado .pfx, logomarca, dados do assinante e textos do carimbo |
| `forja` | `forja/index.php` | visão geral do módulo e das oito ferramentas |
| `forja-comprimir` | aba Comprimir PDF | quatro níveis (300/200/150/120 dpi), seletor de cores, dica contextual, selos do resultado e prévia comparativa |
| `forja-pdf2img`, `forja-img2pdf`, `forja-juntar`, `forja-multiplo`, `forja-dividir`, `forja-word2pdf`, `forja-pdf2word` | abas da Forja | um guia por ferramenta |
| `caixa` | `caixa/index.php` | filtros, modo de visualização, cards e ações de cada caixa |
| `caixa-detalhes`, `caixa-saida`, `caixa-deposito`, `caixa-anexar`, `caixa-depositos`, `caixa-abrir` | janelas do fluxo de caixa | um guia por modal: detalhes, saídas, depósito/fechamento, comprovante, depósitos unificados e abertura |
| `forja-config` | `forja/configurar.php` | caminhos do Ghostscript/ImageMagick/LibreOffice, ativação e instalação portátil |
| `tabela-emolumentos` | `tabela_de_emolumentos.php` | filtros, leitura dos valores, exportação e impressão |
| `liberar-os` | `liberar_os.php` | desfazer as liquidações do dia, com a regra de bloqueio e o log |

---

## 3. Personalização

### Editar textos
Mexa apenas em **`guia-os-passos.js`**. Cada passo é um objeto:

```js
{
    alvo: '#cliente',                 // seletor CSS, ou função que devolve o elemento
    subir: '.form-row',               // (opcional) sobe até esse ancestral para destacar o grupo
    titulo: 'Apresentante',
    texto: 'Aceita <b>HTML</b>, <code>código</code> e listas.',
    posicao: 'baixo',                 // baixo | topo | esq | dir | auto (padrão)
    folga: 8,                         // (opcional) respiro do anel em px
    opcional: true,                   // se o elemento não existir, o passo é pulado
    aguardar: 3000,                   // espera o elemento aparecer (ms) — útil para conteúdo dinâmico
    quando: function () { return true; },      // condição para exibir o passo
    aoEntrar: function (el, guia) {},          // callback ao abrir o passo
    aoSair:   function (el, guia) {},          // callback ao sair
    avancarEm: { evento: 'click', atraso: 700, dica: 'Clique no botão destacado.' },
    irPara: 'criar_os.php',                    // navega para outra tela...
    retomar: { guia: 'criar-os', indice: 0 }   // ...e retoma o guia por lá
}
```

### Criar um guia para outra tela
```js
GuiaOS.registrar('meu-guia', [ /* passos */ ], {
    rotuloAjuda: 'Guia desta tela',
    botaoAjuda: true,                 // false para não criar o botão flutuante
    bloquearFundo: false,             // true = só o elemento destacado aceita cliques
    mensagemFinal: 'Concluído!',
    aoConcluir: function () {}        // substitui a mensagem final
});
GuiaOS.autoIniciar('meu-guia');       // abre sozinho na primeira visita
```

### Reagir à abertura de um modal
```js
GuiaOS.aoAbrirModal('#pagamentoModal',
    function () { GuiaOS.iniciar('pagamento-os', { reiniciar: true }); },
    function () { GuiaOS.parar(); });
```
Usa os eventos `shown/hidden.bs.modal` do jQuery quando disponíveis e, na falta deles,
um `MutationObserver` na classe do elemento (compatível com Bootstrap 4 `.show` e 3 `.in`).
Combine com `GuiaOS.emExecucao()` para guardar o guia atual e retomá-lo depois.

### Trocar o guia oferecido pelo botão “?”
```js
GuiaOS.definirBotaoAjuda('pagamento-os', 'Como adicionar pagamento');
GuiaOS.botaoAjudaAtual();             // nome do guia que o botão abre no momento
```
Existe **um único** botão flutuante por tela: ele é reaproveitado e apenas troca de rótulo
e de destino (com um realce discreto na troca). Guias auxiliares devem ser registrados com
`botaoAjuda: false` para não criar um segundo botão.

### Abrir o guia a partir de um botão seu
```html
<button type="button" data-guia="criar-os">Ver o passo a passo</button>
```

### API
```js
GuiaOS.iniciar('criar-os', { reiniciar: true });
GuiaOS.proximo();  GuiaOS.anterior();  GuiaOS.parar();
GuiaOS.emExecucao();                  // {nome, indice} do guia em andamento, ou null
GuiaOS.jaConcluido('criar-os');       // true/false
GuiaOS.esquecer('criar-os');          // volta a abrir automaticamente
GuiaOS.esquecerTudo();                // limpa o histórico de todos os guias
```

O histórico fica em `localStorage`, com o prefixo `atlasGuiaOS.` (por navegador/usuário).
Para forçar todo mundo a rever o guia depois de uma mudança grande, altere a constante
`VERSAO` no topo de `guia-os.js`.

---

## 3b. Autorização do Assinador (`assinador-autorizar.js`)

Nas telas `assinar-os.php` (módulo O.S.) e `signum/index.php` (Atlas Signum), o link
**Autorizar** abria `http://127.0.0.1:65056` em outra aba e deixava o usuário perdido. O módulo
(carregado pelo mesmo `guia.php`, e que se desativa sozinho onde não houver o link) troca esse
link por um botão que resolve tudo em uma janela do próprio sistema.

O módulo se adapta à marcação de cada tela:

| Tela | Indicador de estado | Botão que refaz a verificação |
|---|---|---|
| `assinar-os.php` | `#serproChip` (`on`/`off`) | `#btnReconnect` |
| `signum/index.php` | `#topChip`, `#sAstat` (`on`/`off`) | `#btnReconnect` |
| `signum/configurar.php` | `#cfgAssBadge` (`cert-ok`/`cert-err`) | `#cfgTestar` |

Para uma tela nova com outra marcação:

```js
window.AUTORIZAR_ASSINADOR = {
    seletoresEstado: ['#meuIndicador'],
    seletoresReconectar: ['#meuBotaoTestar']
};
```

### Por que são duas etapas

O Assinador conversa por `wss://127.0.0.1:65156`, com um **certificado local** que o navegador
não conhece. Enquanto esse certificado não for aceito uma vez, em uma **aba normal**, nada
funciona — e o aviso de segurança do Chrome **nunca aparece dentro de um iframe** (era isso que
produzia a tela “a página pode estar temporariamente indisponível”).

| Etapa | O que o módulo faz |
|---|---|
| **1 — Certificado** | Sonda `https://127.0.0.1:65156` com um `fetch`. Se falhar, mostra o painel “liberar o certificado”, com um botão que abre uma **janelinha (popup)** — não uma guia nova — já no endereço certo, com a instrução exata (*Avançado → Ir para 127.0.0.1 (não seguro)*). Enquanto ela está aberta, o módulo sonda a cada 1,5 s e, ao detectar a liberação, **fecha a janelinha sozinho** e passa à etapa 2. |
| **2 — Autorização** | Com o certificado aceito, embute a página de autorização em um **iframe recortado** (altura fixa, leve zoom, deslocamento e degradês nas bordas), de modo que apareça só o botão de autorizar. |
| **Final** | Tenta reconectar a cada 2 s (acionando o *Reconectar* da própria página), confere 0,9 s após cada tentativa e, ao detectar a conexão, mostra “Assinador conectado!” e **fecha a janela sozinha**. |

Se em 2 minutos nada acontecer, oferece **“Já autorizei — reconectar”** e **“Abrir em outra aba”**.

### Ajustes

A URL de autorização é lida do **próprio link da página**, então acompanha a instalação. O resto
pode ser configurado antes do script:

```js
window.AUTORIZAR_ASSINADOR = {
    url: 'http://127.0.0.1:65056/',            // autorização (padrão: link da página)
    urlCertificado: 'https://127.0.0.1:65156/',// origem do certificado a liberar
    altura: 300,     // altura visível da janelinha, em px
    deslocY: -86,    // sobe a página embutida (corta o cabeçalho)
    zoom: 1.08       // ampliação do conteúdo
};
```

Os números do recorte podem precisar de acerto fino conforme a versão do Assinador instalada.

### Por que a etapa 1 não acontece dentro do modal

Não é limitação do código: **nenhuma página consegue aceitar um certificado por conta própria**.
A tela “Sua conexão não é particular → Avançado → Ir para 127.0.0.1” é do próprio navegador, e o
Chrome se recusa a exibi-la dentro de iframes exatamente para impedir que um site induza o
usuário a confiar num certificado. Não existe API para isso. O mais próximo que o navegador
permite é a janelinha popup — que o módulo abre já no endereço certo e fecha sozinha.

### Como eliminar a etapa 1 de vez (recomendado)

Rode **uma vez por máquina**, como administrador e com o Assinador SERPRO aberto:

```
os/guia/certificado/instalar-certificado-assinador.bat
```

Ele lê o certificado direto do Assinador (`127.0.0.1:65156`), monta a cadeia e instala em
*Autoridades de Certificação Raiz Confiáveis* da máquina. Depois disso o Chrome/Edge confiam no
endereço, o aviso nunca mais aparece e a janela do sistema vai **direto para a autorização** —
o clique único que se espera. Em rede com domínio, o mesmo certificado pode ser distribuído por
GPO e a etapa 1 desaparece para todos os balcões de uma vez.

Vale saber: se o certificado do Assinador não tiver *SAN* (Subject Alternative Name) para
`127.0.0.1`, o Chrome o rejeita mesmo instalado — nesse caso só resta a exceção pela janelinha.
Dá para conferir rapidamente: instale, reabra o navegador e acesse `https://127.0.0.1:65156`.

**Observação:** com o Atlas servido por **HTTPS**, o navegador bloqueia o iframe de
`http://127.0.0.1`; nesse caso o módulo permanece na etapa 1, abrindo a autorização em outra
aba — a reconexão automática continua funcionando.

## 4. Detalhes técnicos

- **Recorte da tela**: quatro painéis escuros ao redor do elemento destacado. Os painéis usam
  `pointer-events: none`, ou seja, **não interceptam cliques**: o usuário preenche o formulário
  normalmente enquanto o guia acompanha. Para o comportamento estrito (só o destaque clicável),
  registre o guia com `bloquearFundo: true`.
- **Teclado sem interferência**: as setas só navegam pelos passos quando o foco não está em
  `input`, `textarea`, `select` ou área editável; e nada é interceptado enquanto houver um
  diálogo do SweetAlert2 ou um modal do Bootstrap aberto.
- **Foco**: o guia nunca tira o foco de um campo que está sendo preenchido.
- **SweetAlert2 acima do guia**: como o overlay usa `z-index: 20000`, os diálogos do Swal são
  elevados para `30000` (via `didOpen`/`onOpen` e por CSS em `body.guia-os-aberto`), senão a
  confirmação apareceria atrás e não receberia cliques.
- **Reposicionamento contínuo** via `requestAnimationFrame`: acompanha rolagem, redimensionamento
  e mudanças de layout (tabela de itens crescendo, por exemplo).
- **Rolagem automática que reserva espaço para o balão**: ao trocar de passo, o guia mede a
  altura real do balão e rola a página para que **o campo destacado e o balão caibam juntos**
  na área entre o cabeçalho fixo (`#system-name`) e o menu inferior (`.bottom-nav`) — as alturas
  são lidas do próprio DOM. Se os dois não couberem, o campo destacado tem prioridade e fica
  logo abaixo do cabeçalho.
- **O balão nunca cobre o elemento destacado**: as quatro posições (abaixo, acima, direita,
  esquerda) são avaliadas a cada quadro; escolhe-se a primeira que caiba na área útil **com
  sobreposição zero** sobre o destaque. Não havendo nenhuma perfeita, vence a de menor
  sobreposição — e a setinha some, para não apontar para o lugar errado. Uma histerese evita
  que o balão fique alternando de lado durante a rolagem.
- **Responsivo**: abaixo de 760 px o balão vira uma folha fixa na parte inferior, acima do
  `.bottom-nav` (a altura do menu é lida do próprio DOM).
- **Acessibilidade**: navegação por teclado, `aria-live` no container e foco no botão principal.
- **Impressão**: o guia é ocultado em `@media print`.
- Não usa `localStorage` para nada além do histórico de conclusão, e nunca bloqueia a página
  se um seletor não existir (passos marcados como `opcional` são pulados).

## 5. Testes automatizados

Os arquivos `teste_guia.js` e `teste_guia2.js` (fora da pasta do módulo) rodam com Node + jsdom
e verificam: criação do botão de ajuda, sequência dos passos, avanço por clique real, navegação
por teclado, gravação da conclusão, retomada do guia entre telas e limpeza do histórico.

```bash
npm install jsdom
node teste_guia.js    # fluxo dos passos, clique real, teclado, conclusão
node teste_guia2.js   # navegação entre telas e retomada do guia
node teste_guia3.js   # interação com a página, foco, teclado em campos e SweetAlert2
node teste_guia4.js   # rolagem automática e balão sem cobrir o elemento destacado
node teste_guia5.js   # guia de Modelos de O.S. (detecção da tela, cliques reais, passo final)
node teste_guia6.js   # guia de Edição de O.S. (regras de liquidação, isenção e remoção)
node teste_guia7.js   # guias da Tabela de Emolumentos e do Desfazer Liquidações
node teste_guia8.js   # guia de Pagamentos: troca ao abrir o modal e retomada ao fechar
node teste_guia9.js   # guia de Anexos e convivência entre as duas janelas
node teste_guia10.js  # guia de assinatura nos dois estados (a assinar / já assinado)
node teste_guia11.js  # janela de autorização: iframe, reconexão automática e troca de guia
node teste_guia12.js  # módulo Signum: guia da tela, configurações e autorização do Assinador
node teste_guia13.js  # módulo Forja: guia geral, um guia por ferramenta e troca ao mudar de aba
node teste_guia14.js  # Fluxo de Caixa: guia da tela e a troca em cada uma das seis janelas
```
