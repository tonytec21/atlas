<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/**
 * Traduções simples de termos comuns (fallback).
 * NÃO remove nomes de elementos; apenas traduz trechos genéricos.
 */
function traduzirMensagemSimples($msg) {
    $map = [
        "Missing child element(s)" => "Elemento(s) filho ausente(s)",
        "This element is not expected" => "Este elemento não era esperado",
        "Invalid content was found starting with element" => "Conteúdo inválido foi encontrado iniciado pelo elemento",
        "One of" => "Um dos",
        "is expected" => "é esperado",
        "Expected is" => "Esperado:",
        "Element content is not allowed, because the content type is EMPTY" =>
            "Conteúdo de texto não é permitido (tipo esperado: EMPTY)",
        "is not an element of the set" => "não é um elemento do conjunto permitido",
        "is not valid according to its type definition" => "não é válido conforme a definição de tipo",
        "does not satisfy the 'pattern'" => "não atende ao 'pattern' exigido",
        "The value has a length of" => "O valor possui comprimento",
        "this exceeds the allowed maximum length of" => "isso excede o comprimento máximo permitido de",
    ];
    foreach ($map as $en => $pt) $msg = str_replace($en, $pt, $msg);
    return $msg;
}

/**
 * Constrói uma mensagem PT-BR rica preservando nomes do elemento, valores e o conjunto esperado.
 */
function traduzirErroDetalhado($raw) {
    $m = trim($raw);

    if (preg_match("/Element '([^']+)': This element is not expected\\. Expected is (.*)\\./", $m, $g)) {
        $el  = $g[1]; $exp = trim($g[2], " ."); $exp = trim($exp, "{}");
        return "Elemento '$el' não era esperado aqui. Esperado: $exp.";
    }
    if (preg_match("/Element '([^']+)': Invalid content was found starting with element '([^']+)'. One of \\{(.*)\\} is expected\\./", $m, $g)) {
        $ctx = $g[1]; $start = $g[2]; $exp = $g[3];
        return "No elemento '$ctx', conteúdo inválido iniciado em '$start'. Esperado: {$exp}.";
    }
    if (preg_match("/Invalid content was found starting with element '([^']+)'. One of \\{(.*)\\} is expected\\./", $m, $g)) {
        $start = $g[1]; $exp = $g[2];
        return "Conteúdo inválido começando no elemento '$start'. Esperado: {$exp}.";
    }
    if (preg_match("/Element '([^']+)': Missing child element\\(s\\)\\. Expected is (.*)\\./", $m, $g)) {
        $el = $g[1]; $exp = trim($g[2], " ."); $exp = trim($exp, "{}");
        return "Elemento '$el' está faltando elemento(s) filho. Esperado: $exp.";
    }
    if (preg_match("/Element '([^']+)': Element content is not allowed, because the content type is EMPTY\\./", $m, $g)) {
        return "Elemento '{$g[1]}' não pode conter conteúdo de texto (tipo esperado: EMPTY).";
    }
    if (preg_match("/\\[facet 'enumeration'\\] The value '([^']+)' is not an element of the set \\{(.*)\\}\\./", $m, $g)) {
        return "O valor '{$g[1]}' não pertence ao conjunto permitido: {$g[2]}.";
    }
    if (preg_match("/\\[facet 'maxLength'\\] The value has a length of '([0-9]+)'; this exceeds the allowed maximum length of '([0-9]+)'\\./", $m, $g)) {
        return "Comprimento inválido: {$g[1]} (máximo permitido: {$g[2]}).";
    }
    if (preg_match("/Element '([^']+)': \\[facet 'pattern'\\] The value '([^']*)' is not accepted by the pattern '([^']+)'/", $m, $g)) {
        return "No elemento '{$g[1]}', o valor '{$g[2]}' não atende ao padrão exigido '{$g[3]}'.";
    }
    if (preg_match("/Element '([^']+)': (.*)/", $m, $g)) {
        return "Elemento '{$g[1]}': " . traduzirMensagemSimples($g[2]);
    }
    return traduzirMensagemSimples($m);
}

/**
 * Reformatar (indentar) XML em um arquivo temporário.
 */
function reformatXml($sourcePath) {
    $dom = new DOMDocument();
    $loadOk = @$dom->load($sourcePath, LIBXML_NOBLANKS);
    if (!$loadOk) return false;
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $tempPath = tempnam(sys_get_temp_dir(), 'xmlreindent_');
    $dom->save($tempPath);
    return $tempPath;
}

