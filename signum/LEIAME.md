# Atlas Signum — Assinatura Eletrônica de PDFs

Módulo do Atlas para assinar PDFs em PAdES (ICP-Brasil). Instale como a pasta
`signum/` no MESMO nível de `oficios/` (usa os assets e o Assinador de lá).

## Fluxo (igual ao módulo de ofícios)
1. **Anexe um PDF** (arraste ou clique). Ele abre a pré-visualização.
2. **Clique no documento** para posicionar o carimbo (arraste para ajustar; slider muda o tamanho).
3. **Assine**:
   - **A3 (padrão):** com o **Assinador SERPRO** aberto/autorizado, clique em *Assinar* → confirme o PIN no token. O Assinador assina o *hash* (`sign('hash', ...)`) e o servidor injeta o CMS no PDF (PAdES / ETSI.CAdES.detached).
   - **A1 (opcional):** envie seu `.pfx`+senha em *Configurar*; a assinatura é feita direto no servidor.
4. O PDF assinado fica na lista **Documentos assinados** para visualizar/baixar.

## Dependências (todas locais — funciona offline)
- PDF.js: `../oficios/pdfjs/pdf.min.js` + `pdf.worker.min.js`
- Assinador: `../oficios/serpro/serpro-signer-promise.js` + `serpro-signer-client.js`
- UI: `../script/jquery-3.5.1.min.js`, `bootstrap.bundle.min.js`, `sweetalert2.js`; `../style/css/*`
- TCPDF/FPDI: reutilizados de `../oficios/` (tcpdf/ e src/)

## Motor PAdES
`AtlasPadesInjector` (em `config_assinatura.php`) é a mesma lógica do seu
`oficios/assin_pades.php`: cria o placeholder com cert *dummy*, troca o SubFilter
para `ETSI.CAdES.detached`, calcula o digest do `/ByteRange` (enviado ao token) e
injeta o CMS devolvido. O `messageDigest` do CMS é conferido contra o ByteRange
antes de gravar.

## Requisitos do banco
Tabelas `assinatura_documentos`, `assinatura_config`, `assinatura_config_usuario`
são criadas sozinhas (idempotente). MySQL da base `atlas`.
