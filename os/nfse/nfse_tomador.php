<?php
/**
 * ATLAS O.S. — Identificação do tomador na NFS-e
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-11-tomador-opcional
 *
 * Liga e desliga o envio do grupo <toma> na DPS.
 *
 * Em 2026 identificar o tomador é facultativo — a NFS-e pode ser emitida
 * como "Tomador não informado". A partir de 01/01/2027 a individualização
 * passa a ser obrigatória, e nessa data a opção deixa de ter efeito
 * automaticamente (nfse_exige_individualizacao() tem precedência).
 *
 * Serve como contorno quando a SEFIN está recusando a identificação: os
 * erros E0206 ("CPF do tomador é inválido") e E0207 ("CPF do tomador não
 * encontrado no cadastro CPF") vêm da consulta que ela faz ao cadastro de
 * CPF. Sem o grupo <toma>, essa consulta não acontece.
 *
 * Acesse: .../os/nfse/nfse_tomador.php   (administrador)
 */

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

date_default_timezone_set('America/Sao_Paulo');

function tm_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$msg = null;
$erro = null;

try {
    nfse_migrar();
    $pdo = nfse_pdo();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $novo = (isset($_POST['omitir']) && $_POST['omitir'] === '1') ? 1 : 0;

        $st = $pdo->prepare(
            "UPDATE nfse_config
                SET omitir_tomador = :v, atualizado_em = NOW(), atualizado_por = :usr
              WHERE id = 1"
        );
        $st->execute([':v' => $novo, ':usr' => ($_SESSION['username'] ?? 'sistema')]);

        nfse_log('config', $novo
            ? 'Identificação do tomador DESLIGADA (DPS passa a sair como "Tomador não informado").'
            : 'Identificação do tomador RELIGADA.', 'info');

        $msg = $novo
            ? 'Pronto. As próximas DPS sairão sem o grupo do tomador.'
            : 'Pronto. As próximas DPS voltam a identificar o tomador.';
    }

    $cfg = nfse_config();
    $omitir = !empty($cfg['omitir_tomador']);
    $venceEm = mktime(0, 0, 0, 1, 1, 2027);
    $expirou = nfse_exige_individualizacao();

    /* ---------------------------------------------------------------
     * Conferência do que realmente sai no XML.
     *
     * Marcar a opção na tela e ver o erro repetir não prova nada se a
     * versão do nfse_lib.php instalada não tiver o recurso — o OPcache
     * do Apache, em particular, serve o arquivo antigo até ser
     * reiniciado. Aqui o DPS é montado de verdade (sem enviar) e o
     * grupo <toma> é procurado no XML resultante.
     * --------------------------------------------------------------- */
    $conf = null;
    $osConf = isset($_POST['os_conf']) ? (int) $_POST['os_conf'] : 0;

    if ($osConf > 0) {
        try {
            $cfgFull  = nfse_config(true);
            $apuracao = nfse_apurar_os($osConf, $cfgFull);

            $numero  = (int) nfse_pdo()->query('SELECT ultimo_numero_dps FROM nfse_config WHERE id = 1')->fetchColumn() + 1;
            $montado = nfse_montar_dps($cfgFull, $apuracao, $numero, null);

            nfse_autoload();
            $xml = (new \Nfse\Xml\DpsXmlBuilder)->build($montado['dps']);

            $conf = [
                'suporta'  => array_key_exists('omitir_tomador', $cfg),
                'tem_toma' => (strpos($xml, '<toma>') !== false),
                'versao'   => preg_match('/<DPS[^>]*versao="([^"]+)"/', $xml, $mv) ? $mv[1] : '?',
                'xml'      => $xml,
            ];
        } catch (Throwable $e) {
            $conf = ['erro' => $e->getMessage()];
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
<title>Identificação do tomador — Atlas O.S.</title>
<style>
    :root{ --azul:#1e40af; --cinza:#475569; --borda:#e2e8f0; --ok:#16a34a; --alerta:#d97706; }
    *{ box-sizing:border-box; }
    body{ margin:0; padding:24px; background:#f8fafc; color:#0f172a;
          font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; font-size:14px; line-height:1.6; }
    .wrap{ max-width:760px; margin:0 auto; }
    h1{ font-size:22px; margin:0 0 4px; color:var(--azul); }
    h1 + p{ margin:0 0 20px; color:var(--cinza); }
    .painel{ background:#fff; border:1px solid var(--borda); border-radius:10px; padding:18px 20px; margin-bottom:18px; }
    .estado{ font-size:15px; font-weight:600; margin:0 0 14px; }
    .on{ color:var(--alerta); } .off{ color:var(--ok); }
    button{ border:0; border-radius:6px; padding:11px 22px; font-size:14px; font-weight:600;
            cursor:pointer; color:#fff; }
    .b-desliga{ background:var(--alerta); } .b-desliga:hover{ background:#b45309; }
    .b-liga{ background:var(--azul); } .b-liga:hover{ background:#1e3a8a; }
    .ok{ background:#dcfce7; border:1px solid var(--ok); border-radius:8px; padding:11px 14px; margin-bottom:16px; }
    .nota{ color:var(--cinza); }
    code{ background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Identificação do tomador na NFS-e</h1>
<p>Controla se o grupo <code>&lt;toma&gt;</code> vai na DPS.</p>

<?php if ($erro): ?>
    <div class="painel"><strong style="color:#b91c1c">Falhou:</strong> <?php echo tm_h($erro); ?></div>
<?php else: ?>

    <?php if ($msg): ?><div class="ok"><?php echo tm_h($msg); ?></div><?php endif; ?>

    <div class="painel">
        <p class="estado <?php echo $omitir ? 'on' : 'off'; ?>">
            <?php echo $omitir
                ? 'Hoje: o tomador NÃO é enviado — as notas saem como “Tomador não informado”.'
                : 'Hoje: o tomador é enviado sempre que a O.S. tiver CPF/CNPJ válido.'; ?>
        </p>

        <form method="post">
            <input type="hidden" name="omitir" value="<?php echo $omitir ? '0' : '1'; ?>">
            <button type="submit" class="<?php echo $omitir ? 'b-liga' : 'b-desliga'; ?>">
                <?php echo $omitir ? 'Voltar a identificar o tomador' : 'Parar de enviar o tomador'; ?>
            </button>
        </form>
    </div>

    <div class="painel">
        <h3 style="margin:0 0 8px; font-size:15px">Conferir o que realmente sai no XML</h3>
        <p style="margin:0 0 10px" class="nota">
            Marcar a opção aqui e ver o erro repetir só prova alguma coisa se a versão instalada
            do <code>nfse_lib.php</code> tiver o recurso. O OPcache do Apache costuma servir o
            arquivo antigo até ser reiniciado. Este teste monta o DPS de verdade, sem enviar, e
            procura o grupo <code>&lt;toma&gt;</code> no XML resultante.
        </p>

        <form method="post">
            <label for="os_conf" style="font-weight:600">Nº de uma O.S. liquidada:</label>
            <input type="number" id="os_conf" name="os_conf" min="1"
                   value="<?php echo $osConf ?: ''; ?>"
                   style="padding:8px 10px; border:1px solid var(--borda); border-radius:6px; width:150px">
            <button type="submit" class="b-liga" style="padding:9px 18px">Conferir</button>
        </form>

        <?php if ($conf && isset($conf['erro'])): ?>
            <p style="margin:12px 0 0; color:#b91c1c"><strong>Falhou:</strong> <?php echo tm_h($conf['erro']); ?></p>
        <?php elseif ($conf): ?>
            <table style="border-collapse:collapse; width:100%; margin-top:14px; font-size:13px">
                <tr>
                    <td style="border:1px solid var(--borda); padding:7px 9px; width:52%">
                        Versão instalada suporta a opção
                    </td>
                    <td style="border:1px solid var(--borda); padding:7px 9px; font-weight:600;
                               color:<?php echo $conf['suporta'] ? '#16a34a' : '#dc2626'; ?>">
                        <?php echo $conf['suporta'] ? 'sim' : 'NÃO — nfse_lib.php desatualizado ou OPcache servindo o antigo'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid var(--borda); padding:7px 9px">
                        O XML gerado contém <code>&lt;toma&gt;</code>
                    </td>
                    <td style="border:1px solid var(--borda); padding:7px 9px; font-weight:600;
                               color:<?php echo $conf['tem_toma'] ? '#d97706' : '#16a34a'; ?>">
                        <?php echo $conf['tem_toma'] ? 'SIM' : 'não'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid var(--borda); padding:7px 9px">Atributo <code>versao</code> do DPS</td>
                    <td style="border:1px solid var(--borda); padding:7px 9px"><?php echo tm_h($conf['versao']); ?></td>
                </tr>
            </table>

            <?php if ($omitir && $conf['tem_toma']): ?>
                <p style="margin:12px 0 0; color:#b91c1c">
                    <strong>A opção está ligada, mas o tomador continua saindo no XML.</strong>
                    O teste anterior não valeu: o que foi transmitido ainda tinha o grupo
                    <code>&lt;toma&gt;</code>. Atualize o <code>nfse_lib.php</code>, reinicie o
                    Apache (o OPcache não recarrega sozinho) e repita.
                </p>
            <?php elseif ($omitir && !$conf['tem_toma']): ?>
                <p style="margin:12px 0 0; color:#166534">
                    <strong>Confirmado.</strong> O DPS está saindo sem o tomador — se a emissão
                    ainda falhar com 500, a hipótese do tomador está descartada de verdade.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="painel">
        <h3 style="margin:0 0 8px; font-size:15px">Quando usar</h3>
        <p style="margin:0 0 10px">
            Os erros <strong>E0206</strong> (“CPF do tomador é inválido”) e <strong>E0207</strong>
            (“CPF do tomador não encontrado no cadastro CPF”) vêm da consulta que a SEFIN faz ao
            cadastro de CPF durante a recepção da DPS. Sem o grupo <code>&lt;toma&gt;</code> essa
            consulta não acontece.
        </p>
        <p style="margin:0" class="nota">
            Vale lembrar que a nota continua válida e vinculada à O.S.; o que se perde é o nome e o
            CPF do apresentante impressos no documento. Se o cliente precisar da nota nominal,
            religue a opção e emita aquela O.S. depois.
        </p>
    </div>

    <div class="painel">
        <h3 style="margin:0 0 8px; font-size:15px">Prazo</h3>
        <p style="margin:0" class="nota">
            <?php if ($expirou): ?>
                A partir de 01/01/2027 a individualização por ato com tomador identificado passou a
                ser obrigatória. <strong>Esta opção já não tem mais efeito</strong> — o tomador é
                enviado de qualquer forma.
            <?php else: ?>
                Facultativo até <strong>31/12/2026</strong>. Em 01/01/2027 a individualização com
                tomador identificado passa a ser obrigatória e esta opção deixa de ter efeito
                sozinha, sem precisar de intervenção
                (<?php echo (int) ceil(($venceEm - time()) / 86400); ?> dias).
            <?php endif; ?>
        </p>
    </div>

<?php endif; ?>

</div>
</body>
</html>
