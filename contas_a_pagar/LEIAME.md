# Contas a Pagar (Atlas) — módulo recriado

Controle de despesas: cadastro de contas recorrentes (mensal/semanal/anual) ou
avulsas, com dashboard, gráficos, relatórios, anexos modernos e **alertas por
e-mail** de contas vencidas e a vencer.

## Novidades

- **Dashboard** (`index.php`): KPIs (em aberto, vencidas, a vencer, pago no mês),
  3 gráficos (Chart.js): situação, por categoria e pagamentos dos últimos 6 meses.
- **Cadastro/edição em modal** com campos no padrão do módulo de notas
  (`page-hero`, `filter-card`, `input-chip`), máscara de moeda e categorias.
- **Recorrência**: ao marcar como paga, se for recorrente, a próxima parcela é
  gerada automaticamente (com tratamento de fim de mês, ex.: 31/01 → 28/02).
- **Situação automática**: contas pendentes vencidas aparecem como *Atrasado*.
- **Anexos modernos** (modal com *drag & drop*, progresso, **visualização de PDF
  e imagens dentro do sistema** ou em nova aba; download apenas para o que não
  tem prévia, como ZIP).
- **Relatórios** (`relatorios.php`): filtro por período/base (vencimento ou
  pagamento)/categoria/situação, totais, gráficos e **exportação CSV**.
- **Notificações por e-mail** configuráveis (`Configurações`): e-mail de destino,
  dias de antecedência, e SMTP próprio (host/porta/segurança/usuário/senha/
  remetente). A senha não é reexibida e é mantida se o campo ficar vazio.

## Contas virtuais (integração com o Controle de Caixa)

Duas "contas" do cartório, alimentadas pelos **depósitos** do módulo Caixa
(tabela `deposito_caixa`, coluna `tipo_deposito`):

| Conta virtual | Alimentada por |
|---|---|
| **Espécie (dinheiro)** | depósitos `Espécie` |
| **Saldo bancário** | depósitos `Depósito Bancário` e `Transferência` |

Ao clicar em **pagar**, o sistema pergunta a **forma de pagamento** e debita a
conta correspondente:

- `Espécie` → debita a conta **Espécie**
- `PIX`, `Transferência`, `Boleto`, `Débito automático`, `Cartão de Débito/Crédito` → debita o **Saldo bancário**
- `Outro (não afeta saldo)` → registra o pagamento sem movimentar as contas

O modal mostra o saldo atual e o **saldo após o pagamento**. Se não houver saldo,
o sistema avisa e só prossegue com confirmação explícita (fica negativo).

**Saldo é derivado** (depósitos − contas pagas naquela conta), então excluir ou
estornar uma conta paga devolve o saldo automaticamente — não há risco de o saldo
"descolar" da realidade. Há uma página de **Extrato** (`extrato.php`) por conta,
com entradas (depósitos), saídas (pagamentos), filtro por período e totais.

## Envio automático de alertas

O SMTP **não fica mais fixo no código** — vai para a tabela `contas_config`.
Para envio automático, agende `enviar_alertas.php`:

- Windows (Agendador de Tarefas) ou Linux (cron), 1x ao dia:
  `php C:\...\contas_a_pagar\enviar_alertas.php`
- Ou acesse a URL do arquivo autenticado (requer sessão) — o botão
  **“Enviar alerta agora”** nas Configurações faz um teste imediato.

## Controle de acesso

Só acessam o módulo:
- usuários com `nivel_de_acesso = 'administrador'`, **ou**
- usuários cujo `funcionarios.acesso_adicional` contenha `Controle de Contas a Pagar`.

A verificação (`guard_acesso.php`) roda no **servidor**, logo após `checkSession()`,
em **todas as páginas** (`index`, `relatorios`, `extrato`) e **todos os endpoints**
(salvar/editar/pagar/excluir, config, alertas, saldos, transferências e anexos).
Páginas mostram aviso e redirecionam; endpoints respondem `403` em JSON e o front
avisa e retorna ao início. Não há como acessar dados chamando um endpoint direto.

## Formas de pagamento e conta debitada

| Forma | Conta virtual debitada |
|---|---|
| Espécie | Espécie (dinheiro) |
| PIX, Transferência, TED/DOC, Boleto, Débito automático, Cartão de Débito, Cartão de Crédito, **Centrais Eletrônicas** | **Saldo bancário** |
| Outro (não afeta saldo) | — (não movimenta) |

## Segurança técnica

- **CSRF** em todas as ações que gravam; **prepared statements** em tudo.
- Upload validado (whitelist de extensão, 20 MB, checagem de MIME, nome
  aleatório) e `.htaccess` (`php_flag engine off`) na pasta `anexos/`.
- `anexos_baixar.php` com *path-guard* (`realpath`) e `X-Content-Type-Options`.

## Tabelas (criadas/migradas automaticamente)

- `contas_a_pagar` (estendida: `categoria`, `fornecedor`, `data_pagamento`,
  `origem_id`, `created_at`).
- `conta_anexos` (anexos por conta).
- `contas_config` (linha única id=1: e-mail, dias de aviso, SMTP…).

## Dependências

- `../menu.php`, `../rodape.php`, `../style/…` (um nível acima do módulo).
- PHPMailer (já incluído na pasta `PHPMailer/`).
- Bibliotecas via CDN: Bootstrap 5, DataTables, Chart.js, SweetAlert2, FontAwesome.
