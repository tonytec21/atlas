<?php
/**
 * atlas/kb/detectar_chave.php
 * Varre o Atlas procurando a chave da API do Gemini ja usada em outro modulo
 * (Vertex, Prov213, Sirius...) e configura a base de conhecimento para
 * reaproveita-la -- por referencia, nao por copia.
 *
 * Assim existe uma unica chave no projeto: trocou no Vertex, vale aqui.
 */

include(__DIR__ . '/../provimentos/session_check.php');
checkSession();

$raizAtlas = realpath(__DIR__ . '/..');
$arquivoLocal = __DIR__ . '/config_kb.local.php';

// Pastas que nao interessam (bibliotecas de terceiros, anexos, build).
$ignorar = array('vendor', 'node_modules', '.git', 'pdfjs', 'anexo', 'uploads',
                 'tcpdf', 'style', 'script', 'kb');

/** Varre .php procurando chaves no formato do Google. */
function varrer($dir, $ignorar, $profundidade = 0, &$achados = array())
{
    if ($profundidade > 3) {
        return $achados;
    }
    $itens = @scandir($dir);
    if (!$itens) {
        return $achados;
    }

    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $caminho = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($caminho)) {
            if (!in_array(strtolower($item), $ignorar, true)) {
                varrer($caminho, $ignorar, $profundidade + 1, $achados);
            }
            continue;
        }

        if (substr($item, -4) !== '.php' || filesize($caminho) > 400000) {
            continue;
        }

        $conteudo = @file_get_contents($caminho);
        if ($conteudo === false || stripos($conteudo, 'AIza') === false) {
            continue;
        }

        // Chave de API do Google: AIza + 35 caracteres.
        if (!preg_match_all('/AIza[0-9A-Za-z\-_]{35}/', $conteudo, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($m[0] as $ocorrencia) {
            $chave  = $ocorrencia[0];
            $pos    = $ocorrencia[1];
            $linha  = substr_count(substr($conteudo, 0, $pos), "\n") + 1;
            $antes  = substr($conteudo, max(0, $pos - 200), min(200, $pos));

            // Descobre como a chave esta declarada, para saber como referencia-la.
            $simbolo = null;
            $forma   = 'literal';
            if (preg_match('/define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*$/i', $antes, $mm)) {
                $simbolo = $mm[1];
                $forma   = 'constante';
            } elseif (preg_match('/\bconst\s+([A-Z0-9_]+)\s*=\s*$/i', $antes, $mm)) {
                $simbolo = $mm[1];
                $forma   = 'constante';
            } elseif (preg_match('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*$/', $antes, $mm)) {
                $simbolo = $mm[1];
                $forma   = 'variavel';
            } elseif (preg_match('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*=>\s*$/', $antes, $mm)) {
                $simbolo = $mm[1];
                $forma   = 'array';
            }

            $achados[] = array(
                'arquivo'  => $caminho,
                'relativo' => str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', $caminho),
                'linha'    => $linha,
                'chave'    => $chave,
                'mascara'  => substr($chave, 0, 8) . str_repeat('*', 22) . substr($chave, -5),
                'simbolo'  => $simbolo,
                'forma'    => $forma,
            );
        }
    }
    return $achados;
}

/** Gera o config_kb.local.php apontando para a origem escolhida. */
function gravarConfig($arquivoLocal, $achado)
{
    $rel = str_replace('\\', '/', $achado['relativo']);

    if ($achado['forma'] === 'constante') {
        $php = "<?php\r\n"
             . "// Reaproveita a chave do modulo: {$rel}\r\n"
             . "// Gerado por detectar_chave.php em " . date('d/m/Y H:i') . "\r\n"
             . "if (!defined('{$achado['simbolo']}')) {\r\n"
             . "    @include_once __DIR__ . '/../{$rel}';\r\n"
             . "}\r\n"
             . "return defined('{$achado['simbolo']}') ? {$achado['simbolo']} : '';\r\n";
    } elseif ($achado['forma'] === 'variavel') {
        $php = "<?php\r\n"
             . "// Reaproveita a chave do modulo: {$rel}\r\n"
             . "// Gerado por detectar_chave.php em " . date('d/m/Y H:i') . "\r\n"
             . "\${$achado['simbolo']} = '';\r\n"
             . "@include __DIR__ . '/../{$rel}';\r\n"
             . "return isset(\${$achado['simbolo']}) ? \${$achado['simbolo']} : '';\r\n";
    } else {
        // Sem simbolo reaproveitavel: copia o valor.
        $php = "<?php\r\n"
             . "// Copiada de: {$rel} (linha {$achado['linha']})\r\n"
             . "// Gerado por detectar_chave.php em " . date('d/m/Y H:i') . "\r\n"
             . "return '{$achado['chave']}';\r\n";
    }

    return @file_put_contents($arquivoLocal, $php) !== false;
}

