<?php
/**
 * ATLAS O.S. — Linha do tempo das falhas de emissão
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-linha-do-tempo
 *
 * Para que serve
 * --------------
 * "Quando exatamente começou?" é a pergunta que resolve este tipo de
 * problema, e o resumo por dia não responde: um dia com 34 autorizações
 * e 18 rejeições pode ser um dia normal com algumas recusas de rotina,
 * ou o dia em que tudo quebrou às 16h36. São diagnósticos opostos.
 *
 * Esta tela abre o histórico hora a hora e, mais importante, classifica
 * cada rejeição pelo TIPO de erro. Rejeição de rotina (a SEFIN recusou
 * um campo e disse qual) não tem nada a ver com o 500 genérico. Somar as
 * duas na mesma coluna esconde justamente o instante da virada.
 *
 * Ela também lista as mensagens distintas com contagem. Entre centenas
 * de falhas iguais pode haver meia dúzia com código de erro real
 * (E0001, E0128…), e essas dizem exatamente o que a SEFIN não gostou.
 *
 * Acesse: .../os/nfse/nfse_linha_do_tempo.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

date_default_timezone_set('America/Sao_Paulo');

function lt_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/**
 * Classifica a mensagem de rejeição. A ordem dos testes importa: o
 * código estruturado tem precedência, porque é o mais informativo.
 */
function lt_classificar(?string $msg): string
{
    $m = (string) $msg;

    if ($m === '') {
        return 'sem_mensagem';
    }
    if (preg_match('/\bE\d{4}\b/', $m) || stripos($m, '"erros"') !== false || stripos($m, 'Rejeição:') === 0) {
        return 'estruturada';
    }
    if (stripos($m, 'An error has occurred') !== false || preg_match('/HTTP 5\d\d/', $m) && stripos($m, '500') !== false) {
        return 'http_500';
    }
    if (stripos($m, 'Service Unavailable') !== false || stripos($m, '503') !== false) {
        return 'http_503';
    }
    if (preg_match('/HTTP 4\d\d/', $m)) {
        return 'http_4xx';
    }
    if (stripos($m, 'resolve host') !== false || stripos($m, 'timed out') !== false
        || stripos($m, 'cURL') !== false || stripos($m, 'Connection') !== false) {
        return 'rede';
    }
    return 'outra';
}

$rotulos = [
    'http_500'     => '500 genérico',
    'http_503'     => '503 indisponível',
    'http_4xx'     => '4xx',
    'estruturada'  => 'Rejeição com código',
    'rede'         => 'Falha de rede',
    'sem_mensagem' => 'Sem mensagem',
    'outra'        => 'Outra',
];

$erro = null;
$porHora = [];
$mensagens = [];
$cfgAud = null;
$primeiro500 = null;
$ultimaOk = null;
$okEntre = [];

