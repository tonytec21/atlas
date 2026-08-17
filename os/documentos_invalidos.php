<?php
/**
 * ATLAS O.S. — O.S. com documento do apresentante inválido
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-08-17-auditoria-documento
 *
 * A validação nova impede que entrem documentos ruins daqui em diante.
 * Só que as O.S. cadastradas enquanto a edição não validava continuam
 * como estão — e cada uma delas é uma rejeição E0206 esperando a hora de
 * emitir a NFS-e.
 *
 * Esta tela lista essas O.S. para correção, priorizando as que ainda vão
 * precisar de nota.
 *
 * Acesse: .../os/documentos_invalidos.php   (administrador)
 */

include(__DIR__ . '/session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../checar_acesso_de_administrador.php');
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/documento_validacao.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(120);

function di_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$erro = null;
$linhas = [];
$totalOs = 0;

try {
    $conn = getDatabaseConnection();

    /* Só O.S. com documento preenchido: em branco é permitido. */
    $sql = "SELECT os.id, os.cliente, os.cpf_cliente, os.total_os, os.criado_em
              FROM ordens_de_servico os
             WHERE os.cpf_cliente IS NOT NULL AND TRIM(os.cpf_cliente) <> ''
             ORDER BY os.id DESC";

    foreach ($conn->query($sql, PDO::FETCH_ASSOC) as $os) {
        $totalOs++;
        $r = doc_validar_apresentante($os['cpf_cliente']);
        if (!$r['ok']) {
            $os['motivo'] = $r['erro'];
            $linhas[] = $os;
        }
    }

    /* Quais dessas já têm NFS-e — essas não urgem. */
    $comNota = [];
    if ($linhas) {
        $ids = implode(',', array_map(static fn($l) => (int) $l['id'], $linhas));
        try {
            $rs = $conn->query(
                "SELECT DISTINCT ordem_servico_id FROM nfse_notas
                  WHERE ordem_servico_id IN ($ids) AND status = 'autorizada'"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rs as $id) { $comNota[(int) $id] = true; }
        } catch (Throwable $e) {
            $comNota = [];   // módulo de NFS-e ausente: apenas não classifica
        }
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$brl = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documentos inválidos — Atlas O.S.</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<style>
    body{ background:#f8fafc; padding:24px; font-size:14px; }
    .wrap{ max-width:1050px; margin:0 auto; }
    h3{ color:#1e40af; }
    .kpi{ background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center; }
    .kpi .n{ font-size:1.6rem; font-weight:700; }
    .kpi .l{ font-size:.76rem; color:#475569; }
    table{ background:#fff; }
    .urg{ background:#fef2f2; }
    .doc{ font-family:ui-monospace,Consolas,monospace; }
    .motivo{ font-size:.76rem; color:#b91c1c; }
</style>
</head>
<body>
<div class="wrap">

<h3 class="mb-1">Documento do apresentante inválido</h3>
<p class="text-muted">
    O.S. cadastradas com CPF ou CNPJ que não passa na verificação dos dígitos. Cada uma delas
    será recusada com o código <code>E0206</code> na emissão da NFS-e.
</p>

<?php if ($erro): ?>
    <div class="alert alert-danger"><b>Falhou:</b> <?= di_h($erro) ?></div>
<?php else: ?>

    <?php
    $urgentes = array_filter($linhas, static fn($l) => empty($comNota[(int) $l['id']]));
    ?>

    <div class="row mb-3">
        <div class="col-4"><div class="kpi"><div class="n"><?= count($linhas) ?></div><div class="l">com documento inválido</div></div></div>
        <div class="col-4"><div class="kpi"><div class="n text-danger"><?= count($urgentes) ?></div><div class="l">ainda sem NFS-e</div></div></div>
        <div class="col-4"><div class="kpi"><div class="n text-muted"><?= (int) $totalOs ?></div><div class="l">O.S. verificadas</div></div></div>
    </div>

    <?php if (!$linhas): ?>
        <div class="alert alert-success mb-0">
            <b>Nenhuma O.S. com documento inválido.</b> Todas as <?= (int) $totalOs ?> verificadas estão corretas.
        </div>
    <?php else: ?>
        <p class="text-muted" style="font-size:.85rem">
            As linhas destacadas ainda não têm NFS-e autorizada — são as que precisam de correção antes
            da emissão. As demais já têm nota e ficam aqui só para acerto de cadastro.
        </p>

        <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="thead-light">
                <tr>
                    <th style="width:80px">O.S.</th>
                    <th>Apresentante</th>
                    <th style="width:170px">Documento</th>
                    <th class="text-right" style="width:110px">Total</th>
                    <th style="width:110px">Situação</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $l):
                $urgente = empty($comNota[(int) $l['id']]); ?>
                <tr class="<?= $urgente ? 'urg' : '' ?>">
                    <td><?= (int) $l['id'] ?></td>
                    <td>
                        <?= di_h($l['cliente']) ?>
                        <div class="motivo"><?= di_h($l['motivo']) ?></div>
                    </td>
                    <td class="doc"><?= di_h($l['cpf_cliente']) ?></td>
                    <td class="text-right"><?= $brl($l['total_os']) ?></td>
                    <td>
                        <?php if ($urgente): ?>
                            <span class="badge badge-danger">sem NFS-e</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">já tem NFS-e</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" target="_blank"
                           href="editar_os.php?id=<?= (int) $l['id'] ?>">Corrigir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
