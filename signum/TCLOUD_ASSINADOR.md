# Atlas Signum — TCloud Assinador (v1.7.3)

O A3 (token) tem dois assinadores, escolhidos por usuário em **Configurar → Certificado A3 → Assinador do token**: **TCloud Assinador** (padrão desde a v1.4.0) ou **Assinador SERPRO**. Na primeira execução da v1.4.0, quem estava no SERPRO só por ser o padrão antigo passa para o TCloud; depois disso, a escolha de cada usuário é respeitada.

## Instalar pela tela (quando o assinador não é detectado)

Um site não pode abrir o PowerShell nem o Terminal (os navegadores não deixam executar nada no computador). O Signum faz o mais perto disso:

- **Detecção:** o navegador não enxerga programas instalados, então no modo na estação a tela mostra só o que sabe: "detectado" (com a última vez que o app respondeu neste navegador), "não detectado" ou "não verificado". Nunca "pronto". O status vem do próprio fluxo (o app pegou o pedido, ou o link não abriu nada) e do botão **Verificar agora**.
- **Testar agora** (painel da tela de assinatura, Configurar e o "Já instalei — testar"): abre um **pedido de teste de verdade** (`tcloudsign://local` com ticket próprio, uso único, preso ao IP). O app abre a janela normal com um pequeno JSON (`teste-do-assinador.json`, título "Teste do TCloud Assinador (nada será gravado)"); o usuário escolhe o certificado e digita o PIN; o app assina (JAdES/JWS) e devolve. O Signum confere a assinatura, lê o titular do certificado (cabeçalho `x5c`) e **descarta o arquivo** — nada é gravado nem entra na lista. O app recebe "Teste concluído… Nada foi gravado." como resposta de sucesso, sem mensagem de erro.
  - "Detectado" = o app abriu o teste; "testado" = a assinatura voltou válida. Se o usuário recusar na janela do app, a tela diz que ele está instalado e que o teste foi cancelado.
  - Prazos: 2 minutos para o app abrir; 10 minutos para concluir. Sem resposta em 25 segundos, a tela diz "não detectado" e oferece instalar.
- **Faixa na página inicial:** enquanto o app não foi confirmado neste computador ("não verificado" ou "não detectado"), a página inicial mostra a faixa com **Instalar neste computador**. A janela de instalação termina com **Já instalei — testar**, que dispara o teste. Some quando o app é detectado. O mesmo botão aparece no painel lateral e na janela "Não abriu?"; em Configurar há sempre "Instalar neste computador".
- **Instalação só pelo comando (sem loja):** o Signum nunca abre a Microsoft Store; a janela "Obter um aplicativo… Procurar na Microsoft Store" é o Windows reagindo a um `tcloudsign://` num computador sem o app.
  - Sem o app confirmado neste navegador, **Assinar** e **Testar** abrem direto o **passo a passo de instalação** (comando + teclas), sem abrir link nenhum. No fim dele: **Já está instalado — assinar** / **Já instalei — testar**.
  - Se o link for aberto (app detectado antes, ou o usuário disse que já instalou) e o app não responder em 6 segundos, a própria janela troca para o passo a passo, com **Copiar comando** e **Já instalei — tentar de novo**, e continua esperando por baixo (se o app estava só perguntando "Confiar neste servidor?", segue normalmente). A caixa do Windows pode aparecer por cima; ao fechá-la, as instruções já estão na tela.
