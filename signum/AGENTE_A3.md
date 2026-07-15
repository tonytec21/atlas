# Atlas Signum — Protocolo do Agente A3 (token/cartão)

A assinatura A3 usa um **agente local** rodando na máquina do usuário (a que tem o
token/leitora). O navegador conversa com ele via HTTP (JSON). É o mesmo padrão do
agente que você já usa para o O.S. — basta apontar a "URL do agente" na configuração.

O endereço fica em: Configurar → método A3 → "URL do agente local"
(ex.: `http://127.0.0.1:9088/assinar`).

## Requisições que o navegador envia

### 1) (Opcional) Ler o titular do certificado
```
POST {url}    Content-Type: application/json
{ "acao": "certificado" }
```
Resposta esperada:
```json
{ "titular": "NOME DO TITULAR:CPF" }
```
(Se o agente não implementar, o carimbo usa o nome configurado.)

### 2) Assinar
```
POST {url}    Content-Type: application/json
{ "acao": "assinar",
  "conteudo_b64": "<bytes a assinar em base64>",
  "hash_sha256": "<hash hex dos mesmos bytes>" }
```
Resposta esperada:
```json
{ "cms_b64": "<PKCS#7/CMS DER em base64>" }
```
(aceita também `assinatura_b64` ou `pkcs7_b64`.)

## O que o agente deve produzir
Um **CMS/PKCS#7 SignedData destacado (detached)** sobre os bytes recebidos em
`conteudo_b64`, assinado pela chave do **token A3**, incluindo o certificado do
signatário. É exatamente o que o Windows produz com `SignedCms`:

```csharp
var content  = Convert.FromBase64String(conteudo_b64);
var signed   = new SignedCms(new ContentInfo(content), detached: true);
var signer   = new CmsSigner(certificadoDoToken); // do repositório MY (token)
signer.DigestAlgorithm = new Oid("2.16.840.1.101.3.4.2.1"); // SHA-256
signed.ComputeSignature(signer); // token pede o PIN aqui
string cms_b64 = Convert.ToBase64String(signed.Encode());
```

## Observações
- O agente precisa responder com **CORS liberado** para a origem do sistema
  (cabeçalho `Access-Control-Allow-Origin`), pois a chamada parte do navegador.
- O servidor injeta esse CMS no espaço reservado do PDF (ByteRange), gerando um
  PDF **PAdES** válido. O certificado A1 (se configurado) é usado só no seu login,
  nunca na assinatura A3.
- Se o CMS ficar maior que o espaço reservado, aumente o placeholder (constante do
  TCPDF `setSignature`) — hoje comporta ~5,8 KB de assinatura, suficiente para A3
  ICP-Brasil típico.
