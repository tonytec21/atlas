# API do módulo O.S. — Atlas

**Versão 1.0.0** · Documento para o desenvolvedor do sistema integrador.

Esta API expõe as Ordens de Serviço do cartório para sistemas externos — tipicamente o
sistema que lavra os atos e gera os selos. O fluxo que ela atende é este:

```
  consultar a O.S. pelo número
        ↓
  ver os atos disponíveis para selagem
        ↓
  conferir se há saldo pago que cubra o ato
        ↓
  [o sistema lavra o ato e gera o selo]
        ↓
  liquidar o ato na O.S., informando o selo
```

A regra que sustenta tudo: **ato só é liquidado se houver saldo pago que o cubra.**
Sem isso, a serventia prestaria o serviço sem receber.

---

## 1. Endereço base

```
https://SEU-SERVIDOR/os/api/v1
```

Sem `mod_rewrite`, use uma destas formas — funcionam igual:

```
https://SEU-SERVIDOR/os/api/index.php/v1/...
https://SEU-SERVIDOR/os/api/index.php?rota=/v1/...
```

Um `GET` na raiz (`/os/api/v1`) devolve a lista de rotas, sem exigir token.

---

## 2. Autenticação

A serventia cadastra o sistema em **O.S. → API → Sistemas integradores** e entrega dois dados:

| Dado | Para quê |
|---|---|
| `client_id` | identifica o sistema (público, aparece nos logs) |
| `token` | segredo, enviado a cada chamada |

O token vai no cabeçalho:

```http
Authorization: Bearer sk_prd_a1b2c3d4...
```

Se o cliente não conseguir enviar `Authorization`, aceita-se `X-Api-Token: <token>`.

> O token é exibido **uma única vez**, na hora em que é gerado. No banco fica apenas o
> SHA-256. Perdido, gera-se outro — e o anterior morre na hora.

### Ciclo de homologação

Todo cadastro nasce **pendente**. Enquanto estiver assim, o token existe mas nenhuma chamada
é aceita:

```json
{"sucesso":false,"erro":{"codigo":"sistema_pendente",
 "mensagem":"O sistema \"X\" está cadastrado, mas ainda não foi homologado."}}
```

A serventia homologa pela tela e o acesso é liberado. Pode suspender depois, sem apagar o
cadastro nem o histórico.

### Homologação × Produção

O prefixo do token indica o ambiente a olho nu: `sk_hml_` ou `sk_prd_`.

| Ambiente | Alcance |
|---|---|
| `homologacao` | **só** as O.S. que o próprio sistema criou pela API em homologação |
| `producao` | o acervo real da serventia |

Isso é o que torna a homologação segura: o parceiro testa o fluxo inteiro — criar O.S.,
pagar, liquidar — sem chance de encostar em uma O.S. real. Uma credencial de homologação que
tentar ler a O.S. 672 recebe:

```json
{"sucesso":false,"erro":{"codigo":"ambiente_incompativel","os":672,
 "mensagem":"Esta credencial é de HOMOLOGAÇÃO e só pode operar O.S. criadas por ela..."}}
```

Ao promover para produção, um token novo é emitido e o de homologação para de funcionar.

> **Atenção:** as O.S. criadas em homologação são linhas reais no banco e aparecem na tela do
> módulo. Depois dos testes, cancele-as pelo sistema para não poluir os relatórios.

### Escopos

A serventia liga ou desliga cada um por sistema:

| Escopo | Permite |
|---|---|
| `os:ler` | consultar O.S., atos, saldo, liquidações e pagamentos |
| `os:criar` | criar Ordens de Serviço |
| `pagamento:criar` | lançar pagamentos |
| `ato:liquidar` | liquidar atos |

Chamada fora do escopo devolve `403 escopo_insuficiente`, dizendo qual escopo falta.

---

## 3. Formato das respostas

Sucesso:

```json
{ "sucesso": true, "dados": { ... } }
```

Erro:

