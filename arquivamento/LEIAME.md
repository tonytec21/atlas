# Atlas · Arquivamento Digital — versão remodelada

Substituição completa do módulo `arquivamento/`. Mantém o formato de dados em
disco da versão anterior (JSON em `meta-dados/`, binários em `arquivos/`), então
**o acervo existente continua funcionando sem conversão**.

---

## 1. Instalação

### 1.1 Faça backup

Antes de qualquer coisa, no servidor:

```bat
cd C:\xampp\htdocs\atlas
xcopy arquivamento arquivamento-backup-20260731\ /E /I /H /Y
```

O backup precisa incluir `meta-dados\`, `arquivos\`, `lixeira\` e
`categorias\` — são os dados reais do cartório.

### 1.2 Substitua os arquivos de código

O ZIP **não traz** o conteúdo de `meta-dados/`, `arquivos/`, `lixeira/` e
`categorias/categorias.json` — só as pastas com os arquivos de proteção. Isso é
proposital: extrair por cima nunca vai sobrescrever documento nenhum.

Extraia o ZIP sobre a pasta `atlas/arquivamento/`, confirmando a substituição
dos `.php`. Depois **apague** os arquivos abaixo, que sobraram da versão antiga
e não são mais usados:

```
atos.json
capa-arquivamento (1).php
selos_arquivamento.php   (se existir com esse nome no singular)
```

### 1.3 Crie o `config.local.php`

Na raiz do módulo, ao lado do `config.php`. Este arquivo tem as credenciais
reais e **não deve ir para o Git**:

```php
<?php
define('ARQ_DB_HOST', 'localhost');
define('ARQ_DB_NAME', 'atlas');
define('ARQ_DB_USER', 'atlas_app');
define('ARQ_DB_PASS', 'a-senha-real');

// 'dev' mostra o detalhe do erro na tela; 'producao' esconde.
define('ARQ_AMBIENTE', 'producao');

// Ative quando o Atlas estiver atrás de HTTPS:
define('ARQ_COOKIE_SECURE', false);

// Dias que um item fica na lixeira antes de poder ser expurgado.
define('ARQ_LIXEIRA_DIAS', 90);
```

O `config.php` traz os valores padrão e é sobrescrito pelo local. Não edite o
`config.php` em produção.

> **Recomendação:** crie um usuário MySQL dedicado (`atlas_app`) com permissão
> só nas tabelas que o Atlas usa, em vez de `root` sem senha. O módulo antigo
> conectava como root — qualquer SQL injection em qualquer módulo dava controle
> total do banco.

### 1.4 Confira o caminho do TCPDF

A capa em PDF procura a biblioteca nesta ordem:

```
../oficios/tcpdf/tcpdf.php
../tcpdf/tcpdf.php
../vendor/tecnickcom/tcpdf/tcpdf.php
./tcpdf/tcpdf.php
```

Se no seu servidor ela estiver em outro lugar, acrescente o caminho em
`lib/Capa.php`, função `arq_carregar_tcpdf()`.

### 1.5 Permissões (produção Linux)

No XAMPP/Windows não é preciso mexer. Em servidor Linux:

```bash
chown -R www-data:www-data meta-dados arquivos lixeira logs categorias arquivos-assinados
chmod -R 770 meta-dados arquivos lixeira logs categorias arquivos-assinados
```

### 1.6 Reinicie o Apache

Obrigatório por causa do OPcache — sem isso o PHP continua servindo o bytecode
dos arquivos antigos.

### 1.7 Rode a migração uma vez

Abra `http://seu-servidor/atlas/arquivamento/migrar.php`.

Ele mostra quantos anexos antigos estão sem tamanho, MIME e hash. Confira os
números, clique em **Executar migração** e depois **apague o `migrar.php` do
servidor**. Ele preenche os metadados que faltam; nada é apagado.

### 1.8 Confira o bloqueio das pastas

Abra no navegador:

```
http://seu-servidor/atlas/arquivamento/meta-dados/1756336097.json
```

Tem que dar **403 Forbidden**. Se o JSON aparecer, o Apache está com
`AllowOverride None` e ignorando os `.htaccess` — nesse caso mova o bloqueio
para o `httpd.conf`:

```apache
<DirectoryMatch "^.*/atlas/arquivamento/(meta-dados|arquivos|lixeira|logs|lib)/">
    Require all denied
</DirectoryMatch>
```

