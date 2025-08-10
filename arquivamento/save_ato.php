<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
    exit;
}

$id = time();
$filePath = "meta-dados/$id.json";
$uploadDir = "arquivos/$id/";

// Garante diretório de upload
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'sistema';
$creationTime = date('Y-m-d H:i:s');

// Helper simples para sanitização de texto
function sanitize_text($v){ return is_string($v) ? trim($v) : ''; }

// Partes envolvidas (aceita vazio)
$partes = [];
if (isset($_POST['partes_envolvidas'])) {
    $tmp = json_decode($_POST['partes_envolvidas'], true);
    if (is_array($tmp)) $partes = $tmp;
}

$dados = [
    'id' => $id,
    'atribuicao' => sanitize_text($_POST['atribuicao'] ?? ''),
    'categoria' => sanitize_text($_POST['categoria'] ?? ''),
    'data_ato' => sanitize_text($_POST['data_ato'] ?? ''),
    'livro' => sanitize_text($_POST['livro'] ?? ''),
    'folha' => sanitize_text($_POST['folha'] ?? ''),
    'termo' => sanitize_text($_POST['termo'] ?? ''),
    'protocolo' => sanitize_text($_POST['protocolo'] ?? ''),
    'matricula' => sanitize_text($_POST['matricula'] ?? ''),
    'descricao' => sanitize_text($_POST['descricao'] ?? ''),
    'partes_envolvidas' => $partes,
    'cadastrado_por' => $username,
    'data_cadastro' => $creationTime
];

$anexos = [];
$anexos_tarefa = []; // <-- Somente referências de anexos vindos das tarefas

/* -------- Normalização / Resolução de caminho -------- */
function normalize_relpath($p) {
    if (!is_string($p) || $p === '') return '';
    $p = str_replace('\\','/',$p);
    $pathOnly = parse_url($p, PHP_URL_PATH);
    if ($pathOnly === null || $pathOnly === false) $pathOnly = $p;
    $decoded  = urldecode($pathOnly);
    $decoded = preg_replace('~/+~','/',$decoded);
    $rel      = ltrim($decoded, "/\\");
    return $rel;
}

// Retorna referência a partir de "arquivos/..." quando possível
function ref_rel_arquivos($p) {
    $rel = normalize_relpath($p);
    $pos = strpos($rel, 'arquivos/');
    if ($pos !== false) {
        return substr($rel, $pos); // ex.: "arquivos/0dfb.../6694.pdf"
    }
    return $rel;
}

function resolve_source_path($raw) {
    if (!is_string($raw) || $raw === '') return false;

    $raw = str_replace('\\','/',$raw);
    $pathOnly = parse_url($raw, PHP_URL_PATH);
    if ($pathOnly === null || $pathOnly === false) $pathOnly = $raw;
    $pathOnly = preg_replace('~/+~','/',$pathOnly);

    // Se já é caminho absoluto de filesystem
    if (preg_match('~^([a-zA-Z]:/|/)~', $pathOnly) && file_exists($pathOnly)) {
        $rp = realpath($pathOnly);
        if ($rp !== false) return $rp;
    }

    $rel = ltrim($pathOnly, '/');
    $projectRoot = realpath(__DIR__ . '/..');
    $docRoot     = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    $candidates = [];
    if ($docRoot)     $candidates[] = $docRoot . '/' . $rel;
    if ($projectRoot) $candidates[] = $projectRoot . '/' . $rel;
    if ($projectRoot) $candidates[] = $projectRoot . '/' . ltrim($pathOnly,'/');

    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $rp = realpath($c);
            if ($rp !== false) return $rp;
        }
    }

    if (file_exists($rel)) {
        $rp = realpath($rel);
        if ($rp !== false) return $rp;
    }

    return false;
}

/* -------- 1) Copiar anexos já existentes (existing_files[]) -------- */
$existingFiles = [];
if (isset($_POST['existing_files'])) {
    $existingFiles = $_POST['existing_files'];
    if (!is_array($existingFiles)) $existingFiles = [$existingFiles];
}

if (!empty($existingFiles)) {
    foreach ($existingFiles as $item) {
        if (!is_string($item) || $item === '') continue;

        // Sempre salva SOMENTE em "anexos_tarefa"
        $refForJson = ref_rel_arquivos($item);
        if ($refForJson !== '') {
            $anexos_tarefa[] = $refForJson;
        }

        // Mantém o comportamento de copiar o arquivo para a pasta do ato,
        // mas NÃO adiciona nada em $anexos (evita duplicidade no JSON).
        $source = resolve_source_path($item);
        if ($source === false || !is_file($source) || !is_readable($source)) {
            // Apenas loga; nada é incluído em "anexos"
            error_log("[arquivamento] Referência de tarefa não copiada (não resolvida): {$item}");
            continue;
        }

        $baseName = basename($source);
        $dest     = $uploadDir . $baseName;

        if (file_exists($dest)) {
            $pi   = pathinfo($baseName);
            $name = $pi['filename'];
            $ext  = isset($pi['extension']) ? '.' . $pi['extension'] : '';
            $n = 1;
            do { $dest = $uploadDir . $name . " ($n)" . $ext; $n++; } while (file_exists($dest));
        }

        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

        if (!@copy($source, $dest)) {
            error_log("[arquivamento] Falha ao copiar de {$source} para {$dest} (anexo de tarefa).");
        }
        // IMPORTANTE: não adicionar $dest em $anexos para não duplicar no JSON
    }
}

/* -------- 2) Adicionar novos uploads -------- */
if (!empty($_FILES['file-input']['name'][0])) {
    $fileCount = count($_FILES['file-input']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = basename($_FILES['file-input']['name'][$i]);
        $targetFilePath = $uploadDir . $fileName;

        if (file_exists($targetFilePath)) {
            $pi   = pathinfo($fileName);
            $name = $pi['filename'];
            $ext  = isset($pi['extension']) ? '.' . $pi['extension'] : '';
            $n = 1;
            do { $targetFilePath = $uploadDir . $name . " ($n)" . $ext; $n++; } while (file_exists($targetFilePath));
        }

        if (move_uploaded_file($_FILES['file-input']['tmp_name'][$i], $targetFilePath)) {
            $anexos[] = $targetFilePath; // uploads novos continuam em "anexos"
        } else {
            error_log("[arquivamento] Falha ao mover upload tmp para {$targetFilePath}");
        }
    }
}

$dados['anexos'] = $anexos;
$dados['anexos_tarefa'] = $anexos_tarefa; // Somente anexos vindos das tarefas

// Salva metadados
@mkdir(dirname($filePath), 0777, true);
file_put_contents($filePath, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Resposta
echo json_encode(['status' => 'success', 'redirect' => "edit_ato.php?id=$id"]);
