<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Instalação/atualização manual. O módulo também se auto-instala no primeiro acesso
 * (via p213_ensure_schema), então esta página é opcional — útil para ver o relatório
 * ou forçar a migração após um deploy.
 */
require_once __DIR__ . '/p213_lib.php';

$conn = p213_db();                 // já dispara o auto-provisionamento
$rel  = p213_migrate($conn);       // roda de novo para produzir o relatório detalhado

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação — Atlas Prov. 213</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial;max-width:760px;margin:40px auto;padding:0 16px;color:#1f2937;line-height:1.6}
  h2{color:#312e81}code{background:#f1f3f7;padding:2px 6px;border-radius:5px;font-size:.9em}
  li{margin:4px 0}.ok{color:#0f9d78;font-weight:600}.err{color:#d64545;font-weight:600}
  .card{border:1px solid #e5e8ee;border-radius:12px;padding:18px 20px;margin:16px 0}
  a.btn{display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:10px 18px;border-radius:9px;font-weight:500}
</style></head><body>
<h2>Módulo Atlas — Conformidade Provimento 213/2026</h2>

<?php if (empty($rel['erros'])): ?>
  <p class="ok">&#10003; Schema verificado e atualizado (versão <?= P213_SCHEMA_VERSION ?>).</p>
<?php else: ?>
  <p class="err">Ocorreram erros durante a instalação:</p>
  <ul><?php foreach ($rel['erros'] as $e) echo '<li class="err">' . htmlspecialchars($e) . '</li>'; ?></ul>
<?php endif; ?>

<div class="card">
  <ul>
    <?php foreach ($rel['tabelas'] as $t): ?>
      <li>Tabela <code><?= htmlspecialchars($t) ?></code> verificada/criada.</li>
    <?php endforeach; ?>
    <li><?= (int)$rel['colunas'] ?> coluna(s) acrescentada(s) nesta execução.</li>
    <li><?= (int)$rel['requisitos'] ?> requisito(s) do catálogo inseridos (total: <?= count(p213_catalogo()) ?>).</li>
    <li>Repositório de evidências: <code><?= htmlspecialchars((string)$rel['dir']) ?></code>
        <?= $rel['dir_ok'] ? '<span class="ok">(gravável)</span>' : '<span class="err">&mdash; SEM PERMISSÃO DE ESCRITA</span>' ?></li>
  </ul>
</div>

<h4>Observação</h4>
<p>A instalação agora é <strong>automática no primeiro acesso</strong> ao módulo &mdash; não é preciso abrir esta
página em cada cartório. Ela permanece disponível apenas para conferência ou para forçar a atualização do
schema após um novo deploy. Você pode manter ou remover este arquivo.</p>

<p style="margin-top:22px"><a class="btn" href="index.php">Ir para o painel &rarr;</a></p>
</body></html>