---

## 2. O que mudou na segurança

| Falha na versão anterior | Como ficou |
|---|---|
| `load_ato.php`, `get_ato.php`, `delete_ato.php`, `save_ato.php`, `restore_ato.php`, `get_selo*.php` e as APIs de categoria não checavam sessão | Toda entrada passa por `arq_exige_login()`. Chamadas AJAX sem sessão recebem 401 com `redirect`; páginas recebem 302 |
| `save_signed_pdf.php` gravava `base64_decode($_POST['pdfData'])` no nome vindo do cliente | O nome é gerado no servidor, o conteúdo precisa começar com `%PDF-`, o destino é fixo em `arquivos-assinados/` (com PHP desligado) |
| `$_GET['id']` concatenado em caminho de arquivo | `arq_id_valido()` só aceita dígitos; `arq_caminho_seguro()` resolve com `realpath` e confere o prefixo |
| Credenciais do selador no código (`gerar_selo.php`) e root sem senha (`db_connection.php`) | Tudo em `config.local.php`, fora do versionamento. O `gerar_selo.php` virou stub |
| Nenhuma escrita tinha CSRF | Token por sessão, exigido em header `X-CSRF-Token` ou campo `_csrf`. Sem ele: 419 |
| Nomes de parte, descrição e nome de anexo iam para `innerHTML` crus | Toda saída passa por `esc()` no JS e `arq_e()` no PHP |
| Upload aceitava qualquer coisa, inclusive `.php`, e o arquivo era gravado com o nome recebido | Qualquer formato continua sendo aceito (é um acervo de cartório), mas extensões executáveis no servidor são gravadas com sufixo `.bin`, o diretório tem `.htaccess` que desliga o PHP, e o download sempre passa por `arquivo.php` |
| Anexos servidos direto pelo Apache | `arquivo.php` autenticado, com `Content-Type` da allowlist, `nosniff`, CSP `sandbox` e suporte a Range |
| `shell_exec('ping ...')` em `verificar_ip.php` | `gethostbyname()`, sem shell |
| `CURLOPT_SSL_VERIFYPEER = false` | Mantido só onde o selador exige (certificado interno do TJMA), isolado e documentado |
| mysqli com concatenação | PDO com prepared statements em toda consulta |
| Nenhum registro de quem fez o quê | Trilha em `logs/auditoria-AAAA-MM.jsonl`: quem viu, baixou, alterou, excluiu, de qual IP |

Os endpoints antigos continuam existindo como ponte (`load_atos.php`,
`save_ato.php`, `delete_ato.php`, etc.) para não quebrar a integração com o
módulo **Tarefas**. Eles agora exigem sessão e conferem a origem da requisição,
e registram no log do PHP toda vez que são chamados — assim dá para acompanhar
o que ainda depende deles e desativar quando não houver mais chamadas.

---

## 3. Como funciona a compilação em PDF único

O botão **Compilar dossiê** junta vários anexos — imagens e PDFs — num
documento só, com capa e índice.

### Por que a junção acontece no navegador

O corte é entre gerar e *importar* PDF. TCPDF gera muito bem, mas não lê PDF
existente; quem lê é o FPDI, e a versão gratuita só abre até **PDF 1.4**.
Documento de cartório hoje vem de scanner, do Word ou da impressão do Chrome —
quase tudo é PDF 1.5+ com tabela de referência cruzada comprimida. Na prática o
FPDI livre falharia na maioria dos casos, e a versão que resolve isso é paga por
servidor.

A **pdf-lib** (`assets/vendor/pdf-lib.min.js`, MIT) não tem essa limitação e roda
no navegador do escrevente. Divisão final:

1. O navegador baixa os anexos pela rota autenticada `arquivo.php`.
2. Conta as páginas de cada PDF e converte imagens (WebP/GIF/BMP passam por
   canvas e viram JPEG; JPG e PNG entram direto).
3. Pede a capa ao servidor (`compilar.php?formato=capa`), enviando a contagem
   de folhas — por isso o índice sai com o intervalo certo (`1–3`, `4`, `5–12`).
4. O TCPDF devolve capa + índice, com timbrado se `style/configuracao.json`
   estiver com `"timbrado":"S"`, e com os selos e QR codes vinculados.
