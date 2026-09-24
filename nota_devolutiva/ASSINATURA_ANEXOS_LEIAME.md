# Nota Devolutiva — Assinatura digital (PAdES/SERPRO) + Anexos

Este módulo ganhou **assinatura eletrônica ICP-Brasil** (PAdES por hash, via
Assinador SERPRO, com posicionamento por clique) e **anexos** (comprovantes),
reaproveitando a infraestrutura já existente no módulo de **ofícios**.

## Pré-requisito

O módulo de **ofícios** (pasta `../oficios`) precisa estar presente **com o
recurso de assinatura instalado**, pois a nota reutiliza:
`../oficios/assin_pades.php`, `../oficios/pdfjs/`, `../oficios/serpro/`,
`../oficios/tcpdf/`, `../oficios/src/` (FPDI) e `../oficios/pades_dummy.*`.

## Como funciona a assinatura

Igual ao módulo de ofícios: o servidor gera o PDF da nota com o **selo na
posição escolhida** + um placeholder; o **Assinador SERPRO** assina o hash do
`/ByteRange` (`sign('hash')`) e devolve um **CMS/CAdES AD-RB**, que é injetado no
PDF. Resultado: PDF **PAdES** (`ETSI.CAdES.detached`) com política ICP-Brasil.

Fluxo: `nota_pades_prepare.php` → `sign('hash')` → `nota_pades_finalize.php`.
Página: `assinar-nota.php?numero=...` (pré-visualização com PDF.js, clique e
arraste para posicionar). O `finalize` confere que o `messageDigest` do CMS bate
com o documento antes de gravar (recusa assinatura de outro documento).

## Anexos (comprovantes)

Página `anexos-nota.php?numero=...` com **dropzone** (arrastar/soltar, progresso,
lista com baixar/excluir). Endpoints: `anexos_upload.php`, `anexos_listar.php`,
`anexos_excluir.php`, `anexos_baixar.php`. Guardados em `anexos/<numero>/`.

## Segurança

- `session_check` em todas as rotas novas (inclusive no `gerar_pdf_nota.php`,
  que antes era público).
- **CSRF** nas ações que gravam (preparo/finalização da assinatura, upload e
  exclusão de anexos).
- **Consultas parametrizadas** (prepared statements) nos novos endpoints.
- **Upload validado**: whitelist de extensões, limite de 20 MB, `finfo` de MIME,
  nome de arquivo aleatório, e `.htaccess` (`php_flag engine off`) nas pastas
  `anexos/` e `assinados/`.
- **Path-traversal**: `view_signed_nota.php` e `anexos_baixar.php` validam o
  caminho com `realpath()` restrito à pasta do módulo.
- Nota **assinada fica bloqueada para edição** na listagem (some o botão Editar,
  aparece “Ver PDF assinado”).

## Novos arquivos

`assinatura_nota_config.php`, `nota_pdf_lib.php`, `prepare_nota_pdf.php`,
`nota_pades_prepare.php`, `nota_pades_finalize.php`, `assinar-nota.php`,
`view_signed_nota.php`, `anexos-nota.php`, `anexos_upload.php`,
`anexos_listar.php`, `anexos_excluir.php`, `anexos_baixar.php`.
Alterados: `gerar_pdf_nota.php` (usa a lib + sessão), `index.php` (schema),
`complementos_index/container.php` (botões Assinar/Anexos/Ver-assinada).

## Colunas/tabelas criadas automaticamente

Em `notas_devolutivas`: `assinado, assinatura_arquivo, assinado_por,
assinante_cert, assinado_em, assinatura_pagina, assinatura_codigo,
assinatura_meta`. Nova tabela `nota_anexos`.