- **Por que não abre o PowerShell direto:** nenhum navegador permite executar comandos, e o Windows não tem link seguro que abra a caixa Executar ou o PowerShell. O botão copia o comando e mostra as teclas.
- **O botão copia o comando e mostra as teclas:**
  - Windows: `Win + R` → `Ctrl + V` → `Enter` → **Sim** na permissão do Windows. O comando da caixa Executar roda o `instalar.ps1 -Todos` num PowerShell **como administrador** (`Start-Process -Verb RunAs`), com TLS 1.2: instala o .NET Desktop Runtime 8 quando falta e registra o TCloud Assinador e o link `tcloudsign://` **para todos os usuários** do computador (funciona mesmo quando a senha de administrador é de outra conta). A janela fica aberta no fim (`-NoExit`). 245 dos 259 caracteres permitidos;
  - Mac: `Cmd + Espaço` → "Terminal" → `Cmd + V` → `Enter` (`curl -fsSL https://tcloudsoft.app/download/instalar-mac.sh | bash`);
  - Linux: `Ctrl + Alt + T` → `Ctrl + Shift + V` → `Enter` (`wget -qO- https://tcloudsoft.app/download/instalar-linux.sh | bash`);
  - celular, tablet, ChromeOS: avisa que o assinador roda em Windows, Mac ou Linux.
- **Detecção do sistema:** pelo navegador (`userAgentData`/`platform`/`userAgent`). Android e ChromeOS também se dizem "Linux", e o iPad se apresenta como Mac; esses vão para "outro".

O TCloud Assinador trabalha **só na estação** (link `tcloudsign://local`): o app instalado no computador de quem clicou assina o documento inteiro, e nada do TCloud roda na VM. O modo pelo serviço da VM foi descontinuado (TCloud Assinador 2.5.0) e saiu de Configurar na v1.6.0; `asg_tc_modo()` devolve sempre `local`, e o código antigo desse modo fica no módulo só por compatibilidade.

## Ofícios, notas devolutivas e O.S. (v1.7.0)

O Signum é o ponto central do TCloud Assinador para os outros módulos do Atlas:

- **Mesma escolha:** o "Assinador do token (A3)" do Configurar do Signum vale para `oficios/assinar-oficio.php`, `nota_devolutiva/assinar-nota.php` e `os/assinar-os.php`. TCloud Assinador (padrão) → fluxo novo; Assinador SERPRO → fluxo antigo, sem mudança.
- **Mesmo procedimento:** as páginas dos módulos mostram o mesmo cartão (detectado / não detectado / não verificado + **Testar agora**), a mesma faixa **Instalar neste computador** e, sem o app confirmado, **Assinar** abre o mesmo passo a passo de instalação do Signum (sem abrir o link).
- **Como funciona:**
  1. `<módulo>/tcloud_iniciar.php` gera o PDF com o **selo do próprio módulo** (a mesma rotina do fluxo SERPRO; o selo passa a dizer "TCloud Assinador") e chama `asg_tcm_iniciar()`;
  2. o Signum cria o pedido `tcloudsign://local` (o endpoint da estação é sempre `…/signum/tcloud_estacao.php`) com assinatura **invisível** — o visual é o selo do módulo;
  3. a estação assina o PDF inteiro; o Signum confere a assinatura e chama `<módulo>/tcloud_gravar.php`, que grava o arquivo e atualiza o banco exatamente como o `*_pades_finalize.php` do módulo (titular e CPF lidos do certificado; `assinatura_meta.pades = "TCloud Assinador (PAdES)"`).
- **Arquivos:** `signum/inc/tcloud_modulo.php` (ponte PHP: escolha do usuário, criação do pedido, cartão/faixa/scripts), `signum/js/tcloud_modulo.js` (liga a página), e em cada módulo `tcloud_iniciar.php` + `tcloud_gravar.php`.
- **Sem o Signum 1.7.0+** (pasta `signum/inc` ausente ou versão antiga), as páginas dos módulos não quebram: seguem pelo Assinador SERPRO e mostram um aviso.
- **Diagnóstico:** `signum/diagnostico_modulos.php` confere versões, extensões e arquivos da integração e, em **Testar página**, executa a página de assinatura do módulo mostrando o erro PHP real no lugar do "HTTP ERROR 500".
- **Novo módulo:** gerar o PDF com o selo, chamar `asg_tcm_iniciar($pdf, $nome, ['modulo'=>…, 'gravador'=>__DIR__.'/tcloud_gravar.php', 'funcao'=>…, 'dados'=>[…]])` e, na página, `asg_tcm_css()`, `asg_tcm_banner_html()`, `asg_tcm_card_html()`, `asg_tcm_scripts('tcloud_iniciar.php')` + `TcModulo.assinar({...})`.

