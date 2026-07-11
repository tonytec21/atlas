# Assinatura digital — Recibo A4 e O.S./Orçamento (módulo O.S. do Atlas)

Adiciona assinatura ICP-Brasil (PAdES / AD-RB) via **Assinador SERPRO** aos
documentos da O.S., no mesmo fluxo dos **Ofícios** e **Notas Devolutivas**:
página dedicada com pré-visualização (PDF.js) e clique para posicionar o selo.

## Documentos cobertos
- **Recibo A4** (`recibo_a4.php`) — `assinar-os.php?tipo=recibo_a4&id=<OS>`
- **O.S. / Orçamento** (`imprimir_os.php`) — `assinar-os.php?tipo=os&id=<OS>`

## Dependência
Reaproveita o módulo **Ofícios já instalado com assinatura** (um nível acima):
`../oficios/assin_pades.php`, `../oficios/tcpdf/`, `../oficios/src/` (FPDI),
`../oficios/pades_dummy.crt` e `.key`, `../oficios/pdfjs/`, `../oficios/serpro/`.
Se os Ofícios já assinam, não é preciso nada além disto.

## Arquivos
**Novos:** `assinatura_os_config.php`, `prepare_os_pdf.php`, `os_pades_prepare.php`,
`os_pades_finalize.php`, `view_signed_os.php`, `assinar-os.php`, este `LEIAME.md`.

**Substituem os originais (mesmo comportamento + integração):**
- `recibo_a4.php` e `imprimir_os.php` — receberam um **"modo captura"**: quando
  chamados pelo fluxo de assinatura (constante `OS_PDF_CAPTURE`), devolvem os
  bytes do PDF em vez de enviá-lo ao navegador. **Sem esse modo, funcionam
  exatamente como antes** (impressão normal). A lógica de geração NÃO foi
  reescrita — só interceptamos a saída.
- `visualizar_os.php` — dois botões novos: **Assinar OS** (ao lado de "Imprimir OS")
  e **Assinar A4** (no grupo de recibos).
- `session_check.php` — versão à prova de inclusão múltipla (funcionalmente
  idêntica; necessária porque o gerador é reincluído durante a captura).

## Banco de dados
Cria automaticamente a tabela `os_documentos_assinados` (chave única por
`tipo` + `os_id`). Guarda o caminho do PDF assinado, quem assinou, data,
código de verificação e metadados.

## Observações
- O tipo `os` usa sempre `imprimir_os.php` (versão com timbrado). Se você precisar
  que ele respeite a configuração `timbrado=N` (usar `imprimir-os.php`), me avise.
- Os PDFs assinados ficam em `os/assinados/<tipo>_<id>/` (com `.htaccess` que
  desliga o PHP na pasta). Não versione essa pasta.
- O selo tem largura inicial de 24% e pode ser reposicionado por clique/arraste.


## Reassinatura (após editar a O.S. ou o recibo)
Se o documento já estiver assinado, a página mostra o PDF atual e um botão
**“Assinar novamente”**. Ele reabre o editor (preview + posicionamento) e gera
uma **nova versão assinada que substitui a anterior** — ideal para quando a O.S.
foi editada e você precisa enviar a versão atualizada. O registro em
`os_documentos_assinados` é atualizado (mesma chave `tipo`+`os_id`).

## Selo
O carimbo agora é **mais largo e mais baixo** (layout em duas colunas:
identidade à esquerda, validação à direita), para ocupar pouca altura.
Largura inicial 42% (~88 mm × ~19 mm em A4), ajustável de 28% a 72%.
A razão altura/largura é controlada pela constante `OS_SEAL_RATIO` (0.22)
em `assinatura_os_config.php`. Também aumentei o espaço entre o texto do
documento e a linha de assinatura no recibo A4 e na O.S.

## Comprovantes de pagamento (anexos)
No modal **Efetuar Pagamento**, cada pagamento que **não é espécie** (PIX,
transferência, cartão, boleto, cheque, centrais eletrônicas…) ganha um botão de
**clipe** com contador. Ao clicar, abre um modal com **dropzone** (arrastar-e-soltar
ou clicar) que aceita **PDF, JPG, PNG, GIF, WEBP** (até 15 MB). Os comprovantes
podem ser **visualizados dentro do próprio sistema** (PDF em iframe, imagens
inline) sem baixar. Novos arquivos:
`pagamento_anexos_config.php`, `pa_upload.php`, `pa_listar.php`, `pa_ver.php`,
`pa_excluir.php`. Cria automaticamente a tabela `pagamento_os_anexos`; os arquivos
ficam em `os/comprovantes_pagamento/` (com `.htaccess` que desliga o PHP).