```json
{ "sucesso": false, "erro": { "codigo": "saldo_insuficiente", "mensagem": "...", "falta": 34.00 } }
```

**Programe contra o `codigo`, nunca contra o texto da `mensagem`** — o texto pode ser
reescrito, o código é estável.

### Códigos de erro

| Código | HTTP | Significado |
|---|---|---|
| `nao_autenticado` | 401 | token ausente |
| `token_invalido` | 401 | token não reconhecido |
| `sistema_pendente` | 403 | cadastro ainda não homologado |
| `sistema_suspenso` | 403 | acesso suspenso pela serventia |
| `ip_nao_autorizado` | 403 | IP fora da lista configurada |
| `escopo_insuficiente` | 403 | falta o escopo para a operação |
| `ambiente_incompativel` | 403 | credencial de homologação em O.S. real |
| `os_nao_encontrada` | 404 | número de O.S. inexistente |
| `item_nao_encontrado` | 404 | o item não pertence a essa O.S. |
| `ato_nao_encontrado` | 404/422 | ato fora da tabela de emolumentos |
| `rota_nao_encontrada` | 404 | rota inexistente |
| `metodo_invalido` | 405 | verbo HTTP errado |
| **`saldo_insuficiente`** | **409** | **não há saldo pago para liquidar o ato** |
| `quantidade_indisponivel` | 409 | ato já liquidado, ou quantidade além da disponível |
| `os_cancelada` | 409 | a O.S. está cancelada |
| `os_ocupada` | 409 | outra liquidação em andamento; repita em instantes |
| `idempotencia_conflitante` | 409 | mesma chave com conteúdo diferente |
| `campo_obrigatorio` | 422 | falta um campo |
| `quantidade_invalida` | 422 | quantidade menor que 1 |
| `valor_invalido` | 422 | valor de pagamento ≤ 0 |
| `forma_pagamento_invalida` | 422 | forma fora da lista aceita |
| `erro_banco` / `erro_interno` | 500 | falha no servidor |

---

## 4. Idempotência

Para `POST`, envie:

```http
Idempotency-Key: selagem-2026-000123
```

Repetir a mesma chave **não executa nada de novo** — devolve a resposta original, com o
cabeçalho `X-Atlas-Idempotencia: repetida`.

Isto é essencial na liquidação. O caso clássico: a liquidação é gravada, a resposta se perde
na rede, o cliente reenvia. Sem a chave, o ato é liquidado duas vezes e o saldo do cliente é
consumido em dobro. Use uma chave própria por operação — o número do protocolo de selagem
serve bem.

Chamadas que falharam **não** são guardadas: uma tentativa recusada por falta de saldo pode e
deve ser repetida depois que o cliente pagar.

---

## 5. Rotas

### `GET /v1/ping`

Confere a credencial. Bom para o parceiro validar a configuração antes de tudo.

```json
{"sucesso":true,"dados":{
  "pong":true,"sistema":"Sistema de Selagem","client_id":"atlas_4533fa550b01",
  "ambiente":"producao","status":"ativo",
  "escopos":["os:ler","os:criar","pagamento:criar","ato:liquidar"],
  "servidor":{"data_hora":"2026-07-31T10:16:00-03:00","fuso":"America/Fortaleza"}}}
```

---

### `GET /v1/os/{numero}`

Retrato completo: dados da O.S., financeiro, itens, liquidações, pagamentos e selos.

```json
{"sucesso":true,"dados":{
  "os":{"numero":672,"cliente":"JULIANE SILVA SANTOS CARNEIRO","cpf_cliente":"12345678900",
        "status":"ativa","cancelada":false,"total_os":122.51,
        "base_de_calculo_os":null,
        "data_criacao":"2026-07-31T10:16:00-03:00"},
  "financeiro":{
    "total_os":122.51,"total_pago":50.00,"total_devolvido":0,
    "pago_liquido":50.00,"total_liquidado":42.00,
    "saldo_liquidacao":8.00,"saldo_a_pagar":72.51,
    "quitada":false,"isenta_de_pagamento":false},
  "resumo_atos":{"total_de_itens":2,"itens_pendentes":2,
                 "unidades_pendentes":2,"totalmente_liquidada":false},
  "itens":[...],"liquidacoes":[...],"pagamentos":[...],"selos":[...]}}
```

