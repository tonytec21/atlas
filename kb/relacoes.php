<?php
/**
 * atlas/kb/relacoes.php
 * Revisao das relacoes entre normas (revogacao / alteracao).
 *
 * A extracao e automatica, mas a aplicacao NAO: nenhuma norma e marcada como
 * revogada sem confirmacao humana. Errar aqui faz a Aria esconder dispositivo
 * valido ou citar dispositivo revogado -- os dois casos sao graves.
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
require_once __DIR__ . '/lib_kb.php';
require_once __DIR__ . '/schema_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

date_default_timezone_set('America/Sao_Paulo');
$conn = getDatabaseConnection();
if (!kbSchemaExiste($conn)) {
    kbGarantirSchema($conn);
}

// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    kbBlindarJson();

    try {
        $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
        $id   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $quem = isset($_SESSION['username']) ? $_SESSION['username'] : null;

        if ($acao === 'confirmar' || $acao === 'descartar') {
            $novo = ($acao === 'confirmar') ? 'confirmada' : 'descartada';
            $st = $conn->prepare(
                "UPDATE kb_relacoes SET status = :s, confirmado_por = :p, confirmado_em = NOW()
                  WHERE id = :id"
            );
            $st->execute(array(':s' => $novo, ':p' => $quem, ':id' => $id));

            $afetados = kbAplicarRelacoes($conn);
            echo json_encode(array('ok' => true,
                'mensagem' => $acao === 'confirmar'
                    ? "Relação confirmada. {$afetados} trecho(s) marcados no acervo."
                    : 'Sugestão descartada.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'reaplicar') {
            $afetados = kbAplicarRelacoes($conn);
            echo json_encode(array('ok' => true,
                'mensagem' => "Recalculado: {$afetados} trecho(s) marcados."), JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(array('ok' => false, 'mensagem' => 'Ação desconhecida.'));
    } catch (Throwable $e) {
        error_log('[kb/relacoes] ' . $e->getMessage());
        echo json_encode(array('ok' => false, 'mensagem' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// --------------------------------------------------------------------------
$filtro = isset($_GET['status']) ? $_GET['status'] : 'sugerida';
if (!in_array($filtro, array('sugerida', 'confirmada', 'descartada'), true)) {
    $filtro = 'sugerida';
}

$st = $conn->prepare("
    SELECT r.*,
           po.numero_provimento AS o_num, po.tipo AS o_tipo, po.origem AS o_origem,
           YEAR(po.data_provimento) AS o_ano, po.descricao AS o_desc,
           pd.numero_provimento AS d_num, pd.tipo AS d_tipo, pd.origem AS d_origem,
           YEAR(pd.data_provimento) AS d_ano, pd.descricao AS d_desc
      FROM kb_relacoes r
      JOIN provimentos po ON po.id = r.origem_id
      LEFT JOIN provimentos pd ON pd.id = r.destino_id
     WHERE r.status = :s
     ORDER BY po.data_provimento DESC, r.id DESC
     LIMIT 300");
$st->execute(array(':s' => $filtro));
$lista = $st->fetchAll(PDO::FETCH_ASSOC);

$contagem = array();
foreach ($conn->query("SELECT status, COUNT(*) n FROM kb_relacoes GROUP BY status") as $r) {
    $contagem[$r['status']] = (int) $r['n'];
}
$marcados = $conn->query("
    SELECT SUM(situacao='revogado') rev, SUM(situacao='alterado') alt FROM kb_chunks")
    ->fetch(PDO::FETCH_ASSOC);

$rotulos = array(
    'revoga_total'   => array('Revoga integralmente', '#c0392b'),
    'revoga_parcial' => array('Revoga dispositivos',  '#e67e22'),
    'altera'         => array('Altera redação',       '#2980b9'),
);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rela&ccedil;&otilde;es entre normas &middot; Aria</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        body.light-mode {
            --kb-sup:#ffffff; --kb-sup2:#f7fafb; --kb-brd:#d5dde5; --kb-txt:#2d3748;
            --kb-txt2:#8a97a5; --kb-ac:#0f6f77; --kb-ac-bg:#0f6f77; --kb-hover:#f2f6f7;
            --kb-in-bg:#ffffff; --kb-in-txt:#2d3748; --kb-alerta:#c0392b;
        }
        body.dark-mode {
            --kb-sup:#0b1324; --kb-sup2:#0e1627; --kb-brd:rgba(255,255,255,.10); --kb-txt:#e5e7eb;
            --kb-txt2:#9ca3af; --kb-ac:#5eead4; --kb-ac-bg:#0f766e; --kb-hover:rgba(255,255,255,.06);
            --kb-in-bg:#0b1324; --kb-in-txt:#e5e7eb; --kb-alerta:#f87171;
        }

        .rel      { color:var(--kb-txt); border:1px solid var(--kb-brd); border-radius:8px; padding:16px 20px;
                    margin-bottom:12px; background:var(--kb-sup); }
        .tag      { font-size:.72rem; padding:3px 10px; border-radius:10px; color:#fff; }
        .seta     { color:var(--kb-txt2); margin:0 8px; }
        .norma    { font-weight:600; }
        .meta     { font-size:.82rem; color:var(--kb-txt2); }
        .trecho   { font-size:.86rem; color:var(--kb-txt); background:var(--kb-sup2); padding:10px 14px;
                    border-left:3px solid var(--kb-brd); border-radius:0 4px 4px 0; margin-top:10px; }
        .disp     { font-family:monospace; font-size:.84rem; color:var(--kb-ac); }
        .naoachou { color:var(--kb-alerta); font-size:.82rem; }
        .painel   { color:var(--kb-txt); border:1px solid var(--kb-brd); border-radius:8px; padding:14px 18px;
                    background:var(--kb-sup2); margin-bottom:20px; }
            .form-control { background:var(--kb-in-bg); color:var(--kb-in-txt); border-color:var(--kb-brd); }
        .form-control:focus { background:var(--kb-in-bg); color:var(--kb-in-txt); border-color:var(--kb-ac); box-shadow:none; }
        .nav-tabs .nav-link { color:var(--kb-txt2); }
        .nav-tabs .nav-link.active { background:var(--kb-sup); color:var(--kb-ac); border-color:var(--kb-brd) var(--kb-brd) var(--kb-sup); }
        pre { color:var(--kb-txt); }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <h3>Rela&ccedil;&otilde;es entre normas</h3>
    <p class="meta">A Aria l&ecirc; os provimentos novos e sugere quais normas anteriores
       eles revogam ou alteram. Nada vale at&eacute; voc&ecirc; confirmar.</p>
    <hr>

    <div class="painel d-flex justify-content-between flex-wrap align-items-center">
      <div>
        <strong><?php echo (int) $marcados['rev']; ?></strong> trechos revogados &middot;
        <strong><?php echo (int) $marcados['alt']; ?></strong> alterados
        <div class="meta">Trechos revogados ficam fora das respostas; alterados entram com aviso.</div>
      </div>
      <button class="btn btn-sm btn-outline-secondary" onclick="agir('reaplicar', 0, this)">
        <i class="fa fa-refresh"></i> Recalcular marca&ccedil;&otilde;es
      </button>
    </div>

    <ul class="nav nav-tabs mb-3">
      <?php foreach (array('sugerida' => 'Aguardando revis&atilde;o',
                           'confirmada' => 'Confirmadas',
                           'descartada' => 'Descartadas') as $k => $v): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $filtro === $k ? 'active' : ''; ?>"
             href="?status=<?php echo $k; ?>">
            <?php echo $v; ?>
            <span class="badge badge-secondary"><?php echo isset($contagem[$k]) ? $contagem[$k] : 0; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (empty($lista)): ?>
      <div class="alert alert-info">
        <?php if ($filtro === 'sugerida'): ?>
          Nada aguardando revis&atilde;o. Novas sugest&otilde;es aparecem aqui depois
          que voc&ecirc; indexar provimentos novos.
        <?php else: ?>
          Nenhuma rela&ccedil;&atilde;o nesta situa&ccedil;&atilde;o.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($lista as $r): ?>
      <?php $rot = $rotulos[$r['tipo']]; ?>
      <div class="rel" id="rel-<?php echo (int) $r['id']; ?>">
        <span class="tag" style="background:<?php echo $rot[1]; ?>"><?php echo $rot[0]; ?></span>

        <div class="mt-2">
          <span class="norma"><?php
            echo htmlspecialchars($r['o_tipo'] . ' ' . $r['o_num'] . '/' . $r['o_ano']
                 . ' ' . $r['o_origem'], ENT_QUOTES, 'UTF-8'); ?></span>
          <i class="fa fa-long-arrow-right seta"></i>
          <?php if ($r['destino_id']): ?>
            <span class="norma"><?php
              echo htmlspecialchars($r['d_tipo'] . ' ' . $r['d_num'] . '/' . $r['d_ano']
                   . ' ' . $r['d_origem'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php else: ?>
            <span class="naoachou">
              <?php echo htmlspecialchars($r['destino_texto'], ENT_QUOTES, 'UTF-8'); ?>
              &mdash; n&atilde;o localizada no acervo
            </span>
          <?php endif; ?>
        </div>

        <?php if ($r['dispositivos']): ?>
          <div class="mt-1 disp"><?php echo htmlspecialchars($r['dispositivos'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($r['trecho']): ?>
          <div class="trecho"><?php echo htmlspecialchars($r['trecho'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($filtro === 'sugerida'): ?>
          <div class="mt-3">
            <?php if ($r['destino_id']): ?>
              <button class="btn btn-sm btn-success" onclick="agir('confirmar', <?php echo (int) $r['id']; ?>, this)">
                <i class="fa fa-check"></i> Confirmar
              </button>
            <?php else: ?>
              <span class="meta mr-2">Sem norma correspondente no acervo, n&atilde;o d&aacute; para aplicar.</span>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="agir('descartar', <?php echo (int) $r['id']; ?>, this)">
              <i class="fa fa-times"></i> Descartar
            </button>
          </div>
        <?php else: ?>
          <div class="meta mt-2">
            <?php echo htmlspecialchars($filtro === 'confirmada' ? 'Confirmada' : 'Descartada', ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($r['confirmado_em']): ?>
              em <?php echo date('d/m/Y H:i', strtotime($r['confirmado_em'])); ?>
              <?php if ($r['confirmado_por']): ?>
                por <?php echo htmlspecialchars($r['confirmado_por'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <p class="mt-3"><a href="aria.php">&larr; Voltar para a consulta</a></p>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<?php include(__DIR__ . '/parcial_swal.php'); ?>
<script>
function agir(acao, id, botao) {
    var b = $(botao), html = b.html();
    b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.post('relacoes.php', { acao: acao, id: id }, null, 'json')
     .done(function (r) {
         Swal.fire({
             icon: r.ok ? 'success' : 'error',
             title: r.ok ? 'Pronto' : 'Não deu certo',
             text: r.mensagem, confirmButtonColor: '#0f6f77'
         }).then(function () { if (r.ok) location.reload(); });
     })
     .fail(function () { Swal.fire('Erro', 'Falha de comunicação com o servidor.', 'error'); })
     .always(function () { b.prop('disabled', false).html(html); });
}
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
