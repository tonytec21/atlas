# Pagamento Online (Parcela Express) — módulo da O.S. — v3.0.6

Cobrança na maquininha e emissão de boleto (com link de pagamento) a partir do
modal **Efetuar Pagamento** de `visualizar_os.php`.

Com o recurso desligado, a tela de O.S. funciona exatamente como antes.

## Instalação

1. Copie a pasta `pagamento_online/` para dentro de `os/`.
2. Acesse `os/pagamento_online/pe_config.php` como administrador. As tabelas
   (`pe_config`, `pe_pos_sales`, `pe_boletos`, `pe_log`) são criadas ou
   atualizadas na primeira visita.
3. Configure, salve e clique em **Testar conexão**.

Ao atualizar de versões anteriores, **apague** estes arquivos, que não existem
mais: `lib/Config.php`, `lib/Endpoints.php`, `lib/HttpClient.php`,
`lib/ApiException.php`, as pastas `lib/Pos/` e `lib/Boleto/`, `pe_rotas.php`,
`pe_schema.php`, `pe_diagnostico.php`, `pe_simular.php` e
`pe_boleto_pagar_sandbox.php`.

## Configuração

| Campo | Uso |
|---|---|
| Habilitar / Maquininha / Boleto | liga o recurso e cada canal |
| Ambiente | Sandbox (homologação) ou Produção |
| E-mail e senha | acesso à Parcela Express. No sandbox, em branco = conta de homologação |
| ID do estabelecimento | opcional: em branco = o seller devolvido pelo login |
| Maquininhas | uma por linha: `serial \| nome` (o operador também pode digitar o serial) |

O estabelecimento (seller) é o informado pelo próprio login.

## Integração (API v2)

| Operação | Chamada |
|---|---|
| Login | `POST /v2/auth/login` → `id_token`, `seller.id` |
| Boleto | `POST /v2/billets/{seller}` — lista com um boleto |
| Maquininha | `POST /v2/pos/payments/{seller}` |
| Situação (boleto e maquininha) | `GET /v2/sellers/{seller}/sales/{sale_id}` |

**Boleto:** valor em **centavos** (R$ 13,37 = `1337`), nome e CPF/CNPJ do pagador (só números), vencimento em 3 dias,
`with_payment_link`, referência `OS_{id}_{timestamp}`. Os demais dados do
pagador seguem os valores padrão do cliente (`lib/ParcelaExpressClient.php`).

**Maquininha:** `terminal_id`, `amount` (centavos), `is_debit`, `is_pix`,
`instalments`, `service_id`, `document` (CPF da O.S.).

**Situação:** `APPROVED`, `PAID`, `LIQUIDADO`, `SETTLED`, `CONFIRMED` = pago;
`CANCELED`, `CANCELLED`, `EXPIRED`, `REFUNDED` = cancelado; o resto = pendente.

## Fluxo

- **Maquininha:** o operador escolhe a maquininha, a forma (Crédito, Débito ou
  PIX) e as parcelas; a tela consulta a situação a cada poucos segundos. Paga, o
  pagamento entra em `pagamento_os` na hora.
- **Boleto:** emitir não dá baixa. O botão **Conferir** consulta a situação;
  pago, o pagamento entra em `pagamento_os` (forma "Boleto").
- O lançamento é idempotente: conferir duas vezes não lança duas vezes.
