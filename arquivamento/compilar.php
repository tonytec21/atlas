<?php
/**
 * Atlas · Arquivamento Digital
 * Compilação de anexos.
 *
 *   GET  ?id=<id>&formato=manifesto   → JSON com a lista de anexos compiláveis
 *   GET  ?id=<id>&formato=zip         → pacote .zip com todos os anexos + manifesto
 *   POST ?id=<id>&formato=capa        → PDF de capa + índice do dossiê (para
 *                                        ser mesclado no navegador com os anexos)
 *
 * A junção dos PDFs/imagens em um único arquivo acontece no navegador
 * (assets/js/compilador.js, com pdf-lib). Isso evita depender de bibliotecas
 * de importação de PDF no servidor, que só leem PDF 1.4 na versão livre e
 * quebrariam com documentos gerados por scanners e pelo Word.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$formato = isset($_GET['formato']) ? (string) $_GET['formato'] : 'manifesto';
$idsRaw  = isset($_GET['ids']) ? (string) $_GET['ids'] : (isset($_GET['id']) ? (string) $_GET['id'] : '');

$ids = [];
foreach (explode(',', $idsRaw) as $i) {
    $v = arq_id_valido(trim($i));
    if ($v !== '') { $ids[] = $v; }
}
$ids = array_values(array_unique($ids));
if (empty($ids)) { arq_erro('Informe ao menos um arquivamento válido.', 400); }
if (count($ids) > 50) { arq_erro('Selecione no máximo 50 arquivamentos por compilação.', 400); }

/** Extensões que o compilador consegue embutir no PDF final. */
function arq_compilavel($ext)
{
    return in_array(mb_strtolower($ext), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

/** Carrega os arquivamentos pedidos, já com anexos resolvidos. */
function arq_carregar_dossies(array $ids)
{
    $dossies = [];
    foreach ($ids as $id) {
        $ato = arq_obter($id);
        if (!$ato) { continue; }
        foreach ($ato['anexos'] as $i => $a) {
            $ato['anexos'][$i]['indice']     = $i;
            $ato['anexos'][$i]['url']        = 'arquivo.php?id=' . rawurlencode($id) . '&a=' . $i;
            $ato['anexos'][$i]['compilavel'] = arq_compilavel($a['ext']);
        }
        $ato['selos'] = arq_selos($id);
        $dossies[] = $ato;
    }
    return $dossies;
}

/* ================================================================== *
 * 1) MANIFESTO — o front usa para saber o que baixar e mesclar
 * ================================================================== */
if ($formato === 'manifesto') {
    $dossies = arq_carregar_dossies($ids);
    if (empty($dossies)) { arq_erro('Nenhum arquivamento encontrado.', 404); }

    $saida = [];
    foreach ($dossies as $d) {
        $anexos = [];
        foreach ($d['anexos'] as $a) {
            $anexos[] = [
                'indice'          => $a['indice'],
                'nome'            => $a['nome'],
                'ext'             => $a['ext'],
                'mime'            => $a['mime'],
                'tamanho'         => (int) $a['tamanho'],
                'tamanho_legivel' => arq_formatar_bytes($a['tamanho']),
                'hash'            => $a['hash'],
                'origem'          => $a['origem'],
                'disponivel'      => (bool) $a['disponivel'],
                'compilavel'      => (bool) $a['compilavel'],
                'url'             => $a['url'],
            ];
        }
        $saida[] = [
            'id'         => $d['id'],
            'atribuicao' => $d['atribuicao'],
            'categoria'  => $d['categoria'],
            'data_ato'   => $d['data_ato'],
            'livro'      => $d['livro'],
            'folha'      => $d['folha'],
            'termo'      => $d['termo'],
            'protocolo'  => $d['protocolo'],
            'matricula'  => $d['matricula'],
            'partes'     => array_column($d['partes_envolvidas'], 'nome'),
            'anexos'     => $anexos,
        ];
    }

    arq_ok([
        'dossies' => $saida,
        'usuario' => arq_usuario_nome(),
        'gerado'  => date('d/m/Y H:i:s'),
    ]);
}

/* ================================================================== *
 * 2) ZIP — pacote com os arquivos originais + manifesto de integridade
 * ================================================================== */
if ($formato === 'zip') {
    if (!class_exists('ZipArchive')) {
        arq_erro('A extensão ZipArchive não está habilitada no PHP deste servidor.', 500);
    }
    if (!arq_limite_taxa('zip', 20, 60)) {
        arq_erro('Muitos downloads em sequência. Aguarde um instante.', 429);
    }

    $dossies = arq_carregar_dossies($ids);
    if (empty($dossies)) { arq_erro('Nenhum arquivamento encontrado.', 404); }

    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arq-' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        arq_erro('Não foi possível criar o pacote.', 500);
    }

    $linhas = [];
    $linhas[] = 'ATLAS · ARQUIVAMENTO DIGITAL — MANIFESTO DO PACOTE';
    $linhas[] = str_repeat('=', 68);
    $linhas[] = 'Gerado em ....: ' . date('d/m/Y H:i:s');
    $linhas[] = 'Gerado por ...: ' . arq_usuario_nome() . ' (' . arq_usuario() . ')';
    $linhas[] = 'Arquivamentos : ' . count($dossies);
    $linhas[] = '';

    $totalArquivos = 0;
    foreach ($dossies as $d) {
        $pasta = $d['id'] . ' - ' . arq_nome_seguro($d['categoria'] !== '' ? $d['categoria'] : 'arquivamento');
        $linhas[] = str_repeat('-', 68);
        $linhas[] = 'ARQUIVAMENTO Nº ' . $d['id'];
        $linhas[] = 'Atribuição ...: ' . $d['atribuicao'];
        $linhas[] = 'Categoria ....: ' . $d['categoria'];
        $linhas[] = 'Data do ato ..: ' . ($d['data_ato'] !== '' ? date('d/m/Y', strtotime($d['data_ato'])) : '—');
        $linhas[] = 'Livro/Folha ..: ' . ($d['livro'] !== '' ? $d['livro'] : '—') . ' / ' . ($d['folha'] !== '' ? $d['folha'] : '—');
        $linhas[] = 'Partes .......: ' . implode('; ', array_column($d['partes_envolvidas'], 'nome'));
        $selos = arq_numeros_selos($d['id']);
        if ($selos) { $linhas[] = 'Selos ........: ' . implode(', ', $selos); }
        $linhas[] = 'Anexos:';

        foreach ($d['anexos'] as $a) {
            $abs = arq_resolver_anexo($a['ref']);
            if ($abs === false) {
                $linhas[] = '  [INDISPONÍVEL] ' . $a['nome'];
                continue;
            }
            $hash = $a['hash'] ? $a['hash'] : hash_file('sha256', $abs);
            $zip->addFile($abs, $pasta . '/' . $a['nome']);
            $linhas[] = '  · ' . $a['nome'];
            $linhas[] = '      tamanho: ' . arq_formatar_bytes($a['tamanho']);
            $linhas[] = '      sha-256: ' . $hash;
            $totalArquivos++;
        }
        $linhas[] = '';
    }

    $linhas[] = str_repeat('=', 68);
    $linhas[] = 'Total de arquivos no pacote: ' . $totalArquivos;
    $linhas[] = 'Confira a integridade com:  certutil -hashfile "arquivo" SHA256';

    $zip->addFromString('MANIFESTO.txt', implode("\r\n", $linhas));
    $zip->close();

    arq_auditar('compilar', implode(',', $ids), ['formato' => 'zip', 'arquivos' => $totalArquivos]);

    $nomeZip = count($ids) === 1
        ? 'arquivamento-' . $ids[0] . '.zip'
        : 'arquivamentos-' . date('Ymd-Hi') . '.zip';

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nomeZip . '"');
    header('Content-Length: ' . filesize($tmp));
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ================================================================== *
 * 3) CAPA + ÍNDICE (TCPDF) — primeira(s) página(s) do PDF compilado
 * ================================================================== */
if ($formato === 'capa') {
    arq_exige_post_seguro();

    require_once __DIR__ . '/lib/Capa.php';
    if (!arq_carregar_tcpdf()) {
        arq_erro('Biblioteca TCPDF não localizada. Verifique o caminho ../oficios/tcpdf/.', 500);
    }

    $corpo = json_decode(file_get_contents('php://input'), true);
    if (!is_array($corpo)) { $corpo = $_POST; }
    $documentos = isset($corpo['documentos']) && is_array($corpo['documentos']) ? $corpo['documentos'] : [];

    $dossies = arq_carregar_dossies($ids);
    if (empty($dossies)) { arq_erro('Nenhum arquivamento encontrado.', 404); }

    $bytes = arq_gerar_capa($dossies, $documentos, $ids);
    if ($bytes === false) { arq_erro('Falha ao gerar a capa do dossiê.', 500); }

    arq_auditar('compilar', implode(',', $ids), ['formato' => 'pdf', 'documentos' => count($documentos)]);

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="capa-dossie.pdf"');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
}

arq_erro('Formato de compilação desconhecido.', 400);
