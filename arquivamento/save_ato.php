<?php
/**
 * Legado: criação de arquivamento a partir de outros módulos (Tarefas).
 * Mantém o contrato antigo: POST multipart com file-input[] e existing_files[],
 * resposta {status:'success', redirect:'...'}.
 */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();
arq_compat_avisar('save_ato.php');

if (!arq_limite_taxa('save_legado', 60, 60)) {
    echo json_encode(['status' => 'error', 'message' => 'Muitas gravações em sequência.']);
    exit;
}

$id = (string) time();
while (is_file(arq_dir_meta() . '/' . $id . '.json')) { $id = (string) ((int) $id + 1); }

function legado_txt($k, $max = 255) {
    $v = isset($_POST[$k]) ? (string) $_POST[$k] : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
    return mb_substr(trim($v), 0, $max);
}

$partes = [];
$brutas = json_decode(isset($_POST['partes_envolvidas']) ? $_POST['partes_envolvidas'] : '[]', true);
if (is_array($brutas)) {
    foreach ($brutas as $p) {
        if (!is_array($p)) { continue; }
        $partes[] = [
            'cpf'   => preg_replace('/\D/', '', isset($p['cpf']) ? $p['cpf'] : ''),
            'nome'  => mb_substr(trim(isset($p['nome']) ? $p['nome'] : ''), 0, 150),
            'papel' => '',
        ];
    }
}

$anexosTarefa = [];
if (!empty($_POST['existing_files'])) {
    $lista = is_array($_POST['existing_files']) ? $_POST['existing_files'] : [$_POST['existing_files']];
    foreach ($lista as $ref) {
        $ref = str_replace('\\', '/', (string) $ref);
        $pos = strpos($ref, 'arquivos/');
        if ($pos !== false) { $ref = substr($ref, $pos); }
        if ($ref === '') { continue; }
        $anexosTarefa[] = $ref;
        arq_importar_referencia($ref, $id);
    }
    $anexosTarefa = array_values(array_unique($anexosTarefa));
}

$up = arq_processar_uploads('file-input', $id, count($anexosTarefa));

$registro = [
    'id'                => (int) $id,
    'atribuicao'        => legado_txt('atribuicao', 80),
    'categoria'         => legado_txt('categoria', 120),
    'data_ato'          => legado_txt('data_ato', 10),
    'livro'             => legado_txt('livro', 30),
    'folha'             => legado_txt('folha', 30),
    'termo'             => legado_txt('termo', 40),
    'protocolo'         => legado_txt('protocolo', 40),
    'matricula'         => legado_txt('matricula', 40),
    'descricao'         => mb_substr((string) (isset($_POST['descricao']) ? $_POST['descricao'] : ''), 0, 4000),
    'partes_envolvidas' => $partes,
    'anexos'            => $up['anexos'],
    'anexos_tarefa'     => $anexosTarefa,
    'cadastrado_por'    => arq_usuario(),
    'data_cadastro'     => date('Y-m-d H:i:s'),
    'modificacoes'      => [[
        'usuario'   => arq_usuario(),
        'data_hora' => date('Y-m-d H:i:s'),
        'acao'      => 'cadastro',
        'ip'        => arq_ip(),
    ]],
];

header('Content-Type: application/json; charset=utf-8');
if (!arq_salvar_ato($id, $registro)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Falha ao gravar os metadados.']);
    exit;
}

arq_auditar('criar', $id, ['origem' => 'legado', 'avisos' => $up['erros']]);
echo json_encode([
    'status'   => 'success',
    'redirect' => 'cadastro.php?id=' . $id,
    'id'       => $id,
    'avisos'   => $up['erros'],
]);