#### O bloco `financeiro`, campo a campo

| Campo | O que é |
|---|---|
| `total_os` | valor da O.S. |
| `total_pago` | soma dos pagamentos |
| `total_devolvido` | soma das devoluções |
| `pago_liquido` | `total_pago − total_devolvido` |
| `total_liquidado` | soma dos atos já liquidados |
| **`saldo_liquidacao`** | **`pago_liquido − total_liquidado`** — o que ainda dá para liquidar |
| `saldo_a_pagar` | quanto falta o cliente pagar |
| `isenta_de_pagamento` | há pagamento com forma de isenção; dispensa a checagem de saldo |

**`saldo_liquidacao` é o número que interessa ao sistema de selagem.**

---

### `GET /v1/os/{numero}/saldo`

Só o financeiro. Mais leve, para checagem rápida.

---

### `GET /v1/os/{numero}/atos-disponiveis`

**A rota principal do fluxo de selagem.** Devolve apenas o que ainda não foi liquidado, já
marcando se o saldo cobre cada um:

```json
{"sucesso":true,"dados":{
  "os":672,"cancelada":false,
  "financeiro":{"saldo_liquidacao":50.00, "...":"..."},
  "quantidade":2,
  "itens":[
    {"item_id":1,"ato":"16.1","descricao":"CERTIDAO DE INTEIRO TEOR",
     "isento":false,"quantidade":2,"quantidade_liquidada":0,
     "base_de_calculo":null,"exige_base_de_calculo":false,"faixa_de_valor":null,
     "quantidade_disponivel":2,"situacao":"pendente",
     "valor_unitario_liquidacao":42.00,
     "valor_restante_liquidacao":84.00,
     "exige_saldo":true,
     "saldo_cobre_uma_unidade":true,
     "saldo_cobre_o_restante":false}]}}
```

| Campo | Uso |
|---|---|
| `quantidade_disponivel` | quantas unidades ainda podem ser seladas |
| `valor_unitario_liquidacao` | quanto custa liquidar **uma** unidade |
| `saldo_cobre_uma_unidade` | dá para selar mais uma? |
| `saldo_cobre_o_restante` | dá para selar tudo o que falta? |
| `base_de_calculo` | valor declarado deste ato (`null` se não informado) |
| `exige_base_de_calculo` | o ato é cobrado por faixa de valor? |
| **`pronto_para_selagem`** | **saldo E base OK — consulte só este antes de selar** |

> Os campos `saldo_cobre_*` avaliam cada item contra o saldo atual, **isoladamente**. Se você
> for selar vários atos da mesma O.S., o saldo é consumido a cada liquidação — o veredito
> final é sempre o da própria liquidação.

Use `GET /v1/os/{numero}/atos` para a lista completa, incluindo os já liquidados.

---

### `POST /v1/os/{numero}/verificar-saldo`

Consulta prévia, **não altera nada**. Serve para o sistema decidir se abre a tela de selagem.

```json
{ "item_id": 1, "quantidade": 1 }
```

```json
{"sucesso":true,"dados":{
  "pode_liquidar":false,
  "impedimentos":[{"codigo":"saldo_insuficiente",
                   "mensagem":"Saldo pago insuficiente... Faltam R$ 42,00."}],
  "item":{"item_id":1,"ato":"16.1","quantidade_disponivel":2,"...":"..."},
  "valor_da_liquidacao":{"emolumentos":30.00,"ferc":6.00,"fadep":3.00,
                         "femp":2.00,"ferrfis":1.00,"total":42.00},
  "financeiro":{"saldo_liquidacao":0,"...":"..."},
  "exige_saldo":true,"saldo_suficiente":false,"falta":42.00}}
```

