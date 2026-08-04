# Correção — Imagens → PDF (HTTP 500)

## Causa

O endpoint tinha `error_reporting(0)` e um `try { } catch (Throwable)`, mas
**erro fatal do PHP não é capturável por `catch`**: o script morre, o corpo da
resposta fica vazio e o Apache devolve `500 Internal Server Error` — exatamente
o que aparecia no console (o JS nem chega a mostrar mensagem, pois o corpo vem
vazio).

O fatal acontecia dentro do TCPDF: `Image(..., $resize = true, 300)` manda o GD
descompactar a imagem inteira em memória — **largura × altura × 4 bytes**, mais
as cópias internas do TCPDF. Uma foto de 12 MP (4000×3000) custa ~110 MB; duas
ou três já estouram o `memory_limit` de 512 MB. PNG com transparência é pior:
o TCPDF usa o caminho `ImagePngAlpha`, que separa RGB e máscara alfa em arquivos
temporários dentro de `K_PATH_CACHE` (se essa pasta não for gravável, quebra).

## O que mudou

**`config_forja.php`**

- `forja_img_preparar()` — normaliza cada imagem **antes** do TCPDF:
  reduz para no máximo 2600 px no maior lado, achata transparência sobre branco,
  corrige orientação EXIF (fotos de celular deitadas) e libera o GD na hora.
  Se a imagem já estiver dentro do limite e sem alfa, o arquivo original é usado
  sem reprocessar (sem perda).
- `forja_img_reduzir_magick()` — para imagens acima de 24 MP ou quando o
  `memory_limit` não pode ser elevado, a redução é feita pelo **ImageMagick**
  (custo zero de RAM no PHP). Só entra se o ImageMagick estiver configurado.
- `forja_memoria_minima()` / `forja_ini_bytes()` — elevam o `memory_limit` de
  forma calculada; se não for possível, lançam exceção **legível** em vez de
  matar o processo.
- `forja_imagens_para_pdf()` — `$resize = false` no `Image()` (a imagem já vem
  pronta), páginas limitadas a 5000 mm (limite do formato PDF), imagens com
  problema são puladas com aviso em vez de derrubar o lote, `gc_collect_cycles()`
  a cada página e limpeza dos temporários no fim.
- `forja_log()` / `forja_msg_fatal()` — log em `tmp/forja_erros.log` e tradução
  das mensagens fatais do PHP para pt-BR acionável.

**`imagens_para_pdf.php`**

- `register_shutdown_function()` que transforma o fatal em JSON com a causa real
  (a resposta deixa de ser 500 vazio).
- Uploads temporários apagados após a conversão.
- Retorna `pico_mb` e `aviso` (imagens ignoradas).

**`index.php`** — exibe o aviso de imagens ignoradas via SweetAlert2.

**`.htaccess`** — `memory_limit` de 512M → 1024M.

**`diag_img2pdf.php`** (novo) — abre no navegador e mostra limites do PHP, GD,
TCPDF/FPDI, `K_PATH_CACHE`, permissões das pastas, faz uma conversão de teste
medindo o pico de memória e lista os últimos erros do log.

## Como validar

1. Substituir os arquivos e **reiniciar o Apache** (OPcache).
2. Abrir `forja/diag_img2pdf.php` — tudo deve ficar OK e o teste gerar o PDF.
3. Repetir a conversão que falhava. Se ainda falhar, a mensagem agora vem
   escrita na tela e detalhada em `forja/tmp/forja_erros.log`.

## Ajuste opcional

Em `config_forja.json`, a chave `img2pdf_max_px` controla a resolução máxima
(padrão 2600). Para digitalização de cartório com OCR posterior, 3500 é um bom
valor — em troca de mais memória e arquivos maiores.

---

# Acompanhamento em porcentagem (todas as ferramentas)

## Como funciona

O andamento é dividido em duas fases numa única barra:

| Faixa | Fase | Origem do número |
|---|---|---|
| 0 – 30% | envio dos arquivos | `XMLHttpRequest.upload.onprogress` (bytes enviados) |
| 30 – 100% | processamento no servidor | `progresso.php`, consultado a cada 700 ms |

O `fetch()` não expõe progresso de upload, por isso as chamadas passaram a usar
XHR. O navegador gera um `job` aleatório, envia junto do POST e consulta
`progresso.php?job=...`; o processamento grava o percentual em
`tmp/prog_<job>.json`, que é apagado automaticamente no fim do request.