try {
    nfse_migrar();
    $pdo = nfse_pdo();

    $cfgAud = $pdo->query('SELECT atualizado_em, atualizado_por FROM nfse_config WHERE id = 1')
                  ->fetch(PDO::FETCH_ASSOC);

    $notas = $pdo->query(
        "SELECT id, ordem_servico_id, numero_dps, status, mensagem, criado_em, criado_por
           FROM nfse_notas
          WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 5 DAY)
          ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notas as $n) {
        $hora = date('Y-m-d H', strtotime($n['criado_em']));

        if ($n['status'] === 'autorizada') {
            $porHora[$hora]['autorizada'] = ($porHora[$hora]['autorizada'] ?? 0) + 1;
            $ultimaOk = $n;
            if ($primeiro500 !== null) {
                $okEntre[] = $n;   // autorização DEPOIS do primeiro 500 — importantíssimo
            }
            continue;
        }

        if ($n['status'] !== 'rejeitada') {
            continue;
        }

        $tipo = lt_classificar($n['mensagem']);
        $porHora[$hora][$tipo] = ($porHora[$hora][$tipo] ?? 0) + 1;

        if ($tipo === 'http_500' && $primeiro500 === null) {
            $primeiro500 = $n;
        }

        // Agrupa mensagens distintas (normaliza números para não pulverizar)
        $chave = preg_replace('/\d+/', '#', mb_substr(trim((string) $n['mensagem']), 0, 300, 'UTF-8'));
        if (!isset($mensagens[$chave])) {
            $mensagens[$chave] = ['qtd' => 0, 'tipo' => $tipo, 'exemplo' => $n['mensagem'], 'ultima' => $n['criado_em']];
        }
        $mensagens[$chave]['qtd']++;
        $mensagens[$chave]['ultima'] = $n['criado_em'];
    }

    uasort($mensagens, fn($a, $b) => $b['qtd'] <=> $a['qtd']);
    ksort($porHora);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Linha do tempo das falhas — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --erro:#dc2626; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.55; }
    .wrap{ max-width:1100px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    h3{ font-size:15px; margin:0 0 10px; }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .destaque{ background:#fff7ed; border:1px solid #fdba74; border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    table{ border-collapse:collapse; width:100%; font-size:13px; }
    th,td{ border:1px solid var(--borda); padding:6px 9px; text-align:left; }
    th{ background:#f1f5f9; }
    td.num{ text-align:right; font-variant-numeric:tabular-nums; }
    tr.virada td{ background:#fef2f2; font-weight:600; }
    .z{ color:#cbd5e1; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
    pre{ background:#0f172a; color:#e2e8f0; padding:10px; border-radius:6px; overflow:auto; max-height:180px;
         font-size:12px; margin:6px 0 0; white-space:pre-wrap; word-break:break-word; }
</style>
</head>
<body>
<div class="wrap">

<h1>Linha do tempo das falhas</h1>
<p>Histórico hora a hora, com as rejeições separadas por tipo de erro.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo lt_h($erro); ?></div>
<?php else: ?>

    <div class="destaque">
        <h3>O instante da virada</h3>
        <table>
            <?php if ($primeiro500): ?>
            <tr><th style="width:34%">Primeira falha com 500 genérico</th>
                <td><?php echo date('d/m/Y H:i:s', strtotime($primeiro500['criado_em'])); ?> ·
                    DPS <?php echo (int) $primeiro500['numero_dps']; ?> ·
                    O.S. <?php echo (int) $primeiro500['ordem_servico_id']; ?> ·
                    por <?php echo lt_h($primeiro500['criado_por']); ?></td></tr>
            <?php else: ?>
            <tr><th style="width:34%">Primeira falha com 500 genérico</th>
                <td>nenhuma nos últimos 5 dias</td></tr>
            <?php endif; ?>

            <?php if ($okEntre): $u = end($okEntre); ?>
            <tr><th>Autorizações DEPOIS do primeiro 500</th>
                <td><strong><?php echo count($okEntre); ?></strong> — a última em
                    <?php echo date('d/m/Y H:i:s', strtotime($u['criado_em'])); ?>
                    (DPS <?php echo (int) $u['numero_dps']; ?>).
                    Se houve autorização depois do primeiro 500, a falha não é contínua e a
                    causa provavelmente não é uma mudança permanente de leiaute ou de certificado.</td></tr>
            <?php elseif ($primeiro500): ?>
            <tr><th>Autorizações DEPOIS do primeiro 500</th>
                <td>nenhuma — a partir daquele instante, 100% de falha.</td></tr>
            <?php endif; ?>

            <?php if ($cfgAud && $cfgAud['atualizado_em']): ?>
            <tr><th>Configuração alterada pela última vez</th>
                <td><?php echo date('d/m/Y H:i:s', strtotime($cfgAud['atualizado_em'])); ?>
                    por <?php echo lt_h($cfgAud['atualizado_por'] ?: '(?)'); ?>
                    <?php
                    if ($primeiro500) {
                        $dif = abs(strtotime($cfgAud['atualizado_em']) - strtotime($primeiro500['criado_em']));
                        if ($dif < 7200) {
                            echo ' <strong style="color:#b91c1c">— a menos de duas horas da primeira falha.</strong>';
                        }
                    }
                    ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="painel">
        <h3>Hora a hora (5 dias)</h3>
        <table>
            <tr>
                <th>Hora</th>
                <th style="text-align:right">Autorizadas</th>
                <?php foreach ($rotulos as $k => $rot): ?>
                    <th style="text-align:right"><?php echo lt_h($rot); ?></th>
                <?php endforeach; ?>
            </tr>
            <?php
            $marcou = false;
            foreach ($porHora as $hora => $linha):
                $ehVirada = false;
                if ($primeiro500 && !$marcou && $hora === date('Y-m-d H', strtotime($primeiro500['criado_em']))) {
                    $ehVirada = true; $marcou = true;
                }
            ?>
                <tr<?php echo $ehVirada ? ' class="virada"' : ''; ?>>
                    <td><?php echo date('d/m H', strtotime($hora . ':00:00')); ?>h</td>
                    <td class="num"><?php echo !empty($linha['autorizada'])
                        ? (int) $linha['autorizada'] : '<span class="z">·</span>'; ?></td>
                    <?php foreach (array_keys($rotulos) as $k): ?>
                        <td class="num"><?php echo !empty($linha[$k])
                            ? (int) $linha[$k] : '<span class="z">·</span>'; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="painel">
        <h3>Mensagens distintas</h3>
        <p style="margin:0 0 10px; color:var(--cinza)">
            Se aparecer alguma linha do tipo <em>Rejeição com código</em>, é ela que interessa:
            traz o código real (E0128, E0166…) que diz qual campo a SEFIN recusou.
        </p>
        <table>
            <tr><th style="width:150px">Tipo</th><th style="width:70px;text-align:right">Qtd</th>
                <th style="width:130px">Última</th><th>Mensagem</th></tr>
            <?php foreach (array_slice($mensagens, 0, 15) as $m): ?>
            <tr>
                <td><?php echo lt_h($rotulos[$m['tipo']] ?? $m['tipo']); ?></td>
                <td class="num"><?php echo (int) $m['qtd']; ?></td>
                <td><?php echo date('d/m H:i', strtotime($m['ultima'])); ?></td>
                <td><pre><?php echo lt_h(mb_substr((string) $m['exemplo'], 0, 600, 'UTF-8')); ?></pre></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$mensagens): ?>
            <tr><td colspan="4">Nenhuma rejeição nos últimos 5 dias.</td></tr>
            <?php endif; ?>
        </table>
    </div>

<?php endif; ?>

</div>
</body>
</html>