5. A pdf-lib costura tudo, carimba `fl. N/M` em cada folha do corpo
   (respeitando páginas giradas) e grava os metadados.

Efeitos colaterais bons: não consome CPU nem memória do servidor, e um dossiê
de 300 MB não passa pelo PHP.

Formatos que a pdf-lib não incorpora (DOCX, XLSX, TXT, P7S) ficam listados numa
seção da capa como "não incorporados", com a orientação de usar o ZIP.

### O download em ZIP

Alternativa quando é preciso manter os originais. Traz um `MANIFESTO.txt` com o
SHA-256 de cada arquivo e a linha de conferência:

```bat
certutil -hashfile "Crf_001_2025.pdf" SHA256
```

Serve para provar que o documento entregue é bit a bit o que está no acervo.

---

## 4. Mapa dos arquivos

```
config.php / config.local.php   Configuração (o local sobrescreve o outro)
bootstrap.php                   Sessão, CSRF, PDO, helpers — todo arquivo começa aqui
_compat.php                     Ponte para os endpoints da versão antiga

lib/Caminhos.php                Contenção de path traversal, nomes seguros
lib/Repositorio.php             Leitura/escrita dos JSON, índice em cache, filtros
lib/Uploads.php                 Validação e gravação de anexos
lib/Auditoria.php               Trilha JSONL e limite de taxa
lib/Selos.php                   Consulta dos selos digitais (PDO)
lib/Capa.php                    Geração da capa/índice em TCPDF

index.php                       Acervo: busca, filtros, seleção múltipla, compilação
cadastro.php                    Formulário em 3 passos (criar e editar)
lixeira.php                     Retenção, restauração e expurgo
categorias.php                  Gestão de categorias
migrar.php                      Utilitário de instalação (apagar depois)

api/listar.php                  Busca com filtros e paginação
api/obter.php                   Detalhe + selos + auditoria
api/salvar.php                  Criação e edição
api/lixeira.php                 Excluir / restaurar / expurgar
api/categorias.php              CRUD de categorias
api/estatisticas.php            KPIs e séries do painel

arquivo.php                     Entrega autenticada de anexo (com Range)
compilar.php                    manifesto | zip | capa
capa_arquivamento.php           Capa avulsa para juntada física

assets/css/arquivamento.css     Sistema visual do módulo
assets/js/arquivamento.js       Tela do acervo
assets/js/compilador.js         Junção de PDF no navegador
assets/vendor/pdf-lib.min.js    pdf-lib 1.17.1 (MIT)
```

---

## 5. Operação no dia a dia

**Compilar vários dossiês:** marque os registros, use **Compilar** na barra
flutuante. Na bandeja dá para reordenar os documentos arrastando antes de gerar.

**Lixeira:** exclusão manda para `lixeira/` e o item fica visível pelo prazo de
`ARQ_LIXEIRA_DIAS`. O expurgo definitivo exige perfil autorizado
(`arq_perfis_expurgo()` no `config.php`) e digitar o número do arquivamento.

**Auditoria:** `logs/auditoria-AAAA-MM.jsonl`, uma linha JSON por evento. Os
últimos eventos de cada registro aparecem no painel de detalhe. É arquivo de
append — para consulta pesada, importe num banco.

**Renomear categoria** reclassifica automaticamente todos os registros que
usavam o nome antigo. Excluir só é permitido se a categoria não estiver em uso.

---

## 6. Verificação depois de instalar

Rode esta lista uma vez:

1. `meta-dados/qualquer.json` pelo navegador → **403**
2. Sair da sessão e abrir `api/listar.php` → **401** com JSON
3. Cadastrar um arquivamento com um anexo → aparece no acervo
4. Renomear um `.php` para `.pdf` e tentar anexar → recusado com aviso de
   "conteúdo não corresponde à extensão"
5. Abrir o anexo pelo visualizador → abre; copiar a URL e abrir em janela
   anônima → 401
6. Compilar um dossiê com um PDF e uma imagem → PDF único, índice com as
   folhas certas, carimbo `fl. N/M`
7. Excluir → conferir na lixeira → restaurar
8. `logs/auditoria-AAAA-MM.jsonl` com as linhas dos passos acima

---

## 7. Compatibilidade

- PHP 7.4+ (testado em 8.3). Extensões: `fileinfo`, `zip`, `curl`, `pdo_mysql`.
  `mbstring` é usado se existir, mas há equivalente interno caso falte.