// --------------------------------------------------------------------------
// POST: gravar a configuracao
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar'])) {
    header('Content-Type: application/json; charset=utf-8');

    $achados = varrer($raizAtlas, $ignorar);
    $i = (int) $_POST['aplicar'];

    if (!isset($achados[$i])) {
        echo json_encode(array('success' => false, 'message' => 'Origem não encontrada. Atualize a página.'));
        exit;
    }
    if (!gravarConfig($arquivoLocal, $achados[$i])) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Sem permissão de escrita em ' . __DIR__ . '. Crie o arquivo config_kb.local.php manualmente.',
        ));
        exit;
    }

    // Confere se o arquivo realmente devolve uma chave valida.
    $valor = @include $arquivoLocal;
    $ok = is_string($valor) && preg_match('/^AIza[0-9A-Za-z\-_]{35}$/', $valor);

    echo json_encode(array(
        'success' => (bool) $ok,
        'message' => $ok
            ? 'Chave configurada com sucesso.'
            : 'O arquivo foi criado, mas não devolveu uma chave válida. Verifique o caminho manualmente.',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$achados   = varrer($raizAtlas, $ignorar);
$jaTemLocal = is_file($arquivoLocal);
$chaveAtual = $jaTemLocal ? @include $arquivoLocal : '';
$chaveOk    = is_string($chaveAtual) && preg_match('/^AIza[0-9A-Za-z\-_]{35}$/', $chaveAtual);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar chave da API</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        .origem     { border:1px solid #d5dde5; border-radius:8px; padding:14px 18px; margin-bottom:12px; background:#fff; }
        .origem.rec { border-color:#0f6f77; box-shadow:0 0 0 2px rgba(15,111,119,.12); }
        .cam        { font-family:monospace; font-size:.86rem; color:#0f6f77; word-break:break-all; }
        .mask       { font-family:monospace; font-size:.86rem; color:#8a97a5; }
        .selo       { font-size:.72rem; padding:2px 8px; border-radius:10px; background:#0f6f77; color:#fff; }
        .selo.alt   { background:#8a97a5; }
        .meta       { font-size:.8rem; color:#8a97a5; }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <h3>Configurar a chave da API</h3>
    <p class="meta">A busca sem&acirc;ntica usa a API do Gemini. Reaproveitamos a chave que
       j&aacute; existe em outro m&oacute;dulo do Atlas.</p>
    <hr>

    <?php if ($chaveOk): ?>
      <div class="alert alert-success">
        <i class="fa fa-check-circle"></i> <strong>Chave j&aacute; configurada.</strong>
        <span class="mask"><?php echo substr($chaveAtual, 0, 8) . str_repeat('*', 22) . substr($chaveAtual, -5); ?></span>
        <a href="aria.php" class="btn btn-sm btn-success ml-2">Ir para a consulta</a>
      </div>
    <?php endif; ?>

    <?php if (empty($achados)): ?>
      <div class="alert alert-warning">
        <strong>Nenhuma chave encontrada</strong> na varredura de
        <span class="cam"><?php echo htmlspecialchars($raizAtlas, ENT_QUOTES, 'UTF-8'); ?></span>.
        <hr>
        Configure manualmente: crie o arquivo <span class="cam">kb/config_kb.local.php</span> com
        <pre class="mb-0">&lt;?php
return 'SUA_CHAVE_AQUI';</pre>
        Ou defina a vari&aacute;vel de ambiente <code>GEMINI_API_KEY</code> no Apache
        (<code>SetEnv GEMINI_API_KEY "..."</code> no httpd.conf).
      </div>
    <?php else: ?>
      <p>Encontrei <strong><?php echo count($achados); ?></strong>
         ocorr&ecirc;ncia(s). Escolha qual usar:</p>

      <?php foreach ($achados as $i => $a): ?>
        <div class="origem <?php echo $a['simbolo'] ? 'rec' : ''; ?>">
          <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div style="flex:1; min-width:280px;">
              <div class="cam"><?php echo htmlspecialchars($a['relativo'], ENT_QUOTES, 'UTF-8'); ?>
                <span class="meta">&middot; linha <?php echo $a['linha']; ?></span></div>
              <div class="mask mt-1"><?php echo $a['mascara']; ?></div>
              <div class="mt-2">
                <?php if ($a['forma'] === 'constante'): ?>
                  <span class="selo">por refer&ecirc;ncia</span>
                  <span class="meta">constante <code><?php echo htmlspecialchars($a['simbolo'], ENT_QUOTES, 'UTF-8'); ?></code>
                    &mdash; trocar a chave no m&oacute;dulo de origem vale aqui tamb&eacute;m</span>
                <?php elseif ($a['forma'] === 'variavel'): ?>
                  <span class="selo">por refer&ecirc;ncia</span>
                  <span class="meta">vari&aacute;vel <code>$<?php echo htmlspecialchars($a['simbolo'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                <?php else: ?>
                  <span class="selo alt">c&oacute;pia</span>
                  <span class="meta">chave escrita direto no c&oacute;digo; ser&aacute; copiada</span>
                <?php endif; ?>
              </div>
            </div>
            <button class="btn btn-primary btn-sm mt-2" style="color:#fff!important"
                    onclick="aplicar(<?php echo $i; ?>, this)">
              <i class="fa fa-check"></i> Usar esta
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<?php include(__DIR__ . '/parcial_swal.php'); ?>
<script>
function aplicar(i, btn) {
    var b = $(btn).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $.post('detectar_chave.php', { aplicar: i }, null, 'json')
     .done(function (r) {
         Swal.fire({
             icon: r.success ? 'success' : 'error',
             title: r.success ? 'Pronto' : 'Não deu certo',
             text: r.message,
             confirmButtonColor: '#0f6f77'
         }).then(function () {
             if (r.success) window.location = 'aria.php';
         });
     })
     .fail(function () { Swal.fire('Erro', 'Falha de comunicação com o servidor.', 'error'); })
     .always(function () { b.prop('disabled', false).html('<i class="fa fa-check"></i> Usar esta'); });
}
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
