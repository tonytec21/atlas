<?php
/**
 * ATLAS O.S. — Recuperação de notas emitidas e gravadas como rejeitadas
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-17-recuperar-orfas
 *
 * Por que isto existe
 * -------------------
 * A recepção da SEFIN responde **201 Created** quando gera a NFS-e. O
 * transporte deste módulo exigia exatamente 200, de modo que uma emissão
 * bem sucedida era gravada aqui como rejeitada. A nota existe do lado da
 * SEFIN e some do lado do cartório — e, pior, o botão "Reemitir" tentaria
 * emitir outra para o mesmo fato gerador.
 *
 * Esta tela varre as notas marcadas como rejeitadas, consulta cada DPS
 * pelo seu Id na SEFIN e, quando encontra NFS-e gerada, corrige o
 * registro: grava chave de acesso, número, código de verificação e o XML.
 *
 * Nada é emitido aqui. A varredura só lê da SEFIN e corrige o que já
 * existe.
 *
 * Acesse: .../os/nfse/nfse_recuperar_orfas.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(600);

function ro_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$erro = null;
$candidatas = [];
$resultados = null;
$dias = isset($_POST['dias']) ? max(1, min(90, (int) $_POST['dias'])) : 30;
$executar = (($_POST['acao'] ?? '') === 'recuperar');

try {
    nfse_migrar();
    $cfg = nfse_config(true);
    $pdo = nfse_pdo();

    /* Candidatas: rejeitadas que podem ter gerado nota do outro lado.
       Prioriza as com HTTP 2xx registrado — essas quase certamente
       existem na SEFIN — mas inclui também 5xx e sem status, que é o
       caso das falhas em que a resposta não chegou. */
    $st = $pdo->prepare(
        "SELECT id, ordem_servico_id, numero_dps, id_dps, http_status, criado_em, mensagem
           FROM nfse_notas
          WHERE status = 'rejeitada'
            AND ambiente = :amb
            AND id_dps IS NOT NULL AND id_dps <> ''
            AND criado_em >= DATE_SUB(NOW(), INTERVAL {$dias} DAY)
          ORDER BY
            CASE WHEN http_status >= 200 AND http_status < 300 THEN 0 ELSE 1 END,
            id DESC"
    );
    $st->execute([':amb' => $cfg['ambiente']]);
    $candidatas = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($executar) {
        $resultados = ['recuperadas' => [], 'sem_nota' => 0, 'falhas' => 0, 'total' => 0];

        foreach ($candidatas as $n) {
            $resultados['total']++;

            try {
                $achado = nfse_recuperar_por_dps($cfg, (string) $n['id_dps'], 25);
            } catch (Throwable $e) {
                $resultados['falhas']++;
                continue;
            }

            if (!$achado) {
                $resultados['sem_nota']++;
                continue;
            }

            // Extrai número e código de verificação do XML, quando houver
            $numero = null; $codVer = null;
            if (!empty($achado['xml'])) {
                if (preg_match('~<nNFSe>([^<]+)</nNFSe>~', $achado['xml'], $m)) { $numero = $m[1]; }
                if (preg_match('~<cVerif>([^<]+)</cVerif>~', $achado['xml'], $m)) { $codVer = $m[1]; }
            }

            $pdo->prepare(
                "UPDATE nfse_notas
                    SET status = 'autorizada', chave_acesso = :c, numero_nfse = :n,
                        cod_verificacao = :cv, xml_nfse = :x,
                        mensagem = 'Recuperada: a NFS-e já existia na SEFIN; o registro local estava incorreto.'
                  WHERE id = :id"
            )->execute([
                ':c' => $achado['chave'], ':n' => $numero, ':cv' => $codVer,
                ':x' => $achado['xml'], ':id' => $n['id'],
            ]);

            nfse_log('emissao', 'Nota recuperada na varredura. Chave: ' . $achado['chave'],
                'info', (int) $n['ordem_servico_id'], (int) $n['id']);

            $resultados['recuperadas'][] = [
                'nota'  => $n['id'],
                'os'    => $n['ordem_servico_id'],
                'dps'   => $n['numero_dps'],
                'http'  => $n['http_status'],
                'chave' => $achado['chave'],
                'data'  => $n['criado_em'],
            ];

            usleep(200000);   // respiro entre consultas
        }
    }

    // Contagem por faixa de status, para dar noção do tamanho
    $porStatus = $pdo->query(
        "SELECT CASE
                  WHEN http_status >= 200 AND http_status < 300 THEN '2xx (emitida)'
                  WHEN http_status >= 500 THEN '5xx'
                  WHEN http_status >= 400 THEN '4xx'
                  WHEN http_status IS NULL OR http_status = 0 THEN 'sem status'
                  ELSE 'outro' END AS faixa,
                COUNT(*) AS qtd
           FROM nfse_notas
          WHERE status = 'rejeitada' AND criado_em >= DATE_SUB(NOW(), INTERVAL {$dias} DAY)
          GROUP BY faixa ORDER BY qtd DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar notas — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.6; }
    .wrap{ max-width:1000px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 18px; color:var(--cinza); }
    h3{ font-size:15px; margin:0 0 10px; }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .ok{ background:#dcfce7; border:1px solid var(--ok); border-radius:8px; padding:14px 16px; margin-bottom:18px; }
    table{ border-collapse:collapse; width:100%; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:7px 9px; text-align:left; }
    th{ background:#f1f5f9; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 22px;
            font-size:14px; font-weight:600; cursor:pointer; }
    button:hover{ background:#1e3a8a; }
    input[type=number]{ padding:8px 10px; border:1px solid var(--borda); border-radius:6px; width:90px; }
    .aviso{ background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:11px 13px; color:#78350f; margin:12px 0; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Recuperar notas emitidas</h1>
<p>Procura na SEFIN as NFS-e que existem lá e ficaram marcadas como rejeitadas aqui.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo ro_h($erro); ?></div>
<?php else: ?>

    <?php if ($resultados): ?>
        <div class="ok">
            <h3 style="margin:0 0 6px">Varredura concluída</h3>
            <p style="margin:0">
                <strong><?php echo count($resultados['recuperadas']); ?></strong> nota(s) recuperada(s)
                de <?php echo (int) $resultados['total']; ?> verificada(s).
                <?php echo (int) $resultados['sem_nota']; ?> não tinham NFS-e na SEFIN (rejeição real).
                <?php if ($resultados['falhas']): ?>
                    <?php echo (int) $resultados['falhas']; ?> não puderam ser consultadas agora —
                    repita mais tarde.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($resultados['recuperadas']): ?>
        <div class="painel">
            <h3>Notas recuperadas</h3>
            <table>
                <tr><th>O.S.</th><th>DPS</th><th>HTTP</th><th>Emitida em</th><th>Chave de acesso</th></tr>
                <?php foreach ($resultados['recuperadas'] as $r): ?>
                <tr>
                    <td><?php echo (int) $r['os']; ?></td>
                    <td><?php echo (int) $r['dps']; ?></td>
                    <td><?php echo $r['http'] !== null ? (int) $r['http'] : '—'; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['data'])); ?></td>
                    <td><code><?php echo ro_h($r['chave']); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="painel">
        <h3>Rejeitadas nos últimos <?php echo (int) $dias; ?> dias</h3>
        <?php if ($porStatus): ?>
            <table>
                <tr><th style="width:220px">Faixa de status</th><th>Quantidade</th></tr>
                <?php foreach ($porStatus as $p): ?>
                <tr>
                    <td><?php echo ro_h($p['faixa']); ?></td>
                    <td><?php echo (int) $p['qtd']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="margin:10px 0 0; color:var(--cinza)">
                As da faixa <strong>2xx</strong> quase certamente existem na SEFIN: a resposta foi de
                sucesso e o registro local é que estava errado. As de <strong>5xx</strong> e sem
                status também são verificadas, porque a resposta pode ter se perdido depois de a nota
                ser gerada.
            </p>
        <?php else: ?>
            <p style="margin:0">Nenhuma rejeitada no período.</p>
        <?php endif; ?>
    </div>

    <form method="post" class="painel">
        <h3>Executar varredura</h3>
        <p style="margin:0 0 10px">
            <label for="dias">Período:</label>
            <input type="number" id="dias" name="dias" min="1" max="90" value="<?php echo (int) $dias; ?>"> dias
            &nbsp;·&nbsp; <?php echo count($candidatas); ?> candidata(s)
        </p>

        <div class="aviso">
            A varredura apenas <strong>consulta</strong> a SEFIN e corrige registros locais. Nenhuma
            DPS é emitida e nenhum número é consumido. Uma consulta por nota, com pausa entre elas —
            com muitas candidatas pode levar alguns minutos.
        </div>

        <input type="hidden" name="acao" value="recuperar">
        <button type="submit">Procurar e recuperar</button>
    </form>

    <div class="painel">
        <h3>Antes de reemitir a fila</h3>
        <p style="margin:0">
            Rode esta varredura primeiro. Sem ela, o botão <em>Reemitir rejeitadas</em> tentaria
            emitir nota nova para O.S. que <strong>já têm NFS-e</strong> na SEFIN — dois documentos
            para o mesmo fato gerador. A trava contra duplicidade do módulo já cobre isso na
            emissão, mas corrigir os registros antes deixa a fila e os relatórios corretos.
        </p>
    </div>

<?php endif; ?>

</div>
</body>
</html>
