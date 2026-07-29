<?php
/**
 * provimentos/sync_diag.php
 *
 * Pagina de diagnostico independente. Abra direto no navegador:
 *   http://localhost/atlas/provimentos/sync_diag.php
 *
 * Existe porque um erro fatal durante o carregamento dos arquivos acontece
 * ANTES do conversor de erros do sync_worker.php, e nesse caso o navegador
 * recebe um HTTP 500 sem mensagem nenhuma. Aqui os erros aparecem.
 */

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
@set_time_limit(300);

header('Content-Type: text/plain; charset=utf-8');

function passo($titulo)
{
    echo "\n" . str_repeat('=', 68) . "\n" . $titulo . "\n" . str_repeat('=', 68) . "\n";
}
function linha($rotulo, $valor)
{
    printf("  %-22s %s\n", $rotulo . ':', $valor);
}

echo "DIAGNOSTICO DA SINCRONIZACAO - " . date('d/m/Y H:i:s') . "\n";

// ---------------------------------------------------------------------------
passo('1. Ambiente');
linha('PHP', PHP_VERSION);
foreach (array('curl', 'openssl', 'mbstring', 'pdo_mysql', 'json') as $ext) {
    linha($ext, extension_loaded($ext) ? 'ok' : '*** AUSENTE ***');
}
linha('memory_limit', ini_get('memory_limit'));
linha('max_execution_time', ini_get('max_execution_time') . 's');
linha('allow_url_fopen', ini_get('allow_url_fopen') ? 'on' : 'off');

$pasta = __DIR__ . DIRECTORY_SEPARATOR . 'anexo';
linha('pasta anexo', is_dir($pasta)
    ? (is_writable($pasta) ? 'existe e gravavel' : '*** SEM PERMISSAO ***')
    : (@mkdir($pasta, 0775, true) ? 'criada agora' : '*** NAO CONSEGUI CRIAR ***'));

// ---------------------------------------------------------------------------
passo('2. Carregamento dos arquivos');
$arquivos = array(
    '../kb/config_kb.php', '../kb/lib_kb.php', '../kb/schema_kb.php',
    'db_connection.php', 'sync_lib.php',
);
foreach ($arquivos as $a) {
    $caminho = __DIR__ . '/' . $a;
    if (!is_file($caminho)) {
        linha($a, '*** ARQUIVO NAO EXISTE ***');
        continue;
    }
    try {
        require_once $caminho;
        linha($a, 'carregado');
    } catch (Throwable $e) {
        linha($a, '*** ERRO: ' . $e->getMessage() . ' ***');
        echo "\n  >> O carregamento parou aqui. Corrija este ponto antes de seguir.\n";
        exit;
    }
}

// ---------------------------------------------------------------------------
passo('3. Funcoes esperadas');
$necessarias = array(
    'getDatabaseConnection', 'kbApiKey', 'kbModelo', 'kbHttpPost', 'kbBlindarJson',
    'syncGarantirSchema', 'syncSchemaExiste', 'syncGet', 'syncHtmlParaTexto',
    'syncNormalizarTipo', 'syncNormalizarNumero', 'syncPrioridade',
    'syncCnjDescobrir', 'syncCnjLinhasListagem', 'syncCnjMontarLinha',
    'syncCnjFicha', 'syncCnjLinksTexto',
    'syncCgjmaDescobrir', 'syncCgjmaItemListagem', 'syncCgjmaFicha', 'syncCgjmaLinksDownload',
    'syncRegistrarItem', 'syncImportar', 'syncBaixarPdf', 'syncPdfTexto', 'syncPdfGemini',
    'syncLacunas', 'syncBuscarLacunas', 'syncChecarAlteracoes', 'syncTestarListagem',
    'syncTestarAnexo', 'syncReanexar', 'syncImportarPorUrl',
);
$faltando = array();
foreach ($necessarias as $f) {
    if (!function_exists($f)) { $faltando[] = $f; }
}
linha('total verificado', count($necessarias));
linha('ausentes', $faltando ? '*** ' . implode(', ', $faltando) . ' ***' : 'nenhuma');