Note que `pode_liquidar:false` vem com **HTTP 200** — a consulta funcionou, a resposta é que
foi negativa. Só a liquidação de verdade devolve 409.

---

### `POST /v1/os/{numero}/liquidar`

Liquida o ato depois da selagem. Aceita liquidação **parcial** (algumas unidades) ou
**total**.

```json
{
  "item_id": 1,
  "quantidade": 1,
  "selo": "MA00123456",
  "protocolo": "PROT-9911",
  "operador": "joao.silva"
}
```

| Campo | Obrigatório | Observação |
|---|---|---|
| `item_id` | sim | o `item_id` vindo de `/atos-disponiveis` |
| `quantidade` | não (1) | não pode exceder `quantidade_disponivel` |
| `selo` | não | selo gerado; fica registrado e visível na O.S. |
| `protocolo` | não | referência do sistema de origem |
| `operador` | **sim, na prática** | quem lavrou o ato; ver "O campo operador" abaixo |

Sucesso:

```json
{"sucesso":true,"dados":{
  "liquidado":true,"os":672,"liquidacao_id":1,"tabela":"atos_liquidados",
  "item":{"item_id":1,"ato":"16.1","quantidade":2,"quantidade_liquidada":1,
          "quantidade_disponivel":1,"situacao":"parcialmente_liquidado"},
  "quantidade_liquidada_agora":1,
  "valores":{"emolumentos":30.00,"ferc":6.00,"fadep":3.00,
             "femp":2.00,"ferrfis":1.00,"total":42.00},
  "selo":"MA00123456",
  "financeiro":{"saldo_liquidacao":8.00,"...":"..."}}}
```

Sem saldo — **HTTP 409**, com os números para a tela do operador:

```json
{"sucesso":false,"erro":{
  "codigo":"saldo_insuficiente",
  "mensagem":"Saldo pago insuficiente para liquidar este ato. Faltam R$ 34,00.",
  "os":672,"item_id":1,
  "valor_necessario":42.00,"saldo_disponivel":8.00,"falta":34.00,
  "total_os":122.51,"pago_liquido":50.00,"total_liquidado":42.00}}
```

#### O campo `operador` — leia antes de integrar

Formalmente opcional, mas **envie sempre**. É por ele que a serventia sabe a quem atribuir o
ato no fluxo de caixa.

Aceita o **nome completo** do colaborador como cadastrado no Atlas, ou o **nome de usuário**.
Os dois funcionam:

```json
"operador": "Antonio José Martins Garcia"
"operador": "ADMIN"
```

O servidor resolve o nome contra o cadastro de colaboradores e grava o usuário
correspondente. A comparação ignora acentuação e maiúsculas, então `Antonio Jose Martins
Garcia` casa com `Antonio José Martins Garcia`.

**Sem `operador`**, ou com um nome que não exista no cadastro, o ato fica registrado como
avulso da integração e o fluxo de caixa o exibe separado, fora do caixa de qualquer
colaborador. Ninguém perde dinheiro com isso, mas o fechamento do dia fica confuso e alguém
da serventia vai ter que corrigir à mão.

O mesmo vale para o `operador` de `POST /v1/os/{numero}/pagamentos`.

#### Garantias

- **Atômica.** Ou grava a liquidação e atualiza o item, ou não faz nada.
- **Trava por O.S.** Duas selagens simultâneas na mesma O.S. são serializadas.
- **Saldo reconferido dentro da trava.** O `verificar-saldo` é orientativo; a checagem que
  vale é esta. Sem isso, duas chamadas paralelas poderiam liquidar mais do que foi pago.
- **Mesmo cálculo da tela.** Os valores saem dos campos gravados no item, com rateio
  cumulativo por quantidade — liquidações parciais somam exatamente o total do item, sem a
  diferença de centavo.