**Detalhe importante:** cada endpoint chama `session_write_close()` logo após
validar o CSRF. Sem isso o `progresso.php` ficaria travado esperando o lock do
arquivo de sessão do request principal e a barra nunca se moveria.

## Progresso real por ferramenta

- **Imagens → PDF** — por imagem (`Imagem 3 de 12…`), 88% ao gravar o PDF.
- **Juntar PDFs** — por arquivo e, em arquivos longos, a cada 5 páginas.
- **Juntar múltiplo** — por documento do Lado B.
- **Dividir PDF** — por parte gerada.
- **PDF → imagens** — o Ghostscript renderiza tudo numa chamada só, então o
  `progresso.php` **conta os arquivos já gravados na pasta de saída** e calcula
  o percentual real (`Renderizando página 7 de 30…`).
- **Comprimir** — etapas nomeadas: análise, calibração na amostra, cada tentativa
  da escada (com o dpi em uso), aplicação no arquivo inteiro, finalização.
- **Word ↔ PDF** — etapas do LibreOffice (abrindo / convertendo / finalizando).

## Arquivos alterados

- `config_forja.php` — API de progresso (`forja_job_iniciar`, `forja_prog`,
  `forja_prog_ler`) e instrumentação das sete funções de conversão.
  O `forja_gc()` deixou de apagar o `forja_erros.log`.
- `progresso.php` (novo) — leitura do andamento.
- `index.php` — barra de progresso injetada em todos os painéis, envio por XHR
  com percentual de upload e polling do servidor.
- Todos os endpoints — `forja_job_iniciar()` + `session_write_close()`.

Nada disso é obrigatório: se o `job` não vier no POST, as funções continuam
funcionando exatamente como antes (o `forja_prog()` simplesmente não faz nada).

---

# Limpeza automática e otimização do Imagens → PDF

## 1. Limpeza dos temporários

Antes, cada conversão deixava resíduo em `forja/tmp` e `forja/saida`, e o
`forja_gc()` só apagava **arquivos** — as pastas (`imgs_*`, `split_*`,
`multi_*`, `lo_*`) ficavam para sempre. Agora:

- **Fim de cada request:** tudo que a conversão criou em `tmp` (uploads,
  imagens preparadas, PDFs normalizados, amostras, tentativas de compressão) é
  registrado e apagado automaticamente. O único caso protegido é o arquivo que
  virou download — `forja_registrar_saida()` marca o caminho como intocável.
- **Fim de cada empacotamento:** ao gerar o ZIP de PDF→imagens, dividir e
  juntar múltiplo, a pasta com os arquivos soltos é removida na hora (o ZIP já
  tem tudo). O Word↔PDF passou a mover o resultado e descartar a pasta do
  LibreOffice.
- **Retenção:** todo endpoint chama `forja_gc()`, que remove arquivos **e
  pastas** vencidos nas duas pastas. O prazo é configurável (padrão 3 h).
- **Botão manual:** em *Configurar* há um painel de espaço em disco com
  "Limpar vencidos" e "Limpar tudo agora" (`limpar_tmp.php`, restrito a admin).

## 2. Por que estava lento — e o que mudou

Três gargalos, medidos:

**a) O TCPDF não era o formato certo para o trabalho.** Ele carrega fontes,
monta o documento inteiro em memória e reprocessa cada imagem. Mas uma página
que é só uma imagem não precisa de nada disso. Foi escrito um **motor nativo**
que grava o PDF em streaming: cada JPEG entra como `/DCTDecode` (os bytes do
arquivo, sem recompressão) e cada PNG como `/FlateDecode` com `/Predictor 15`
(o IDAT como está). Sem TCPDF, sem FPDI, memória constante.

**b) A reamostragem do GD.** `imagecopyresampled` gastava 0,67 s numa foto de
12 MP; `imagescale` com `IMG_BILINEAR_FIXED` faz o mesmo em 0,21 s, com
diferença visual de ~1%. Passou a usar `imagescale` em reduções de até 2× e a
reamostragem completa só em reduções maiores, onde a diferença aparece.

**c) A dica de tamanho do ImageMagick estava errada.** O `-define jpeg:size`
usava o dobro do alvo — isso **desliga** o DCT scaling da libjpeg. Medição na
mesma foto: 9,7 s com a dica errada, **1,3 s** com a dica no tamanho alvo.
Além disso, o GD passou a ser a primeira opção (mais rápido, sem processo
externo); o ImageMagick entra só quando a imagem não cabe na memória.