/**
 * Detecta e corrige "lixo" antes da declaração XML, como:
 * Warning: PHP Request Startup: Input variables exceeded 1000...
 * Retorna:
 * [ 'had_preamble'=>bool, 'preamble'=>string, 'clean_temp_path'=>string|null, 'clean_content'=>string|null ]
 */
function corrigirPreambleSeNecessario($tmpPath) {
    $out = ['had_preamble'=>false, 'preamble'=>'', 'clean_temp_path'=>null, 'clean_content'=>null];
    $raw = @file_get_contents($tmpPath);
    if ($raw === false) return $out;

    $pos = strpos($raw, '<?xml');
    if ($pos === false) return $out; // sem declaração; não alteramos nada

    $preamble = substr($raw, 0, $pos);
    // Se existir algo não-espaco antes da declaração, consideramos problema estrutural
    if (preg_match('/\S/', $preamble)) {
        $clean = substr($raw, $pos);
        $tempPath = tempnam(sys_get_temp_dir(), 'xmlclean_');
        file_put_contents($tempPath, $clean);
        $out['had_preamble']   = true;
        $out['preamble']       = $preamble;
        $out['clean_temp_path']= $tempPath;
        $out['clean_content']  = $clean;
    }
    return $out;
}

/**
 * Mapeia referências por linha considerando o tipo de ato:
 * - Casamento: NOMECONJUGE1 / NOMECONJUGE2
 * - Nascimento: NOMEREGISTRADO
 * - Óbito: NOMEFALECIDO
 *
 * Retorna:
 *  [
 *    'linemap' => [linha => 'referência legível'],
 *    'tipo'    => 'casamento'|'nascimento'|'obito'|'desconhecido'
 *  ]
 */
function mapearReferenciasPorLinha($arquivoXml) {
    $linhas = @file($arquivoXml);
    if (!$linhas) return ['linemap'=>[], 'tipo'=>'desconhecido'];

    $linemap = [];
    $seen = [
        'NOMEREGISTRADO' => null,
        'NOMECONJUGE1'   => null,
        'NOMECONJUGE2'   => null,
        'NOMEFALECIDO'   => null,
    ];

    $hasCasamento = false;
    $hasNascimento = false;
    $hasObito = false;

    foreach ($linhas as $i => $conteudo) {
        $linha = $i + 1;

        foreach (['NOMEREGISTRADO','NOMECONJUGE1','NOMECONJUGE2','NOMEFALECIDO'] as $tag) {
            if (preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/', $conteudo, $m)) {
                $val = trim($m[1]);
                if ($val !== '') $seen[$tag] = $val;
                if ($tag === 'NOMECONJUGE1' || $tag === 'NOMECONJUGE2') $hasCasamento = true;
                if ($tag === 'NOMEREGISTRADO') $hasNascimento = true;
                if ($tag === 'NOMEFALECIDO') $hasObito = true;
            }
        }

        $tipo = 'desconhecido';
        if ($hasCasamento)     $tipo = 'casamento';
        elseif ($hasObito)     $tipo = 'obito';
        elseif ($hasNascimento)$tipo = 'nascimento';

        $ref = '';
        if ($tipo === 'casamento') {
            $c1 = $seen['NOMECONJUGE1'] ?: '';
            $c2 = $seen['NOMECONJUGE2'] ?: '';
            if ($c1 || $c2) $ref = trim($c1 . ($c1 && $c2 ? ' & ' : '') . $c2);
        } elseif ($tipo === 'obito') {
            $ref = $seen['NOMEFALECIDO'] ?: '';
        } else { // nascimento por padrão
            $ref = $seen['NOMEREGISTRADO'] ?: '';
        }

        if ($ref !== '') $linemap[$linha] = $ref;
    }

    $last = '';
    $full = [];
    $max = count($linhas);
    for ($l=1; $l<=$max; $l++) {
        if (isset($linemap[$l])) $last = $linemap[$l];
        if ($last !== '') $full[$l] = $last;
    }

    $tipo = $hasCasamento ? 'casamento' : ($hasObito ? 'obito' : ($hasNascimento ? 'nascimento' : 'desconhecido'));
    return ['linemap'=>$full, 'tipo'=>$tipo];
}

