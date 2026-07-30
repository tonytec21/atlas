# Compressão de PDF — motor v2

## O problema do motor anterior

O código antigo delegava tudo aos presets do Ghostscript:

```php
$mapa = ['tela' => '/screen', 'recomendado' => '/ebook', 'alta' => '/printer', 'maxima' => '/prepress'];
gs -sDEVICE=pdfwrite -dPDFSETTINGS=$set ...
```

Dois defeitos herdados desses presets:

1. **"Recomendada" muitas vezes não comprimia nada.**
   O preset `/ebook` só reamostra imagens acima de **1,5× o alvo** (`DownsampleThreshold=1.5`),
   ou seja, acima de 225 dpi. E, pior, o `pdfwrite` vem com `-dPassThroughJPEGImages=true`:
   **JPEG já existente é copiado byte a byte, sem recompressão**. Uma digitalização de 200 dpi
   (o padrão da maioria dos scanners de cartório) passava intacta pelos dois filtros.

   Medido aqui com um PDF digitalizado de 200 dpi:

   | | tamanho | redução |
   |---|---|---|
   | original | 2.000.238 B | — |
   | `/ebook` (antigo "Recomendada") | 2.000.150 B | **0,0 %** |

   Daí a mensagem *"já estava otimizado"*.

2. **"Máxima compressão" destruía o documento.**
   `/screen` reamostra para **72 dpi** usando o filtro `/Average`. Em texto digitalizado isso
   borra os glifos. Medindo a legibilidade por OCR (similaridade do texto reconhecido contra o
   texto reconhecido no original):

   | | tamanho | redução | legibilidade |
   |---|---|---|---|
   | original 300 dpi | 10,95 MB | — | 1,000 |
   | `/screen` (antigo "Máxima") | 0,52 MB | 95,3 % | **0,146** |

   0,146 é exatamente o que você descreveu: quase ilegível.

## O que o motor v2 faz

Nada de preset. Todos os parâmetros são explícitos:

| Parâmetro | Valor | Por quê |
|---|---|---|
| `-dPassThroughJPEGImages=false` | desligado | **a correção principal** — força recompressão dos JPEGs já embutidos |
| `-dPassThroughJPXImages=false` | desligado | idem para JPEG 2000 |
| `-dColorImageDownsampleThreshold=1.0` | 1.0 | reamostra sempre que estiver acima do alvo (antes: só acima de 1,5×) |
| `-dColorImageDownsampleType=/Bicubic` | bicúbico | preserva a borda das letras; `/Average` (do `/screen`) borra |
| `/QFactor` via `setdistillerparams` | 0,25 a 1,7 | controle fino da qualidade JPEG, que os presets não expõem |
| `-dMonoImageFilter=/CCITTFaxEncode` | G4 | imagens 1 bit comprimem muito **sem nenhuma perda** |
| `-dDetectDuplicateImages=true` | ligado | logotipos/carimbos repetidos viram um objeto só |
| `-dCompatibilityLevel=1.7` + `-dSubsetFonts` | ligado | object streams e subset de fontes encolhem a estrutura |
| `-sColorConversionStrategy=Gray` | condicional | digitalização colorida de documento P&B encolhe 30–60 % a mais |

Mais quatro comportamentos novos:

- **Escada progressiva.** Cada nível tem 3 passos. Se o primeiro não atingir a meta de redução,
  tenta o próximo — **sempre dentro do piso de legibilidade daquele nível**. É isso que acaba com
  o "já está compactado".
- **Calibração por amostra.** Em arquivos grandes (> 25 MB ou > 40 páginas) a escada roda numa
  amostra de 4 páginas; só o passo vencedor é aplicado ao documento inteiro. Evita processar
  100 MB três vezes.
- **Tons de cinza automático.** Usa o device `inkcov` do Ghostscript para medir a cobertura CMYK.
  Se as páginas forem cromaticamente neutras (C≈M≈Y), converter para cinza é seguro e gratuito
  em qualidade. Você pode forçar ou desligar pelo seletor "Cores".
- **Nunca devolve arquivo maior.** Se nenhuma tentativa ficar menor que o original, o módulo
  devolve o próprio original e avisa — em vez de entregar um PDF inchado.

## Resultado medido

Mesmo PDF digitalizado de 300 dpi, 4 páginas, 10,95 MB. Legibilidade = similaridade do OCR
contra o OCR do original (1,000 = idêntico):