## Configurar → TCloud Assinador

- Status **deste computador**: "detectado" (com a última vez que respondeu), "não detectado" ou "ainda não verificado", e o botão **Testar neste computador**.
- **Instruções de instalação do sistema detectado** (Windows, Mac ou Linux: teclas + comando + **Copiar comando** + **Já instalei — testar**) aparecem **só enquanto o app não foi detectado** neste computador.
- "Comandos de instalação para outros computadores" (recolhido): os três comandos, para instalar em outras estações ou atualizar.

## Modo na estação (local)

1. O usuário clica em Assinar. O Signum cria o pedido (arquivo em `uploads_tmp/tcl_*.json`) com um ticket de 256 bits, guardado só como hash.
2. O navegador abre `tcloudsign://local?u=http://<endereço do Atlas>/…/signum/tcloud_estacao.php&t=<ticket>`. O endereço é o mesmo que o navegador usou (rede local, VPN…).
3. O app da estação chama `tcloud_estacao.php?acao=ticket_abrir|ticket_documento|ticket_entregar|ticket_concluir|ticket_recusar` (contrato 3.6 do guia 2.4.0). Essas ações não usam a sessão do Atlas: a credencial é o ticket.
4. Regras garantidas pelo Signum: um único `ticket_abrir`; conferência do IP de quem clicou × IP do app em `ASG_TCL_IP_MODO` (`rede`, padrão: mesmo IP, ou IPs diferentes da rede interna / IPv4×IPv6 do mesmo computador, registrados no log do PHP; `estrito`: só o mesmo IP; `livre`: sem conferência); as demais ações só do IP que abriu; 3 minutos para abrir e 15 para concluir.
5. Antes de gravar, o Signum confere o PDF devolvido: a última assinatura precisa cobrir o arquivo inteiro (`/ByteRange` de 0 até o fim), e o titular é lido do certificado dentro da própria assinatura (`NOME:CPF` da ICP-Brasil).
6. A página acompanha pelo mesmo `tcloud_api.php?acao=status` do outro modo.

Requisitos: `tcloud_estacao.php` acessível pelas estações no mesmo endereço do Atlas; extensão `openssl` do PHP (para ler o titular — sem ela, grava "Titular do certificado"); `post_max_size` do PHP comportando o PDF assinado em base64 (cerca de 1,4× o tamanho do arquivo).

## Modo pelo serviço da VM — como o pedido chega só ao computador que está sendo usado

- O Signum cria o pedido sempre com `"modo": "link"` e informa `ip_estacao` (IP do navegador).
- O serviço devolve o link com um ticket de 256 bits, de uso único, que expira com o pedido.
- Quem abre o link é o navegador de quem clicou; com `LinkExigirMesmoIp: true` (padrão), o ticket só abre no computador com esse IP.
- O Signum troca o servidor do link (`s=`) pelo **mesmo endereço que o navegador usou para abrir o Atlas** (rede local, VPN/Tailscale…), mantendo a porta 9480. Assim a estação chega ao serviço pelo mesmo caminho e com o mesmo IP que o PHP viu — sem isso, quem acessa por VPN recebe "Este link foi gerado para outro computador". Só vale com o serviço na mesma VM do Atlas; desligue com `ASG_TC_LINK_PELO_NAVEGADOR = false`.
- Se o serviço for de versão anterior (sem link), o Signum cancela o pedido e avisa para atualizar — não cai no pareamento.

## Implantação (modo pelo serviço da VM)

