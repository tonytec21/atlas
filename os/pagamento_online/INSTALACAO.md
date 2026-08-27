# Pagamento Online (Parcela Express) — módulo da O.S.

Cobrança na maquininha a partir do modal **Efetuar Pagamento** de
`visualizar_os.php`, com habilitação controlada por administrador.

## Princípio

O módulo é **aditivo**. Com `pe_config.ativo = 0` (o padrão após a
instalação), a tela de O.S. se comporta exatamente como antes: nenhum botão
novo, nenhuma consulta a mais, nenhum fluxo alterado. O `pe_ui.php` sequer é
carregado.

Três camadas garantem isso:

1. `is_file()` — se a pasta `pagamento_online/` não existir, o `require` nem
   é tentado.
2. `try/catch (Throwable)` em volta do carregamento — uma falha no módulo
   novo não pode derrubar a página de O.S.
3. `pe_habilitado()` engole exceções e retorna `false` em caso de dúvida. Se
   o banco cair, o recurso simplesmente desaparece em vez de quebrar a tela.

Testado: com o módulo presente e o banco indisponível, a página continua
executando normalmente.

## Instalação

1. Copie a pasta `pagamento_online/` para dentro de `os/`.
2. Aplique o patch em `visualizar_os.php`:

   ```bash
   cd /caminho/do/projeto
   patch -p1 < os/pagamento_online/visualizar_os.patch
   ```

   Ou use o `visualizar_os.php` já modificado que acompanha a entrega. São
   duas inserções, nenhuma linha existente foi alterada:
   - após a linha 67 (fim do bloco de controle por acessos adicionais): o
     carregamento do módulo;
   - antes de `</body>`: o include da interface.

3. Acesse `os/pagamento_online/pe_config.php` como administrador. As tabelas
   (`pe_config`, `pe_pos_sales`, `pe_log`) são criadas na primeira visita,
   no mesmo padrão de migração automática que o módulo NFS-e já usa.

4. Adicione o link no menu, junto das demais configurações do módulo:

   ```html
   <a href="os/pagamento_online/pe_config.php">Pagamento Online</a>
   ```

Nenhuma tabela existente é alterada. Nenhum arquivo existente além de
`visualizar_os.php` é tocado.

## Linguagem visual compartilhada

`os/ui-config.css` é a folha de estilo das telas de configuração do Atlas.
Hoje ela atende `pagamento_online/pe_config.php`, `nfse/nfse_config.php` e
`nfse/nfse_notas.php`.

Existe como arquivo único de propósito: duas telas com o mesmo papel devem
envelhecer juntas. Mexer nela muda as duas.