| | tamanho | redução | legibilidade |
|---|---|---|---|
| `/screen` — **antigo "Máxima"** | 0,52 MB | 95,3 % | 0,146 ❌ |
| `/ebook` — **antigo "Recomendada"** | 1,44 MB | 86,8 % | 0,963 |
| **novo — Alta qualidade** | 9,55 MB | 12,8 % | 0,985 |
| **novo — Recomendada** | 2,99 MB | 72,7 % | **0,991** |
| **novo — Máxima compressão legível** | 1,32 MB | 88,0 % | **0,956** |
| **novo — Compressão extrema** | 0,81 MB | 92,6 % | **0,953** |

O "Compressão extrema" novo é praticamente do tamanho do `/screen` antigo (0,81 MB × 0,52 MB) e
**6,5× mais legível**. O "Máxima compressão legível" é menor que o `/ebook` antigo *e* com
qualidade melhor.

E no caso que não comprimia nada (digitalização de 200 dpi, 1,91 MB):

| nível | redução |
|---|---|
| Recomendada | **32,6 %** (antes: 0,0 %) |
| Máxima compressão legível | **60,8 %** |
| Compressão extrema | **75,5 %** |

## Níveis

| Nível | dpi | QFactor | Uso |
|---|---|---|---|
| Alta qualidade | 300 → 250 | 0,25 → 0,55 | vai ser impresso ou passar por OCR |
| **Recomendada** (padrão) | 200 → 150 | 0,45 → 0,80 | uso geral, arquivamento |
| Máxima compressão legível | 150 → 120 | 0,80 → 1,20 | anexar em processo, e-mail |
| Compressão extrema | 120 → 100 | 1,10 → 1,70 | limite de upload apertado |

## Arquivos alterados

| Arquivo | O quê |
|---|---|
| `config_forja.php` | motor novo: `forja_comprimir_pdf()`, `forja_gs_tentativa()`, `forja_gs_estrutural()`, `forja_pdf_neutro()`, `forja_pdf_tem_imagens()`, `forja_pdf_amostra()`, `forja_perfis_compressao()`, `forja_pdf_previa_jpeg()`, `forja_gc()` |
| `comprimir.php` | recebe `cinza`, devolve JSON detalhado (dpi aplicado, tentativas, avisos) e um token do original para a prévia comparativa |
| `ver.php` | **novo** — exibe o PDF *inline* no visualizador nativo do navegador (`Content-Disposition: inline`), com suporte a `Range` para carregar só as páginas visíveis |
| `previa.php` | **novo** — renderiza uma página como JPEG a 150 dpi; usado só como plano B quando o navegador não exibe PDF embutido |
| `index.php` | seletor de 4 níveis + seletor de cores, dicas contextuais, selos de resultado e prévia em PDF real (comprimido / original / lado a lado) |

`forja_comprimir_pdf()` continua aceitando os nomes antigos (`tela`, `recomendado`, `alta`,
`maxima`), então qualquer chamada existente em outro ponto do Atlas continua funcionando.

## Observações de implantação

- Precisa de Ghostscript **9.50+** (testado em 10.02). O `setdistillerparams` é escrito num
  `.ps` temporário e passado como primeiro arquivo de entrada — isso evita o problema clássico do
  `cmd.exe` no Windows interpretar `<<` e `>>` como redirecionamento.
- Depois de subir, **reinicie o Apache** (OPcache).
- `comprimir.php` chama `forja_gc(6)`, que apaga temporários e saídas com mais de 6 horas.
- O `memory_limit` do `comprimir.php` subiu para 1024M (o Ghostscript roda fora do PHP, mas o
  upload de 200 MB passa por ele).

## Prévia da qualidade

A prévia usa o **visualizador de PDF do próprio navegador**, não uma imagem rasterizada — assim
o que você vê é exatamente o arquivo que será baixado, com o zoom real do documento. Três modos:
**Comprimido**, **Original** e **Lado a lado** (dois visualizadores sincronizados na mesma página).

Detalhes de implementação:

- `ver.php` responde `Content-Disposition: inline` e `Accept-Ranges: bytes`. O visualizador do
  Chrome/Edge/Firefox faz *byte serving*, então abrir um original de 100 MB não baixa os 100 MB —
  só os trechos das páginas exibidas.
- `@session_write_close()` é chamado antes de servir o arquivo. Sem isso, o lock da sessão do PHP
  faria os dois visualizadores do modo "lado a lado" carregarem **em fila** em vez de em paralelo.
- `ETag` + `Cache-Control: private, max-age=900` evitam recarregar o mesmo PDF ao alternar entre os modos.
- O campo "pág." reposiciona os dois visualizadores via `#page=N&view=FitH` (PDF Open Parameters).
- Se o navegador não exibir PDF embutido (alguns navegadores de celular), o link
  *"Não carregou? Ver como imagem"* volta para o `previa.php`, agora a 150 dpi e JPEG 92.
