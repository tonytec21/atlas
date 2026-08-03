<?php
/**
 * =====================================================================
 * doc.php — Documentação da API do módulo O.S. (para o desenvolvedor)
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * Página HTML com a documentação completa e um console de teste. O
 * endereço base é detectado do próprio servidor, então o link já sai
 * pronto para enviar ao desenvolvedor do sistema parceiro.
 *
 * ACESSO
 * ------
 * Por padrão exige login no Atlas. Para liberar o acesso ao
 * desenvolvedor externo sem criar usuário, mude a constante abaixo
 * para true. A página não exibe token nenhum — só a documentação.
 * =====================================================================
 */

define('ATLAS_API_DOC_PUBLICA', false);

if (!ATLAS_API_DOC_PUBLICA) {
    include(__DIR__ . '/../session_check.php');
    checkSession();
}

require_once __DIR__ . '/api_config.php';

$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
      . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/os/api/'), '/') . '/v1';

/* Endereço que sempre funciona, mesmo sem mod_rewrite. */
$baseAlt = str_replace('/v1', '/index.php/v1', $base);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API do módulo O.S. — documentação de integração</title>
<link rel="icon" href="../../style/img/favicon.png" type="image/png">
<style>
  :root{
    --tinta:#0f172a; --tinta2:#334155; --suave:#64748b; --linha:#e2e8f0;
    --fundo:#f8fafc; --papel:#fff; --teal:#0f766e; --teal-claro:#ccfbf1;
    --vermelho:#b91c1c; --ambar:#b45309; --verde:#15803d;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
       color:var(--tinta2);background:var(--fundo);line-height:1.65;font-size:15px}
  .wrap{display:flex;max-width:1400px;margin:0 auto;align-items:flex-start}

  nav{position:sticky;top:0;width:250px;flex:0 0 250px;height:100vh;overflow-y:auto;
      padding:24px 16px;background:var(--papel);border-right:1px solid var(--linha)}
  nav h4{margin:0 0 4px;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--suave)}
  nav a{display:block;padding:5px 10px;color:var(--tinta2);text-decoration:none;border-radius:6px;font-size:.86rem}
  nav a:hover{background:var(--teal-claro);color:var(--teal)}
  nav .sep{height:1px;background:var(--linha);margin:14px 0}

  main{flex:1;min-width:0;padding:32px 40px 100px;max-width:920px}
  h1{font-size:1.7rem;color:var(--tinta);margin:0 0 4px}
  h2{font-size:1.25rem;color:var(--tinta);margin:44px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--linha)}
  h3{font-size:1rem;color:var(--tinta);margin:26px 0 8px}
  p{margin:10px 0}
  code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.86em;color:var(--vermelho);
       font-family:ui-monospace,"SF Mono",Consolas,monospace}
  pre{background:var(--tinta);color:#e2e8f0;padding:16px;border-radius:10px;overflow-x:auto;
      font-size:.82rem;line-height:1.55;font-family:ui-monospace,"SF Mono",Consolas,monospace}
  pre code{background:none;color:inherit;padding:0;font-size:inherit}
  table{width:100%;border-collapse:collapse;margin:14px 0;font-size:.88rem;background:var(--papel)}
  th,td{border:1px solid var(--linha);padding:8px 11px;text-align:left;vertical-align:top}
  th{background:var(--fundo);font-weight:600;color:var(--tinta)}
  blockquote{margin:14px 0;padding:12px 16px;background:#fffbeb;border-left:4px solid var(--ambar);
             border-radius:0 8px 8px 0;font-size:.9rem}
  .verb{display:inline-block;padding:2px 9px;border-radius:5px;font-size:.72rem;font-weight:700;
        color:#fff;margin-right:8px;vertical-align:2px;font-family:ui-monospace,monospace}
  .verb.get{background:#0369a1} .verb.post{background:var(--verde)}
  .rota{background:var(--papel);border:1px solid var(--linha);border-radius:10px;padding:14px 16px;margin:20px 0}
  .rota .url{font-family:ui-monospace,monospace;font-size:.92rem;color:var(--tinta);font-weight:600}
  .destaque{background:var(--teal-claro);border-left:4px solid var(--teal);padding:12px 16px;
            border-radius:0 8px 8px 0;margin:14px 0;font-size:.9rem}
  .perigo{background:#fef2f2;border-left:4px solid var(--vermelho);padding:12px 16px;
          border-radius:0 8px 8px 0;margin:14px 0;font-size:.9rem}
  .cabecalho{background:var(--papel);border:1px solid var(--linha);border-radius:12px;padding:20px 24px;margin-bottom:8px}
  .cabecalho .base{font-family:ui-monospace,monospace;font-size:1rem;color:var(--teal);font-weight:600;word-break:break-all}
  .rot{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--suave)}

  /* console */
  .console{background:var(--papel);border:1px solid var(--linha);border-radius:12px;padding:18px;margin:16px 0}
  .console label{display:block;font-size:.78rem;color:var(--suave);margin-bottom:3px}
  .console input,.console select,.console textarea{width:100%;padding:8px 10px;border:1px solid var(--linha);
       border-radius:7px;font-size:.86rem;font-family:ui-monospace,monospace;margin-bottom:10px}
  .console textarea{min-height:84px}
  .console button{background:var(--teal);color:#fff;border:0;padding:9px 20px;border-radius:7px;
                  font-weight:600;cursor:pointer;font-size:.88rem}
  .console button:hover{background:#115e59}
  .console .lin{display:flex;gap:10px;flex-wrap:wrap}
  .console .lin > div{flex:1;min-width:170px}
  #saida{margin-top:12px;display:none}
  .st{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:700;margin-bottom:8px}
  .st.ok{background:#dcfce7;color:var(--verde)} .st.err{background:#fee2e2;color:var(--vermelho)}
  @media(max-width:900px){nav{display:none} main{padding:20px}}
  @media print{nav{display:none} .console{display:none} main{max-width:100%}}
</style>
</head>
<body>
<div class="wrap">

<nav>
  <h4>Início</h4>
  <a href="#inicio">Visão geral</a>
  <a href="#endereco">Endereço base</a>
  <a href="#auth">Autenticação</a>
  <a href="#homologacao">Homologação</a>
  <a href="#escopos">Escopos</a>
  <div class="sep"></div>
  <h4>Convenções</h4>
  <a href="#respostas">Formato das respostas</a>
  <a href="#erros">Códigos de erro</a>
  <a href="#idempotencia">Idempotência</a>
  <div class="sep"></div>
  <h4>Rotas</h4>
  <a href="#ping">ping</a>
  <a href="#os-get">Consultar O.S.</a>
  <a href="#saldo">Saldo</a>
  <a href="#disponiveis">Atos disponíveis</a>
  <a href="#verificar">Verificar saldo</a>
  <a href="#liquidar">Liquidar ato</a>
  <a href="#pagamentos">Pagamentos</a>
  <a href="#criar">Criar O.S.</a>
  <a href="#liquidacoes">Liquidações</a>
  <a href="#atos">Tabela de emolumentos</a>
  <a href="#base">Base de cálculo (por ato)</a>
  <a href="#desconto">Desconto legal</a>
  <div class="sep"></div>
  <h4>Prático</h4>
  <a href="#fluxo">Fluxo completo</a>
  <a href="#console">Console de teste</a>
  <a href="#seguranca">Segurança</a>
</nav>

<main>

<div class="cabecalho">
  <h1 style="margin-bottom:6px">API do módulo O.S. — Atlas</h1>
  <p style="margin:0 0 14px;color:var(--suave)">
    Versão <?= $esc(ATLAS_OS_API) ?> · documento de integração para o desenvolvedor
  </p>
  <div class="rot">Endereço base</div>
  <div class="base"><?= $esc($base) ?></div>
  <div class="rot" style="margin-top:10px">Alternativa (servidor sem mod_rewrite)</div>
  <div class="base" style="font-size:.86rem;color:var(--suave)"><?= $esc($baseAlt) ?></div>
</div>

<h2 id="inicio">Visão geral</h2>

<p>Esta API expõe as Ordens de Serviço do cartório para sistemas externos — tipicamente o
sistema que lavra os atos e gera os selos. O fluxo que ela atende:</p>

<pre><code>  consultar a O.S. pelo número
        ↓
  ver os atos disponíveis para selagem
        ↓
  conferir se há saldo pago que cubra o ato
        ↓
  [o sistema parceiro lavra o ato e gera o selo]
        ↓
  liquidar o ato na O.S., informando o selo</code></pre>

<div class="destaque">
  <b>A regra que sustenta tudo:</b> um ato só é liquidado se houver saldo pago que o cubra.
  Sem isso, a serventia prestaria o serviço sem receber. Toda tentativa de liquidar acima do
  saldo é recusada com <code>saldo_insuficiente</code> e <b>nada</b> é gravado.
</div>

<h2 id="endereco">Endereço base</h2>

<pre><code><?= $esc($base) ?></code></pre>

<p>Se o servidor estiver sem <code>mod_rewrite</code>, qualquer uma destas formas funciona
igual:</p>

<pre><code><?= $esc($baseAlt) ?>/...
<?= $esc(str_replace('/v1', '/index.php?rota=/v1', $base)) ?>/...</code></pre>

<p>Um <code>GET</code> na raiz devolve a lista de rotas e não exige token — bom para
conferir se a instalação respondeu.</p>

<h2 id="auth">Autenticação</h2>

<p>A serventia cadastra o sistema e entrega dois dados:</p>

<table>
  <tr><th style="width:150px">Dado</th><th>Para quê</th></tr>
  <tr><td><code>client_id</code></td><td>identifica o sistema; é público e aparece nos logs</td></tr>
  <tr><td><code>token</code></td><td>segredo, enviado a cada chamada</td></tr>
</table>

<p>O token vai no cabeçalho de toda requisição:</p>

<pre><code>Authorization: Bearer sk_prd_a1b2c3d4...</code></pre>

<p>Se o seu cliente HTTP não conseguir enviar <code>Authorization</code>, a API também aceita
<code>X-Api-Token: &lt;token&gt;</code>.</p>

<div class="perigo">
  <b>O token é exibido uma única vez</b>, na hora em que é gerado. No banco fica apenas o
  SHA-256. Perdido, a serventia gera outro — e o anterior deixa de funcionar imediatamente.
</div>

<h2 id="homologacao">Homologação</h2>

<p>Todo cadastro nasce <b>pendente</b>. O token já existe, mas nenhuma chamada é aceita:</p>

<pre><code>{"sucesso":false,"erro":{
  "codigo":"sistema_pendente",
  "mensagem":"O sistema \"X\" está cadastrado, mas ainda não foi homologado."}}</code></pre>

<p>A serventia homologa pela tela do módulo e o <b>mesmo token</b> passa a funcionar. Pode
suspender depois, sem apagar o cadastro nem o histórico.</p>

<h3>Homologação × Produção</h3>

<p>O prefixo do token indica o ambiente a olho nu: <code>sk_hml_</code> ou
<code>sk_prd_</code>.</p>

<table>
  <tr><th style="width:150px">Ambiente</th><th>Alcance</th></tr>
  <tr><td><code>homologacao</code></td>
      <td><b>Só</b> as O.S. que o próprio sistema criou pela API em homologação.</td></tr>
  <tr><td><code>producao</code></td>
      <td>O acervo real da serventia.</td></tr>
</table>

<p>É isso que torna a homologação segura: você testa o fluxo inteiro — criar O.S., lançar
pagamento, liquidar — sem chance de encostar em um ato real. Uma credencial de homologação
que tentar ler uma O.S. real recebe:</p>

<pre><code>{"sucesso":false,"erro":{
  "codigo":"ambiente_incompativel","os":672,
  "mensagem":"Esta credencial é de HOMOLOGAÇÃO e só pode operar O.S. criadas por ela..."}}</code></pre>

<p>Ao promover para produção, um token novo é emitido e o de homologação para de valer.</p>

<h2 id="escopos">Escopos</h2>

<p>A serventia liga ou desliga cada um por sistema. Chamada fora do escopo devolve
<code>403 escopo_insuficiente</code>, informando qual escopo falta.</p>

<table>
  <tr><th style="width:180px">Escopo</th><th>Permite</th></tr>
  <tr><td><code>os:ler</code></td><td>consultar O.S., atos, saldo, liquidações e pagamentos</td></tr>
  <tr><td><code>os:criar</code></td><td>criar Ordens de Serviço</td></tr>
  <tr><td><code>pagamento:criar</code></td><td>lançar pagamentos</td></tr>
  <tr><td><code>ato:liquidar</code></td><td>liquidar atos</td></tr>
</table>

<h2 id="respostas">Formato das respostas</h2>

<pre><code>// sucesso
{ "sucesso": true, "dados": { ... } }

// erro
{ "sucesso": false, "erro": { "codigo": "saldo_insuficiente",
                              "mensagem": "...", "falta": 34.00 } }</code></pre>

<div class="destaque">
  Programe contra o <code>codigo</code>, nunca contra o texto da <code>mensagem</code>.
  O texto pode ser reescrito; o código é estável.
</div>

<h2 id="erros">Códigos de erro</h2>

<table>
  <tr><th style="width:210px">Código</th><th style="width:70px">HTTP</th><th>Significado</th></tr>
  <tr><td><code>nao_autenticado</code></td><td>401</td><td>token ausente</td></tr>
  <tr><td><code>token_invalido</code></td><td>401</td><td>token não reconhecido</td></tr>
  <tr><td><code>sistema_pendente</code></td><td>403</td><td>cadastro ainda não homologado</td></tr>
  <tr><td><code>sistema_suspenso</code></td><td>403</td><td>acesso suspenso pela serventia</td></tr>
  <tr><td><code>ip_nao_autorizado</code></td><td>403</td><td>IP fora da lista configurada</td></tr>
  <tr><td><code>escopo_insuficiente</code></td><td>403</td><td>falta o escopo para a operação</td></tr>
  <tr><td><code>ambiente_incompativel</code></td><td>403</td><td>credencial de homologação em O.S. real</td></tr>
  <tr><td><code>os_nao_encontrada</code></td><td>404</td><td>número de O.S. inexistente</td></tr>
  <tr><td><code>item_nao_encontrado</code></td><td>404</td><td>o item não pertence a essa O.S.</td></tr>
  <tr><td><code>ato_nao_encontrado</code></td><td>404/422</td><td>ato fora da tabela de emolumentos</td></tr>
  <tr><td><code>rota_nao_encontrada</code></td><td>404</td><td>rota inexistente</td></tr>
  <tr><td><code>metodo_invalido</code></td><td>405</td><td>verbo HTTP errado</td></tr>
  <tr style="background:#fef2f2"><td><b><code>saldo_insuficiente</code></b></td><td><b>409</b></td>
      <td><b>não há saldo pago para liquidar o ato</b></td></tr>
  <tr><td><code>quantidade_indisponivel</code></td><td>409</td><td>ato já liquidado, ou quantidade além da disponível</td></tr>
  <tr><td><code>os_cancelada</code></td><td>409</td><td>a O.S. está cancelada</td></tr>
  <tr><td><code>os_ocupada</code></td><td>409</td><td>outra liquidação em andamento; repita em instantes</td></tr>
  <tr><td><code>idempotencia_conflitante</code></td><td>409</td><td>mesma chave com conteúdo diferente</td></tr>
  <tr><td><code>campo_obrigatorio</code></td><td>422</td><td>falta um campo</td></tr>
  <tr><td><code>quantidade_invalida</code></td><td>422</td><td>quantidade menor que 1</td></tr>
  <tr><td><code>valor_invalido</code></td><td>422</td><td>valor de pagamento ≤ 0</td></tr>
  <tr><td><code>forma_pagamento_invalida</code></td><td>422</td><td>forma fora da lista aceita</td></tr>
  <tr><td><code>erro_banco</code> / <code>erro_interno</code></td><td>500</td><td>falha no servidor</td></tr>
</table>

<h2 id="idempotencia">Idempotência</h2>

<p>Em qualquer <code>POST</code>, envie:</p>

<pre><code>Idempotency-Key: selagem-2026-000123</code></pre>

<p>Repetir a mesma chave <b>não executa nada de novo</b> — devolve a resposta original, com o
cabeçalho <code>X-Atlas-Idempotencia: repetida</code>.</p>

<div class="destaque">
  Isto é essencial na liquidação. O caso clássico: a liquidação é gravada, a resposta se perde
  na rede, o cliente reenvia. Sem a chave, o ato seria liquidado duas vezes e o saldo do
  cliente consumido em dobro. Use uma chave própria por operação — o número do protocolo de
  selagem serve bem.
</div>

<p>Chamadas que <b>falharam não são guardadas</b>: uma tentativa recusada por falta de saldo
pode e deve ser repetida depois que o cliente pagar.</p>

<h2>Rotas</h2>

<div class="rota" id="ping">
  <div class="url"><span class="verb get">GET</span>/v1/ping</div>
  <p>Confere a credencial. Use isto primeiro, antes de qualquer coisa.</p>
<pre><code>{"sucesso":true,"dados":{
  "pong":true,
  "sistema":"Sistema de Selagem",
  "client_id":"atlas_4533fa550b01",
  "ambiente":"producao",
  "status":"ativo",
  "escopos":["os:ler","os:criar","pagamento:criar","ato:liquidar"],
  "servidor":{"data_hora":"2026-07-31T10:16:00-03:00","fuso":"America/Fortaleza"}}}</code></pre>
</div>

<div class="rota" id="os-get">
  <div class="url"><span class="verb get">GET</span>/v1/os/{numero}</div>
  <p>Retrato completo: dados da O.S., financeiro, itens, liquidações, pagamentos e selos.</p>
<pre><code>{"sucesso":true,"dados":{
  "os":{"numero":672,"cliente":"JULIANE SILVA SANTOS CARNEIRO",
        "cpf_cliente":"12345678900","status":"ativa","cancelada":false,
        "total_os":122.51,"base_de_calculo_os":null,
        "data_criacao":"2026-07-31T10:16:00-03:00"},
  "financeiro":{
    "total_os":122.51,"total_pago":50.00,"total_devolvido":0,
    "pago_liquido":50.00,"total_liquidado":42.00,
    "saldo_liquidacao":8.00,"saldo_a_pagar":72.51,
    "quitada":false,"isenta_de_pagamento":false},
  "resumo_atos":{"total_de_itens":2,"itens_pendentes":2,
                 "unidades_pendentes":2,"totalmente_liquidada":false},
  "itens":[...],"liquidacoes":[...],"pagamentos":[...],"selos":[...]}}</code></pre>

  <h3>O bloco <code>financeiro</code>, campo a campo</h3>
  <table>
    <tr><th style="width:200px">Campo</th><th>O que é</th></tr>
    <tr><td><code>total_os</code></td><td>valor da O.S.</td></tr>
    <tr><td><code>total_pago</code></td><td>soma dos pagamentos</td></tr>
    <tr><td><code>total_devolvido</code></td><td>soma das devoluções</td></tr>
    <tr><td><code>pago_liquido</code></td><td><code>total_pago − total_devolvido</code></td></tr>
    <tr><td><code>total_liquidado</code></td><td>soma dos atos já liquidados</td></tr>
    <tr style="background:var(--teal-claro)"><td><b><code>saldo_liquidacao</code></b></td>
        <td><b><code>pago_liquido − total_liquidado</code></b> — o que ainda dá para liquidar</td></tr>
    <tr><td><code>saldo_a_pagar</code></td><td>quanto falta o cliente pagar</td></tr>
    <tr><td><code>isenta_de_pagamento</code></td>
        <td>há pagamento com forma de isenção; dispensa a checagem de saldo</td></tr>
  </table>
  <p><b><code>saldo_liquidacao</code> é o número que interessa ao sistema de selagem.</b></p>
</div>

<div class="rota" id="saldo">
  <div class="url"><span class="verb get">GET</span>/v1/os/{numero}/saldo</div>
  <p>Só o bloco financeiro. Mais leve, para checagem rápida.</p>
</div>

<div class="rota" id="disponiveis">
  <div class="url"><span class="verb get">GET</span>/v1/os/{numero}/atos-disponiveis</div>
  <p><b>A rota principal do fluxo de selagem.</b> Devolve apenas o que ainda não foi liquidado,
     já marcando se o saldo cobre cada item.</p>
<pre><code>{"sucesso":true,"dados":{
  "os":672,"cancelada":false,
  "base_de_calculo_os":null,
  "financeiro":{"saldo_liquidacao":50.00, ...},
  "quantidade":2,
  "itens":[
    {"item_id":1,
     "ato":"16.1",
     "descricao":"CERTIDAO DE INTEIRO TEOR",
     "isento":false,
     "quantidade":2,
     "quantidade_liquidada":0,
     "quantidade_disponivel":2,
     "situacao":"pendente",
     "valor_unitario_liquidacao":42.00,
     "valor_restante_liquidacao":84.00,
     "exige_saldo":true,
     "saldo_cobre_uma_unidade":true,
     "saldo_cobre_o_restante":false}]}}</code></pre>

  <table>
    <tr><th style="width:250px">Campo</th><th>Uso</th></tr>
    <tr><td><code>item_id</code></td><td>é o identificador usado para liquidar</td></tr>
    <tr><td><code>quantidade_disponivel</code></td><td>quantas unidades ainda podem ser seladas</td></tr>
    <tr><td><code>valor_unitario_liquidacao</code></td><td>quanto custa liquidar <b>uma</b> unidade</td></tr>
    <tr><td><code>saldo_cobre_uma_unidade</code></td><td>dá para selar mais uma?</td></tr>
    <tr><td><code>saldo_cobre_o_restante</code></td><td>dá para selar tudo o que falta?</td></tr>
  </table>

  <blockquote>
    Os campos <code>saldo_cobre_*</code> avaliam cada item contra o saldo atual,
    <b>isoladamente</b>. Ao selar vários atos da mesma O.S., o saldo é consumido a cada
    liquidação — o veredito final é sempre o da própria liquidação.
  </blockquote>

  <p>Use <code>GET /v1/os/{numero}/atos</code> para a lista completa, incluindo os já
     liquidados.</p>
</div>

<div class="rota" id="verificar">
  <div class="url"><span class="verb post">POST</span>/v1/os/{numero}/verificar-saldo</div>
  <p>Consulta prévia. <b>Não altera nada.</b> Serve para decidir se abre a tela de selagem.</p>
<pre><code>// requisição
{ "item_id": 1, "quantidade": 1 }

// resposta
{"sucesso":true,"dados":{
  "pode_liquidar":false,
  "impedimentos":[{"codigo":"saldo_insuficiente",
                   "mensagem":"Saldo pago insuficiente... Faltam R$ 42,00."}],
  "item":{"item_id":1,"ato":"16.1","quantidade_disponivel":2, ...},
  "valor_da_liquidacao":{"emolumentos":30.00,"ferc":6.00,"fadep":3.00,
                         "femp":2.00,"ferrfis":1.00,"total":42.00},
  "financeiro":{"saldo_liquidacao":0, ...},
  "exige_saldo":true,"saldo_suficiente":false,"falta":42.00}}</code></pre>
  <p>Note que <code>pode_liquidar:false</code> vem com <b>HTTP 200</b> — a consulta funcionou,
     a resposta é que foi negativa. Só a liquidação de verdade devolve 409.</p>
</div>

<div class="rota" id="liquidar">
  <div class="url"><span class="verb post">POST</span>/v1/os/{numero}/liquidar</div>
  <p>Liquida o ato depois da selagem. Aceita liquidação <b>parcial</b> (algumas unidades) ou
     <b>total</b>.</p>
<pre><code>{
  "item_id": 1,
  "quantidade": 1,
  "selo": "MA00123456",
  "protocolo": "PROT-9911",
  "operador": "joao.silva"
}</code></pre>

  <table>
    <tr><th style="width:130px">Campo</th><th style="width:110px">Obrigatório</th><th>Observação</th></tr>
    <tr><td><code>item_id</code></td><td>sim</td><td>vindo de <code>/atos-disponiveis</code></td></tr>
    <tr><td><code>quantidade</code></td><td>não (1)</td><td>não pode exceder <code>quantidade_disponivel</code></td></tr>
    <tr><td><code>selo</code></td><td>não</td><td>selo gerado; fica registrado e visível na O.S.</td></tr>
    <tr><td><code>protocolo</code></td><td>não</td><td>referência do sistema de origem</td></tr>
    <tr><td><code>operador</code></td><td><b>sim, na prática</b></td><td>quem lavrou o ato; ver o aviso abaixo</td></tr>
  </table>

  <div class="destaque">
    <b>O campo <code>operador</code> — leia antes de integrar.</b>
    Formalmente opcional, mas <b>envie sempre</b>: é por ele que a serventia sabe a quem
    atribuir o ato no fluxo de caixa.
    <br><br>
    Aceita o <b>nome completo</b> do colaborador como cadastrado no Atlas, ou o
    <b>nome de usuário</b> — os dois funcionam:
    <pre style="margin:8px 0"><code>"operador": "Antonio José Martins Garcia"
"operador": "ADMIN"</code></pre>
    O servidor resolve o nome contra o cadastro de colaboradores e grava o usuário
    correspondente. A comparação ignora acentuação e maiúsculas, então
    <code>Antonio Jose Martins Garcia</code> casa com <code>Antonio José Martins Garcia</code>.
    <br><br>
    <b>Sem <code>operador</code></b>, ou com um nome que não exista no cadastro, o ato fica
    registrado como avulso da integração e o fluxo de caixa o exibe separado, fora do caixa de
    qualquer colaborador. Ninguém perde dinheiro com isso, mas o fechamento do dia fica confuso
    e alguém da serventia terá de corrigir à mão. O mesmo vale para o <code>operador</code> do
    lançamento de pagamentos.
  </div>

  <h3>Sucesso</h3>
<pre><code>{"sucesso":true,"dados":{
  "liquidado":true,"os":672,"liquidacao_id":1,"tabela":"atos_liquidados",
  "item":{"item_id":1,"ato":"16.1","quantidade":2,
          "quantidade_liquidada":1,"quantidade_disponivel":1,
          "situacao":"parcialmente_liquidado"},
  "quantidade_liquidada_agora":1,
  "valores":{"emolumentos":30.00,"ferc":6.00,"fadep":3.00,
             "femp":2.00,"ferrfis":1.00,"total":42.00},
  "selo":"MA00123456",
  "financeiro":{"saldo_liquidacao":8.00, ...}}}</code></pre>

  <h3>Sem saldo — HTTP 409</h3>
  <p>O erro traz os números prontos para a tela do operador:</p>
<pre><code>{"sucesso":false,"erro":{
  "codigo":"saldo_insuficiente",
  "mensagem":"Saldo pago insuficiente para liquidar este ato. Faltam R$ 34,00.",
  "os":672,"item_id":1,
  "valor_necessario":42.00,
  "saldo_disponivel":8.00,
  "falta":34.00,
  "total_os":122.51,"pago_liquido":50.00,"total_liquidado":42.00}}</code></pre>

  <h3>Garantias</h3>
  <ul>
    <li><b>Atômica.</b> Ou grava a liquidação e atualiza o item, ou não faz nada.</li>
    <li><b>Trava por O.S.</b> Duas selagens simultâneas na mesma O.S. são serializadas.</li>
    <li><b>Saldo reconferido dentro da trava.</b> O <code>verificar-saldo</code> é orientativo;
        a checagem que vale é esta. Sem isso, duas chamadas paralelas poderiam liquidar mais
        do que foi pago.</li>
    <li><b>Mesmo cálculo da tela.</b> Rateio cumulativo por quantidade: liquidações parciais
        somam exatamente o total do item, sem diferença de centavo.</li>
    <li><b>Dispara os mesmos ganchos</b> do botão da tela: rastreio de pedidos e emissão
        automática de NFS-e quando todos os atos ficam liquidados.</li>
  </ul>
  <p>Ato <b>isento</b> (total zero, ou O.S. com pagamento de isenção) liquida sem exigir
     saldo.</p>
</div>

<div class="rota" id="pagamentos">
  <div class="url"><span class="verb post">POST</span>/v1/os/{numero}/pagamentos</div>
<pre><code>{ "valor": 122.51, "forma_de_pagamento": "PIX", "operador": "caixa01" }</code></pre>
  <p>Formas aceitas: <code>Espécie</code>, <code>PIX</code>, <code>Débito</code>,
     <code>Crédito</code>, <code>Transferência Bancária</code>, <code>Depósito Bancário</code>,
     <code>Ato Isento</code>, <code>Isento de Pagamento</code>.</p>
  <p>Aceita <code>122.51</code> ou <code>"122,51"</code>. Devolve o <code>financeiro</code>
     atualizado, já com o novo <code>saldo_liquidacao</code>.</p>
  <p><span class="verb get">GET</span> na mesma rota lista os pagamentos.</p>
</div>

<div class="rota" id="criar">
  <div class="url"><span class="verb post">POST</span>/v1/os</div>
<pre><code>{
  "cliente": "MARIA DAS GRACAS SILVA",
  "cpf_cliente": "123.456.789-00",
  "descricao": "Certidões e averbação",
  "operador": "joao.silva",
  "itens": [
    { "ato": "16.1", "quantidade": 2 },
    { "ato": "16.3.18", "quantidade": 1, "desconto_legal": 50 },
    { "ato": "16.1", "quantidade": 1, "isento": true },
    { "descricao": "SERVICO AVULSO", "quantidade": 1,
      "valores": { "emolumentos": 10.00, "ferc": 2.00 } }
  ]
}</code></pre>

  <div class="destaque">
    <b>Os valores não vêm do cliente.</b> O servidor busca o ato na tabela de emolumentos
    vigente e aplica quantidade e desconto legal. Aceitar valores prontos permitiria lançar
    ato a preço arbitrário. A exceção é o item manual (sem <code>ato</code>, com
    <code>descricao</code> + <code>valores</code>), que espelha o lançamento manual da tela.
  </div>

  <p><code>isento: true</code> zera os valores e marca o ato com <code>(isento)</code>, como
     o botão "Ato Isento" do módulo.</p>
  <p>Resposta: <b>201</b>, no mesmo formato de <code>GET /v1/os/{numero}</code>. O número da
     O.S. está em <code>dados.os.numero</code>.</p>
</div>

<div class="rota" id="liquidacoes">
  <div class="url"><span class="verb get">GET</span>/v1/os/{numero}/liquidacoes</div>
  <p>Atos já liquidados, com valores, funcionário, data e os selos registrados.</p>
</div>

<div class="rota" id="atos">
  <div class="url"><span class="verb get">GET</span>/v1/atos/{codigo}</div>
  <p>Consulta a tabela de emolumentos vigente — útil para montar a O.S. e mostrar o preço
     antes.</p>
<pre><code>GET /v1/atos/16.1

{"sucesso":true,"dados":{
  "ato":"16.1","descricao":"CERTIDAO DE INTEIRO TEOR",
  "emolumentos":30.00,"ferc":6.00,"fadep":3.00,
  "femp":2.00,"ferrfis":1.00,"total":42.00}}</code></pre>
</div>

<h2 id="base">Base de cálculo — por ato</h2>

<p>Valor declarado do negócio jurídico. Nos <b>atos com valor declarado</b> — compra e venda,
doação, procuração com poderes de alienação — é a base que determina a faixa do selo.</p>

<div class="destaque">
  <b>Mudou em agosto/2026.</b> Antes havia uma única base por O.S. Isso não representava um
  orçamento com duas escrituras de valores diferentes, e não havia como saber a qual ato a base
  pertencia. <b>Agora a base está no ato.</b>
</div>

<h3>Onde encontrar</h3>

<p>A base fica dentro de cada item, junto com a faixa que o ato cobre:</p>

<pre><code>{
  "item_id": 10,
  "ato": "13.1.18",
  "descricao": "Escritura de compra e venda. De R$ 327.953,99 a R$ 409.942,47",
  "base_de_calculo": 350000.00,
  "exige_base_de_calculo": true,
  "faixa_de_valor": {
    "tipo": "intervalo",
    "minimo": 327953.99,
    "maximo": 409942.47,
    "rotulo": "de R$ 327.953,99 a R$ 409.942,47"
  }
}</code></pre>

<table>
  <tr><th style="width:250px">Campo</th><th>Significado</th></tr>
  <tr><td><code>base_de_calculo</code></td><td>valor declarado deste ato; <code>null</code> quando não informado</td></tr>
  <tr><td><code>exige_base_de_calculo</code></td><td><code>true</code> quando o ato é cobrado por faixa de valor</td></tr>
  <tr><td><code>faixa_de_valor</code></td><td>a faixa lida da descrição; <code>null</code> nos atos sem faixa</td></tr>
</table>

<p>Rotas que trazem os itens: <code>GET /v1/os/{n}</code>, <code>/atos</code>,
<code>/atos-disponiveis</code> e <code>/liquidacoes</code>. Em
<code>verificar-saldo</code> e <code>liquidar</code>, que tratam de <b>um</b> ato, a base dele
vem também no primeiro nível.</p>

<h3><code>pronto_para_selagem</code></h3>

<p>Em <code>/atos-disponiveis</code>, este campo já combina saldo e base:</p>

<pre><code>"saldo_cobre_uma_unidade": true,
"base_de_calculo": null,
"exige_base_de_calculo": true,
"pronto_para_selagem": false</code></pre>

<div class="destaque">
  <b>É o único campo que precisa ser consultado antes de gerar o selo.</b>
</div>

<h3>Campo legado: <code>base_de_calculo_os</code></h3>

<p>O antigo campo de nível de O.S. continua sendo devolvido, renomeado para
<code>base_de_calculo_os</code>. Ele só vem preenchido nas <b>O.S. lançadas antes da
mudança</b>, que têm a base apenas nesse nível. Em O.S. novas vem <code>null</code>.</p>

<div class="perigo">
  <b>Não use <code>base_de_calculo_os</code> para escolher a faixa do selo.</b> Ele não diz a
  qual ato pertence. Use o <code>base_de_calculo</code> do item.
</div>

<h3>Se estava usando o campo antigo</h3>

<p>O campo <code>base_de_calculo</code> no primeiro nível de <code>/saldo</code>,
<code>/atos</code> e <code>/atos-disponiveis</code> <b>deixou de existir</b> — virou
<code>base_de_calculo_os</code>. Quem lia dali precisa passar a ler
<code>itens[].base_de_calculo</code>.</p>

<h3>Validação na criação e na liquidação</h3>

<p><code>POST /v1/os</code> aceita a base dentro de cada item, em <code>base_de_calculo</code>
ou <code>base_calculo</code>, no formato brasileiro ou como número JSON:</p>

<pre><code>{
  "cliente": "COMPRADOR DA SILVA",
  "itens": [
    { "ato": "13.1.18", "quantidade": 1, "base_de_calculo": "350.000,00" },
    { "ato": "05.1",    "quantidade": 6 }
  ]
}</code></pre>

<p>O servidor lê a faixa da descrição do ato na tabela de emolumentos e valida:</p>

<table>
  <tr><th style="width:280px">Situação</th><th>Resposta</th></tr>
  <tr><td>Ato de faixa <b>sem</b> base</td><td><code>422 base_obrigatoria</code></td></tr>
  <tr><td>Base <b>fora</b> da faixa</td><td><code>422 base_fora_da_faixa</code></td></tr>
  <tr><td>Ato <b>sem</b> faixa</td><td>base opcional; ignorada se ausente</td></tr>
</table>

<pre><code>{"sucesso":false,"erro":{
  "codigo":"base_fora_da_faixa",
  "mensagem":"A base informada (R$ 350.000,00) está ACIMA da faixa deste ato
              (de R$ 262.363,19 a R$ 327.953,98). Confira o valor declarado
              ou selecione o ato da faixa correta.",
  "ato":"13.1.17",
  "faixa_de_valor":{"minimo":262363.19,"maximo":327953.98,
                    "rotulo":"de R$ 262.363,19 a R$ 327.953,98"},
  "base_recebida":350000.00}}</code></pre>

<p>A mesma checagem roda na <b>liquidação</b>: ato de faixa sem base é recusado com
<code>422 base_obrigatoria</code>, mesmo havendo saldo. Sem a base não há como escolher o selo,
e selo de faixa errada é ato viciado.</p>

<h3>Registro imutável</h3>

<p>Ao liquidar, a base fica gravada <b>junto do ato liquidado</b>, e não só no item. Se alguém
editar a O.S. depois, a base do que já foi selado não muda — o dado que fundamentou o selo fica
congelado. Ela volta em <code>GET /v1/os/{n}/liquidacoes</code>.</p>

<h3>Alterando depois</h3>

<p>Esta versão não expõe alteração da base pela API. Se a O.S. foi criada sem base e o ato
precisa dela na selagem, alguém da serventia informa pela tela do Atlas (Editar O.S.) e a
consulta seguinte já devolve o valor.</p>

<h2 id="desconto">Desconto legal</h2>

<p>Percentual de gratuidade ou redução aplicado ao ato (Lei 1.060/50, gratuidade de registro
civil, convênios). Vem no campo <code>desconto_legal</code> de cada item, como número —
<code>0</code> significa sem desconto.</p>

<pre><code>{ "item_id": 27, "ato": "16.1", "quantidade": 2, "desconto_legal": 50, ... }</code></pre>

<h3>Onde aparece</h3>

<table>
  <tr><th style="width:330px">Rota</th><th>Onde</th></tr>
  <tr><td><code>GET /v1/os/{n}</code></td><td><code>itens[].desconto_legal</code></td></tr>
  <tr><td><code>GET /v1/os/{n}/atos</code> e <code>/atos-disponiveis</code></td><td><code>itens[].desconto_legal</code></td></tr>
  <tr><td><code>POST /v1/os/{n}/verificar-saldo</code></td><td><code>item.desconto_legal</code></td></tr>
  <tr><td><code>POST /v1/os/{n}/liquidar</code></td><td><code>item.desconto_legal</code></td></tr>
  <tr><td><code>GET /v1/os/{n}/liquidacoes</code></td><td><code>liquidacoes[].desconto_legal</code></td></tr>
</table>

<h3>Os valores já vêm com o desconto aplicado</h3>

<div class="destaque">
  <b>Não aplique o percentual de novo.</b> Todos os valores devolvidos pela API já estão com o
  desconto embutido.
</div>

<p>Um ato de R$ 42,00, quantidade 2, com 50% de desconto:</p>

<pre><code>"quantidade": 2,
"desconto_legal": 50,
"valores_do_item": { "total": 42.00 },     // 2 × 42,00 − 50% = 42,00
"valor_unitario_liquidacao": 21.00         // já com desconto</code></pre>

<p>O mesmo vale para <code>valor_da_liquidacao</code> em <code>verificar-saldo</code>, para
<code>valores</code> em <code>liquidar</code> e para <code>total</code> em
<code>/liquidacoes</code>. O <code>desconto_legal</code> está lá para <b>exibição e
conferência</b> — para o sistema de lavratura imprimir "50% de desconto legal" no ato, não para
recalcular.</p>

<h3>Na criação</h3>

<p><code>POST /v1/os</code> aceita <code>desconto_legal</code> por item, de 0 a 100. Fora dessa
faixa, <code>422 desconto_invalido</code>. O servidor busca o valor do ato na tabela de
emolumentos e aplica quantidade e desconto — os valores nunca vêm do cliente.</p>

<pre><code>{ "ato": "16.1", "quantidade": 2, "desconto_legal": 50 }</code></pre>

<h2 id="fluxo">Fluxo completo de selagem</h2>

<pre><code>&lt;?php
$API   = '<?= $esc($base) ?>';
$TOKEN = 'sk_prd_...';

function chamar($metodo, $rota, $corpo = null, $idem = null) {
    global $API, $TOKEN;
    $ch = curl_init($API . $rota);
    $h  = ['Authorization: Bearer ' . $TOKEN, 'Content-Type: application/json'];
    if ($idem) { $h[] = 'Idempotency-Key: ' . $idem; }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER =&gt; true,
        CURLOPT_CUSTOMREQUEST  =&gt; $metodo,
        CURLOPT_HTTPHEADER     =&gt; $h,
        CURLOPT_TIMEOUT        =&gt; 30,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
    }

    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$osNumero = 672;

// 1. O que há para selar
$r = chamar('GET', "/os/$osNumero/atos-disponiveis");
if (!$r['sucesso']) {
    exit('Não foi possível consultar a O.S.: ' . $r['erro']['mensagem']);
}

foreach ($r['dados']['itens'] as $item) {

    if (!$item['saldo_cobre_uma_unidade']) {
        echo "Ato {$item['ato']}: sem saldo, aguardando pagamento.\n";
        continue;
    }

    // 2. Lavra o ato e gera o selo no SEU sistema
    $selo = gerarSelo($item['ato']);

    // 3. Liquida — a chave de idempotência protege a repetição
    $liq = chamar('POST', "/os/$osNumero/liquidar", [
        'item_id'    =&gt; $item['item_id'],
        'quantidade' =&gt; 1,
        'selo'       =&gt; $selo,
        'protocolo'  =&gt; 'PROT-' . date('YmdHis'),
        'operador'   =&gt; 'joao.silva',
    ], 'selo-' . $selo);

    if ($liq['sucesso']) {
        echo "Ato {$item['ato']} liquidado. Selo {$selo}. "
           . "Saldo restante: {$liq['dados']['financeiro']['saldo_liquidacao']}\n";
        continue;
    }

    // 4. Falhou: o ato NÃO foi liquidado, cancele o selo do seu lado
    cancelarSelo($selo);

    if ($liq['erro']['codigo'] === 'saldo_insuficiente') {
        echo "Sem saldo. Faltam R$ {$liq['erro']['falta']}\n";
    } else {
        echo "Falha ({$liq['erro']['codigo']}): {$liq['erro']['mensagem']}\n";
    }
}</code></pre>

<h3>Selar antes ou liquidar antes?</h3>

<p>O exemplo acima sela e depois liquida, que é o fluxo natural. Mas a liquidação pode falhar
depois de o selo já existir — se outro caixa consumiu o saldo no intervalo, por exemplo.</p>

<p>Duas formas de lidar:</p>
<ol>
  <li><b>Chamar <code>verificar-saldo</code> imediatamente antes de gerar o selo.</b> Encurta
      muito a janela, embora não a elimine.</li>
  <li><b>Tratar a falha cancelando o selo</b>, como no exemplo. É o caminho mais seguro: o
      <code>saldo_insuficiente</code> garante que <b>nada</b> foi gravado na O.S., então é o
      selo que fica sobrando.</li>
</ol>

<p>Não existe reserva de saldo nesta versão. Se o volume de selagem simultânea na mesma O.S.
justificar, dá para acrescentar.</p>

<h2 id="console">Console de teste</h2>

<div class="destaque">
  Cole o token de homologação recebido da serventia e dispare chamadas reais daqui mesmo,
  sem precisar de curl ou Postman.
</div>

<div class="console">
  <label>Token</label>
  <input type="text" id="cToken" placeholder="sk_hml_...">

  <div class="lin">
    <div style="flex:0 0 110px">
      <label>Método</label>
      <select id="cMetodo"><option>GET</option><option>POST</option></select>
    </div>
    <div>
      <label>Rota</label>
      <input type="text" id="cRota" value="/ping" placeholder="/os/123/atos-disponiveis">
    </div>
  </div>

  <label>Corpo (JSON, só para POST)</label>
  <textarea id="cCorpo" placeholder='{"item_id": 1, "quantidade": 1}'></textarea>

  <button onclick="testar()">Enviar</button>

  <div id="saida">
    <div style="margin-top:14px"><span class="st" id="cSt"></span></div>
    <pre id="cResp"></pre>
  </div>
</div>

<h2 id="seguranca">Segurança</h2>

<ul>
  <li><b>Use HTTPS.</b> O token viaja em cabeçalho; em HTTP puro ele vai em texto claro na
      rede.</li>
  <li><b>Restrinja por IP</b> quando o sistema parceiro tiver IP fixo — a serventia configura
      isso no cadastro.</li>
  <li><b>Um token por sistema.</b> Não compartilhe entre integrações: o log de auditoria
      identifica quem chamou.</li>
  <li><b>Escopos mínimos.</b> Um sistema que só sela precisa de <code>os:ler</code> e
      <code>ato:liquidar</code>; não precisa criar O.S. nem lançar pagamento.</li>
  <li><b>Comece em homologação.</b> Só peça a promoção a produção depois do fluxo inteiro
      validado.</li>
</ul>

<h3>Auditoria</h3>
<p>Toda chamada — inclusive as recusadas — fica registrada: sistema, IP, rota, O.S., status
HTTP, código de erro, duração e o corpo enviado. A serventia acompanha pela tela do módulo.</p>

<p style="margin-top:50px;padding-top:16px;border-top:1px solid var(--linha);
          color:var(--suave);font-size:.83rem">
  API do módulo O.S. — Atlas · versão <?= $esc(ATLAS_OS_API) ?> ·
  documento gerado em <?= $esc(date('d/m/Y')) ?>.
  Dúvidas sobre credenciais, homologação ou promoção a produção: fale com a serventia.
</p>

</main>
</div>

<script>
const BASE = <?= json_encode($baseAlt) ?>;

async function testar() {
    const token  = document.getElementById('cToken').value.trim();
    const metodo = document.getElementById('cMetodo').value;
    let   rota   = document.getElementById('cRota').value.trim();
    const corpo  = document.getElementById('cCorpo').value.trim();

    if (!token) { alert('Informe o token.'); return; }
    if (!rota.startsWith('/')) { rota = '/' + rota; }

    const saida = document.getElementById('saida');
    const st    = document.getElementById('cSt');
    const resp  = document.getElementById('cResp');

    saida.style.display = 'block';
    st.className = 'st';
    st.textContent = 'enviando…';
    resp.textContent = '';

    const opcoes = {
        method: metodo,
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }
    };

    if (metodo === 'POST' && corpo) {
        try {
            JSON.parse(corpo);
        } catch (e) {
            st.className = 'st err';
            st.textContent = 'JSON inválido';
            resp.textContent = e.message;
            return;
        }
        opcoes.body = corpo;
        opcoes.headers['Idempotency-Key'] = 'console-' + Date.now();
    }

    try {
        const r = await fetch(BASE + rota, opcoes);
        const t = await r.text();

        st.className = 'st ' + (r.ok ? 'ok' : 'err');
        st.textContent = 'HTTP ' + r.status;

        try {
            resp.textContent = JSON.stringify(JSON.parse(t), null, 2);
        } catch (e) {
            resp.textContent = t;
        }
    } catch (e) {
        st.className = 'st err';
        st.textContent = 'falha de rede';
        resp.textContent = e.message;
    }
}

document.getElementById('cMetodo').addEventListener('change', function () {
    const exemplos = {
        'GET':  '/ping',
        'POST': '/os/1/verificar-saldo'
    };
    document.getElementById('cRota').value = exemplos[this.value] || '/ping';
    document.getElementById('cCorpo').value =
        this.value === 'POST' ? '{\n  "item_id": 1,\n  "quantidade": 1\n}' : '';
});
</script>
</body>
</html>
