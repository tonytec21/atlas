<?php
/**
 * provimentos/sincronizar.php
 * Verifica novos atos nos portais do CNJ e da CGJ/MA e importa para o acervo.
 */
include(__DIR__ . '/session_check.php');
checkSession();
require_once __DIR__ . '/sync_lib.php';

date_default_timezone_set('America/Sao_Paulo');
$conn = getDatabaseConnection();
if (!syncSchemaExiste($conn)) { syncGarantirSchema($conn); }

$filtro = isset($_GET['status']) ? $_GET['status'] : 'pendentes';
$where = array('novo', 'atualizado');
if ($filtro === 'importados') { $where = array('importado'); }
if ($filtro === 'erros')      { $where = array('erro'); }
if ($filtro === 'ignorados')  { $where = array('ignorado'); }

$in = "'" . implode("','", $where) . "'";
$itens = $conn->query("
    SELECT i.*, f.nome AS fonte_nome, f.origem
      FROM kb_sync_itens i JOIN kb_fontes f ON f.id = i.fonte_id
     WHERE i.status IN ({$in})
     ORDER BY i.prioridade, i.ano DESC, CAST(i.numero AS UNSIGNED) DESC
     LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizar provimentos</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        body.light-mode {
            --kb-sup:#fff; --kb-sup2:#f7fafb; --kb-brd:#d5dde5; --kb-txt:#2d3748;
            --kb-txt2:#8a97a5; --kb-ac:#0f6f77; --kb-ac-bg:#0f6f77; --kb-alerta:#c0392b;
        }
        body.dark-mode {
            --kb-sup:#0b1324; --kb-sup2:#0e1627; --kb-brd:rgba(255,255,255,.10); --kb-txt:#e5e7eb;
            --kb-txt2:#9ca3af; --kb-ac:#5eead4; --kb-ac-bg:#0f766e; --kb-alerta:#f87171;
        }
        .cx      { border:1px solid var(--kb-brd); border-radius:8px; padding:16px 20px;
                   background:var(--kb-sup); color:var(--kb-txt); margin-bottom:14px; }
        .fonte   { display:flex; justify-content:space-between; align-items:center;
                   flex-wrap:wrap; gap:10px; padding:12px 0; border-bottom:1px solid var(--kb-brd); }
        .fonte:last-child { border-bottom:none; }
        .item    { border:1px solid var(--kb-brd); border-radius:7px; padding:12px 16px;
                   margin-bottom:9px; background:var(--kb-sup); color:var(--kb-txt);
                   display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .item.novo  { border-left:4px solid var(--kb-ac-bg); }
        .item.atual { border-left:4px solid #e67e22; }
        .item.erro  { border-left:4px solid var(--kb-alerta); }
        .meta    { font-size:.8rem; color:var(--kb-txt2); }
        .tag     { font-size:.7rem; padding:2px 9px; border-radius:10px; color:#fff; }
        .num     { font-weight:600; color:var(--kb-ac); }
        .barra   { height:20px; }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <h3>Sincronizar com os portais</h3>
    <p class="meta">Verifica atos novos no CNJ e na CGJ/MA, baixa o texto e alimenta o acervo.</p>
    <hr>

    <div class="cx">
      <h5 class="mb-2">Fontes</h5>
      <div id="listaFontes"><span class="meta">Carregando...</span></div>
      <hr>
      <button id="btnNovos" class="btn btn-primary" style="color:#fff!important"
              title="Rápido: procura apenas atos que ainda não estão na base">
        <i class="fa fa-search-plus"></i> Buscar novos provimentos
      </button>
      <button id="btnAlteracoes" class="btn btn-warning" style="color:#fff!important"
              title="Reconfere a situação dos atos já importados">
        <i class="fa fa-exchange"></i> Checar alterações
      </button>
      <button id="btnCompleto" class="btn btn-outline-secondary"
              title="Varredura integral: demorada, use quando desconfiar de falhas">
        <i class="fa fa-refresh"></i> Varredura completa
      </button>
      <button id="btnImportarTudo" class="btn btn-success" style="color:#fff!important; display:none">
        <i class="fa fa-download"></i> Importar todos os pendentes
      </button>
      <button id="btnParar" class="btn btn-outline-danger" style="display:none">
        <i class="fa fa-stop"></i> Parar
      </button>
      <button id="btnReanexar" class="btn btn-outline-info" style="display:none">
        <i class="fa fa-paperclip"></i> Baixar anexos faltantes
      </button>
      <button id="btnLacunas" class="btn btn-outline-warning">
        <i class="fa fa-list-ol"></i> Ver lacunas
      </button>
      <button id="btnPorUrl" class="btn btn-outline-primary">
        <i class="fa fa-link"></i> Importar pelo endereço
      </button>
      <button id="btnDiag" class="btn btn-outline-secondary float-right">
        <i class="fa fa-stethoscope"></i> Diagnóstico
      </button>
      <div id="progresso" class="mt-3" style="display:none">
        <div class="progress barra">
          <div id="barra" class="progress-bar progress-bar-striped progress-bar-animated"
               style="width:0%; background:#0f6f77">0%</div>
        </div>
        <div class="meta mt-2" id="detalhe"></div>
      </div>
    </div>

    <ul class="nav nav-tabs mb-3">
      <?php foreach (array('pendentes'=>'Pendentes','importados'=>'Importados',
                           'erros'=>'Com erro','ignorados'=>'Ignorados') as $k=>$v): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $filtro===$k?'active':''; ?>" href="?status=<?php echo $k; ?>"><?php echo $v; ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div id="lista">
    <?php if (!$itens): ?>
      <div class="alert alert-info">Nada nesta situação. Clique em <strong>Verificar novidades</strong>.</div>
    <?php endif; ?>
    <?php foreach ($itens as $it):
        $cls = $it['status']==='novo' ? 'novo' : ($it['status']==='atualizado' ? 'atual' :
              ($it['status']==='erro' ? 'erro' : '')); ?>
      <div class="item <?php echo $cls; ?>" id="item-<?php echo (int)$it['id']; ?>">
        <div style="flex:1; min-width:260px">
          <span class="num"><?php echo htmlspecialchars($it['tipo'].' '.$it['numero'].'/'.$it['ano'], ENT_QUOTES,'UTF-8'); ?></span>
          <span class="meta"><?php echo htmlspecialchars($it['origem'], ENT_QUOTES,'UTF-8'); ?></span>
          <?php if ($it['situacao']): ?>
            <span class="tag" style="background:<?php echo preg_match('/revog/i',$it['situacao'])?'#c0392b':(preg_match('/alterad/i',$it['situacao'])?'#e67e22':'#0f6f77'); ?>">
              <?php echo htmlspecialchars($it['situacao'], ENT_QUOTES,'UTF-8'); ?></span>
          <?php endif; ?>
          <?php if ($it['origem_texto']): ?>
            <span class="meta">· texto via <?php echo htmlspecialchars($it['origem_texto'], ENT_QUOTES,'UTF-8'); ?></span>
          <?php endif; ?>
          <div class="meta mt-1"><?php echo htmlspecialchars(mb_substr((string)$it['ementa'],0,200), ENT_QUOTES,'UTF-8'); ?></div>
          <?php if ($it['mensagem']): ?>
            <div class="meta mt-1" style="color:var(--kb-alerta)"><?php echo htmlspecialchars($it['mensagem'], ENT_QUOTES,'UTF-8'); ?></div>
          <?php endif; ?>
        </div>
        <div>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars($it['url'], ENT_QUOTES,'UTF-8'); ?>">
            <i class="fa fa-external-link"></i> Ver no portal</a>
          <button class="btn btn-sm btn-outline-info" title="Testar o anexo passo a passo"
                  onclick="testarAnexo(<?php echo (int)$it['id']; ?>, this)">
            <i class="fa fa-stethoscope"></i></button>
          <?php if (in_array($it['status'], array('novo','atualizado','erro'), true)): ?>
            <button class="btn btn-sm btn-success" style="color:#fff!important"
                    onclick="importar(<?php echo (int)$it['id']; ?>, this)">
              <i class="fa fa-download"></i> Importar</button>
            <button class="btn btn-sm btn-outline-secondary"
                    onclick="ignorar(<?php echo (int)$it['id']; ?>, this)" title="Ignorar">
              <i class="fa fa-times"></i></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <p class="mt-3">
      <a href="index.php">&larr; Voltar aos provimentos</a>
      <a href="sync_diag.php" target="_blank" class="ml-3 meta">
        <i class="fa fa-stethoscope"></i> Página de diagnóstico
      </a>
    </p>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<?php include(__DIR__ . '/../kb/parcial_swal.php'); ?>
<script>
var rodando = false;

function esc(s){ return $('<div>').text(s==null?'':s).html(); }

function pintarFontes(s){
  var h = '';
  s.fontes.forEach(function(f){
    h += '<div class="fonte"><div><strong>' + esc(f.nome) + '</strong>'
       + (f.ativo == 1 ? '' : ' <span class="meta">(desativada)</span>')
       + '<div class="meta">' + (f.ultima_verif ? 'Última verificação: ' + f.ultima_verif : 'Nunca verificada')
       + (f.ultimo_id ? ' · até o ato #' + f.ultimo_id : '')
       + (f.ultimo_erro ? '<br><span style="color:var(--kb-alerta)">' + esc(f.ultimo_erro) + '</span>' : '')
       + '</div></div><div>'
       + '<button class="btn btn-sm btn-outline-secondary" onclick="toggleFonte(' + f.id + ')">'
       + (f.ativo == 1 ? 'Desativar' : 'Ativar') + '</button> '
       + '<button class="btn btn-sm btn-outline-info" onclick="testarListagem(' + f.id + ')" '
       + 'title="Ver o que a listagem do portal devolve"><i class="fa fa-stethoscope"></i></button> '
       + '<button class="btn btn-sm btn-outline-secondary" onclick="resetFonte(' + f.id + ')" '
       + 'title="Recomeçar a varredura do início">Reiniciar</button>'
       + '</div></div>';
  });
  $('#listaFontes').html(h);

  var pend = s.novos + s.atualizados;
  var detalhe = [];
  if (s.pend_prov) detalhe.push(s.pend_prov + ' provimento(s)');
  if (s.pend_res)  detalhe.push(s.pend_res + ' resolução(ões)');
  $('#btnReanexar').toggle((s.sem_anexo || 0) > 0)
    .html('<i class="fa fa-paperclip"></i> Baixar ' + (s.sem_anexo || 0) + ' anexo(s) faltante(s)');
  $('#btnImportarTudo').toggle(pend > 0).html('<i class="fa fa-download"></i> Importar '
    + (detalhe.length ? detalhe.join(' e ') : pend + ' pendente(s)'));
  if (!s.tem_chave) {
    $('#detalhe').html('<span style="color:var(--kb-alerta)">Chave do Gemini não configurada: '
      + 'PDFs digitalizados sem camada de texto não poderão ser lidos.</span>');
  }
}

function carregar(){ $.post('sync_worker.php', {acao:'status'}, null, 'json').done(function(r){
  if (r.status) pintarFontes(r.status); }); }

function testarListagem(id){
  Swal.fire({title:'Consultando o portal...', didOpen:function(){ Swal.showLoading(); },
             allowOutsideClick:false});
  $.post('sync_worker.php', {acao:'testar_listagem', fonte_id:id}, null, 'json')
   .done(function(r){
     var h = '<div style="text-align:left;font-size:.8rem;font-family:monospace;word-break:break-all">';
     $.each(r.diag, function(k, v){
       var ruim = /FALHOU|^0 ato|NENHUM|nao tem|Provavel/.test(v);
       h += '<div style="margin-bottom:5px"><b>' + esc(k) + ':</b> <span style="color:'
          + (ruim ? '#c0392b' : '#0f6f77') + '">' + esc(v) + '</span></div>';
     });
     Swal.fire({title:'Teste da listagem', html:h + '</div>', width:820,
                confirmButtonColor:'#0f6f77'});
   })
   .fail(function(xhr){ Swal.fire({icon:'error', title:'Erro no servidor',
       html:'<div style="text-align:left;font-size:.78rem;font-family:monospace">'
          + esc(mensagemDoErro(xhr)) + '</div>', width:720}); });
}

/**
 * Extrai a mensagem util de uma resposta com erro. O console mostra so
 * "500 Internal Server Error"; o motivo vem no corpo.
 */
function mensagemDoErro(xhr){
  var t = (xhr && xhr.responseText) || '';
  try { var j = JSON.parse(t); return j.mensagem || j.message || t; } catch(e){}
  var limpo = t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  return limpo ? limpo.substring(0, 700)
               : 'Sem resposta do servidor. Abra a Página de diagnóstico.';
}

function toggleFonte(id){ $.post('sync_worker.php', {acao:'fonte_toggle', fonte_id:id}, null, 'json')
  .done(function(r){ pintarFontes(r.status); }); }

function resetFonte(id){
  Swal.fire({title:'Reiniciar a varredura?', text:'A fonte será verificada desde o início na próxima vez.',
    icon:'question', showCancelButton:true, confirmButtonText:'Reiniciar', cancelButtonText:'Cancelar',
    confirmButtonColor:'#0f6f77'}).then(function(r){
      if(!r.isConfirmed) return;
      $.post('sync_worker.php', {acao:'reset_fonte', fonte_id:id}, null, 'json')
        .done(function(x){ pintarFontes(x.status); });
    });
}

function verificar(modo, botao){
  var b = $(botao).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verificando...');
  $('#btnNovos, #btnAlteracoes, #btnCompleto').prop('disabled', true);
  $('#progresso').show();
  $('#detalhe').text('Consultando os portais...');

  $.post('sync_worker.php', {acao:'status'}, null, 'json').done(function(r){
    var fontes = r.status.fontes.filter(function(f){ return f.ativo == 1; });
    var i = 0, total = 0, novos = 0, rodadas = 0, ondeEstou = '';
    var MAX = (modo === 'completo') ? 80 : 12;

    function terminar(){
      $('#btnNovos, #btnAlteracoes, #btnCompleto').prop('disabled', false);
      b.html(botao.getAttribute('data-html') || b.html());
      $('#barra').css('width','100%').text('100%');
      Swal.fire({icon: novos ? 'success' : 'info', title:'Verificação concluída',
        html: novos
          ? '<b>' + novos + '</b> ato(s) novo(s) ou alterado(s).<br>'
            + '<small>' + total + ' ficha(s) analisada(s).</small>'
          : 'Nenhuma novidade. ' + total + ' ficha(s) analisada(s).'})
        .then(function(){ if (novos) location.reload(); });
    }

    (function proximaFonte(){
      if (i >= fontes.length){ terminar(); return; }
      var f = fontes[i];
      rodadas = 0;

      (function lote(){
        rodadas++;
        $('#detalhe').html(esc(f.nome) + ' &middot; ' + esc(ondeEstou || 'lote ' + rodadas)
          + ' &middot; <b>' + novos + '</b> novidade(s) em ' + total + ' ato(s)');
        var pct = Math.min(95, Math.round(((i + Math.min(rodadas/MAX, 0.95)) / fontes.length) * 100));
        $('#barra').css('width', pct + '%').text(pct + '%');

        $.post('sync_worker.php', {acao:'verificar', fonte_id:f.id, modo:modo}, null, 'json')
          .done(function(x){
            if (!x.ok){ i++; proximaFonte(); return; }
            total += x.achados; novos += (x.novos || 0);
            ondeEstou = x.ate_id || '';
            pintarFontes(x.status);
            if (x.concluido || rodadas >= MAX){ i++; proximaFonte(); } else { lote(); }
          })
          .fail(function(xhr){
            var m = mensagemDoErro(xhr);
            $('#detalhe').html('<span style="color:var(--kb-alerta)">' + esc(f.nome) + ': ' + esc(m) + '</span>');
            i++; proximaFonte();
          });
      })();
    })();
  });
}

$('#btnNovos').on('click', function(){ verificar('novos', this); });
$('#btnCompleto').on('click', function(){
  Swal.fire({title:'Varredura completa?',
    html:'Percorre todos os atos dos portais, um a um.<br>Pode levar vários minutos.',
    icon:'question', showCancelButton:true, confirmButtonText:'Executar',
    cancelButtonText:'Cancelar', confirmButtonColor:'#0f6f77'})
   .then(function(r){ if (r.isConfirmed) verificar('completo', document.getElementById('btnCompleto')); });
});

$('#btnAlteracoes').on('click', function(){
  var b = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Conferindo...');
  $('#btnNovos, #btnCompleto').prop('disabled', true);
  $('#progresso').show();
  var conferidos = 0, mudancas = [];

  (function lote(){
    $.post('sync_worker.php', {acao:'checar_alteracoes'}, null, 'json')
     .done(function(r){
       if (!r.ok){ Swal.fire('Erro', r.mensagem || '', 'error'); }
       else {
         conferidos += r.conferidos;
         if (r.mudaram && r.mudaram.length){ mudancas = mudancas.concat(r.mudaram); }
         $('#detalhe').html(conferidos + ' ato(s) conferido(s) &middot; <b>' + mudancas.length
           + '</b> mudança(s) &middot; ' + r.restam + ' restante(s)');
         var tot = conferidos + r.restam;
         $('#barra').css('width', (tot ? Math.round(conferidos/tot*100) : 100) + '%');
         if (!r.concluido && r.conferidos > 0){ lote(); return; }
       }
       var h = mudancas.length
         ? '<div style="text-align:left;font-size:.86rem">' + mudancas.map(function(m){
             return '<div style="margin-bottom:5px"><b>' + esc(m.ato) + '</b><br>'
                  + esc(m.de) + ' &rarr; <span style="color:#c0392b">' + esc(m.para) + '</span></div>';
           }).join('') + '</div>'
         : 'Nenhuma mudança de situação. ' + conferidos + ' ato(s) conferido(s).';
       Swal.fire({icon: mudancas.length ? 'warning' : 'success',
         title: mudancas.length ? mudancas.length + ' ato(s) mudaram de situação' : 'Tudo em ordem',
         html: h, width: 640}).then(function(){ if (mudancas.length) location.reload(); });
       $('#btnNovos, #btnCompleto, #btnAlteracoes').prop('disabled', false);
       b.html('<i class="fa fa-exchange"></i> Checar alterações');
     })
     .fail(function(){
       Swal.fire('Erro','Falha de comunicação.','error');
       $('#btnNovos, #btnCompleto, #btnAlteracoes').prop('disabled', false);
       b.html('<i class="fa fa-exchange"></i> Checar alterações');
     });
  })();
});

function importar(id, btn){
  var b = $(btn).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
  $.post('sync_worker.php', {acao:'importar', item_id:id}, null, 'json')
   .done(function(r){
     Swal.fire({icon: r.ok?'success':'error', title: r.ok?'Importado':'Não deu certo',
       text: r.mensagem, confirmButtonColor:'#0f6f77'})
       .then(function(){ if(r.ok) location.reload(); });
   })
   .fail(function(){ Swal.fire('Erro','Falha de comunicação com o servidor.','error'); })
   .always(function(){ b.prop('disabled', false).html('<i class="fa fa-download"></i> Importar'); });
}

function testarAnexo(id, btn){
  var b = $(btn).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
  $.post('sync_worker.php', {acao:'testar_anexo', item_id:id}, null, 'json')
   .done(function(r){
     var h = '<div style="text-align:left;font-size:.8rem;font-family:monospace;word-break:break-all">';
     $.each(r.diag, function(k, v){
       var ruim = /FALHOU|NENHUM|NAO |SEM PERMISSAO/.test(v);
       h += '<div style="margin-bottom:4px"><b>' + esc(k) + ':</b> <span style="color:'
          + (ruim ? '#c0392b' : '#0f6f77') + '">' + esc(v) + '</span></div>';
     });
     Swal.fire({title:'Teste do anexo', html:h + '</div>', width:760, confirmButtonColor:'#0f6f77'})
       .then(function(){ if (JSON.stringify(r.diag).indexOf('GRAVADO') > -1) location.reload(); });
   })
   .fail(function(xhr){ Swal.fire('Erro', esc((xhr.responseText||'').substring(0,500)), 'error'); })
   .always(function(){ b.prop('disabled', false).html('<i class="fa fa-stethoscope"></i>'); });
}

function ignorar(id, btn){
  $.post('sync_worker.php', {acao:'ignorar', item_id:id}, null, 'json')
   .done(function(){ $('#item-'+id).fadeOut(200); });
}

$('#btnImportarTudo').on('click', function(){
  Swal.fire({title:'Importar todos os pendentes?',
    html:'Cada ato tem o texto baixado do portal.<br>Mantenha esta aba aberta.',
    icon:'question', showCancelButton:true, confirmButtonText:'Importar',
    cancelButtonText:'Cancelar', confirmButtonColor:'#0f6f77'}).then(function(res){
      if(!res.isConfirmed) return;
      rodando = true;
      $('#btnImportarTudo, #btnNovos, #btnAlteracoes, #btnCompleto').hide();
      $('#btnParar').show();
      $('#progresso').show();

      var inicial = 0;
      (function lote(){
        if(!rodando) return;
        $.post('sync_worker.php', {acao:'importar_lote'}, null, 'json')
         .done(function(r){
           if(!r.ok){ Swal.fire('Erro', r.mensagem||'', 'error'); rodando=false; return; }
           var pend = r.status.novos + r.status.atualizados;
           if(!inicial) inicial = pend + r.feitos;
           var pct = inicial ? Math.round((inicial-pend)/inicial*100) : 100;
           $('#barra').css('width', pct+'%').text(pct+'%');
           $('#detalhe').text(pend + ' restante(s) · ' + r.status.importados + ' importado(s) · '
                              + r.status.erros + ' com erro');
           if(r.concluido || r.feitos === 0){
             rodando = false;
             Swal.fire({icon:'success', title:'Importação concluída',
               text:'Agora indexe a base na tela da Aria para as respostas usarem os atos novos.'})
               .then(function(){ location.reload(); });
             return;
           }
           lote();
         })
         .fail(function(){ rodando=false; Swal.fire('Erro','Falha de comunicação.','error'); });
      })();
    });
});

$('#btnParar').on('click', function(){ rodando = false; location.reload(); });

$('#btnReanexar').on('click', function () {
  var b = $(this).prop('disabled', true);
  $('#progresso').show();
  (function lote(){
    $.post('sync_worker.php', {acao: 'reanexar'}, null, 'json')
     .done(function (r) {
       if (!r.ok) { Swal.fire('Erro', r.mensagem || '', 'error'); b.prop('disabled', false); return; }
       $('#detalhe').text(r.restam + ' anexo(s) faltando'
         + (r.falhas && r.falhas.length ? ' · ' + r.falhas[0] : ''));
       if (r.concluido || (r.anexados === 0 && r.restam === 0)) {
         Swal.fire({icon:'success', title:'Anexos concluídos',
           text: r.restam ? r.restam + ' não têm PDF disponível no portal.' : 'Todos baixados.'})
           .then(function(){ location.reload(); });
         b.prop('disabled', false);
         return;
       }
       if (r.anexados === 0) {   // nao avancou: os que restam nao tem PDF
         Swal.fire({icon:'info', title:'Sem mais o que baixar',
           html: r.restam + ' ato(s) sem PDF na ficha do portal.'
               + (r.falhas && r.falhas.length ? '<br><small>' + esc(r.falhas[0]) + '</small>' : '')});
         b.prop('disabled', false);
         return;
       }
       lote();
     })
     .fail(function(){ Swal.fire('Erro','Falha de comunicação.','error'); b.prop('disabled', false); });
  })();
});

$('#btnLacunas').on('click', function () {
  var b = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
  $.post('sync_worker.php', {acao:'lacunas'}, null, 'json')
   .done(function (r) {
     var h;
     if (!r.lacunas || !r.lacunas.length) {
       h = 'Nenhum buraco na numeração dos últimos anos.';
     } else {
       h = '<div style="text-align:left;font-size:.86rem">'
         + '<p class="meta">O CNJ numera em sequência contínua entre os anos; a CGJ/MA '
         + 'reinicia a cada ano.<br>'
         + '<b>No portal</b> = existe na listagem e pode ser importado. '
         + '<b>Fora da listagem</b> = o portal não expõe esse número, então provavelmente '
         + 'não existe, é de outro tipo de ato ou está em outra listagem.</p>';
       r.lacunas.forEach(function (l) {
         h += '<div style="margin-bottom:10px"><b>' + esc(l.origem) + ' &middot; ' + l.ano + '</b>'
            + ' <span class="meta">(tem ' + l.tem + ', maior é ' + l.maior + ')</span><br>'
            + '<span style="font-family:monospace;font-size:.8rem;user-select:all">'
            + esc(l.resumo)
                .replace('NO PORTAL (da para importar):', '<b style="color:#0f6f77">NO PORTAL '
                  + '(dá para importar):</b>')
                .replace('fora da listagem:', '<b style="color:#8a97a5">fora da listagem:</b>')
            + '</span></div>';
       });
       h += '</div>';
     }
     var temLacuna = r.lacunas && r.lacunas.length;
     var temImportavel = temLacuna && r.lacunas.some(function(l){
       return l.importaveis && l.importaveis.length; });
     Swal.fire({
       title: 'Lacunas na numeração', html: h, width: 720,
       showCancelButton: temLacuna,
       showDenyButton: temImportavel,
       confirmButtonText: temLacuna ? 'Buscar nos portais' : 'Fechar',
       denyButtonText: 'Reabrir os que já estão no portal',
       cancelButtonText: 'Fechar',
       confirmButtonColor: '#0f6f77', denyButtonColor: '#e67e22'
     }).then(function (res) {
       if (res.isConfirmed && temLacuna) { buscarLacunas(); }
       else if (res.isDenied) { reabrirLacunas(); }
     });
   })
   .fail(function(){ Swal.fire('Erro','Falha ao consultar.','error'); })
   .always(function(){ b.prop('disabled', false).html('<i class="fa fa-list-ol"></i> Ver lacunas'); });
});

function reabrirLacunas(){
  Swal.fire({title:'Reabrindo...', didOpen:function(){ Swal.showLoading(); }, allowOutsideClick:false});
  $.post('sync_worker.php', {acao:'reabrir_lacunas'}, null, 'json')
   .done(function(r){
     var h = '<div style="text-align:left;font-size:.88rem">'
       + '<b>' + r.reabertos + '</b> ato(s) devolvido(s) à fila de importação.<br>'
       + '<b>' + r.pendentes + '</b> no total aguardando importação.'
       + (r.detalhe && r.detalhe.length
          ? '<div class="meta" style="margin-top:8px;font-family:monospace">'
            + esc(r.detalhe.join(', ')) + '</div>' : '')
       + '</div>';
     Swal.fire({icon: (r.reabertos || r.pendentes) ? 'success' : 'info',
       title:'Fila atualizada', html:h, width:660, confirmButtonColor:'#0f6f77'})
      .then(function(){ if (r.reabertos || r.pendentes) location.reload(); });
   })
   .fail(function(xhr){ Swal.fire({icon:'error', title:'Erro',
     html:'<div style="text-align:left;font-size:.8rem">' + esc(mensagemDoErro(xhr)) + '</div>'}); });
}

function buscarLacunas(){
  $('#btnNovos, #btnAlteracoes, #btnCompleto, #btnLacunas').prop('disabled', true);
  $('#progresso').show();
  $('#detalhe').text('Procurando os atos que faltam...');

  $.post('sync_worker.php', {acao:'status'}, null, 'json').done(function (r0) {
    var fontes = r0.status.fontes.filter(function(f){ return f.ativo == 1; });
    var i = 0, achados = 0, novos = 0, rodadas = 0;
    var MAX = 120;   // teto por fonte

    function fim(){
      $('#btnNovos, #btnAlteracoes, #btnCompleto, #btnLacunas').prop('disabled', false);
      $('#barra').css('width','100%').text('100%');
      Swal.fire({icon: novos ? 'success' : 'info',
        title: novos ? novos + ' ato(s) recuperado(s)' : 'Busca encerrada',
        html: novos
          ? 'Os atos que faltavam foram localizados e estão em <b>Pendentes</b>.'
          : 'Não localizei os atos que faltam nos portais.<br>'
            + '<small>Podem ter sido revogados, ter numeração fora do padrão, '
            + 'ou pertencer a outra listagem. Use <b>Importar pelo endereço</b> '
            + 'se souber a URL.</small>'})
        .then(function(){ if (novos) location.reload(); });
    }

    (function proxima(){
      if (i >= fontes.length){ fim(); return; }
      var f = fontes[i];
      rodadas = 0;

      (function lote(){
        rodadas++;
        $('#detalhe').html(esc(f.nome) + ' &middot; ' + '<b>' + novos + '</b> recuperado(s)'
          + ' &middot; lote ' + rodadas);
        $('#barra').css('width', Math.min(95, Math.round((i/fontes.length)*100 + rodadas/MAX*50)) + '%');

        $.post('sync_worker.php', {acao:'buscar_lacunas', fonte_id:f.id}, null, 'json')
          .done(function(x){
            if (!x.ok){ i++; proxima(); return; }
            achados += x.achados; novos += (x.novos || 0);
            $('#detalhe').html(esc(f.nome) + ' &middot; <b>' + novos + '</b> recuperado(s)'
              + ' &middot; ' + x.faltam + ' número(s) ainda faltando &middot; ' + esc(x.ate_id));
            if (x.concluido || rodadas >= MAX){ i++; proxima(); } else { lote(); }
          })
          .fail(function(xhr){
            var m = mensagemDoErro(xhr);
            $('#detalhe').html('<span style="color:var(--kb-alerta)">' + esc(m) + '</span>');
            i++; proxima();
          });
      })();
    })();
  });
}

$('#btnPorUrl').on('click', function () {
  Swal.fire({
    title: 'Importar pelo endereço',
    html: '<div style="text-align:left;font-size:.88rem">'
        + '<p>Cole o endereço da ficha no portal, ou só o número do ato no CNJ.</p>'
        + '<input id="urlAto" class="swal2-input" style="margin:0;width:100%" '
        + 'placeholder="https://atos.cnj.jus.br/atos/detalhar/6929">'
        + '<p class="meta" style="margin-top:8px">Serve para os que a varredura não '
        + 'alcança: no CNJ os identificadores recentes não são sequenciais.</p></div>',
    showCancelButton: true, confirmButtonText: 'Importar', cancelButtonText: 'Cancelar',
    confirmButtonColor: '#0f6f77',
    preConfirm: function(){ return (document.getElementById('urlAto') || {}).value || ''; }
  }).then(function (res) {
    if (!res.isConfirmed || !res.value) return;
    Swal.fire({title:'Importando...', didOpen: function(){ Swal.showLoading(); },
               allowOutsideClick:false});
    $.post('sync_worker.php', {acao:'importar_url', url: res.value}, null, 'json')
     .done(function (r) {
       Swal.fire({icon: r.ok ? 'success' : 'error',
         title: r.ok ? 'Importado' : 'Não deu certo',
         html: (r.ato ? '<b>' + esc(r.ato) + '</b><br>' : '') + esc(r.mensagem || ''),
         confirmButtonColor:'#0f6f77'})
        .then(function(){ if (r.ok) location.reload(); });
     })
     .fail(function(xhr){
       Swal.fire({icon:'error', title:'Erro', html:'<div style="text-align:left;font-size:.8rem">'
         + esc(mensagemDoErro(xhr)) + '</div>', width:700});
     });
  });
});

$('#btnDiag').on('click', function () {
  var b = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
  $.post('sync_worker.php', {acao: 'diagnostico'}, null, 'json')
   .done(function (r) {
     var h = '<div style="text-align:left;font-size:.82rem;font-family:monospace">';
     $.each(r.diag, function (k, v) {
       var ruim = /AUSENTE|FALHOU|SEM PERMISSAO|NAO EXISTE|ERRO/.test(v);
       h += '<div style="margin-bottom:3px">' + esc(k) + ': <span style="color:'
          + (ruim ? '#c0392b' : '#0f6f77') + '">' + esc(v) + '</span></div>';
     });
     Swal.fire({title: 'Diagnóstico', html: h + '</div>', width: 700, confirmButtonColor: '#0f6f77'});
   })
   .fail(function (xhr) {
     Swal.fire('Erro no diagnóstico',
       esc((xhr.responseText || '').substring(0, 600)) || 'Sem resposta do servidor.', 'error');
   })
   .always(function () { b.prop('disabled', false).html('<i class="fa fa-stethoscope"></i> Diagnóstico'); });
});

carregar();
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