- **Dispara os mesmos ganchos** do botão da tela: rastreio de pedidos e emissão automática de
  NFS-e (quando todos os atos ficam liquidados).

Ato **isento** (`total = 0`, ou O.S. com pagamento de isenção) liquida sem exigir saldo.

---

### `POST /v1/os/{numero}/pagamentos`

```json
{ "valor": 122.51, "forma_de_pagamento": "PIX", "operador": "caixa01" }
```

Formas aceitas: `Espécie`, `PIX`, `Débito`, `Crédito`, `Transferência Bancária`,
`Depósito Bancário`, `Ato Isento`, `Isento de Pagamento`.

Aceita `122.51` ou `"122,51"`. Devolve `financeiro` atualizado, já com o novo
`saldo_liquidacao`.

`GET` na mesma rota lista os pagamentos.

---

### `POST /v1/os`

```json
{
  "cliente": "MARIA DAS GRACAS SILVA",
  "cpf_cliente": "123.456.789-00",
  "descricao": "Certidões e averbação",
  "observacoes": "",
  "operador": "joao.silva",
  "itens": [
    { "ato": "13.1.18", "quantidade": 1, "base_de_calculo": "350.000,00" },
    { "ato": "16.1", "quantidade": 2 },
    { "ato": "16.3.18", "quantidade": 1, "desconto_legal": 50 },
    { "ato": "16.1", "quantidade": 1, "isento": true },
    { "descricao": "SERVICO AVULSO", "quantidade": 1,
      "valores": { "emolumentos": 10.00, "ferc": 2.00 } }
  ]
}
```

**Os valores não vêm do cliente.** O servidor busca o ato na tabela de emolumentos vigente e
aplica quantidade e desconto legal. Aceitar valores prontos permitiria lançar ato a preço
arbitrário. A exceção é o item manual (sem `ato`, com `descricao` + `valores`), que espelha o
lançamento manual da tela.

`isento: true` zera os valores e marca o ato com `(isento)`, como o botão "Ato Isento".

Resposta: **201** com o mesmo formato de `GET /v1/os/{numero}`. O número da O.S. está em
`dados.os.numero`.

---

### `GET /v1/os/{numero}/liquidacoes`

Atos já liquidados, com valores, funcionário, data e os selos registrados.

### `GET /v1/atos/{codigo}`

Consulta a tabela de emolumentos vigente — útil para montar a O.S. e mostrar o preço antes.

```
GET /v1/atos/16.1
```

---

## 5.1 Base de cálculo — POR ATO

Valor declarado do negócio jurídico. Nos **atos com valor declarado** — compra e venda, doação,
procuração com poderes de alienação — é a base que determina a faixa do selo.

> **Mudou em agosto/2026.** Antes havia uma única base por O.S. Isso não representava um
> orçamento com duas escrituras de valores diferentes, e não havia como saber a qual ato a base
> pertencia. **Agora a base está no ato.**

### Onde encontrar

A base fica dentro de cada item, junto com a faixa que o ato cobre:

```json
{
  "item_id": 10,
  "ato": "13.1.18",
  "descricao": "Escritura de compra e venda. De R$ 327.953,99 a R$ 409.942,47",
  "base_de_calculo": 350000.00,
  "exige_base_de_calculo": true,
  "faixa_de_valor": {
    "tipo": "intervalo",
    "minimo": 327953.99,
    "maximo": 409942.47,
    "rotulo": "de R$ 327.953,99 a R$ 409.942,47"
  }
}
```

| Campo | Significado |
|---|---|
| `base_de_calculo` | valor declarado deste ato; `null` quando não informado |
| `exige_base_de_calculo` | `true` quando o ato é cobrado por faixa de valor |
| `faixa_de_valor` | a faixa lida da descrição; `null` nos atos sem faixa |

Rotas que trazem os itens: `GET /v1/os/{n}`, `GET /v1/os/{n}/atos`,
`GET /v1/os/{n}/atos-disponiveis` e `GET /v1/os/{n}/liquidacoes`.

