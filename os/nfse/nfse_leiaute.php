<?php
/**
 * ATLAS O.S. — Leiaute do DPS (versão e grupo IBSCBS)
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-leiaute-dps
 *
 * Permite testar, pelo botão normal de emitir, as duas hipóteses de
 * leiaute que restaram — sem tocar em código e sem depender do
 * laboratório de variantes.
 *
 * O que cada opção faz
 * --------------------
 * VERSÃO. O SDK embarcado fixa `versao="1.00"` no elemento DPS. Em
 * 10/08/2026 entrou em Produção o pacote do CNPJ Alfanumérico, "com
 * atualização dos schemas XML", e os esquemas publicados são da série
 * v1.01. Se a recepção passou a carregar só o schema novo, um documento
 * que se declara 1.00 pode não casar com contrato nenhum.
 *
 * IBSCBS. Conforme a NT 004/005, o grupo é filho direto de `infDPS` e, na
 * DPS, leva apenas CST e cClassTrib — os valores e alíquotas são
 * calculados pelo Ambiente Nacional. A documentação também registra que o
 * envio do grupo só é aceito em versao 1.01; na 1.00 dá falha de schema.
 * Por isso as duas opções costumam andar juntas.
 *
 * Acesse: .../os/nfse/nfse_leiaute.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');

function lq_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$msg = null;
$erro = null;
$previa = null;
$osPrev = isset($_POST['os_prev']) ? (int) $_POST['os_prev'] : 0;

try {
    nfse_migrar();
    $pdo = nfse_pdo();

    if (($_POST['acao'] ?? '') === 'salvar') {
        $versao = trim((string) ($_POST['dps_versao'] ?? '1.00'));
        if (!in_array($versao, ['1.00', '1.01'], true)) { $versao = '1.00'; }

        $enviar = (isset($_POST['ibscbs_enviar']) && $_POST['ibscbs_enviar'] === '1') ? 1 : 0;
        $cst    = preg_replace('/\D/', '', (string) ($_POST['ibscbs_cst'] ?? '000'));
        $class  = preg_replace('/\D/', '', (string) ($_POST['ibscbs_class'] ?? '000001'));
        $cst    = str_pad(substr($cst, 0, 3), 3, '0', STR_PAD_LEFT);
        $class  = str_pad(substr($class, 0, 6), 6, '0', STR_PAD_LEFT);

        $pdo->prepare(
            "UPDATE nfse_config
                SET dps_versao = :v, ibscbs_enviar = :e, ibscbs_cst = :c, ibscbs_class = :k,
                    atualizado_em = NOW(), atualizado_por = :u
              WHERE id = 1"
        )->execute([
            ':v' => $versao, ':e' => $enviar, ':c' => $cst, ':k' => $class,
            ':u' => ($_SESSION['username'] ?? 'sistema'),
        ]);

        nfse_log('config', "Leiaute do DPS alterado: versao={$versao}, IBSCBS="
            . ($enviar ? "sim (CST {$cst} / cClassTrib {$class})" : 'não'), 'info');

        $msg = 'Salvo. As próximas emissões já saem com este leiaute.';
    }

    $cfg = nfse_config(true);

    /* Prévia do XML que sairia, para conferir antes de emitir. */
    if ($osPrev > 0) {
        try {
            $apuracao = nfse_apurar_os($osPrev, $cfg);
            $numero   = (int) $pdo->query('SELECT ultimo_numero_dps FROM nfse_config WHERE id = 1')->fetchColumn() + 1;
            $montado  = nfse_montar_dps($cfg, $apuracao, $numero, null);

            nfse_autoload();
            $bruto = (new \Nfse\Xml\DpsXmlBuilder)->build($montado['dps']);
            $ajust = nfse_ajustar_leiaute_dps($bruto, $cfg);

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            @$dom->loadXML($ajust);

            $previa = [
                'xml'      => $dom->saveXML(),
                'versao'   => preg_match('/<DPS[^>]*versao="([^"]+)"/', $ajust, $m) ? $m[1] : '?',
                'ibscbs'   => (strpos($ajust, '<IBSCBS>') !== false),
                'toma'     => (strpos($ajust, '<toma>') !== false),
            ];
        } catch (Throwable $e) {
            $previa = ['erro' => $e->getMessage()];
        }
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leiaute do DPS — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.6; }
    .wrap{ max-width:860px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 18px; color:var(--cinza); }
    h3{ font-size:15px; margin:0 0 10px; }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    label{ font-weight:600; }
    select,input[type=text],input[type=number]{ padding:8px 10px; border:1px solid var(--borda);
        border-radius:6px; font-size:14px; }
    .campo{ margin-bottom:14px; }
    .campo .dica{ font-weight:400; color:var(--cinza); font-size:13px; display:block; margin-top:2px; }
    button{ background:var(--azul); color:#fff; border:0; border-radius:6px; padding:10px 22px;
            font-size:14px; font-weight:600; cursor:pointer; }
    button:hover{ background:#1e3a8a; }
    .ok{ background:#dcfce7; border:1px solid var(--ok); border-radius:8px; padding:11px 14px; margin-bottom:16px; }
    .aviso{ background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:11px 13px; color:#78350f; margin:12px 0; }
    pre{ background:#0f172a; color:#e2e8f0; padding:12px; border-radius:6px; overflow:auto; max-height:380px;
         font-size:12px; margin:10px 0 0; white-space:pre-wrap; word-break:break-word; }
    table{ border-collapse:collapse; width:100%; font-size:13px; margin-top:10px; }
    th,td{ border:1px solid var(--borda); padding:7px 9px; text-align:left; }
    th{ background:#f1f5f9; width:45%; }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Leiaute do DPS</h1>
<p>Versão declarada e grupo IBSCBS, ajustáveis sem mexer em código.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo lq_h($erro); ?></div>
<?php else: ?>

    <?php if ($msg): ?><div class="ok"><?php echo lq_h($msg); ?></div><?php endif; ?>

    <form method="post" class="painel">
        <input type="hidden" name="acao" value="salvar">

        <div class="campo">
            <label for="dps_versao">Versão declarada no elemento <code>DPS</code></label>
            <span class="dica">
                O SDK fixa 1.00. Os esquemas publicados junto com o CNPJ Alfanumérico, que entrou
                em Produção em 10/08/2026, são da série 1.01.
            </span>
            <select id="dps_versao" name="dps_versao">
                <?php foreach (['1.00', '1.01'] as $v): ?>
                    <option value="<?php echo $v; ?>" <?php echo ((string) ($cfg['dps_versao'] ?? '1.00') === $v) ? 'selected' : ''; ?>>
                        <?php echo $v; ?><?php echo $v === '1.00' ? ' (padrão atual)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label>
                <input type="checkbox" name="ibscbs_enviar" value="1"
                       <?php echo !empty($cfg['ibscbs_enviar']) ? 'checked' : ''; ?>>
                Enviar o grupo <code>IBSCBS</code> na DPS
            </label>
            <span class="dica">
                Filho direto de <code>infDPS</code>, com CST e cClassTrib apenas — valores e
                alíquotas de IBS e CBS são calculados pelo Ambiente Nacional.
            </span>
        </div>

        <div class="campo">
            <label for="ibscbs_cst">CST</label>
            <input type="text" id="ibscbs_cst" name="ibscbs_cst" maxlength="3" size="4"
                   value="<?php echo lq_h($cfg['ibscbs_cst'] ?? '000'); ?>">
            &nbsp;&nbsp;
            <label for="ibscbs_class">cClassTrib</label>
            <input type="text" id="ibscbs_class" name="ibscbs_class" maxlength="6" size="7"
                   value="<?php echo lq_h($cfg['ibscbs_class'] ?? '000001'); ?>">
            <span class="dica">
                000 / 000001 é tributação integral pelo IBS e pela CBS — o padrão geral.
                <strong>Confirme com o contador</strong> antes de deixar fixo: a classificação
                tributária de serviços notariais é decisão fiscal, não técnica.
            </span>
        </div>

        <div class="aviso">
            Se a SEFIN aceitar a DPS com estas opções, a nota é válida e vale para valer. Comece
            por uma O.S. só, confira, e depois reemita a fila.
        </div>

        <button type="submit">Salvar</button>
    </form>

    <div class="painel">
        <h3>Prévia do XML</h3>
        <p style="margin:0 0 10px; color:var(--cinza)">
            Monta o DPS de uma O.S. com as opções salvas, sem enviar. Serve para conferir que a
            configuração pegou — o OPcache do Apache costuma servir o arquivo antigo até ser
            reiniciado.
        </p>

        <form method="post">
            <label for="os_prev">Nº de uma O.S. liquidada:</label>
            <input type="number" id="os_prev" name="os_prev" min="1" value="<?php echo $osPrev ?: ''; ?>" style="width:150px">
            <button type="submit" style="padding:9px 18px">Gerar prévia</button>
        </form>

        <?php if ($previa && isset($previa['erro'])): ?>
            <p style="margin:12px 0 0; color:#b91c1c"><strong>Falhou:</strong> <?php echo lq_h($previa['erro']); ?></p>
        <?php elseif ($previa): ?>
            <table>
                <tr><th>Atributo <code>versao</code></th>
                    <td><strong><?php echo lq_h($previa['versao']); ?></strong></td></tr>
                <tr><th>Grupo <code>IBSCBS</code> presente</th>
                    <td><strong><?php echo $previa['ibscbs'] ? 'sim' : 'não'; ?></strong></td></tr>
                <tr><th>Grupo <code>toma</code> presente</th>
                    <td><strong><?php echo $previa['toma'] ? 'sim' : 'não'; ?></strong></td></tr>
            </table>
            <pre><?php echo lq_h($previa['xml']); ?></pre>
        <?php endif; ?>
    </div>

    <div class="painel">
        <h3>Sugestão de ordem</h3>
        <p style="margin:0 0 8px">
            Uma mudança de cada vez, sempre conferindo a prévia antes de emitir:
        </p>
        <ol style="margin:0; padding-left:20px">
            <li>Versão <strong>1.01</strong>, sem IBSCBS — emita uma O.S.</li>
            <li>Versão <strong>1.01</strong> com IBSCBS — emita uma O.S.</li>
            <li>Versão <strong>1.00</strong> com IBSCBS — improvável, mas descarta a combinação.</li>
        </ol>
        <p style="margin:10px 0 0; color:var(--cinza)">
            Se voltar 503, ignore e repita: é o servidor web recusando antes da aplicação, e não
            diz nada sobre o leiaute. O que vale é 200 ou 500.
        </p>
    </div>

<?php endif; ?>

</div>
</body>
</html>
