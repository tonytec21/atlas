# Pesquisa avançada de O.S. — os/index.php

## Arquivos

| Arquivo | Papel |
|---|---|
| `os/pesquisa_os_lib.php` | monta WHERE, parâmetros e chips a partir da URL |
| `os/buscar_atos.php` | sugestões de ato para o autocomplete |
| `os/ui-config.css` | estilos (já usado pelas telas de configuração) |
| `os/index.php` | filtros, consulta, resumo e paginação |

## Instalação

Copie os quatro arquivos. Nada no banco muda.

## Compatibilidade

Os dez parâmetros antigos continuam valendo — `os_id`, `cliente`,
`cpf_cliente`, `total_os`, `data_inicial`, `data_final`, `funcionario`,
`situacao`, `descricao_os`, `observacoes`. Links salvos e favoritos seguem
funcionando.

Oito novos: `q`, `ato[]`, `ato_modo`, `valor_min`, `valor_max`, `pagamento`,
`ord`, `pp`.

## Filtro por ato

O ato mora em `ordens_de_servico_itens`, não na O.S. — por isso a busca usa
`EXISTS`, e não `JOIN`: um join multiplicaria a linha da O.S. por cada item
que casasse, e o mesmo número apareceria repetido no resultado.

Com vários atos há duas perguntas diferentes, e o filtro distingue as duas:

- **Contém qualquer um** — um `EXISTS` com `IN`. Serve para "toda O.S. que
  tenha procuração ou escritura".
- **Contém todos** — um `EXISTS` por ato. Serve para "O.S. que tenham
  procuração *e* reconhecimento de firma".

Um `IN` simples responderia sempre a primeira pergunta, mesmo quando a
intenção fosse a segunda.

## Situação de pagamento

Derivada de `pagamento_os` menos `devolucao_os`, comparada com `total_os`.
Também por subconsulta, pelo mesmo motivo do ato. As quatro faixas: sem
pagamento, parcial, quitada e com crédito.

## Busca rápida

Um campo só, que decide onde procurar pelo formato do termo: dígitos e curto
é nº de O.S.; 11 a 14 dígitos é CPF/CNPJ (comparado só pelos dígitos, porque
o banco tem registros com e sem pontuação); o resto vai para nome, título e
observação.

Quem precisa de precisão usa os campos específicos — a busca rápida é para o
balcão.

## Paginação

Antes a tela trazia 100 registros sem filtro, e **todos** os registros quando
havia filtro — uma pesquisa ampla podia trazer milhares de linhas, cada uma
disparando cinco subconsultas de saldo. Agora há `LIMIT`/`OFFSET` com janela
de páginas em volta da atual.

## Nº da O.S.: intervalos e listas

O campo **Nº da O.S.** (e a busca rápida, quando o texto tem esse formato)
aceita:

- `120` — uma O.S.
- `100-150` — intervalo (`100 a 150`, `100..150` e `100 até 150` também valem)
- `10;25;40` — só essas (separador `;` ou `,`)
- `10;20-30;45` — lista e intervalos misturados

Parse em `pos_parse_numeros_os()`; SQL em `pos_cond_numeros_os()` — `IN` para
os avulsos e um `BETWEEN` por intervalo, tudo em um único parêntese com `OR`.

## Resultados por página (opcional)

`pp` deixou de ter valor obrigatório:

| `pp` | filtro | resultado |
|---|---|---|
| vazio | nenhum | últimas `POS_PADRAO_SEM_FILTRO` (100), paginadas |
| vazio | algum  | tudo que o filtro devolver, até `POS_LIMITE_SEM_PAGINACAO` (1000), sem paginação — acima disso a tela avisa |
| 25/50/100/200 | qualquer | pagina no tamanho escolhido |

Os dois limites são constantes no topo de `pesquisa_os_lib.php`.
