<?php
/**
 * atlas/kb/configurar.php
 * Tela unica para configurar a chave da API do Gemini.
 * A chave fica na tabela kb_config -- sem mexer em arquivo nem em permissao.
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
// AJAX: testar / salvar / remover
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    kbBlindarJson();
    $acao  = isset($_POST['acao']) ? $_POST['acao'] : '';
    $chave = isset($_POST['chave']) ? trim($_POST['chave']) : '';
    $quem  = isset($_SESSION['username']) ? $_SESSION['username'] : null;

    try {
        if ($acao === 'testar') {
            echo json_encode(kbTestarChave($chave), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar') {
            $r = kbTestarChave($chave);
            if (!$r['ok']) {
                echo json_encode(array('ok' => false,
                    'mensagem' => 'Não salvei: ' . $r['mensagem']), JSON_UNESCAPED_UNICODE);
                exit;
            }
            kbSalvarApiKey($conn, $chave, $quem);
            echo json_encode(array('ok' => true,
                'mensagem' => 'Chave salva e testada com sucesso.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ---------------- modelos ----------------
        if ($acao === 'modelo_add') {
            $tipo = $_POST['tipo'] === 'embedding' ? 'embedding' : 'chat';
            $nome = trim($_POST['nome']);
            $rot  = trim(isset($_POST['rotulo']) ? $_POST['rotulo'] : '');
            $dim  = ($tipo === 'embedding' && !empty($_POST['dimensao'])) ? (int) $_POST['dimensao'] : null;

            if (!preg_match('/^[a-z0-9][a-z0-9.\-]{2,110}$/i', $nome)) {
                echo json_encode(array('ok' => false,
                    'mensagem' => 'Nome inválido. Use o identificador da API, ex.: gemini-3.1-flash-lite.'),
                    JSON_UNESCAPED_UNICODE);
                exit;
            }

            $st = $conn->prepare(
                "INSERT INTO kb_modelos (tipo, nome, rotulo, dimensao, ativo, criado_em)
                 VALUES (:t, :n, :r, :d, 0, NOW())
                 ON DUPLICATE KEY UPDATE rotulo = VALUES(rotulo), dimensao = VALUES(dimensao)"
            );
            $st->execute(array(':t' => $tipo, ':n' => $nome, ':r' => ($rot ?: null), ':d' => $dim));
            echo json_encode(array('ok' => true, 'mensagem' => 'Modelo cadastrado.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'modelo_ativar') {
            $id = (int) $_POST['id'];
            $st = $conn->prepare("SELECT * FROM kb_modelos WHERE id = :id");
            $st->execute(array(':id' => $id));
            $m = $st->fetch(PDO::FETCH_ASSOC);
            if (!$m) {
                echo json_encode(array('ok' => false, 'mensagem' => 'Modelo não encontrado.'));
                exit;
            }

            // Trocar o modelo de embedding muda o espaco vetorial: os vetores
            // antigos deixam de ser comparaveis com as novas consultas. Nao ha
            // conversao possivel -- so reindexando.
            $invalidar = 0;
            if ($m['tipo'] === 'embedding') {
                $atualNome = kbModelo('embedding');
                if ($atualNome !== $m['nome']) {
                    $invalidar = (int) $conn->query(
                        "SELECT COUNT(*) FROM kb_chunks WHERE embedding IS NOT NULL")->fetchColumn();
                    if ($invalidar > 0 && empty($_POST['confirmado'])) {
                        echo json_encode(array(
                            'ok' => false, 'precisa_confirmar' => true, 'total' => $invalidar,
                            'mensagem' => 'Trocar o modelo de embedding invalida os '
                                . number_format($invalidar, 0, ',', '.')
                                . ' trechos já indexados. Eles terão de ser reindexados do zero.',
                        ), JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }
            }

            $conn->beginTransaction();
            $conn->prepare("UPDATE kb_modelos SET ativo = 0 WHERE tipo = :t")
                 ->execute(array(':t' => $m['tipo']));
            $conn->prepare("UPDATE kb_modelos SET ativo = 1 WHERE id = :id")
                 ->execute(array(':id' => $id));
            if ($m['tipo'] === 'embedding' && $invalidar > 0) {
                $conn->exec("UPDATE kb_chunks SET embedding = NULL, dim = NULL, indexado_em = NULL");
            }
            $conn->commit();

            echo json_encode(array('ok' => true, 'mensagem' => $invalidar > 0
                ? 'Modelo ativado. Reindexe a base pela tela de consulta.'
                : 'Modelo ativado.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'modelo_excluir') {
            $id = (int) $_POST['id'];
            $st = $conn->prepare("SELECT * FROM kb_modelos WHERE id = :id");
            $st->execute(array(':id' => $id));
            $m = $st->fetch(PDO::FETCH_ASSOC);

            if (!$m) {
                echo json_encode(array('ok' => false, 'mensagem' => 'Modelo não encontrado.'));
                exit;
            }
            if ((int) $m['ativo'] === 1) {
                echo json_encode(array('ok' => false,
                    'mensagem' => 'Não dá para excluir o modelo em uso. Ative outro antes.'),
                    JSON_UNESCAPED_UNICODE);
                exit;
            }
            $conn->prepare("DELETE FROM kb_modelos WHERE id = :id")->execute(array(':id' => $id));
            echo json_encode(array('ok' => true, 'mensagem' => 'Modelo excluído.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'remover') {
            $conn->exec("DELETE FROM kb_config WHERE chave = 'gemini_api_key'");
            echo json_encode(array('ok' => true, 'mensagem' => 'Chave removida.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(array('ok' => false, 'mensagem' => 'Ação desconhecida.'));
    } catch (Throwable $e) {
        error_log('[kb/configurar] ' . $e->getMessage());
        echo json_encode(array('ok' => false, 'mensagem' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// --------------------------------------------------------------------------
// Estado atual
// --------------------------------------------------------------------------
$modelos = array('chat' => array(), 'embedding' => array());
foreach ($conn->query("SELECT * FROM kb_modelos ORDER BY tipo, ativo DESC, nome")
              ->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $modelos[$m['tipo']][] = $m;
}

$atual = kbApiKey();
$temChave = ($atual !== '');
$mascara  = $temChave
    ? substr($atual, 0, 8) . str_repeat('*', 20) . substr($atual, -4)
    : '';

$origem = 'nenhuma';
if (getenv('GEMINI_API_KEY')) {
    $origem = 'variável de ambiente';
} elseif ($temChave) {
    $st = $conn->prepare("SELECT funcionario, atualizado_em FROM kb_config WHERE chave='gemini_api_key'");
    $st->execute();
    $meta = $st->fetch(PDO::FETCH_ASSOC);
    $origem = $meta ? 'salva no sistema' : 'arquivo config_kb.local.php';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar chave da API &middot; Aria</title>
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

        .cx     { color:var(--kb-txt); border:1px solid var(--kb-brd); border-radius:8px; padding:20px 24px; background:var(--kb-sup); max-width:720px; }
        .meta   { font-size:.84rem; color:var(--kb-txt2); }
        .mask   { font-family:monospace; letter-spacing:.5px; }
        .passos { font-size:.9rem; color:var(--kb-txt); padding-left:20px; }
        .passos li { margin-bottom:6px; }
        .tipo-cab { font-size:.8rem; text-transform:uppercase; letter-spacing:.5px;
                    color:var(--kb-txt2); margin-bottom:6px; }
        .mdl     { color:var(--kb-txt); display:flex; justify-content:space-between; align-items:center;
                   flex-wrap:wrap; gap:8px; border:1px solid var(--kb-brd); border-radius:6px;
                   padding:10px 14px; margin-bottom:6px; background:var(--kb-sup2); }
        .mdl.on  { border-color:var(--kb-ac); background:var(--kb-hover); }
        .selo    { font-size:.7rem; padding:2px 8px; border-radius:10px;
                   background:var(--kb-ac-bg); color:#fff; margin-left:6px; }
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

    <h3>Chave da API do Gemini</h3>
    <p class="meta">Usada pela Aria para entender as perguntas e redigir as respostas
       a partir dos provimentos.</p>
    <hr>

    <div class="cx mb-4">
      <?php if ($temChave): ?>
        <div class="alert alert-success mb-3">
          <i class="fa fa-check-circle"></i>
          <strong>Chave configurada</strong>
          <span class="mask ml-2"><?php echo htmlspecialchars($mascara, ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="meta mt-1">Origem: <?php echo htmlspecialchars($origem, ENT_QUOTES, 'UTF-8'); ?><?php
            if (!empty($meta['atualizado_em'])) {
                echo ' &middot; atualizada em ' . date('d/m/Y H:i', strtotime($meta['atualizado_em']));
                if (!empty($meta['funcionario'])) {
                    echo ' por ' . htmlspecialchars($meta['funcionario'], ENT_QUOTES, 'UTF-8');
                }
            }
          ?></div>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label for="chave"><strong><?php echo $temChave ? 'Substituir a chave' : 'Chave da API'; ?></strong></label>
        <div class="input-group">
          <input type="password" class="form-control" id="chave"
                 placeholder="AIza..." autocomplete="off" spellcheck="false">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" id="btnVer" title="Mostrar">
              <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>
        <small class="meta">Ao salvar, a chave é testada contra o Google antes de ser gravada.</small>
      </div>

      <div class="d-flex flex-wrap" style="gap:8px;">
        <button id="btnSalvar" class="btn btn-primary" style="color:#fff!important">
          <i class="fa fa-save"></i> Testar e salvar
        </button>
        <button id="btnTestar" class="btn btn-outline-secondary">
          <i class="fa fa-plug"></i> Só testar
        </button>
        <?php if ($temChave): ?>
          <button id="btnRemover" class="btn btn-outline-danger ml-auto">
            <i class="fa fa-trash"></i> Remover
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="cx mb-4">
      <h5>Modelos</h5>
      <p class="meta">Identificadores da API do Google. O Google descontinua modelos
         periodicamente &mdash; quando isso acontecer, cadastre o substituto aqui,
         sem mexer no c&oacute;digo.</p>

      <?php foreach (array('chat' => 'Gera&ccedil;&atilde;o de respostas',
                           'embedding' => 'Busca sem&acirc;ntica') as $tipo => $titulo): ?>
        <div class="mb-3">
          <div class="tipo-cab"><?php echo $titulo; ?></div>
          <?php if (empty($modelos[$tipo])): ?>
            <div class="meta">Nenhum modelo cadastrado.</div>
          <?php endif; ?>
          <?php foreach ($modelos[$tipo] as $m): ?>
            <div class="mdl <?php echo $m['ativo'] ? 'on' : ''; ?>">
              <div style="flex:1; min-width:220px;">
                <code><?php echo htmlspecialchars($m['nome'], ENT_QUOTES, 'UTF-8'); ?></code>
                <?php if ($m['ativo']): ?><span class="selo">em uso</span><?php endif; ?>
                <?php if ($m['rotulo']): ?>
                  <div class="meta"><?php echo htmlspecialchars($m['rotulo'], ENT_QUOTES, 'UTF-8'); ?><?php
                    if ($m['dimensao']) { echo ' &middot; ' . (int) $m['dimensao'] . ' dimens&otilde;es'; }
                  ?></div>
                <?php endif; ?>
              </div>
              <div>
                <?php if (!$m['ativo']): ?>
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="modelo('ativar', <?php echo (int) $m['id']; ?>, this)">Usar</button>
                  <button class="btn btn-sm btn-outline-danger"
                          onclick="modelo('excluir', <?php echo (int) $m['id']; ?>, this)"
                          title="Excluir"><i class="fa fa-trash"></i></button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <hr>
      <div class="form-row align-items-end">
        <div class="form-group col-md-3">
          <label class="meta">Finalidade</label>
          <select class="form-control form-control-sm" id="mTipo">
            <option value="chat">Gera&ccedil;&atilde;o de respostas</option>
            <option value="embedding">Busca sem&acirc;ntica</option>
          </select>
        </div>
        <div class="form-group col-md-4">
          <label class="meta">Identificador na API</label>
          <input type="text" class="form-control form-control-sm" id="mNome"
                 placeholder="gemini-3.1-flash-lite" spellcheck="false">
        </div>
        <div class="form-group col-md-3">
          <label class="meta">Nome amig&aacute;vel</label>
          <input type="text" class="form-control form-control-sm" id="mRotulo" placeholder="opcional">
        </div>
        <div class="form-group col-md-2" id="boxDim" style="display:none;">
          <label class="meta">Dimens&otilde;es</label>
          <input type="number" class="form-control form-control-sm" id="mDim" value="768">
        </div>
        <div class="form-group col-md-12">
          <button class="btn btn-sm btn-secondary" id="btnAddModelo">
            <i class="fa fa-plus"></i> Cadastrar modelo
          </button>
        </div>
      </div>
    </div>

    <div class="cx">
      <h5>Como obter a chave</h5>
      <ol class="passos mb-2">
        <li>Acesse <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>.</li>
        <li>Entre com a conta Google do escrit&oacute;rio e clique em <strong>Criar chave de API</strong>.</li>
        <li>Copie a chave gerada (come&ccedil;a com <code>AIza</code>) e cole no campo acima.</li>
      </ol>
      <p class="meta mb-0">
        Se o Atlas j&aacute; usa o Gemini em outro m&oacute;dulo, a mesma chave serve.
        <a href="detectar_chave.php">Procurar automaticamente</a>.
      </p>
    </div>

    <p class="mt-3"><a href="aria.php">&larr; Voltar para a consulta</a></p>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<?php include(__DIR__ . '/parcial_swal.php'); ?>
<script>
function chamar(acao, botao, rotuloOcupado) {
    var chave = $('#chave').val().trim();
    if (acao !== 'remover' && chave.length < 20) {
        Swal.fire('Chave incompleta', 'Cole a chave completa, começando com AIza.', 'info');
        return;
    }

    var b = $(botao), html = b.html();
    b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + rotuloOcupado);

    $.post('configurar.php', { acao: acao, chave: chave }, null, 'json')
     .done(function (r) {
         Swal.fire({
             icon: r.ok ? 'success' : 'error',
             title: r.ok ? 'Tudo certo' : 'Não deu certo',
             text: r.mensagem,
             confirmButtonColor: '#0f6f77'
         }).then(function () {
             if (r.ok && acao !== 'testar') location.reload();
         });
     })
     .fail(function () {
         Swal.fire('Erro', 'Falha de comunicação com o servidor.', 'error');
     })
     .always(function () { b.prop('disabled', false).html(html); });
}

$('#btnSalvar').on('click',  function () { chamar('salvar', this, 'Testando...'); });
$('#btnTestar').on('click',  function () { chamar('testar', this, 'Testando...'); });

$('#btnRemover').on('click', function () {
    var self = this;
    Swal.fire({
        title: 'Remover a chave?',
        text: 'A busca semântica para de funcionar até você configurar outra.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Remover', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(function (r) { if (r.isConfirmed) chamar('remover', self, 'Removendo...'); });
});

$('#btnVer').on('click', function () {
    var c = $('#chave');
    var oculto = c.attr('type') === 'password';
    c.attr('type', oculto ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
});

function modelo(acao, id, botao, confirmado) {
    var b = $(botao), html = b.html();
    b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.post('configurar.php', {
        acao: 'modelo_' + acao, id: id, confirmado: confirmado ? 1 : 0
    }, null, 'json')
    .done(function (r) {
        if (r.precisa_confirmar) {
            b.prop('disabled', false).html(html);
            Swal.fire({
                title: 'Reindexação necessária',
                text: r.mensagem,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Trocar mesmo assim',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then(function (res) {
                if (res.isConfirmed) modelo(acao, id, botao, true);
            });
            return;
        }
        Swal.fire({
            icon: r.ok ? 'success' : 'error',
            title: r.ok ? 'Pronto' : 'Não deu certo',
            text: r.mensagem, confirmButtonColor: '#0f6f77'
        }).then(function () { if (r.ok) location.reload(); });
    })
    .fail(function () { Swal.fire('Erro', 'Falha de comunicação com o servidor.', 'error'); })
    .always(function () { b.prop('disabled', false).html(html); });
}

$('#mTipo').on('change', function () {
    $('#boxDim').toggle($(this).val() === 'embedding');
});

$('#btnAddModelo').on('click', function () {
    var nome = $('#mNome').val().trim();
    if (!nome) {
        Swal.fire('Informe o identificador', 'Ex.: gemini-3.1-flash-lite', 'info');
        return;
    }
    var b = $(this).prop('disabled', true);
    $.post('configurar.php', {
        acao: 'modelo_add',
        tipo: $('#mTipo').val(),
        nome: nome,
        rotulo: $('#mRotulo').val().trim(),
        dimensao: $('#mDim').val()
    }, null, 'json')
    .done(function (r) {
        Swal.fire({
            icon: r.ok ? 'success' : 'error',
            title: r.ok ? 'Pronto' : 'Não deu certo',
            text: r.mensagem, confirmButtonColor: '#0f6f77'
        }).then(function () { if (r.ok) location.reload(); });
    })
    .always(function () { b.prop('disabled', false); });
});

$('#chave').on('keydown', function (e) {
    if (e.key === 'Enter') $('#btnSalvar').click();
});
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