1. **VM:** `TCloudSigner-Setup-2.1.2.exe /silencioso /servidor` (administrador). No `servidor.json`: `"ModoEstacoes": "link"`, `"LinkExigirMesmoIp": true`, `"PermitirOutraEstacaoDoUsuario": false`. Se a VM tiver mais de uma placa de rede, preencha `EnderecoPublico` (ex.: `http://192.168.0.10:9480`).
2. **Estações:**
   - Windows (PowerShell): `[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor 3072; irm https://tcloudsoft.app/download/instalar.ps1 | iex` — o início liga o TLS 1.2, sem o qual o PowerShell 5.1 falha com "Não foi possível criar um canal seguro SSL/TLS".
   - Mac (Terminal): `curl -fsSL https://tcloudsoft.app/download/instalar-mac.sh | bash`.
   - Precisa do driver do token.
3. **Primeira assinatura em cada estação:** o navegador pergunta se pode abrir o TCloud Assinador (marcar *sempre permitir*) e o assinador pergunta se o servidor é confiável.
4. **Porta 9480** acessível das estações pelo mesmo endereço do Atlas (ex.: `http://100.65.23.52:9480/versao` no navegador da estação). Na VPN, confira se a regra do firewall do Windows vale para o perfil de rede da interface da VPN.
5. Se houver assistente instalado na VM de testes antigos (`/com-assistente`), pode desinstalar: no modo link ele não é usado.

## Nível da assinatura

Por enquanto **básico** (sem carimbo de tempo), em `ASG_TC_NIVEL` no `config_assinatura.php`. Com a ACT configurada no `servidor.json`, troque para `'carimbo'` ou `'ltv'`; o botão "Testar carimbo de tempo" volta em Configurar.

## Constantes (config_assinatura.php)

- `ASG_VERSAO` — versão do módulo.
- `ASG_TC_NIVEL` — `basico` | `carimbo` | `ltv`.
- `ASG_TC_URL_INSTALACAO` — de onde a estação baixa o TCloud Assinador (`https://tcloudsoft.app/download`).
- `ASG_TC_CMD_WINDOWS`, `ASG_TC_CMD_MAC`, `ASG_TC_CMD_LINUX` — comandos de instalação mostrados em Configurar.
- `ASG_TC_CMD_EXECUTAR` — comando para a caixa Executar do Windows (Win + R), copiado pelo botão de instalar.
- `ASG_TC_VERSAO_MIN` — versão mínima do serviço (`2.0.0`).
- `ASG_TC_LINK_PELO_NAVEGADOR` — link aponta para o host usado pelo navegador (padrão `true`).
- `ASG_TCL_IP_MODO`, `ASG_TCL_MIN_ABRIR`, `ASG_TCL_MIN_CONCLUIR`, `ASG_TCL_MAX_BYTES` — modo local (em `lib/tcloud_local.php`).

## Arquivos

- `lib/TCloudAssinador.php` — cliente PHP (mesma interface do guia oficial; se a classe oficial já estiver carregada, ela é usada).
- `tcloud_api.php` — endpoint AJAX da tela (situacao, testar, iniciar, status, cancelar), com a sessão e o CSRF do Signum.
- `tcloud_estacao.php` + `lib/tcloud_local.php` — modo local: ações `ticket_*` do app da estação, sem sessão.
- `js/tcloud_signum.js` — abre o link, mostra "Abrir o TCloud Assinador" e "Não abriu? Instale…", acompanha até o fim.

## Banco (migração automática)

- `assinatura_config_usuario.a3_assinador` — `tcloud` (padrão) | `serpro`.
- `assinatura_config.tcloud_modo` — `local` (padrão) | `servidor`.
- `assinatura_config.tcloud_url`, `assinatura_config.tcloud_token_enc` — conexão opcional (serviço em outra VM).
- `assinatura_documentos.provedor` — `arquivo` (A1), `serpro`, `tcloud` ou `agente`; a lista mostra "A3 · TCloud".

## Chancela

Desenhada pelo próprio assinador, na posição e tamanho escolhidos na tela: título do carimbo, nome e CPF **do certificado usado**, data/hora, motivo, local, cargo, código do documento e logomarca do cartório.