**Resultado medido:** 12 fotos de 12 MP (4000×3000) → **4,2 s** e **4 MB** de
pico de memória, PDF de 4,9 MB.

## 3. Compatibilidade

Formatos que o PDF não embute direto (JPEG progressivo ou CMYK, PNG
entrelaçado ou com `tRNS`) são reconvertidos **uma vez** para JPEG baseline e
seguem pelo motor rápido. O caminho TCPDF continua existindo como último
recurso, mas na prática não é mais acionado.

O PDF gerado foi validado com `qpdf --check` (sem erros de sintaxe ou de
stream) e renderizado com `pdftoppm`, conferindo página a página: JPEG, PNG
RGB, PNG cinza, PNG paletizado, PNG 1 bit, PNG com alfa, JPEG progressivo e
JPEG CMYK.

## 4. Novas opções em Configurar

- **Retenção dos arquivos gerados** (horas, padrão 3).
- **Resolução máxima em Imagens → PDF** (padrão 2600 px no maior lado).
- Correção: o campo **caminho do LibreOffice** existia no formulário mas nunca
  era salvo; e desmarcar "Módulo ativo" não gravava `N`.

---

# Limite de envio elevado para 2 GB

## O que mudou

- **`.htaccess`**: `upload_max_filesize 2048M`, `post_max_size 2100M`,
  `max_execution_time`/`max_input_time` 3600 s.
- **Limite do módulo configurável** (`limite_upload_mb`, padrão 2048) em
  *Configurar → Tamanho máximo de arquivo enviado*. O limite que vale é sempre
  o **menor** entre esse valor, o `upload_max_filesize` e o `post_max_size`, e a
  tela mostra qual dos três está mandando.
- O rótulo do painel de compressão e a validação no navegador passaram a usar
  esse limite: um arquivo acima do teto é recusado **antes** de subir, com a
  instrução de qual diretiva ajustar.

## O `.htaccess` sozinho não basta

`php_value` no `.htaccess` só funciona quando o PHP roda como **módulo do
Apache**. Em instalações do XAMPP com PHP em FastCGI as diretivas são ignoradas
sem erro — e continuaria valendo o limite antigo. Por isso, ajuste também em
`C:\xampp\php\php.ini`:

```ini
upload_max_filesize = 2048M
post_max_size       = 2100M
max_execution_time  = 3600
max_input_time      = 3600
```

Depois reinicie o Apache e confira em `forja/diag_img2pdf.php` (seção
"Limites de envio e disco") ou na própria tela de Configurar.

**PHP de 32 bits não trata arquivos acima de 2 GB** (`filesize`/`ftell`
estouram). O módulo detecta isso e reduz o teto sozinho, avisando na tela.

## Correções que 2 GB exigiram

Três pontos quebrariam com arquivos desse tamanho:

1. **`forja_pdf_num_paginas()` fazia `file_get_contents()` do PDF inteiro** como
   última heurística — com 2 GB isso é um erro fatal de memória (o 500 mudo de
   novo). Agora a varredura é feita em blocos de 1 MB, com controle de borda
   para não contar a mesma ocorrência duas vezes. Acima de 200 MB o parser do
   FPDI também é pulado: vai direto ao Ghostscript, que lê em streaming.
2. **Mensagem errada quando o POST estoura.** Quando o corpo passa do
   `post_max_size`, o PHP descarta `$_POST` e `$_FILES` inteiros — a validação
   de CSRF então acusava "sessão expirada", que não tem nada a ver. Agora
   `forja_checar_post()` compara o `CONTENT_LENGTH` com o limite e diz o motivo
   real, com o tamanho enviado e o limite vigente.
3. **Espaço em disco.** A compressão chega a usar ~2,5× o tamanho do arquivo em
   cópias intermediárias. O upload agora é recusado com mensagem clara se o
   disco não comportar, e a cópia do original para a prévia comparativa deixou
   de ser feita acima de 300 MB (economiza tempo e o dobro do espaço).

## Recomendação prática

Um POST único de 2 GB é frágil: qualquer queda de rede reinicia tudo, e o
navegador segura o arquivo inteiro. A barra de progresso ajuda, mas para
arquivos muito grandes vale considerar colocá-los direto numa pasta do servidor.
Se isso for útil, dá para acrescentar depois um modo "processar arquivo já
presente no servidor" ou envio em blocos.
