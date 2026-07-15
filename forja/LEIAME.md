# Atlas Forja — Ferramentas de PDF

Módulo do Atlas para: **comprimir PDF**, **PDF → imagens**, **imagens → PDF** e **juntar PDFs**.
Instale como a pasta `forja/` no mesmo nível de `oficios/` (usa `../menu.php`, `../rodape.php`,
`../script/`, `../style/` e as libs TCPDF/FPDI de `../oficios`).

## Operações
- **Comprimir PDF** — reduz o tamanho (Ghostscript). Níveis: máxima / recomendada / alta qualidade.
- **PDF → Imagens** — uma imagem por página (PNG ou JPG, 100/150/300 DPI), entregue em um ZIP.
- **Imagens → PDF** — junta várias imagens (PNG/JPG) em um PDF (tamanho da imagem ou ajustado em A4).
- **Dividir PDF** — divide um PDF em N partes (ou N páginas por parte), entregue em um ZIP.
- **Word → PDF** e **PDF → Word** — conversão via LibreOffice headless.
- **Juntar PDFs** — combina vários PDFs em um só, na ordem escolhida.

## Ferramentas externas
- **Ghostscript** (obrigatório para *comprimir* e *PDF→imagens*): ghostscript.com
- **ImageMagick** (alternativa opcional): imagemagick.org
- **LibreOffice** (obrigatório para *Word ↔ PDF*): libreoffice.org
- **Imagens→PDF** e **Juntar PDFs** funcionam só com PHP (TCPDF/FPDI), sem ferramentas externas.

Configure os caminhos em **Configurar** (só administrador) — ou deixe em branco para detecção
automática. Há um botão **Testar ferramentas** que mostra o que foi encontrado.

## Ativação (padrão Vertex)
O card no início do Atlas é controlado por `forja/config_forja.json`:
```json
{ "forja_ativo": "S", "gs_path": "", "magick_path": "" }
```
`"S"` mostra o card; `"N"` (ou sem o arquivo) oculta.

## Requisitos
- PHP com **cURL não é necessário**; precisa de **ZipArchive** (PDF→imagens) e **finfo** (padrão no XAMPP).
- Limite de 60 MB por arquivo.

## LibreOffice sem instalar em cada servidor
Em Configurar há "Instalar LibreOffice automaticamente": cole a URL de um .zip do LibreOffice portátil (hospedado internamente) ou de um .msi oficial (Windows). O módulo baixa, extrai para forja/libreoffice/ e configura o caminho sozinho — sem instalação nem administrador. A pasta forja/libreoffice/ também é detectada automaticamente se você copiá-la manualmente.
