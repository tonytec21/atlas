<?php
/**
 * API · Criação e edição de arquivamento.
 * POST multipart. Sem "id" cria; com "id" atualiza.
 */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();
arq_exige_post_seguro();

if (!arq_limite_taxa('salvar', 120, 60)) {
    arq_erro('Muitas gravações em sequência. Aguarde um instante.', 429);
}

/* ------------------------------------------------------------------ *
 * Entrada
 * ------------------------------------------------------------------ */
function post_txt($chave, $max = 255)
{
    $v = isset($_POST[$chave]) ? (string) $_POST[$chave] : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
    $v = trim(preg_replace('/[ \t]+/u', ' ', $v));
    return mb_substr($v, 0, $max);
}

$id        = arq_id_valido(isset($_POST['id']) ? $_POST['id'] : '');
$editando  = $id !== '';

$atribuicao = post_txt('atribuicao', 80);
$categoria  = post_txt('categoria', 120);
$data_ato   = post_txt('data_ato', 10);
$descricao  = mb_substr(trim((string) (isset($_POST['descricao']) ? $_POST['descricao'] : '')), 0, 4000);

/* Validações -------------------------------------------------------- */
$erros = [];

if ($atribuicao === '' || !in_array($atribuicao, arq_atribuicoes(), true)) {
    $erros[] = 'Selecione uma atribuição válida.';
}
if ($categoria === '') {
    $erros[] = 'Informe a categoria.';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ato)) {
    $erros[] = 'Informe a data do ato no formato correto.';
} else {
    list($ano, $mes, $dia) = array_map('intval', explode('-', $data_ato));
    if (!checkdate($mes, $dia, $ano)) {
        $erros[] = 'A data do ato não existe no calendário.';
    } elseif ($data_ato > date('Y-m-d')) {
        $erros[] = 'A data do ato não pode ser futura.';
    } elseif ($ano < 1850) {
        $erros[] = 'A data do ato é anterior ao limite aceito (1850).';
    }
}

