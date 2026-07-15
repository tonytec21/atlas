# Atlas Iris — Extração de dados (OCR) de imagem/PDF para texto

Módulo do Atlas que usa o **Gemini** como agente de OCR para transcrever, na
íntegra e fiel ao original, o conteúdo de imagens e PDFs, entregando o texto em
um **editor de revisão** (copiar, editar, baixar .txt).

Instale como a pasta `iris/` no mesmo nível de `oficios/` (usa `../menu.php`,
`../rodape.php`, `../script/` e `../style/`).

## Como usar
1. Em **Configurar**, cadastre a **chave da API do Gemini** (Google AI Studio).
2. Na tela principal, arraste uma imagem ou PDF, escolha o modelo e clique em **Extrair texto**.
3. O texto aparece no **editor** — revise, edite, copie ou baixe em .txt.

## Modelos
- Padrão de fábrica: **Gemini 3.1 Flash Lite** (`gemini-3.1-flash-lite`).
- Também já cadastrados: **Gemini 3.5 Flash** e **Gemini 3.1 Pro**.
- Em Configurar você pode **cadastrar** novos modelos, **excluir** e **definir o padrão**.
  O *identificador* é o nome de API do Gemini (ex.: `gemini-3.1-pro`).

## Extração fiel
O prompt instrui o modelo a transcrever tudo na íntegra, preservando ordem de
leitura, quebras de linha, acentuação e pontuação, sem resumir, corrigir ou
comentar. Trechos ilegíveis são marcados como `[ilegível]`.

## Requisitos
- PHP com **cURL** e **OpenSSL** (XAMPP já traz).
- Base MySQL `atlas` (tabelas `iris_config` e `iris_modelos` criadas sozinhas).
- Limite de 20 MB por arquivo (envio inline à API). PDFs muito longos podem
  atingir o limite de tokens de saída — nesse caso, divida em partes.

## Segurança
- A chave da API é guardada **criptografada** (AES-256-GCM) na pasta `seguranca/`
  (protegida por `.htaccess`).
- Todas as ações exigem sessão ativa e token CSRF.
