# Transferência de saldo entre O.S. — v1.0.2 (módulo O.S. do Atlas)

Permite aproveitar em outra O.S. um valor que o cliente **já pagou** e que não foi
usado, sem lançar pagamento nem devolução no caixa.

Caso típico: o cliente pagou a O.S. em 2025, o serviço não foi feito por falta de
documentos e, em 2026, foi feito um novo orçamento com a tabela atual. O valor de
2025 é transferido para a nova O.S. e o cliente paga só a diferença.

## Por que não afeta o caixa
- O dinheiro entrou no caixa **no dia em que foi pago** na O.S. antiga e continua lá.
- A transferência é gravada numa tabela própria (`os_transferencias_saldo`). Nada é
  lançado em `pagamento_os` ou `devolucao_os`. Por isso caixa, fechamento, livro de
  depósito prévio e relatórios por data **não mudam**.

## Como usar
1. Abra a O.S. antiga (origem). No quadro **Transferências de saldo**, ou em
   **Pagamentos → Ações rápidas**, clique em **Transferir saldo**.
2. Digite o nº da O.S. nova e clique em **Conferir O.S.** O sistema mostra o
   cliente, o total, o que já foi pago e o que falta pagar.
3. O valor sugerido é o que falta na O.S. nova, limitado ao disponível na antiga.
   Informe o **motivo** (obrigatório, com no mínimo 10 caracteres) e confirme.
4. Na O.S. nova, o crédito entra como **Depósito prévio** e libera a liquidação.
   O cliente paga somente a diferença, como um pagamento normal.
5. Se a O.S. antiga não for mais executada, cancele-a. O sistema mostra essa dica
   quando todo o valor dela foi transferido.

## Regras de segurança
- **Quem pode transferir:** administradores e colaboradores com acesso adicional
  **Fluxo de Caixa**.
- **Quem pode estornar:** somente **administradores**, com motivo. O valor volta para
  a O.S. de origem.
- O estorno é bloqueado quando a O.S. de destino já usou o crédito em liquidações ou
  devoluções, e também quando a O.S. de origem está cancelada.
- Não é possível transferir mais do que o disponível na origem:
  pagamentos + créditos recebidos − devoluções − transferências enviadas − repasses − liquidado.
- Não é possível transferir de uma O.S. cancelada, nem para uma.
- Se o CPF/CNPJ das duas O.S. não for o mesmo (ou estiver vazio), é exigida uma
  confirmação explícita. A divergência fica registrada com um ícone de alerta.
- Uma trava no banco (`GET_LOCK`) somada a uma transação impede que duas transferências
  simultâneas usem o mesmo saldo.
- **Nada é apagado.** O estorno só muda a situação para "Estornada" e grava quem fez,
  quando e por quê.

## Rastreabilidade
- Cada transferência recebe um código no formato `TS-AAAA-NNNNNN`. Também ficam
  gravados o usuário, o nome, o IP, a data e hora, o motivo e um retrato dos valores
  das duas O.S. no momento da transferência.
- As duas O.S. mostram a transferência com link de uma para a outra: no quadro, nos
  campos do topo, no modal de pagamentos, nos recibos (80 mm e A4) e na impressão da O.S.
- **Auditoria de O.S.:** as ações "Saldo transferido entre O.S." e "Transferência de
  saldo estornada" são registradas **nas duas O.S.**, com antes × depois e o motivo.
- **Integridade:** os registros formam uma cadeia de hash SHA-256 e cada estorno tem
  um selo próprio. Se alguém alterar a tabela direto no banco, o administrador vê um
  alerta no quadro da O.S. com os códigos afetados.

## Onde o cálculo foi ajustado
Transferência **recebida** conta como depósito prévio. Transferência **enviada** sai
do saldo, como uma devolução, mas sem passar pelo caixa.

- `visualizar_os.php`: totais, saldo, situação ("Saldo transferido"), liquidação e
  cancelamento.
- `index.php`: lista de O.S., com a situação "Transferida".
- `pesquisa_os_lib.php`: filtro "Situação de pagamento".
- `salvar_devolucao.php`: agora limita no servidor o valor ao disponível.
- `salvar_repasse.php`: saldo do repasse.
- `api/api_lib.php`: `api_os_saldo()`, que ganhou os campos
  `credito_transferido_recebido` e `credito_transferido_enviado`.
- `recibo.php`, `recibo_a4.php`, `imprimir_os.php`, `imprimir-os.php`: impressões.
- `auditoria_os_lib.php`, `auditoria_os_view.php`: novas seções e ações.

## Arquivos
**Novos:** `transferencia_saldo_lib.php`, `transferir_saldo.php`, este LEIAME.
**Alterados:** os arquivos listados acima.

## Banco de dados
A tabela `os_transferencias_saldo` (InnoDB) é criada automaticamente no primeiro
acesso. Não é preciso rodar nenhum script de atualização.