Em `POST /v1/os/{n}/verificar-saldo` e `POST /v1/os/{n}/liquidar`, que tratam de **um** ato,
a base dele vem também no primeiro nível, em `base_de_calculo`.

### `pronto_para_selagem`

Em `/atos-disponiveis`, este campo já combina saldo e base:

```json
"saldo_cobre_uma_unidade": true,
"base_de_calculo": null,
"exige_base_de_calculo": true,
"pronto_para_selagem": false
```

**É o único campo que precisa ser consultado antes de gerar o selo.**

### Campo legado: `base_de_calculo_os`

O antigo campo de nível de O.S. continua sendo devolvido, renomeado para
**`base_de_calculo_os`**, nas rotas `GET /v1/os/{n}` (dentro de `os`), `/saldo`, `/atos`,
`/atos-disponiveis`, `verificar-saldo` e `liquidar`.

Ele só vem preenchido nas **O.S. lançadas antes da mudança**, que têm a base apenas nesse
nível. Em O.S. novas vem `null`.

> **Não use `base_de_calculo_os` para escolher a faixa do selo.** Ele não diz a qual ato
> pertence. Use o `base_de_calculo` do item.

### Se estava usando o campo antigo

O campo `base_de_calculo` no primeiro nível de `/saldo`, `/atos` e `/atos-disponiveis`
**deixou de existir** — virou `base_de_calculo_os`. Quem lia dali precisa passar a ler
`itens[].base_de_calculo`.

### Validação na criação

`POST /v1/os` aceita a base dentro de cada item, em `base_de_calculo` ou `base_calculo`,
no formato brasileiro ou como número JSON:

```json
{
  "cliente": "COMPRADOR DA SILVA",
  "itens": [
    { "ato": "13.1.18", "quantidade": 1, "base_de_calculo": "350.000,00" },
    { "ato": "05.1",    "quantidade": 6 }
  ]
}
```

O servidor lê a faixa da descrição do ato na tabela de emolumentos e valida:

| Situação | Resposta |
|---|---|
| Ato de faixa **sem** base | `422 base_obrigatoria` |
| Base **fora** da faixa | `422 base_fora_da_faixa` |
| Ato **sem** faixa | base opcional; ignorada se ausente |

O erro traz a faixa esperada, para a tela do operador:

```json
{"sucesso":false,"erro":{
  "codigo":"base_fora_da_faixa",
  "mensagem":"A base informada (R$ 350.000,00) está ACIMA da faixa deste ato (de R$ 262.363,19 a R$ 327.953,98). Confira o valor declarado ou selecione o ato da faixa correta.",
  "ato":"13.1.17",
  "faixa_de_valor":{"minimo":262363.19,"maximo":327953.98,"rotulo":"de R$ 262.363,19 a R$ 327.953,98"},
  "base_recebida":350000.00}}
```

A mesma checagem roda na **liquidação**: ato de faixa sem base é recusado com
`422 base_obrigatoria`, mesmo havendo saldo. Sem a base não há como escolher o selo, e selo de
faixa errada é ato viciado.

### Registro imutável

Ao liquidar, a base fica gravada **junto do ato liquidado**, e não só no item. Se alguém editar
a O.S. depois, a base do que já foi selado não muda — o dado que fundamentou o selo fica
congelado. Ela volta em `GET /v1/os/{n}/liquidacoes`.

### Alterando depois

Esta versão não expõe alteração da base pela API. Se a O.S. foi criada sem base e o ato precisa
dela na selagem, alguém da serventia informa pela tela do Atlas (Editar O.S.) e a consulta
seguinte já devolve o valor.

## 5.2 Desconto legal

Percentual de gratuidade ou redução aplicado ao ato (Lei 1.060/50, gratuidade de registro civil,
convênios). Vem no campo **`desconto_legal`** de cada item, como número:

```json
{ "item_id": 27, "ato": "16.1", "quantidade": 2, "desconto_legal": 50, "...": "..." }
```

`0` significa sem desconto.

### Onde aparece

| Rota | Onde |
|---|---|
| `GET /v1/os/{n}` | `itens[].desconto_legal` |
| `GET /v1/os/{n}/atos` e `/atos-disponiveis` | `itens[].desconto_legal` |
| `POST /v1/os/{n}/verificar-saldo` | `item.desconto_legal` |
| `POST /v1/os/{n}/liquidar` | `item.desconto_legal` |
| `GET /v1/os/{n}/liquidacoes` | `liquidacoes[].desconto_legal` |

### Os valores já vêm com o desconto aplicado

Isto é o que mais importa na hora de conferir: **não aplique o percentual de novo.**

Um ato de R$ 42,00, quantidade 2, com 50% de desconto:

```json
"quantidade": 2,
"desconto_legal": 50,
"valores_do_item": { "total": 42.00 },        // 2 × 42,00 − 50% = 42,00
"valor_unitario_liquidacao": 21.00            // já com desconto
```

O mesmo vale para `valor_da_liquidacao` em `verificar-saldo`, para `valores` em `liquidar` e
para `total` em `/liquidacoes`. O `desconto_legal` está lá para **exibição e conferência** —
para o sistema de lavratura imprimir "50% de desconto legal" no ato, não para recalcular.

### Na criação

`POST /v1/os` aceita `desconto_legal` por item, de 0 a 100:

```json
{ "ato": "16.1", "quantidade": 2, "desconto_legal": 50 }
```

Fora dessa faixa, `422 desconto_invalido`. O servidor busca o valor do ato na tabela de
emolumentos e aplica quantidade e desconto — os valores nunca vêm do cliente.

## 6. Fluxo completo de selagem

```php
<?php
$API   = 'https://cartorio.exemplo.br/os/api/v1';
$TOKEN = 'sk_prd_...';

function chamar($metodo, $rota, $corpo = null, $idem = null) {
    global $API, $TOKEN;
    $ch = curl_init($API . $rota);
    $h  = ['Authorization: Bearer ' . $TOKEN, 'Content-Type: application/json'];
    if ($idem) { $h[] = 'Idempotency-Key: ' . $idem; }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
    }

    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$http, json_decode($resp, true)];
}

$osNumero = 672;

// 1. O que há para selar
[$http, $r] = chamar('GET', "/os/$osNumero/atos-disponiveis");
if (!$r['sucesso']) {
    exit('Não foi possível consultar a O.S.: ' . $r['erro']['mensagem']);
}

echo "Saldo disponível: R$ " . number_format($r['dados']['financeiro']['saldo_liquidacao'], 2, ',', '.') . "\n";

foreach ($r['dados']['itens'] as $item) {
    if (!$item['saldo_cobre_uma_unidade']) {
        echo "Ato {$item['ato']}: sem saldo, aguardando pagamento.\n";
        continue;
    }

    // 2. Lavra o ato e gera o selo no seu sistema
    $selo = gerarSelo($item['ato']);

    // 3. Liquida — a chave de idempotência protege a repetição
    [$http, $liq] = chamar('POST', "/os/$osNumero/liquidar", [
        'item_id'    => $item['item_id'],
        'quantidade' => 1,
        'selo'       => $selo,
        'protocolo'  => 'PROT-' . date('YmdHis'),
        'operador'   => 'joao.silva',
    ], 'selo-' . $selo);

    if ($liq['sucesso']) {
        echo "Ato {$item['ato']} liquidado. Selo {$selo}. "
           . "Saldo restante: R$ {$liq['financeiro']['saldo_liquidacao']}\n";
        continue;
    }

    if ($liq['erro']['codigo'] === 'saldo_insuficiente') {
        // Cancele o selo do seu lado: o ato NÃO foi liquidado.
        cancelarSelo($selo);
        echo "Sem saldo. Faltam R$ {$liq['erro']['falta']}\n";
    } else {
        cancelarSelo($selo);
        echo "Falha ({$liq['erro']['codigo']}): {$liq['erro']['mensagem']}\n";
    }
}
```