- Sem CDN: pdf-lib e SweetAlert2 são servidos localmente.
- Sem jQuery no núcleo, para não conflitar com a versão carregada pelo
  `menu.php`. O SweetAlert2 é usado quando presente, com aviso nativo de
  reserva.
- Arquivos gravados com CRLF, padrão do ambiente Windows.

---

---

## 8. Sobre a aparência

O módulo tem folha de estilo própria, `assets/css/arquivamento.css`, carregada
depois do `style.css` do Atlas. Ela usa a **Inter**, a mesma família que o
`menu.php` já carrega — título e texto se distinguem pelo peso, não pela
fonte. A monoespaçada aparece só nos códigos (livro, folha, termo, protocolo,
matrícula), para que alinhem em coluna.

A paleta é teal/petróleo com âmbar para os selos, e cada atribuição tem sua cor
na lombada vertical da ficha. O modo escuro acompanha o `body.dark-mode` do
Atlas automaticamente.

### Selos

A emissão continua em `selos_arquivamentos.php`, com as credenciais lidas da
tabela `conexao_selador`. A interface de **Solicitar selo** fica no
`cadastro.php`, no passo 04, visível quando se está editando um arquivamento
existente — o selo precisa do número do arquivamento como `numeroControle`.
Livro, folha, termo, escrevente e partes são preenchidos a partir do que já
está na tela, sem redigitação.

A capa (`capa_arquivamento.php`) mantém os moldes: uma moldura de 100 mm por
selo emitido, com QR, texto e funcionário, e uma moldura vazia de 100 x 50 mm
quando ainda não há selo, para ser preenchida depois.

---

## 9. Tipos de arquivo aceitos

O acervo aceita **qualquer formato** — planilha, XML de nota, DWG, backup,
o que a serventia precisar arquivar. A constante que controla isso é
`ARQ_UPLOAD_ACEITA_TUDO`, ligada por padrão no `config.php`.

A segurança não vem da lista de tipos, e sim de três camadas independentes:

1. `arquivos/` tem `.htaccess` que nega acesso direto e desliga o motor PHP.
2. Extensões que o servidor poderia executar (`.php`, `.phtml`, `.cgi`,
   `.jsp`, `.asp`, `.py`…) são gravadas em disco com o sufixo `.bin`. O nome
   original fica nos metadados e é o que o usuário vê na tela e recebe ao
   baixar. Assim, mesmo que o Apache esteja com `AllowOverride None` e ignore
   o `.htaccess`, o arquivo nunca roda. A checagem olha todos os segmentos do
   nome, então `laudo.php.pdf` também é neutralizado.
3. `arquivo.php` só abre na tela (inline) PDF, imagens e TXT. Todo o resto sai
   como download, sempre com `X-Content-Type-Options: nosniff`.

Se em algum momento você quiser voltar para a lista branca de formatos, basta
pôr `define('ARQ_UPLOAD_ACEITA_TUDO', false);` no `config.local.php` — a
validação por MIME real volta a valer, sem alterar mais nada.

---

## 10. Diálogos

Todas as caixas do módulo — avisos, confirmações e perguntas com campo de
texto — passam por `assets/js/dialogos.js`, que expõe `ArqDlg.aviso()`,
`ArqDlg.toast()`, `ArqDlg.confirmar()` e `ArqDlg.perguntar()`. Nenhuma tela
usa `alert()`, `confirm()` ou `prompt()` do navegador.

Se o SweetAlert2 ainda não estiver na página quando um diálogo for pedido, o
helper o carrega sob demanda (primeiro `../script/sweetalert2.js`, depois o
CDN) e só então mostra a caixa. Se as duas tentativas falharem, aparece uma
faixa discreta no topo da página — nunca um diálogo nativo.

**Uma exceção que não dá para contornar:** o aviso "Sair do site? É possível
que as alterações feitas não sejam salvas." é do `beforeunload`, disparado
pelo próprio navegador. A especificação não permite conteúdo ou botões
próprios ali, justamente para que uma página não consiga impedir o usuário de
fechá-la. O que dá para controlar é *quando* ele aparece: no `cadastro.php`
ele só dispara se houver anexo selecionado e ainda não enviado, e a navegação
feita pelo próprio sistema (salvar, links do módulo) o desliga.