// ---------------------------------------------------------------------------
passo('4. Banco de dados');
try {
    $conn = getDatabaseConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    linha('conexao', 'ok');
    linha('servidor', $conn->getAttribute(PDO::ATTR_SERVER_VERSION));

    // Cada consulta isolada: num diagnostico, uma falha nao pode derrubar
    // o restante do relatorio.
    try {
        linha('sql_mode', $conn->query("SELECT @@sql_mode")->fetchColumn() ?: '(vazio)');
    } catch (Throwable $e) {
        linha('sql_mode', 'nao disponivel');
    }

    try {
        linha('schema atualizado', syncSchemaExiste($conn) ? 'sim' : 'nao - vai migrar agora');
        foreach (syncGarantirSchema($conn) as $l) {
            echo '  ' . $l . "\n";
        }
    } catch (Throwable $e) {
        linha('migracao', '*** ' . $e->getMessage() . ' ***');
    }

    foreach (array('kb_fontes', 'kb_sync_itens', 'kb_config', 'kb_relacoes') as $t) {
        try {
            $n = $conn->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            linha($t, $n . ' linha(s)');
        } catch (Throwable $e) {
            linha($t, '*** ' . $e->getMessage() . ' ***');
        }
    }

    echo "\n  Fontes cadastradas:\n";
    try {
    foreach ($conn->query("SELECT * FROM kb_fontes") as $f) {
        printf("    [%s] %s | ativo=%d | pagina=%s\n      %s\n",
            $f['adaptador'], $f['nome'], $f['ativo'],
            $f['pagina'] === null ? '-' : $f['pagina'],
            $f['url_listagem'] ?: '*** SEM URL DE LISTAGEM ***');
        if (!empty($f['ultimo_erro'])) {
            echo "      ultimo erro: " . $f['ultimo_erro'] . "\n";
        }
    }
    } catch (Throwable $e) {
        linha('fontes', '*** ' . $e->getMessage() . ' ***');
    }
} catch (Throwable $e) {
    linha('banco', '*** ' . $e->getMessage() . ' ***');
}

// ---------------------------------------------------------------------------
passo('5. Alcance dos portais');
$alvos = array(
    'CNJ    (listagem)' => 'https://atos.cnj.jus.br/atos?atos=sim&tipoAto%5B0%5D=20&page=1',
    'CNJ    (ficha)'    => 'https://atos.cnj.jus.br/atos/detalhar/5243',
    'CGJ/MA (listagem)' => 'https://www.tjma.jus.br/atos/extrajudicial/geral/0/5657/pnao/provimentos-cogex',
);
$paginas = array();
foreach ($alvos as $rotulo => $url) {
    try {
        $t0 = microtime(true);
        $html = syncGet($url, $http);
        $paginas[$rotulo] = $html;
        linha($rotulo, sprintf('HTTP %d, %d KB, %.1fs', $http, strlen($html) / 1024,
                               microtime(true) - $t0));
    } catch (Throwable $e) {
        linha($rotulo, '*** ' . $e->getMessage() . ' ***');
    }
}

// ---------------------------------------------------------------------------
passo('6. Leitura das listagens');

if (isset($paginas['CNJ    (listagem)'])) {
    $html = $paginas['CNJ    (listagem)'];
    linha('refs a ficha', preg_match_all('#\bdetalhar/\d+#i', $html));
    linha('tags <tr>', preg_match_all('#<tr[^>]*>#i', $html));
    linha('tags <table>', preg_match_all('#<table[^>]*>#i', $html));

    $linhas = syncCnjLinhasListagem($html);
    linha('atos interpretados', count($linhas));
    foreach (array_slice($linhas, 0, 5) as $l) {
        printf("    %s %s/%d  %s  [%s]\n", $l['tipo'], $l['numero'], $l['ano'],
               $l['data'], $l['situacao']);
    }
    if (!$linhas) {
        // A linha crua da tabela e a evidencia que resolve: mostra como o
        // portal escreve o identificador e as celulas.
        if (preg_match_all('#<tr[^>]*>.*?</tr>#is', $html, $trs)) {
            echo "\n  Total de linhas <tr>: " . count($trs[0]) . "\n";
            foreach (array_slice($trs[0], 0, 3) as $i => $tr) {
                echo "\n  --- linha " . ($i + 1) . " (bruta) ---\n  "
                   . mb_substr(preg_replace('/\s+/', ' ', $tr), 0, 1400) . "\n";
            }
        } else {
            echo "\n  Nenhuma tag <tr>. Inicio da pagina:\n  "
               . mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($html))), 0, 400) . "\n";
        }
    }
}

if (isset($paginas['CGJ/MA (listagem)'])) {
    $html = $paginas['CGJ/MA (listagem)'];
    echo "\n";
    linha('links COGEX', preg_match_all('#/atos/extrajudicial/geral/\d+/5657#', $html));
    $ok = 0; $amostra = array();
    if (preg_match_all('#<a[^>]+href="([^"]*?/atos/extrajudicial/geral/(\d+)/5657/pnao[^"]*)"[^>]*>(.*?)</a>#is',
                       $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $it) {
            $meta = syncCgjmaItemListagem($it[3]);
            if ($meta) {
                $ok++;
                if (count($amostra) < 5) {
                    $amostra[] = $meta['tipo'] . ' ' . $meta['numero'] . '/' . $meta['ano']
                               . ' (' . $meta['data'] . ')';
                }
            }
        }
    }
    linha('atos interpretados', $ok);
    foreach ($amostra as $a) { echo "    " . $a . "\n"; }
}

// ---------------------------------------------------------------------------
passo('7. Chave do Gemini');
linha('configurada', kbApiKey() !== '' ? 'sim' : 'nao (afeta so PDF digitalizado)');
if (kbApiKey() !== '') {
    linha('modelo de texto', kbModelo('chat'));
}

echo "\n" . str_repeat('=', 68) . "\nFIM. Copie tudo acima ao relatar o problema.\n";
