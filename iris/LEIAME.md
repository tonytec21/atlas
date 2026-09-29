# Atlas Iris 2.0.1 — Extração de texto e dados (OCR) de imagens e PDFs

Módulo do Atlas que usa o **Gemini** para transcrever, na íntegra e fiel ao original,
documentos impressos e **manuscritos**. Também extrai os **dados estruturados**
(certidões, matrículas, documentos de identificação etc.) para conferência.

Instale como a pasta `iris/` no mesmo nível dos demais módulos (usa `../menu.php`,
`../rodape.php`, `../script/` e `../style/`). As tabelas são criadas e atualizadas sozinhas.

## Como usar
1. Em **Configurar**, cadastre a chave da API do Gemini e clique em **Testar e listar modelos**.
2. Na tela principal, arraste um ou vários arquivos: PDF, TIFF (inclusive multipágina/G4), JPG, PNG, WEBP ou HEIC.
   Também dá para colar uma imagem com Ctrl+V.
3. Escolha **o que extrair** (Texto, Dados, Texto + dados) e a **precisão**:
   - **Padrão**: uma leitura em alta resolução por página.
   - **Máxima (manuscritos)**: a página é enviada inteira e em duas ampliações,
     depois passa por uma **segunda leitura de verificação** feita pelo modelo de verificação.
4. Revise lado a lado com a imagem. Leituras incertas aparecem como `[?palavra?]` (amarelo) e
   `[ilegível]` (vermelho). A aba **Dúvidas** lista cada uma: clique para localizar no texto e na
   página. **F8** vai para a próxima dúvida.
5. Para um trecho difícil, ative **Reler região**, desenhe um retângulo sobre ele e aplique o
   resultado no texto. Se houver uma seleção no editor, ela é substituída.
6. Salve com **Ctrl+S**. Tudo fica no **Histórico**, que tem busca por nome, texto e dados.

## Recursos
- Processamento **página a página**: sem limite de páginas nem estouro de tokens em PDFs longos.
  As páginas são processadas em paralelo (configurável) e dá para escolher quais extrair.
- Visualizador com zoom (Ctrl + roda do mouse), rotação por página e **realce de tinta apagada**
  (o realce também vale para o envio à IA). Use PageUp/PageDown para navegar entre as páginas.
- **Texto digital do PDF**: quando a página já tem texto, é possível usá-lo sem gastar IA.
- **Vocabulário da serventia** (Configurar): nomes, municípios, oficiais antigos. A IA usa esse
  vocabulário só para desempatar leituras ambíguas de manuscritos.
- Marcações padronizadas: `[assinatura: Nome]`, `[rubrica]`, `[carimbo: …]`, `[selo: …]`,
  `[riscado: …]`, `[entrelinha: …]`, `[margem: …]`, `[Página N]`. O menu **Marcações** remove
  essas marcações em bloco.
- **Tipos de documento** configuráveis, com campos, dicas e listas (tabelas). Os tipos de fábrica são:
  certidões de nascimento, casamento e óbito, matrícula de imóvel, RG/CIN/CNH, comprovante de
  endereço, procuração e escritura pública. Há detecção automática do tipo e o modo **Livre**.
- Validação dos campos:
  - CPF e CNPJ (dígitos verificadores);
  - datas, inclusive **por extenso e com grafia antiga** ("mil oitocentos e noventa e dous");
  - CEP e valores em reais;
  - **matrícula de certidão** (32 dígitos), decomposta em CNS, livro, folha e termo e
    **conferida** com os campos extraídos.
- Exportação: .txt, **.docx** (com as dúvidas realçadas em amarelo), .json, e .csv dos dados.
- **Fila** de vários arquivos, com "Extrair a fila toda".
- Aviso quando o mesmo arquivo já foi extraído antes.
- Estatísticas de uso por modelo e por usuário (tokens de entrada, saída e raciocínio).
- Novas tentativas automáticas (429/5xx) e **modelo reserva**.
- Recuo automático de parâmetros que o modelo não aceita.

## Modelos
Todas as extrações usam sempre o **modelo padrão** definido em Configurar; o usuário não escolhe o modelo.
Os modelos de fábrica são `gemini-3.1-flash-lite` (padrão), `gemini-3.5-flash` e `gemini-3.1-pro-preview`.
Na instalação existente, o identificador `gemini-3.1-pro` é migrado para `gemini-3.1-pro-preview`.
Para manuscritos, recomenda-se o **Pro** como modelo de verificação (Configurar → Leitura e manuscritos).

## Requisitos
- PHP 7.4+ (recomendado 8.x) com **cURL**, **OpenSSL**, **fileinfo** e **zip** (esta última para o .docx).
  O XAMPP já traz todas.
- `upload_max_filesize` / `post_max_size` de pelo menos 8 MB. O XAMPP vem com 40 MB.
  A tela adapta o tamanho das imagens ao limite do servidor.
- Bibliotecas locais (sem CDN) em `vendor/`: pdf.js 3.11 (Apache-2.0), UTIF.js (MIT), pako (MIT).

## Pastas protegidas (.htaccess "deny")
- `seguranca/`: chave-mestra que criptografa a chave da API. **Não vem no pacote** para não
  sobrescrever a do servidor. Se faltar, é recriada (e aí a chave da API precisa ser cadastrada de novo).
- `arquivos/`: originais guardados no histórico (com retenção configurável).
- `tmp/`: arquivos temporários.

## Atualização a partir da 1.x
Copie os arquivos por cima da pasta `iris/` e reinicie o Apache (OPcache). Os arquivos
`modelo_excluir.php`, `modelo_padrao.php`, `modelo_salvar.php` e `salvar_config.php` não são mais
usados (agora tudo passa por `api.php`) e podem ser apagados. O `extrair.php` continua funcionando
para compatibilidade.

## Histórico de versões
- **2.0.1**: removida a escolha de modelo na tela de extração; todas as extrações usam o modelo padrão.
- **2.0.0**: reestruturação completa. Leitura página a página, modo Máxima precisão com
  verificação, releitura de região, dúvidas destacadas, dados estruturados com validação,
  tipos de documento, histórico, fila, TIFF/HEIC, DOCX, estatísticas, vocabulário e modelo reserva.
- **1.x**: extração do arquivo inteiro em uma chamada, com editor de texto.