### Ordem: selar antes ou liquidar antes?

O exemplo acima **sela e depois liquida**, que é o fluxo pedido. Mas a liquidação pode falhar
depois de o selo já existir — se outro caixa consumiu o saldo no intervalo, por exemplo.

Duas formas de lidar:

1. **Chamar `verificar-saldo` imediatamente antes de gerar o selo.** Encurta muito a janela,
   embora não a elimine.
2. **Tratar a falha cancelando o selo**, como no exemplo. É o caminho mais seguro: o
   `saldo_insuficiente` garante que **nada** foi gravado na O.S., então o selo é que fica
   sobrando.

Não existe "reserva de saldo" nesta versão. Se o volume de selagem simultânea na mesma O.S.
justificar, dá para acrescentar.

---

## 7. Segurança

- **Use HTTPS.** O token viaja em cabeçalho; em HTTP puro ele vai em texto claro na rede.
- **Restrinja por IP** na tela de cadastro quando o parceiro tiver IP fixo.
- **Um token por sistema.** Não compartilhe entre integrações — o log identifica quem chamou.
- **Escopos mínimos.** Um sistema que só sela precisa de `os:ler` e `ato:liquidar`; não
  precisa criar O.S. nem lançar pagamento.
- **Comece em homologação.** Só promova a produção depois do fluxo inteiro validado.

---

## 8. Auditoria

Toda chamada — inclusive as recusadas — fica em `api_log`: sistema, IP, rota, O.S., status
HTTP, código de erro, duração e o corpo enviado (sem campos sensíveis). As últimas 60 aparecem
na tela de cadastro; o resto se consulta direto na tabela.

Os selos informados ficam em `api_selos`, ligados à liquidação e à O.S., e voltam em
`GET /v1/os/{numero}`.

---

## 9. Instalação

1. Copie `os/api/` e `os/api_sistemas.php` para o servidor.
2. Acesse `/os/api_sistemas.php` (exige login de administrador). As tabelas `api_*` são
   criadas sozinhas no primeiro acesso.
3. Cadastre o sistema parceiro em **homologação** e copie o token.
4. Peça ao parceiro para validar com `GET /v1/ping` — deve retornar `sistema_pendente`.
5. Clique em **Homologar**. O mesmo token passa a funcionar.
6. Valide o fluxo completo em homologação.
7. **Promover a produção** — um token novo é emitido; entregue-o ao parceiro.

### Requisitos

PHP 7.4+ (8.1+ recomendado, para o gancho de NFS-e), extensões `pdo_mysql`, `mbstring`,
`json`, `openssl`. Com `mod_rewrite`, o `.htaccess` já cuida das URLs limpas; sem ele, use a
forma `index.php/v1/...`.

Se o servidor engolir o cabeçalho `Authorization` (acontece em algumas configurações
CGI/FastCGI), o `.htaccess` já repassa via `HTTP_AUTHORIZATION`. Persistindo, use
`X-Api-Token`.

---

## 10. Tabelas criadas

Nenhuma tabela do módulo O.S. é alterada. A API cria apenas as suas:

| Tabela | Conteúdo |
|---|---|
| `api_sistemas` | sistemas cadastrados, hash do token, ambiente, status, escopos |
| `api_log` | trilha de auditoria de cada chamada |
| `api_idempotencia` | respostas guardadas por `Idempotency-Key` |
| `api_os_vinculo` | O.S. × sistema × ambiente (isolamento da homologação) |
| `api_selos` | selos informados na liquidação |

A API lê e grava nas tabelas existentes: `ordens_de_servico`, `ordens_de_servico_itens`,
`pagamento_os`, `devolucao_os`, `atos_liquidados`, `atos_manuais_liquidados` e
`tabela_emolumentos`.