**Segue o design system de `criar_os.php`** — mesma paleta de marca (índigo
#4f46e5 e o gradiente 667eea→764ba2), mesma escala de raios, sombras e
espaços, mesmos gestos nos botões (elevação de 2px no hover, retorno no
clique). O objetivo é que estas telas não pareçam de outro sistema.

O que ela define: hero com gradiente, cartões, pílulas de status, interruptor
e caixas de seleção próprios, grid de 12 colunas, listas de verificação, área
de envio de arquivo, botões — e, para listagens, indicadores, tabela,
etiquetas de estado, ações compactas e paginação.

Os componentes de listagem ficam na folha, e não na página que os usa, para
que uma futura tela de listagem herde o mesmo comportamento em vez de
recriá-lo.

**Instalação:** copie `ui-config.css` para dentro de `os/`. As duas páginas a
referenciam como `../ui-config.css`.

### Barra de ação fixa

Em formulário longo — o do NFS-e tem três blocos — o botão de salvar não pode
ficar a uma rolagem de distância do campo que se acabou de editar. Por isso o
rodapé é `sticky`, com fundo opaco e sombra: ele passa por cima do conteúdo
enquanto se rola, e precisa se distinguir dele.

## Tema claro e escuro

No **claro**, as cores derivam dos tokens do Atlas com valores de reserva:

```css
--surface: var(--bg-elevated, #ffffff);
--ink:     var(--text-primary, #0b1b2b);
```

No **escuro**, `ui-config.css` declara tudo por extenso, sem derivar de nada.
Isso não é redundância: os valores escuros de `--text-primary` e afins estão
declarados **inline dentro de `index.php` e `editar_os.php`**, não numa folha
compartilhada. Nas telas de configuração a classe `.dark-mode` chega ao body,
mas aqueles valores não — então `--text-primary` continuaria com a cor do tema
claro, produzindo texto escuro sobre fundo escuro.

Foi exatamente o defeito observado: sumiram os títulos dos cartões, os rótulos
dos interruptores e o conteúdo dos campos, enquanto os textos auxiliares
seguiam legíveis. A diferença era essa — o que sumiu usava `--text-primary`,
o que sobreviveu usava os valores de reserva do próprio módulo.

Se um dia o Atlas passar a definir o tema escuro numa folha comum, dá para
voltar a derivar. Enquanto isso, declarar é o que funciona em qualquer tela.

Os controles (interruptor e caixas de seleção) também são desenhados no
módulo, não herdados do `custom-control` do Bootstrap, cujos rótulos somem no
tema escuro.

## Encerramento do formulário

O formulário termina com a ação em largura total, dentro de um cartão, como
faz `criar_os.php`. Não há barra flutuante.

A primeira tentativa usou rodapé `sticky`. Duas coisas deram errado: destoava
do resto do sistema, onde nenhuma tela tem isso, e disputava espaço com a
navegação inferior do Atlas — o botão de salvar ficava atrás do menu. Levantar
a barra resolveria a sobreposição, mas não o fato de ela não pertencer ali.

## Controle de acesso## Controle de acesso

| Recurso | Quem acessa |
|---|---|
| `pe_config.php` (página) | administrador — gate `../../checar_acesso_de_administrador.php` |
| `pe_salvar_config.php` | administrador — verificação própria, respondendo JSON |
| `pe_terminais.php`, `pe_simular.php`, `pe_venda_*.php` | qualquer usuário logado, com o recurso habilitado e CSRF válido |

O gate de administrador da gravação **não** usa o include da página: aquele
emite HTML e redirect, o que corromperia a resposta JSON. Por isso
`pe_usuario_e_admin()` lê `nivel_de_acesso` da tabela `funcionarios`,
aceitando `administrador` ou `admin` — os mesmos valores que
`visualizar_os.php` já testa hoje.

## Integração com o fluxo atual

Este é o ponto mais importante do desenho:

**A venda aprovada é gravada em `pagamento_os`, com as mesmas colunas e as
mesmas formas de pagamento que o lançamento manual já usa** (`Crédito`,
`Débito`, `PIX` — valores que já existem no select). Não há caminho paralelo
de pagamento.

Consequência: recibo, liquidação, cálculo de saldo, NFS-e, guias e
relatórios continuam funcionando sem saber que a integração existe. A tabela
`pe_pos_sales` apenas amarra a venda ao `pagamento_id` criado, para
conciliação.

O lançamento acontece no servidor (`pe_venda_status.php`), nunca no
JavaScript. O front só reflete na tela chamando as funções que já existem:
`atualizarTabelaPagamentos()`, `atualizarSaldo()` e
`refreshOsHeaderAndStats()`.

## Proteções

**O valor vem do banco, não do navegador.** `pe_venda_criar.php` recalcula o
saldo em aberto a partir de `ordem_de_servico` e `pagamento_os` e recusa
qualquer valor acima dele. Sem isso, bastaria adulterar o POST para cobrar
valor diferente do devido.

**Idempotência.** Cada tentativa gera um `local_reference` único, enviado
como `sale_id` e como header de idempotência. `pe_registrar_pagamento()`
verifica se a venda já tem `pagamento_id` antes de inserir — o polling pode
ver "aprovada" mais de uma vez, e isso não pode virar lançamento duplicado.

**Uma cobrança por vez.** Se já existe venda pendente para a mesma O.S. nos
últimos 15 minutos, a nova requisição devolve a existente em vez de mandar
uma segunda ao terminal.

**Fechar o modal durante a espera exige interromper.** Sair deixando a venda
viva no terminal é o caminho mais curto para uma cobrança sem lançamento
correspondente.

**Dois status separados.** `pre_capture_status` (envio ao terminal) e
`transaction_status` (aprovação financeira) são colunas distintas, como na
listagem do CartExpress. Uma venda pode ser capturada no aparelho e ainda
assim negada pelo emissor.

## Rotas da API

**Confirmadas** contra o spec OpenAPI "Parcela Express API - Parceiros" v1.0.
Não são mais palpite.

| Operação | Rota |
|---|---|
| Login | `POST /v1/auth/login` |
| Listar terminais | `GET /v1/sellers/{sellerId}/pos` |
| Simular parcelas | `GET /v1/simulation/sellers/{seller_id}` |
| Criar venda no POS | `POST /v1/pos/payments/{seller_id}` |
| Status da venda | `GET /v1/sellers/{sellerId}/sales/{saleId}` |
| Cancelar venda no POS | `POST /v1/pos/cancel_sale/{seller_id}` |
| Vendas de um terminal | `GET /v1/sellers/{sellerId}/sales/pos/{terminalId}` |
| Emitir boleto | `POST /v1/billets/{sellerId}` |
| Consultar boleto | `GET /v1/billets/{id}` |
| URL do PDF | `GET /v1/billets/{billetId}/url` |
| Baixar boleto | `POST /v1/billets/{sellerId}/{billetId}/void` |
| Listar boletos | `GET /v1/sellers/{sellerId}/billets` |
| Pagar boleto (sandbox) | `POST /v1/billets/{billetId}/pay` |

Quatro descobertas mudaram o código, e vale saber por quê:

**O recurso de terminal chama-se `pos`, não `terminals`.** Nomenclatura, mas
era o que produzia o 404.

**A simulação inverte a ordem:** `/v1/simulation/sellers/{id}`, e não
`/sellers/{id}/simulation`. Nenhuma convenção previa isso.

**Cancelar venda no POS não leva o id na URL** — ele vai no corpo. A rota é só
`/v1/pos/cancel_sale/{seller_id}`.

**Baixar boleto é POST em `/void`, não DELETE.** O `BoletoService::cancel()`
foi reescrito por causa disso.

Duas rotas que eu nem sabia que existiam e agora são usadas:

**`GET /v1/billets/{id}/url`** — o POST de emissão não devolve o link do PDF.
É chamada separada, feita logo após criar e guardada junto do registro.

**`POST /v1/billets/{id}/pay`** — a API descreve como "em ambiente de
desenvolvimento". Permite marcar um boleto como pago sem esperar compensação
bancária, o que torna possível testar o ciclo inteiro hoje. O módulo expõe
isso como botão **"Simular pagamento"** na lista de boletos, visível apenas
quando o ambiente é sandbox; em produção o endpoint recusa antes de qualquer
chamada.

### Sobre o acompanhamento da venda no POS

A suposição de long polling estava errada: não há rota dedicada de POS para
acompanhamento — usa-se a consulta comum de venda,
`GET /v1/sellers/{id}/sales/{saleId}`.

As chamadas agora são espaçadas pelo **Intervalo entre consultas de status**
da tela de configuração (padrão 3s, faixa de 1 a 30). Isso vale para os dois
lados: o navegador e o `awaitOutcome()` no PHP.

Instalações que já gravaram `60` nesse campo — quando ele significava timeout
de long polling — voltam ao padrão de 3s automaticamente, em vez de ficarem
com um minuto de espera entre consultas.

### "Bad Request" na emissão

Rota certa e nomes de campo errados produzem `Bad Request Exception` — e o
NestJS não diz qual campo quando a exceção é lançada sem detalhes.

Duas coisas ajudam:

**A mensagem agora mostra o que veio.** Quando o corpo traz a lista de
validação (`message: ["amount must be...", ...]`), ela aparece inteira. Quando
vem só o texto genérico, o corpo completo da resposta é anexado — ao menos
revela quais campos a API enxergou.

**Configuração → Formato dos envios** (`pe_schema.php`) lê o spec e mostra,
para cada operação, os campos esperados com tipo e obrigatoriedade,
expandindo objetos aninhados. É a forma direta de descobrir se o campo é
`amount` ou `amount_cents`, `dueDate` ou `due_date`, `payer` ou `customer`.

A tela compara rotas ignorando o nome dos placeholders, então encontra a
operação mesmo que o spec use `{sellerId}` onde o módulo usa `{seller_id}`.

### Campos da emissão de boleto

Confirmados pela própria API, que rejeitou a primeira tentativa listando campo
a campo:

| Meu palpite | Campo real |
|---|---|
| `amount_cents` | `value` |
| `due_date` | `delivery_date` (ISO 8601 completo, data pura é recusada) |
| `customer` | `shopper` (objeto) |
| `customer.document` | `social_security_number` (na raiz, só dígitos) |
| `description` | **continua existindo** — e é obrigatório |
| — | `shopper_statement` (novo, obrigatório) |
| `reference`, `protocol` | não existem |

`description` e `shopper_statement` são campos **distintos** e ambos
obrigatórios. Descobri isso errando: ao renomear `description` para
`shopper_statement`, a API cobrou o primeiro de volta. Na rodada anterior ele
não tinha sido listado como "should not exist" justamente porque era válido.
O módulo envia o mesmo texto nos dois; nenhum aceita vazio, então há um padrão
("Emolumentos") para o caso de a descrição não vir.

O desenho espelha o Adyen: `social_security_number`, `shopper_statement`,
`delivery_date` e `shopper` são exatamente os campos que o Adyen exige para
boleto bancário. Coerente, já que é o gateway por trás da Parcela Express.

**Não há campo de referência nem de protocolo.** A amarração com a O.S. fica
só do nosso lado, em `pe_boletos`, e chega ao pagador pelo `shopper_statement`,
que recebe `"O.S. {id} - {cliente}"`.

**`shopper` exige nome e sobrenome separados**, e o cartório guarda um nome só.
`separarNome()` divide no primeiro espaço; com nome único, repete-o nos dois
campos em vez de mandar vazio, que a validação recusa.

### Endereço do pagador

O erro `Cannot read properties of undefined (reading 'complement')` é um **500**,
não um 400: a validação passou e o código da API quebrou depois, ao montar o
boleto sem o endereço do pagador.

Ou seja, o endereço não é opcional na prática, embora não apareça como
obrigatório na validação. Vai em `shopper.billing_address` — chave confirmada.

Os nomes dos campos **não** seguem o CustomerDTO do checkout, ao contrário do
que parecia:

| Checkout (cartão) | Boleto |
|---|---|
| `state` | `state_or_province` |
| `country` | não existe |
| — | `district` (bairro), obrigatório |
| `street`, `house_number_or_name`, `postal_code`, `city` | iguais |
| — | `complement` |

**Nenhum campo vai como `null`** — string vazia a API aceita, campo ausente
derruba. `house_number_or_name` cai para `"S/N"` quando não informado.

O formulário ganhou o bloco de endereço, com busca automática pelo CEP via
ViaCEP. Se o serviço estiver fora ou a máquina sem internet, o aviso é
informativo e o operador digita à mão — a emissão não fica bloqueada por isso.

CEP, logradouro, bairro, cidade e UF são validados nos dois lados, para que a
falta vire mensagem clara em vez de erro interno do servidor deles. O ViaCEP
já preenche o bairro junto com o resto.

### Atenção: a unidade de `value`

A mensagem da API não disse se `value` é em centavos ou reais. Enviamos em
centavos, porque o checkout deles usa `amount_cents` com esse significado e o
Adyen trabalha em unidades menores.

Se a dedução estiver errada, um boleto de R$ 471,64 vira R$ 47.164,00. Por
isso, após emitir, o módulo compara o valor devolvido pela API com o enviado e,
havendo divergência, registra `error` em `pe_log` e exibe um aviso vermelho no
canhoto.

**Confira o valor do primeiro boleto emitido antes de usar em produção.**

### Ainda em aberto

Os nomes dos campos dentro dos payloads (`PaymentPosRequest`, `CreateBilletDto`)
não estavam visíveis no HTML capturado — o Swagger renderiza os schemas sob
demanda. O parsing das respostas por isso ainda aceita várias grafias. Quando
a primeira chamada real funcionar, vale registrar o formato exato e enxugar.

A tela **Rotas da API** continua disponível para ajustar qualquer caminho sem
mexer em código, caso algo mude do lado deles.

## Roteiro de testes

Antes de habilitar em produção, com terminal de homologação:

1. Recurso **desativado**: abrir uma O.S., lançar pagamento manual, emitir
   recibo. Tudo deve funcionar exatamente como antes.
2. Recurso ativo mas com configuração incompleta: o botão não aparece.
3. Venda aprovada em 1x — conferir se entrou em `pagamento_os` e se o saldo
   e o cabeçalho da O.S. atualizaram.
4. Venda recusada pelo emissor.
5. "Interromper venda" com o modal aberto.
6. Timeout: gerar a venda e não tocar no terminal.
7. Derrubar a rede logo após criar a venda e verificar se o registro fica
   `unknown` em `pe_pos_sales`.
8. Tentar cobrar valor maior que o saldo — deve ser recusado no servidor.
9. Usuário não-administrador tentando `pe_salvar_config.php` — deve receber
   403.
10. Emitir boleto e conferir que a O.S. **continua em aberto**.
11. Conferir o mesmo boleto duas vezes seguidas depois de pago — deve lançar
    um único pagamento.
12. Tentar emitir boletos que somados passem do saldo — deve ser recusado.
13. CPF do pagador com dígito errado — deve ser recusado antes da chamada.
14. Em sandbox: emitir boleto → "Simular pagamento" → "Conferir" → conferir
    se o pagamento entrou em `pagamento_os` e o saldo da O.S. zerou.

Os itens 7 e 8 são os que mais importam e os que costumam ficar de fora.

## Credenciais

A cobrança na maquininha precisa de exatamente três dados:

| Campo | Onde achar |
|---|---|
| ID do estabelecimento | CartExpress → Perfil → Dados do Estabelecimento → **Id da Serventia** |
| Usuário da API | o mesmo login do CartExpress |
| Senha da API | a senha desse login |

Não há chave de checkout envolvida. O `clientKey` do componente
`@parcelaexpress/checkout-react-component` existe para criptografar cartão
digitado num formulário web; na maquininha o cartão é inserido no próprio
aparelho, então não há nada para criptografar no navegador. Verificado no
bundle oficial: todas as ocorrências dele estão na camada do browser —
iframe de campos seguros, consulta de BIN, fingerprint 3DS2, sessões e
analytics. Nenhuma nas chamadas de servidor.

Cuidado para não confundir o Id da Serventia com a **Chave da Plataforma de
Cobrança**, que aparece na mesma tela: aquele é o identificador público da
serventia, não uma credencial de API. O que o módulo usa é o UUID.

Se um dia o cartório passar a oferecer checkout online ou link de pagamento
na própria página, aí sim será preciso um `clientKey` — e o lugar de
adicioná-lo é `lib/Config.php`, junto com o componente JS correspondente.

## Sobre a senha da API

Fica em `pe_config.senha`, em texto, seguindo o padrão que o módulo NFS-e já
usa para `cert_senha`. Se quiser elevar isso, o lugar é `pe_salvar_config.php`
(cifrar na gravação) e `Config::fromArray()` (decifrar na leitura) — os dois
únicos pontos que tocam o campo.