/* Partes envolvidas ------------------------------------------------- */
$partes = [];
$brutas = json_decode(isset($_POST['partes_envolvidas']) ? $_POST['partes_envolvidas'] : '[]', true);
if (is_array($brutas)) {
    foreach ($brutas as $p) {
        if (!is_array($p)) { continue; }
        $nome = trim(preg_replace('/\s+/u', ' ', (string) (isset($p['nome']) ? $p['nome'] : '')));
        $cpf  = preg_replace('/\D/', '', (string) (isset($p['cpf']) ? $p['cpf'] : ''));
        $papel= trim((string) (isset($p['papel']) ? $p['papel'] : ''));
        if ($nome === '' && $cpf === '') { continue; }
        if ($cpf !== '' && !in_array(strlen($cpf), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ inválido em "' . mb_substr($nome, 0, 40) . '".';
            continue;
        }
        $partes[] = [
            'cpf'   => $cpf,
            'nome'  => mb_strtoupper(mb_substr($nome, 0, 150), 'UTF-8'),
            'papel' => mb_substr($papel, 0, 40),
        ];
        if (count($partes) >= 50) { break; }
    }
}
if (empty($partes)) {
    $erros[] = 'Adicione ao menos uma parte envolvida.';
}

if ($erros) { arq_erro(implode(' ', $erros), 422, ['erros' => $erros]); }

/* ------------------------------------------------------------------ *
 * Carrega o registro existente (edição) ou cria um novo id
 * ------------------------------------------------------------------ */
$anteriores        = [];
$anexosAcervo      = [];
$anexosTarefa      = [];
$cadastradoPor     = arq_usuario();
$dataCadastro      = date('Y-m-d H:i:s');
$modificacoes      = [];

if ($editando) {
    $arquivoMeta = arq_caminho_meta($id);
    if ($arquivoMeta === false || !is_file($arquivoMeta)) {
        arq_erro('Arquivamento não encontrado.', 404);
    }
    $anteriores = json_decode(@file_get_contents($arquivoMeta), true);
    if (!is_array($anteriores)) { $anteriores = []; }

    $anexosAcervo  = isset($anteriores['anexos']) && is_array($anteriores['anexos']) ? $anteriores['anexos'] : [];
    $anexosTarefa  = isset($anteriores['anexos_tarefa']) && is_array($anteriores['anexos_tarefa']) ? $anteriores['anexos_tarefa'] : [];
    $cadastradoPor = isset($anteriores['cadastrado_por']) ? $anteriores['cadastrado_por'] : arq_usuario();
    $dataCadastro  = isset($anteriores['data_cadastro']) ? $anteriores['data_cadastro'] : $dataCadastro;
    $modificacoes  = isset($anteriores['modificacoes']) && is_array($anteriores['modificacoes']) ? $anteriores['modificacoes'] : [];
} else {
    // id = timestamp; garante unicidade mesmo em gravações simultâneas.
    $id = (string) time();
    while (is_file(arq_dir_meta() . '/' . $id . '.json')) { $id = (string) ((int) $id + 1); }
}

/* ------------------------------------------------------------------ *
 * Remoção de anexos (por índice na lista normalizada)
 * ------------------------------------------------------------------ */
$removidos = [];
$paraRemover = json_decode(isset($_POST['remover']) ? $_POST['remover'] : '[]', true);
if (is_array($paraRemover) && $paraRemover) {
    $indices = array_map('intval', $paraRemover);
    rsort($indices); // remove de trás para frente, preservando as posições
    $nAcervo = count($anexosAcervo);
    foreach ($indices as $idx) {
        if ($idx < 0) { continue; }
        if ($idx < $nAcervo) {
            $item = $anexosAcervo[$idx];
            $ref  = is_array($item) ? (isset($item['ref']) ? $item['ref'] : '') : (string) $item;
            $abs  = arq_resolver_anexo($ref);
            // Só apaga do disco arquivos que pertencem a este arquivamento.
            if ($abs !== false && strpos(str_replace('\\', '/', $ref), 'arquivos/' . $id . '/') === 0) {
                @unlink($abs);
            }
            $removidos[] = basename($ref);
            unset($anexosAcervo[$idx]);
        } else {
            $j = $idx - $nAcervo;
            if (isset($anexosTarefa[$j])) {
                $ref = is_array($anexosTarefa[$j]) ? (isset($anexosTarefa[$j]['ref']) ? $anexosTarefa[$j]['ref'] : '') : (string) $anexosTarefa[$j];
                $removidos[] = basename($ref);
                unset($anexosTarefa[$j]); // referência externa: só desvincula
            }
        }
    }
    $anexosAcervo = array_values($anexosAcervo);
    $anexosTarefa = array_values($anexosTarefa);
}

/* ------------------------------------------------------------------ *
 * Novos uploads
 * ------------------------------------------------------------------ */
$up = arq_processar_uploads('file-input', $id, count($anexosAcervo) + count($anexosTarefa));
foreach ($up['anexos'] as $a) { $anexosAcervo[] = $a; }

/* Importação de anexos já existentes no servidor (módulo de tarefas) */
if (!empty($_POST['existing_files'])) {
    $lista = is_array($_POST['existing_files']) ? $_POST['existing_files'] : [$_POST['existing_files']];
    foreach ($lista as $ref) {
        $ref = str_replace('\\', '/', (string) $ref);
        $pos = strpos($ref, 'arquivos/');
        if ($pos !== false) { $ref = substr($ref, $pos); }
        if ($ref === '') { continue; }
        // mantém a referência (compatibilidade) e traz uma cópia para o acervo
        $anexosTarefa[] = $ref;
        $copia = arq_importar_referencia($ref, $id);
        if ($copia === null) {
            $up['erros'][] = basename($ref) . ': referência de tarefa não pôde ser copiada';
        }
    }
    $anexosTarefa = array_values(array_unique($anexosTarefa));
}

/* ------------------------------------------------------------------ *
 * Trilha de modificações
 * ------------------------------------------------------------------ */
$modificacoes[] = [
    'usuario'   => arq_usuario(),
    'data_hora' => date('Y-m-d H:i:s'),
    'acao'      => $editando ? 'edicao' : 'cadastro',
    'ip'        => arq_ip(),
];
if (count($modificacoes) > 200) {
    $modificacoes = array_slice($modificacoes, -200);
}

$registro = [
    'id'                => (int) $id,
    'atribuicao'        => $atribuicao,
    'categoria'         => $categoria,
    'data_ato'          => $data_ato,
    'livro'             => post_txt('livro', 30),
    'folha'             => post_txt('folha', 30),
    'termo'             => post_txt('termo', 40),
    'protocolo'         => post_txt('protocolo', 40),
    'matricula'         => post_txt('matricula', 40),
    'descricao'         => $descricao,
    'partes_envolvidas' => $partes,
    'anexos'            => array_values($anexosAcervo),
    'anexos_tarefa'     => array_values($anexosTarefa),
    'cadastrado_por'    => $cadastradoPor,
    'data_cadastro'     => $dataCadastro,
    'modificacoes'      => $modificacoes,
];

if (!arq_salvar_ato($id, $registro)) {
    arq_erro('Falha ao gravar os metadados. Verifique as permissões da pasta meta-dados.', 500);
}

arq_auditar($editando ? 'editar' : 'criar', $id, [
    'anexos_novos'    => array_column($up['anexos'], 'nome'),
    'anexos_removidos'=> $removidos,
]);

arq_ok([
    'id'       => $id,
    'editando' => $editando,
    'avisos'   => $up['erros'],
    'mensagem' => $editando ? 'Arquivamento atualizado.' : 'Arquivamento cadastrado.',
]);
