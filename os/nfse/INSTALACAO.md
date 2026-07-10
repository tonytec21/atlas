# Atlas O.S. — NFS-e Nacional (serviços notariais e de registro)

`ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional`

Integração do módulo de Ordem de Serviço com o **Emissor Nacional da NFS-e** (SEFIN Nacional),
usando o SDK [`nfse-nacional/nfse-php`](https://github.com/nfse-nacional/nfse-php) e **certificado A1**.

---

## 1. Fundamentação normativa das regras codificadas

| Regra no código | Fundamento |
|---|---|
| `cTribNac = 210101` | Subitem **21.01** da lista anexa à **LC 116/2003** — "serviços de registros públicos, cartorários e notariais" — desdobrado em `21.01.01` na tabela nacional |
| Emissão obrigatória pelo Ambiente Nacional | **LC 214/2025, art. 62, §1º, I** — todos os municípios adotam o Ambiente Nacional da NFS-e a partir de **01/01/2026** |
| `regEspTrib = 4` (Notário ou Registrador) | Tabela de Regime Especial de Tributação do leiaute da DPS |
| `opSimpNac = 1` (não optante) | **LC 123/2006, art. 17, XI** — vedação do Simples Nacional às serventias extrajudiciais |
| NFS-e **consolidada** e "Tomador não informado" | Regime **transitório do exercício de 2026** |
| NFS-e **individualizada por ato** + tomador identificado | Obrigatório a partir de **01/01/2027**. O sistema alterna sozinho (`nfse_exige_individualizacao()`) |
| Discriminação com descrição objetiva do ato | Exigência do regime transitório: descrição em conformidade com a **Tabela de Emolumentos** estadual |
| Emissão na **liquidação**, nunca no **depósito prévio** | O depósito prévio é adiantamento, não preço de serviço prestado. O fato gerador do ISSQN é a prestação do serviço (ato praticado) |
| ISSQN incide sobre o preço do serviço (não é ISS fixo) | **STF, ADI 3.089** — cartórios são contribuintes do ISS sobre a receita de emolumentos |
| Base = emolumentos; FERC/FADEP/FEMP/FERRFIS fora | Repasses obrigatórios a fundos não são receita do delegatário. Configurável na tela |
| Redução de base de 12% (fator 0,88) | Replica exatamente a regra já usada em `atualizar_os.php` (`$baseISS = $totalEmol * 0.88`). Vai no grupo `vDedRed/pDR` |
| `cMotivo` do cancelamento: 1, 2 ou 9 | Schema de eventos v1.01: 1 – Erro na emissão; 2 – Serviço não prestado; 9 – Outros |
| Guarda do XML autorizado | Responsabilidade do emissor. A partir de 01/07/2026 a API oficial de geração do DANFSe é descontinuada e o layout passa a ser responsabilidade do sistema emissor (NT 008/2026) |

> **Confira antes de ligar em produção.** As alíquotas, a inscrição municipal e o tratamento da base
> de cálculo dos emolumentos variam por município. A tela de configuração tem o botão
> *"Consultar alíquota do 210101"*, que lê o valor direto do Ambiente Nacional. Valide com a
> Secretaria de Finanças de Bom Jardim/MA e com o contador da serventia. Isto é um módulo de
> software, não um parecer tributário.

---

## 2. Requisitos

- PHP **≥ 8.1** (o SDK usa enums e argumentos nomeados)
- Extensões: `openssl`, `curl`, `dom`, `zlib`, `pdo_mysql`
- Composer
- Certificado **A1** (`.pfx` / `.p12`) válido, em nome da serventia (CNPJ) ou do delegatário (CPF)
- Inscrição municipal ativa no cadastro de contribuintes do município

Se o Atlas O.S. ainda roda em PHP < 8.1, os hooks se desativam sozinhos (guarda `PHP_VERSION_ID`)
e o resto do módulo continua funcionando normalmente.

---

## 3. Instalação

```bash
cd C:\xampp\htdocs\<projeto>\os\nfse

composer install          # ou: composer require nfse-nacional/nfse-php

php tools\patch_tls.php   # corrige o HTTP 403 do IIS da SEFIN (TLS 1.3)
```

Depois:

1. Reinicie o **Apache** (o OPcache guarda o bytecode antigo).
2. Acesse `os/nfse/nfse_config.php` como administrador.
3. As tabelas `nfse_config`, `nfse_notas` e `nfse_log` são criadas sozinhas no primeiro acesso.

### Sobre o `patch_tls.php`

O front-end IIS da SEFIN Nacional devolve **403** quando a negociação sobe para TLS 1.3.
O SDK já força HTTP/1.1, mas não limita a versão do TLS. O patch acrescenta
`CURLOPT_SSLVERSION => CURL_SSLVERSION_MAX_TLSv1_2` aos clientes HTTP do SDK.
É idempotente e gera `.bak`. **Rode novamente após cada `composer update`.**

---

## 4. Configuração

Em `os/nfse/nfse_config.php`:

1. **Certificado A1** — envie o `.pfx` e a senha. O arquivo é validado (senha, validade,
   tamanho de chave) e gravado **cifrado com AES-256-GCM** na coluna `nfse_config.cert_blob`.
   A chave mestra vive em `certs/.nfse.key`, bloqueada por `.htaccess`.

   > **Backup.** Sem `certs/.nfse.key`, o certificado no banco é irrecuperável.
   > Guarde a chave junto com o dump do banco, em cofre separado.

   > **OpenSSL 3.** Se o upload falhar com *"digital envelope routines::unsupported"*, o `.pfx`
   > usa cifra legada (RC2-40). Reempacote:
   > ```
   > openssl pkcs12 -legacy -in original.pfx -nodes -out tmp.pem
   > openssl pkcs12 -export -in tmp.pem -out novo.pfx
   > ```

2. **Prestador** — CPF/CNPJ, inscrição municipal, nome, endereço completo e código IBGE
   do município. Bom Jardim/MA: verifique o código de 7 dígitos na tabela do IBGE.

3. **Tributação** — alíquota do ISSQN, composição do valor do serviço e redução da base.
   A tela mostra uma simulação com R$ 100,00 de emolumentos.

4. **Testes** — *"Verificar adesão do município"* e *"Consultar alíquota do 210101"*
   batem no Ambiente Nacional com o certificado instalado.

5. Só então marque **Habilitar emissão**. Comece em **Homologação**.

O botão de salvar recusa habilitar a emissão com configuração incompleta e recusa
retroceder a numeração da DPS em produção.

---

## 5. Como a emissão acontece

```
Criar O.S.  →  Depósito prévio (pagamento)     →  NÃO emite nota
            →  Liquidar ato / Liquidar tudo     →  FATO GERADOR
                                                →  hook nfse_hook_pos_liquidacao()
                                                →  DPS assinada → SEFIN Nacional → NFS-e
```

- **Emissão automática** (opcional): dispara quando *todos* os itens ficam liquidados.
- **Emissão manual**: botão no painel *NFS-e Nacional* dentro de `visualizar_os.php`.
- O.S. **sem valor tributável** (ato gratuito/isento) não gera nota — fica registrado no `nfse_log`.
- O pseudo-item `ato = 'ISS'` (repasse do imposto ao usuário) **nunca** entra no valor do serviço.
- Atos marcados `(isento)` e os pseudo-atos `0`, `00`, `9999` ficam fora da base.
- Um `GET_LOCK` por O.S. impede DPS duplicada em cliques simultâneos.
- A numeração da DPS é atômica (`LAST_INSERT_ID(ultimo_numero_dps + 1)`).

---

## 6. Arquivos

| Arquivo | Papel |
|---|---|
| `nfse_lib.php` | Núcleo: cripto, migrações, apuração, montagem da DPS, emissão, cancelamento |
| `nfse_config.php` | Página de configuração (admin) |
| `nfse_salvar_config.php` | Persistência da configuração, com validações |
| `nfse_upload_certificado.php` | Upload/remoção do A1 |
| `nfse_testar.php` | Testes de convênio e alíquota |
| `nfse_emitir.php` | Emissão via AJAX |
| `nfse_cancelar.php` | Evento 101101 (admin) |
| `nfse_consultar.php` | Reconsulta e ressincroniza status |
| `nfse_xml.php` | Download do XML autorizado |
| `nfse_notas.php` | Monitor de notas emitidas |
| `nfse_painel_os.php` | Painel embutido em `visualizar_os.php` |
| `tools/patch_tls.php` | Patch de TLS do SDK |

### Alterações em arquivos existentes

- `liquidar_os.php` — hook após o commit; a resposta JSON ganhou a chave `nfse`
- `liquidar_ato.php` — hook após o commit (só dispara quando tudo está liquidado)
- `visualizar_os.php` — `include` do painel antes do bloco "Itens da Ordem de Serviço"

Todos os hooks são *best-effort*: qualquer falha na NFS-e é registrada em `error_log` e em
`nfse_log`, **sem** interromper a liquidação.

---

## 7. Pendências conhecidas

- **DANFSe.** A API oficial de geração do PDF é descontinuada em 01/07/2026 (NT 008/2026).
  O módulo entrega o XML; o DANFSe em A4 com QR Code e campos de IBS/CBS precisa ser
  implementado com TCPDF, como nos demais relatórios do Atlas.
- **IBS/CBS.** O grupo `IBSCBS` do leiaute ainda é opcional. Quando se tornar obrigatório,
  os campos entram em `nfse_montar_dps()`.
- **Substituição de NFS-e** (evento `105102`) não implementada — hoje só cancelamento.
- **Modo individualizado** gera uma DPS por item da O.S. Em O.S. com muitos atos, considere
  processar em fila (worker) em vez de síncrono no clique.
