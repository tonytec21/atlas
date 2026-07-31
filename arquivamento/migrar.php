<?php
/**
 * Atlas · Arquivamento Digital
 * Utilitário de migração — roda uma vez após a instalação.
 *
 * Os registros criados pela versão anterior guardam os anexos como texto puro
 * ("arquivos/123/doc.pdf"), sem tamanho, tipo ou hash. Este script completa
 * esses dados no lugar, sem mexer em nada que já esteja preenchido:
 *
 *   - tamanho em bytes
 *   - MIME real detectado pelo conteúdo
 *   - SHA-256 (usado para conferência de integridade e no manifesto do ZIP)
 *
 * Também aponta anexos referenciados no JSON cujo arquivo sumiu do disco.
 *
 * Abra em: /arquivamento/migrar.php   (exige login; use ?executar=1 para gravar)
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$executar = isset($_GET['executar']) && $_GET['executar'] === '1';
$arquivos = glob(arq_dir_meta() . '/*.json') ?: [];

$relatorio = ['registros' => 0, 'anexos' => 0, 'completados' => 0, 'ausentes' => [], 'alterados' => []];

foreach ($arquivos as $caminho) {
    $id = basename($caminho, '.json');
    if (arq_id_valido($id) === '') { continue; }

    $dados = json_decode(@file_get_contents($caminho), true);
    if (!is_array($dados)) { continue; }
    $relatorio['registros']++;

    $mudou = false;
    $lista = isset($dados['anexos']) && is_array($dados['anexos']) ? $dados['anexos'] : [];

    foreach ($lista as $i => $item) {
        $relatorio['anexos']++;
        $ref = is_array($item) ? (isset($item['ref']) ? $item['ref'] : '') : (string) $item;
        $abs = arq_resolver_anexo($ref);

        if ($abs === false) {
            $relatorio['ausentes'][] = $id . ' → ' . basename($ref);
            continue;
        }

        $completo = is_array($item) ? $item : ['ref' => str_replace('\\', '/', $ref)];
        $faltava = false;

        if (empty($completo['nome']))    { $completo['nome'] = basename($abs); $faltava = true; }
        if (!isset($completo['tamanho']) || $completo['tamanho'] === null) {
            $completo['tamanho'] = filesize($abs); $faltava = true;
        }
        if (empty($completo['mime']))    { $completo['mime'] = arq_mime_real($abs); $faltava = true; }
        if (empty($completo['hash']))    { $completo['hash'] = hash_file('sha256', $abs); $faltava = true; }
        if (empty($completo['origem']))  { $completo['origem'] = 'acervo'; $faltava = true; }

        if ($faltava) {
            $lista[$i] = $completo;
            $mudou = true;
            $relatorio['completados']++;
        }
    }

    if ($mudou) {
        $relatorio['alterados'][] = $id;
        if ($executar) {
            $dados['anexos'] = array_values($lista);
            arq_salvar_ato($id, $dados);
        }
    }
}

if ($executar) {
    arq_invalidar_indice();
    arq_auditar('migrar', 'acervo', [
        'registros'   => $relatorio['registros'],
        'completados' => $relatorio['completados'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Atlas · Migração do arquivamento</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/css/arquivamento.css?v=8">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container arq" style="max-width:760px">

    <header class="arq-topo">
      <div>
        
        <h1><?= $executar ? 'Migração concluída' : 'Conferência do acervo' ?></h1>
        <p>Completa tamanho, tipo e hash SHA-256 dos anexos cadastrados pela versão anterior do módulo.</p>
      </div>
      <a class="arq-btn" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a>
    </header>

    <section class="arq-cartao">
      <div class="arq-dados">
        <div class="arq-dado"><em>Registros lidos</em><b><?= (int) $relatorio['registros'] ?></b></div>
        <div class="arq-dado"><em>Anexos vistos</em><b><?= (int) $relatorio['anexos'] ?></b></div>
        <div class="arq-dado"><em><?= $executar ? 'Completados' : 'A completar' ?></em><b><?= (int) $relatorio['completados'] ?></b></div>
        <div class="arq-dado"><em>Arquivos ausentes</em><b><?= count($relatorio['ausentes']) ?></b></div>
      </div>

      <?php if (!$executar && $relatorio['completados'] > 0): ?>
        <div class="arq-nota arq-nota-info" style="margin-top:18px">
          <i class="fa fa-info-circle"></i>
          <div>Nada foi gravado ainda. <b><?= count($relatorio['alterados']) ?></b> registro(s) serão atualizados.
          Faça backup da pasta <code>meta-dados/</code> antes de prosseguir.</div>
        </div>
        <a class="arq-btn arq-btn-p" style="margin-top:16px" href="migrar.php?executar=1">
          <i class="fa fa-play"></i> Executar migração
        </a>
      <?php elseif ($executar): ?>
        <div class="arq-nota arq-nota-info" style="margin-top:18px">
          <i class="fa fa-check-circle"></i>
          <div><b><?= count($relatorio['alterados']) ?></b> registro(s) atualizados. Este script pode ser removido do servidor.</div>
        </div>
      <?php else: ?>
        <div class="arq-nota arq-nota-info" style="margin-top:18px">
          <i class="fa fa-check-circle"></i>
          <div>Nenhum anexo precisa de complemento. O acervo já está com os metadados completos.</div>
        </div>
      <?php endif; ?>

      <?php if ($relatorio['ausentes']): ?>
        <h3 style="font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin:22px 0 8px">
          Anexos referenciados sem arquivo no disco
        </h3>
        <div class="arq-nota arq-nota-alerta">
          <i class="fa fa-exclamation-triangle"></i>
          <div style="max-height:240px;overflow:auto">
            <?php foreach (array_slice($relatorio['ausentes'], 0, 200) as $a): ?>
              <div><?= arq_e($a) ?></div>
            <?php endforeach; ?>
            <?php if (count($relatorio['ausentes']) > 200): ?>
              <div>… e mais <?= count($relatorio['ausentes']) - 200 ?>.</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
