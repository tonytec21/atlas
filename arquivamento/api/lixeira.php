<?php
/**
 * API · Lixeira.
 *   GET  ?acao=listar
 *   POST acao=excluir|restaurar|expurgar  (id)
 */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();

$acao = isset($_REQUEST['acao']) ? (string) $_REQUEST['acao'] : '';

/* ---------------------------------------------------------------- *
 * Listagem
 * ---------------------------------------------------------------- */
if ($acao === 'listar') {
    arq_ok([
        'itens'      => arq_listar_lixeira(),
        'retencao'   => ARQ_LIXEIRA_DIAS,
        'pode_expurgar' => arq_pode_expurgar(),
    ]);
}

/* ---------------------------------------------------------------- *
 * Operações de escrita
 * ---------------------------------------------------------------- */
arq_exige_post_seguro();

$id = arq_id_valido(isset($_POST['id']) ? $_POST['id'] : '');
if ($id === '') { arq_erro('Identificador inválido.', 400); }

$metaAtivo  = arq_dir_meta()    . DIRECTORY_SEPARATOR . $id . '.json';
$metaLixo   = arq_dir_lixeira() . DIRECTORY_SEPARATOR . $id . '.json';
$pastaAtiva = arq_dir_arquivos() . DIRECTORY_SEPARATOR . $id;
$pastaLixo  = arq_dir_lixeira()  . DIRECTORY_SEPARATOR . $id;

switch ($acao) {

    /* -------- Mover para a lixeira -------- */
    case 'excluir':
        if (!is_file($metaAtivo)) { arq_erro('Arquivamento não encontrado.', 404); }

        $dados = json_decode(@file_get_contents($metaAtivo), true);
        if (!is_array($dados)) { $dados = []; }
        $dados['excluido_por']  = arq_usuario();
        $dados['data_exclusao'] = date('Y-m-d H:i:s');
        $dados['motivo_exclusao'] = mb_substr(trim((string) (isset($_POST['motivo']) ? $_POST['motivo'] : '')), 0, 300);

        if (!is_dir(arq_dir_lixeira())) { @mkdir(arq_dir_lixeira(), 0770, true); }
        if (@file_put_contents($metaLixo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            arq_erro('Não foi possível mover o registro para a lixeira.', 500);
        }
        @unlink($metaAtivo);
        if (is_dir($pastaAtiva)) { @rename($pastaAtiva, $pastaLixo); }

        arq_invalidar_indice();
        arq_auditar('excluir', $id, ['motivo' => $dados['motivo_exclusao']]);
        arq_ok(['mensagem' => 'Arquivamento movido para a lixeira.']);
        break;

    /* -------- Restaurar -------- */
    case 'restaurar':
        if (!is_file($metaLixo)) { arq_erro('Registro não encontrado na lixeira.', 404); }
        if (is_file($metaAtivo)) { arq_erro('Já existe um arquivamento ativo com este identificador.', 409); }

        $dados = json_decode(@file_get_contents($metaLixo), true);
        if (!is_array($dados)) { $dados = []; }
        unset($dados['excluido_por'], $dados['data_exclusao'], $dados['motivo_exclusao']);
        if (!isset($dados['modificacoes']) || !is_array($dados['modificacoes'])) { $dados['modificacoes'] = []; }
        $dados['modificacoes'][] = [
            'usuario'   => arq_usuario(),
            'data_hora' => date('Y-m-d H:i:s'),
            'acao'      => 'restauracao',
            'ip'        => arq_ip(),
        ];

        if (@file_put_contents($metaAtivo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            arq_erro('Não foi possível restaurar o registro.', 500);
        }
        @unlink($metaLixo);
        if (is_dir($pastaLixo)) { @rename($pastaLixo, $pastaAtiva); }

        arq_invalidar_indice();
        arq_auditar('restaurar', $id);
        arq_ok(['mensagem' => 'Arquivamento restaurado para o acervo.']);
        break;

    /* -------- Exclusão definitiva -------- */
    case 'expurgar':
        if (!arq_pode_expurgar()) {
            arq_erro('Seu perfil não tem permissão para excluir definitivamente.', 403);
        }
        if (!is_file($metaLixo)) { arq_erro('Registro não encontrado na lixeira.', 404); }

        $conf = isset($_POST['confirmacao']) ? trim((string) $_POST['confirmacao']) : '';
        if ($conf !== $id) {
            arq_erro('Confirmação incorreta. Digite o número do arquivamento para confirmar.', 400);
        }

        $dados = json_decode(@file_get_contents($metaLixo), true);
        $resumo = [
            'categoria' => isset($dados['categoria']) ? $dados['categoria'] : '',
            'data_ato'  => isset($dados['data_ato']) ? $dados['data_ato'] : '',
            'partes'    => isset($dados['partes_envolvidas']) ? array_column($dados['partes_envolvidas'], 'nome') : [],
        ];

        @unlink($metaLixo);
        if (is_dir($pastaLixo)) { arq_remover_dir($pastaLixo); }

        arq_auditar('expurgar', $id, $resumo);
        arq_ok(['mensagem' => 'Arquivamento excluído definitivamente.']);
        break;

    default:
        arq_erro('Ação desconhecida.', 400);
}
