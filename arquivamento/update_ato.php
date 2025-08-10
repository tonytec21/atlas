<?php
include(__DIR__ . '/session_check.php');
checkSession();

header('Content-Type: application/json; charset=utf-8');

function respond($payload, $code = 200) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(['status' => 'error', 'message' => 'Método não permitido'], 405);
}

/* -------------------- Entrada -------------------- */
$id           = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
$atribuicao   = $_POST['atribuicao'] ?? '';
$categoria    = $_POST['categoria'] ?? '';
$data_ato     = $_POST['data_ato'] ?? '';
$livro        = $_POST['livro'] ?? '';
$folha        = $_POST['folha'] ?? '';
$termo        = $_POST['termo'] ?? '';
$protocolo    = $_POST['protocolo'] ?? '';
$matricula    = $_POST['matricula'] ?? '';
$descricao    = $_POST['descricao'] ?? '';
$partes_json  = $_POST['partes_envolvidas'] ?? '[]';
$partes       = json_decode($partes_json, true);
if (!is_array($partes)) $partes = [];

if ($id === '' || !preg_match('/^\d+$/', $id)) {
  respond(['status' => 'error', 'message' => 'ID ausente ou inválido'], 400);
}

/* -------------------- Pastas/arquivos -------------------- */
$BASE_DIR   = __DIR__;
$META_DIR   = $BASE_DIR . '/meta-dados';
$FILES_DIR  = $BASE_DIR . "/arquivos/$id";
$META_FILE  = $META_DIR . "/$id.json";

if (!is_dir($META_DIR))  @mkdir($META_DIR, 0775, true);
if (!is_dir($FILES_DIR)) @mkdir($FILES_DIR, 0775, true);

/* -------------------- Utilitários -------------------- */
function sanitize_filename($name) {
  // mantém letras/números/.-_ e espaços; troca o resto por _
  $name = preg_replace('/[^\w.\- ]+/u', '_', $name);
  // compacta espaços
  return trim(preg_replace('/\s+/', ' ', $name));
}

function unique_name($dir, $name) {
  $pi   = pathinfo($name);
  $base = $pi['filename'] ?? 'arquivo';
  $ext  = isset($pi['extension']) && $pi['extension'] !== '' ? ('.'.$pi['extension']) : '';
  $try  = $base . $ext;
  $i = 1;
  while (file_exists("$dir/$try")) {
    $try = $base . " ($i)" . $ext;
    $i++;
  }
  return $try;
}

/* -------------------- Carrega JSON existente -------------------- */
$existing = [];
if (file_exists($META_FILE)) {
  $decoded = json_decode(@file_get_contents($META_FILE), true);
  if (is_array($decoded)) $existing = $decoded;
}
$existing_anexos        = isset($existing['anexos']) && is_array($existing['anexos']) ? $existing['anexos'] : [];
$existing_anexos_tarefa = isset($existing['anexos_tarefa']) && is_array($existing['anexos_tarefa']) ? $existing['anexos_tarefa'] : []; // <-- preserva

/* -------------------- Remoção de anexos -------------------- */
/* Observação: só removemos anexos do próprio arquivamento.
   Anexos que vieram das tarefas (anexos_tarefa) são preservados,
   pois não há ação de remoção para eles na tela. */
$to_remove = $_POST['files_to_remove'] ?? '[]';
$to_remove = json_decode($to_remove, true);
if (!is_array($to_remove)) $to_remove = [];

if ($to_remove) {
  // remove do array e do disco
  foreach ($to_remove as $rel) {
    // segurança: só permite remover dentro de "arquivos/<id>/..."
    $prefix = "arquivos/$id/";
    if (strpos($rel, $prefix) !== 0) continue;

    // remove da lista (somente de $existing_anexos)
    $key = array_search($rel, $existing_anexos, true);
    if ($key !== false) unset($existing_anexos[$key]);

    // remove do disco
    $full = $BASE_DIR . '/' . $rel;
    if (is_file($full)) @unlink($full);
  }
  // reindexa
  $existing_anexos = array_values($existing_anexos);
}

/* -------------------- Upload de novos anexos -------------------- */
$new_anexos = [];
if (isset($_FILES['file-input']) && is_array($_FILES['file-input']['name'])) {
  $names = $_FILES['file-input']['name'];
  $tmps  = $_FILES['file-input']['tmp_name'];
  $errs  = $_FILES['file-input']['error'];

  foreach ($names as $i => $origName) {
    if (!isset($tmps[$i])) continue;
    if (isset($errs[$i]) && $errs[$i] !== UPLOAD_ERR_OK) continue;
    $safe = sanitize_filename($origName);
    if ($safe === '') $safe = 'arquivo';
    $safe = unique_name($FILES_DIR, $safe);
    $dest = $FILES_DIR . '/' . $safe;

    if (@move_uploaded_file($tmps[$i], $dest)) {
      // guarda caminho relativo para a web, que seu front usa
      $new_anexos[] = "arquivos/$id/$safe";
    }
  }
}

/* -------------------- Monta objeto final -------------------- */
$all_anexos = array_values(array_merge($existing_anexos, $new_anexos));

// trilha de modificações
if (!isset($existing['modificacoes']) || !is_array($existing['modificacoes'])) {
  $existing['modificacoes'] = [];
}
$existing['modificacoes'][] = [
  'usuario'   => $_SESSION['username'] ?? 'sistema',
  'data_hora' => date('Y-m-d H:i:s')
];

$ato = [
  'id'                => $id,
  'atribuicao'        => $atribuicao,
  'categoria'         => $categoria,
  'data_ato'          => $data_ato,
  'livro'             => $livro,
  'folha'             => $folha,
  'termo'             => $termo,
  'protocolo'         => $protocolo,
  'matricula'         => $matricula,
  'descricao'         => $descricao,
  'partes_envolvidas' => $partes,
  'anexos'            => $all_anexos,                // continua salvando anexos locais
  'anexos_tarefa'     => $existing_anexos_tarefa,    // <-- preserva os anexos vindos das tarefas
  // preserva cadastro original se existir
  'cadastrado_por'    => $existing['cadastrado_por'] ?? ($_SESSION['username'] ?? 'sistema'),
  'data_cadastro'     => $existing['data_cadastro']  ?? date('Y-m-d H:i:s'),
  'modificacoes'      => $existing['modificacoes']
];

/* -------------------- Salva JSON -------------------- */
$ok = @file_put_contents($META_FILE, json_encode($ato, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
if ($ok === false) {
  respond(['status' => 'error', 'message' => 'Falha ao salvar metadados. Verifique permissões.'], 500);
}

respond(['status' => 'success']);