/**
 * Usa o linemap para obter a referência da linha do erro.
 */
function obterReferenciaPorLinha($mapResult, $linhaErro) {
    $linemap = $mapResult['linemap'] ?? [];
    $ref = '';
    foreach ($linemap as $linha => $nome) {
        if ($linha <= $linhaErro) $ref = $nome; else break;
    }
    return $ref;
}

/* ================== PIPELINE RESULTADO ================== */
$resultado = [
    'ok' => null,
    'arquivo' => null,
    'tamanho' => null,
    'erros' => [],    // ['linha','registro','mensagem_pt','mensagem_en']
    'mensagem' => '',
    'tipo_ato' => 'desconhecido',

    // novo: problema estrutural
    'structural_issue' => false,
    'preamble_excerpt' => '',
    'corrected_xml'    => null,   // conteúdo limpo para download
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['xmlFile']) && is_uploaded_file($_FILES['xmlFile']['tmp_name'])) {
        $nomeOriginal = $_FILES['xmlFile']['name'];
        $tmpPath      = $_FILES['xmlFile']['tmp_name'];
        $tamanho      = (int)$_FILES['xmlFile']['size'];

        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if ($ext !== 'xml') {
            $resultado['ok'] = false;
            $resultado['mensagem'] = 'Arquivo inválido: envie um XML.';
        } else {
            // 0) Checa e corrige preâmbulo inválido
            $corr = corrigirPreambleSeNecessario($tmpPath);
            $sourceForParsing = $tmpPath;
            if ($corr['had_preamble']) {
                $resultado['structural_issue'] = true;
                // guarda apenas um trecho curto do "erro" para exibir
                $resultado['preamble_excerpt'] = mb_substr(trim($corr['preamble']), 0, 500);
                $resultado['corrected_xml'] = $corr['clean_content']; // para download via JS
                $sourceForParsing = $corr['clean_temp_path'];          // valida usando o limpo
            }

            $xsdFile = __DIR__ . '/catalogo-crc.xsd';
            if (!file_exists($xsdFile)) {
                $resultado['ok'] = false;
                $resultado['mensagem'] = 'Arquivo XSD não encontrado no servidor.';
            } else {
                $xmlReformatado = reformatXml($sourceForParsing);
                if ($xmlReformatado === false) {
                    $resultado['ok'] = false;
                    $resultado['mensagem'] = 'Não foi possível ler/formatar o XML (arquivo inválido ou corrompido).';
                } else {
                    $mapResult = mapearReferenciasPorLinha($xmlReformatado);
                    $resultado['tipo_ato'] = $mapResult['tipo'];

                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument();

                    if (@$dom->load($xmlReformatado)) {
                        $isValid = @$dom->schemaValidate($xsdFile);
                        if ($isValid) {
                            $resultado['ok'] = true;
                            $resultado['mensagem'] = 'Validação bem-sucedida! O XML está de acordo com o XSD.';
                        } else {
                            $errors = libxml_get_errors();
                            libxml_clear_errors();

                            foreach ($errors as $error) {
                                $linhaErro = (int)$error->line;
                                $registro  = obterReferenciaPorLinha($mapResult, $linhaErro);
                                $mens_en   = trim($error->message);
                                $mens_pt   = traduzirErroDetalhado($mens_en);

                                $resultado['erros'][] = [
                                    'linha'       => $linhaErro,
                                    'registro'    => $registro,
                                    'mensagem_pt' => $mens_pt,
                                    'mensagem_en' => $mens_en
                                ];
                            }
                            usort($resultado['erros'], fn($a,$b)=>$a['linha']<=>$b['linha']);
                            $resultado['ok'] = false;
                            $resultado['mensagem'] = 'Erros encontrados na validação do XML.';
                        }
                    } else {
                        $resultado['ok'] = false;
                        $resultado['mensagem'] = 'Erro ao carregar XML reformatado (arquivo inválido).';
                    }

                    if (file_exists($xmlReformatado)) { @unlink($xmlReformatado); }
                }
            }
        }

        $resultado['arquivo'] = $nomeOriginal;
        $resultado['tamanho'] = $tamanho;
    } else {
        $resultado['ok'] = false;
        $resultado['mensagem'] = 'Por favor, selecione um arquivo XML.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Validação de XML CRC</title>

    <!-- CSS base -->
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">

    <!-- MDI (garantia de ícones) -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css"></noscript>
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">

    <script src="../../script/jquery-3.6.0.min.js"></script>
    <script src="../../script/sweetalert2.js"></script>

    <!-- Tokens de tema (dark/light) + UI -->
    <style>
        body.dark-mode, body:not(.light-mode) {
            --bg:#0b1220; --surface:#0f172a; --surface-2:#111827; --text:#e5e7eb; --muted:#94a3b8;
            --border:#1f2937; --ring:rgba(37,99,235,.35); --primary:#2563eb; --primary-2:#1d4ed8;
            --success-bg:rgba(22,163,74,.12); --success-bd:rgba(22,163,74,.35); --success-fg:#bbf7d0;
            --danger-bg:rgba(220,38,38,.12); --danger-bd:rgba(220,38,38,.35); --danger-fg:#fecaca;
            --shadow:0 10px 30px rgba(0,0,0,.35); --drop-bg:rgba(2,6,23,.5); --drop-bg-hover:rgba(2,6,23,.65);
            --table-head:rgba(17,24,39,.9); --pill-bg:rgba(17,24,39,.65);
        }
        body.light-mode {
            --bg:#f4f7fb; --surface:#ffffff; --surface-2:#f9fafb; --text:#111827; --muted:#6b7280;
            --border:#e5e7eb; --ring:rgba(37,99,235,.25); --primary:#2563eb; --primary-2:#1d4ed8;
            --success-bg:#ecfdf5; --success-bd:#a7f3d0; --success-fg:#065f46;
            --danger-bg:#fef2f2; --danger-bd:#fecaca; --danger-fg:#7f1d1d;
            --shadow:0 8px 24px rgba(0,0,0,.08); --drop-bg:#f8fafc; --drop-bg-hover:#f1f5f9;
            --table-head:#f3f4f6; --pill-bg:#f3f4f6;
        }

        body{ background:var(--bg); }
        #main .container{ max-width: 1100px; }

        .card-modern{ background:var(--surface); border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow); overflow:hidden; }
        .card-modern .card-header{ border-bottom:1px solid var(--border); background:var(--surface); color:var(--text); padding:1.1rem 1.25rem .85rem; }
        .card-modern .card-body{ color:var(--text); padding:1.25rem; background:var(--surface); }
        .muted{ color:var(--muted); }

        .dropzone{ position:relative; border:2px dashed rgba(148,163,184,.35); border-radius:16px; padding:28px; text-align:center; transition:.2s; background:var(--drop-bg); cursor:pointer; }
        .dropzone:hover{ border-color:var(--primary); background:var(--drop-bg-hover); transform:translateY(-1px); box-shadow:0 0 0 6px var(--ring); }
        .dropzone.dragover{ border-color:var(--primary); box-shadow:0 0 0 6px var(--ring); }
        .dropzone .icon{ font-size:48px; margin-bottom:10px; color:var(--muted); }
        .dropzone input[type=file]{ position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
        .file-pill{ display:inline-flex; align-items:center; gap:.5rem; padding:.45rem .8rem; border-radius:999px; border:1px solid var(--border); background:var(--pill-bg); margin-top:.75rem; font-size:.925rem; color:var(--text); }

        .btn-primary{ background:linear-gradient(180deg,var(--primary),var(--primary-2)); border:0; border-radius:12px; padding:.9rem 1.1rem; font-weight:600; color:#fff; }
        .btn-primary:hover{ filter:brightness(1.05); }
        .btn-outline{ border:1px solid var(--border); background:transparent; color:var(--text); border-radius:10px; }
        .btn-outline:hover{ background:rgba(0,0,0,.03); }
        .btn-sm{ padding:.45rem .7rem; border-radius:10px; }

        .alert-modern{ background:var(--success-bg); border:1px solid var(--success-bd); color:var(--success-fg); border-radius:12px; }
        .alert-danger-modern{ background:var(--danger-bg); border:1px solid var(--danger-bd); color:var(--danger-fg); border-radius:12px; }

        .table-errors{ width:100%; border-collapse:collapse; overflow:hidden; border-radius:14px; }
        .table-errors th, .table-errors td{ border-bottom:1px solid var(--border); padding:.8rem .9rem; vertical-align:top; color:var(--text); word-break:break-word; background:var(--surface); }
        .table-errors thead th{ position:sticky; top:0; background:var(--table-head); z-index:1; }
        .badge{ display:inline-block; padding:.2rem .55rem; border-radius:999px; font-size:.78rem; border:1px solid var(--border); background:var(--surface-2); color:var(--text); }
        .scroll-wrap{ max-height:48vh; overflow:auto; border:1px solid var(--border); border-radius:14px; background:var(--surface); }
        .kbd{ border:1px solid var(--border); background:var(--surface-2); border-radius:6px; padding:.05rem .35rem; font-family:ui-monospace,Menlo,Consolas,"Courier New",monospace; font-size:.86rem; color:var(--text); }

        .only-mobile{ display:none; }
        @media (max-width: 768px){
            .dropzone .icon{ font-size:40px; }
            .btn-primary,.btn-outline{ width:100%; }
            .only-desktop{ display:none; }
            .only-mobile{ display:block; }
            .error-card{
                background:var(--surface);
                border:1px solid var(--border);
                border-radius:14px;
                padding:1rem;
                box-shadow:var(--shadow);
                margin-bottom:.75rem;
                color:var(--text);
            }
            .error-card .title{ font-weight:600; margin-bottom:.35rem; }
            .error-card .meta{ font-size:.9rem; color:var(--muted); margin-bottom:.35rem; }
        }
        @media (min-width: 769px){
            .only-desktop{ display:block; }
        }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container py-4">

        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="m-0">
                <i class="mdi mdi-file-xml-outline"></i> Validação de Arquivo XML - CRC
            </h3>
            <span class="muted">
                <?= $resultado['tipo_ato']==='casamento' ? 'Tipo detectado: Casamento' : ($resultado['tipo_ato']==='obito' ? 'Tipo detectado: Óbito' : ($resultado['tipo_ato']==='nascimento' ? 'Tipo detectado: Nascimento' : 'Tipo: não identificado')) ?>
                • Compatível com <span class="kbd">.xml</span>
            </span>
        </div>

        <!-- Upload -->
        <div class="card-modern mb-4">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="mdi mdi-cloud-upload-outline"></i>
                    <strong>Envie o arquivo para validação</strong>
                </div>
                <!-- <small class="muted d-block mt-1">
                    O arquivo será validado contra o <span class="kbd">catalogo-crc.xsd</span> desta instalação.
                </small> -->
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data" id="formValidacao" novalidate>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label muted mb-2">Arraste e solte o XML ou clique para selecionar</label>
                            <div class="dropzone" id="dropzone">
                                <div class="icon"><i class="mdi mdi-tray-arrow-up"></i></div>
                                <div class="h5 mb-1">Solte o arquivo aqui</div>
                                <div class="muted">ou clique para procurar no dispositivo</div>
                                <div id="filePill" class="file-pill d-none">
                                    <i class="mdi mdi-file-xml-outline"></i>
                                    <span id="fileName"></span>
                                    <span class="muted" id="fileSize"></span>
                                </div>
                                <input type="file" id="xmlFile" name="xmlFile" accept=".xml" required />
                            </div>
                            <small class="muted d-block mt-2">Dica: <span class="kbd">Ctrl</span> + <span class="kbd">Enter</span> valida rapidamente.</small>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check-decagram-outline"></i> Validar XML
                            </button>
                            <button type="button" class="btn btn-outline" id="btnLimpar">
                                <i class="mdi mdi-broom"></i> Limpar seleção
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aviso de problema estrutural (preamble antes do ) -->
        <?php if ($resultado['structural_issue'] && $resultado['corrected_xml'] !== null): ?>
            <div class="card-modern mb-4">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-alert"></i>
                        <strong>Conteúdo inválido antes da declaração XML</strong>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-outline btn-sm" id="btnBaixarCorrigido">
                            <i class="mdi mdi-file-download-outline"></i> Baixar XML corrigido
                        </button>
                        <button class="btn btn-outline btn-sm" id="btnCopiarCorrigido">
                            <i class="mdi mdi-content-copy"></i> Copiar XML corrigido
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="muted mb-2">
                        Foi detectado texto/HTML antes de <span class="kbd">&lt;?xml ... ?&gt;</span> (ex.: “PHP Request Startup: Input variables exceeded 1000 ...”).
                        Esse problema não é coberto pela validação XSD. O arquivo foi saneado automaticamente para a validação abaixo.
                    </p>
                    <div class="scroll-wrap" style="max-height:24vh;">
<pre style="margin:0; padding:1rem; color:var(--text); background:var(--surface-2); border-radius:12px; border:1px solid var(--border); white-space:pre-wrap; word-break:break-word;"><?=
    htmlspecialchars($resultado['preamble_excerpt'])
?></pre>
                    </div>
                    <!-- conteúdo corrigido é inserido num textarea oculto para download/cópia -->
                    <textarea id="correctedXmlContent" class="d-none"><?= htmlspecialchars($resultado['corrected_xml']) ?></textarea>
                </div>
            </div>
        <?php endif; ?>

        <!-- RESULTADOS -->
        <?php if (!is_null($resultado['ok'])): ?>
            <?php if ($resultado['ok'] === true): ?>
                <div class="alert alert-modern">
                    <div class="d-flex align-items-start gap-2">
                        <i class="mdi mdi-check-circle-outline" style="font-size:22px;"></i>
                        <div>
                            <div class="fw-bold">Validação bem-sucedida!</div>
                            <div class="muted"><?= htmlspecialchars($resultado['mensagem']) ?></div>
                            <?php if ($resultado['arquivo']): ?>
                                <div class="mt-2">
                                    <span class="badge"><i class="mdi mdi-file-xml-outline"></i> <?= htmlspecialchars($resultado['arquivo']) ?></span>
                                    <span class="badge"><i class="mdi mdi-weight"></i> <?= number_format(($resultado['tamanho'] ?? 0)/1024, 2, ',', '.') ?> KB</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <script>Swal.fire({icon:'success',title:'XML válido!',text:'O XML está de acordo com o XSD.',timer:1800,showConfirmButton:false});</script>
            <?php else: ?>
                <div class="alert alert-danger-modern">
                    <div class="d-flex align-items-start gap-2">
                        <i class="mdi mdi-alert-octagon-outline" style="font-size:22px;"></i>
                        <div>
                            <div class="fw-bold">Erros na validação</div>
                            <div class="muted"><?= htmlspecialchars($resultado['mensagem']) ?></div>
                            <?php if ($resultado['arquivo']): ?>
                                <div class="mt-2">
                                    <span class="badge"><i class="mdi mdi-file-xml-outline"></i> <?= htmlspecialchars($resultado['arquivo']) ?></span>
                                    <span class="badge"><i class="mdi mdi-weight"></i> <?= number_format(($resultado['tamanho'] ?? 0)/1024, 2, ',', '.') ?> KB</span>
                                    <span class="badge"><i class="mdi mdi-counter"></i> <?= count($resultado['erros']) ?> erro(s)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE ERROS (Desktop: tabela | Mobile: cards) -->
                <div class="card-modern mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="mdi mdi-format-list-text"></i>
                            <strong>Lista de erros de validação</strong>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline btn-sm" id="btnCopiarErros"><i class="mdi mdi-content-copy"></i> Copiar</button>
                            <button class="btn btn-outline btn-sm" id="btnBaixarErros"><i class="mdi mdi-download"></i> Baixar TXT</button>
                            <button class="btn btn-outline btn-sm" id="btnMostrarOriginal"><i class="mdi mdi-translate"></i> Ver mensagens originais</button>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Desktop (tabela) -->
                        <div class="only-desktop">
                            <div class="scroll-wrap">
                                <table class="table-errors">
                                    <thead>
                                        <tr>
                                            <th style="width:90px;">Linha</th>
                                            <th style="width:35%;">
                                                <?php
                                                echo $resultado['tipo_ato']==='casamento' ? 'Referência (NOMECONJUGE1 & NOMECONJUGE2)'
                                                    : ($resultado['tipo_ato']==='obito' ? 'Referência (NOMEFALECIDO)'
                                                    : 'Referência (NOMEREGISTRADO)');
                                                ?>
                                            </th>
                                            <th>Mensagem (PT-BR)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultado['erros'] as $e): ?>
                                            <tr>
                                                <td><span class="kbd"><?= (int)$e['linha'] ?></span></td>
                                                <td><?= $e['registro'] ? htmlspecialchars($e['registro']) : '<span class="muted">—</span>' ?></td>
                                                <td><?= htmlspecialchars($e['mensagem_pt']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="muted d-block mt-3">Dica: clique no cabeçalho e use <span class="kbd">Ctrl</span> + <span class="kbd">C</span> para copiar.</small>
                        </div>

                        <!-- Mobile (cards) -->
                        <div class="only-mobile">
                            <?php foreach ($resultado['erros'] as $e): ?>
                                <div class="error-card">
                                    <div class="title"><i class="mdi mdi-alert-circle-outline"></i> Linha <?= (int)$e['linha'] ?></div>
                                    <div class="meta">
                                        <i class="mdi mdi-account-badge"></i>
                                        <?php
                                        $label = ($resultado['tipo_ato']==='casamento') ? 'NOMECONJUGE1/NOMECONJUGE2'
                                                : ($resultado['tipo_ato']==='obito' ? 'NOMEFALECIDO' : 'NOMEREGISTRADO');
                                        echo "<strong>$label:</strong> " . ($e['registro'] ? htmlspecialchars($e['registro']) : '<span class="muted">—</span>');
                                        ?>
                                    </div>
                                    <div><i class="mdi mdi-information-outline"></i> <?= htmlspecialchars($e['mensagem_pt']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Mensagens originais (toggle) -->
                        <div id="originaisWrap" class="mt-3" style="display:none;">
                            <div class="muted mb-2"><i class="mdi mdi-translate-variant"></i> Mensagens originais do validador (inglês):</div>
                            <div class="scroll-wrap" style="max-height:32vh;">
<pre style="margin:0; padding:1rem; color:var(--text); background:var(--surface-2); border-radius:12px; border:1px solid var(--border); white-space:pre-wrap; word-break:break-word;"><?php
$linhasOut = [];
foreach ($resultado['erros'] as $e) {
    $reg = $e['registro'] ? "Registro: \"{$e['registro']}\" - " : "";
    $linhasOut[] = "{$reg}Linha {$e['linha']} => {$e['mensagem_en']}";
}
echo htmlspecialchars(implode(PHP_EOL, $linhasOut));
?></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    Swal.fire({icon:'error', title:'Validação falhou', text:'Foram encontrados <?= count($resultado['erros']) ?> erro(s).', timer:2200, showConfirmButton:false});
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Ajuda rápida -->
        <div class="card-modern">
            <div class="card-header"><strong><i class="mdi mdi-help-circle-outline"></i> Ajuda rápida</strong></div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Use arquivos <span class="kbd">.xml</span> exportados no padrão exigido pela CRC.</li>
                    <li>As linhas exibidas referem-se ao XML reformatado (indentado) para facilitar a leitura.</li>
                    <li>A referência exibida varia conforme o tipo de ato detectado: Casamento (NOMECONJUGE1/NOMECONJUGE2), Nascimento (NOMEREGISTRADO) e Óbito (NOMEFALECIDO).</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<?php include(__DIR__ . '/../../rodape.php'); ?>

<script>
(function(){
    const drop = document.getElementById('dropzone');
    const input = document.getElementById('xmlFile');
    const pill = document.getElementById('filePill');
    const nameEl = document.getElementById('fileName');
    const sizeEl = document.getElementById('fileSize');
    const btnLimpar = document.getElementById('btnLimpar');
    const form = document.getElementById('formValidacao');

    function humanSize(bytes){
        if (!bytes && bytes !== 0) return '';
        const units = ['B','KB','MB','GB'];
        let i=0,n=bytes;
        while(n>=1024 && i<units.length-1){ n/=1024; i++; }
        return n.toFixed((i===0)?0:2).replace('.', ',')+' '+units[i];
    }
    function showFileInfo(file){
        if (!file){ pill.classList.add('d-none'); return; }
        nameEl.textContent=file.name; sizeEl.textContent='('+humanSize(file.size)+')';
        pill.classList.remove('d-none');
    }

    ['dragenter','dragover'].forEach(evt=>{
        drop.addEventListener(evt, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(evt=>{
        drop.addEventListener(evt, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('dragover'); });
    });
    drop.addEventListener('drop',(e)=>{
        if(e.dataTransfer.files && e.dataTransfer.files.length){
            const f = e.dataTransfer.files[0];
            if(!f.name.toLowerCase().endsWith('.xml')){
                Swal.fire({icon:'error',title:'Arquivo inválido',text:'Selecione um arquivo .xml'}); return;
            }
            input.files = e.dataTransfer.files; showFileInfo(f);
        }
    });
    input.addEventListener('change', ()=>{
        const f = input.files && input.files[0]; if(!f) return;
        if(!f.name.toLowerCase().endsWith('.xml')){
            input.value=''; Swal.fire({icon:'error',title:'Arquivo inválido',text:'Selecione um arquivo .xml'}); return;
        }
        showFileInfo(f);
    });
    btnLimpar.addEventListener('click', ()=>{ input.value=''; pill.classList.add('d-none'); });

    document.addEventListener('keydown', (e)=>{
        if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='enter'){ e.preventDefault(); form.requestSubmit(); }
    });

    const btnCopiar = document.getElementById('btnCopiarErros');
    const btnBaixar = document.getElementById('btnBaixarErros');
    const btnOriginal= document.getElementById('btnMostrarOriginal');
    const origWrap  = document.getElementById('originaisWrap');

    if(btnOriginal && origWrap){
        btnOriginal.addEventListener('click', ()=>{
            const vis = origWrap.style.display !== 'none';
            origWrap.style.display = vis ? 'none' : 'block';
            btnOriginal.innerHTML = vis ? '<i class="mdi mdi-translate"></i> Ver mensagens originais'
                                        : '<i class="mdi mdi-translate"></i> Ocultar mensagens originais';
        });
    }

    function coletarErrosComoTexto(){
        const desktopRows = document.querySelectorAll('.only-desktop .table-errors tbody tr');
        const useDesktop = desktopRows.length > 0;
        let out = [];
        if (useDesktop){
            desktopRows.forEach(tr=>{
                const tds = tr.querySelectorAll('td');
                const linha = tds[0]?.innerText?.trim() || '';
                const registro = tds[1]?.innerText?.trim() || '';
                const msg = tds[2]?.innerText?.trim() || '';
                out.push(`Linha ${linha}${registro && registro !== '—' ? ` | Referência: "${registro}"` : ''} => ${msg}`);
            });
        } else {
            document.querySelectorAll('.only-mobile .error-card').forEach(card=>{
                const linha = card.querySelector('.title')?.innerText?.replace(/[^0-9]/g,'') || '';
                const meta  = card.querySelector('.meta')?.innerText?.trim() || '';
                const msg   = card.querySelector('div:last-child')?.innerText?.trim() || '';
                out.push(`Linha ${linha} | ${meta} => ${msg}`);
            });
        }
        return out.join('\n');
    }

    if(btnCopiar){
        btnCopiar.addEventListener('click', ()=>{
            const txt = coletarErrosComoTexto();
            if(!txt){ return; }
            navigator.clipboard.writeText(txt).then(()=>{
                Swal.fire({icon:'success',title:'Copiado!',text:'Lista de erros copiada.',timer:1500,showConfirmButton:false});
            });
        });
    }
    if(btnBaixar){
        btnBaixar.addEventListener('click', ()=>{
            const txt = coletarErrosComoTexto(); if(!txt) return;
            const blob = new Blob([txt], {type:'text/plain;charset=utf-8'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href=url; a.download='erros_validacao_crc.txt';
            document.body.appendChild(a); a.click();
            setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); }, 200);
        });
    }

    // ====== Baixar/Copiar XML corrigido (apenas se existir) ======
    const btnBaixarCorrigido = document.getElementById('btnBaixarCorrigido');
    const btnCopiarCorrigido = document.getElementById('btnCopiarCorrigido');
    const correctedField     = document.getElementById('correctedXmlContent');

    if (btnBaixarCorrigido && correctedField) {
        btnBaixarCorrigido.addEventListener('click', ()=>{
            const content = correctedField.value || '';
            if (!content) return;
            const blob = new Blob([content], {type:'application/xml;charset=utf-8'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'xml_corrigido.xml';
            document.body.appendChild(a); a.click();
            setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); }, 200);
        });
    }
    if (btnCopiarCorrigido && correctedField) {
        btnCopiarCorrigido.addEventListener('click', ()=>{
            const content = correctedField.value || '';
            if (!content) return;
            navigator.clipboard.writeText(content).then(()=>{
                Swal.fire({icon:'success',title:'Copiado!',text:'XML corrigido copiado para a área de transferência.',timer:1500,showConfirmButton:false});
            });
        });
    }
})();
</script>
</body>
</html>
