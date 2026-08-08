<?php
/**
 * Atlas · Tarefas — gestão do catálogo de modelos Gemini e da configuração.
 *
 * Ações (POST, exceto `listar`):
 *   listar        · devolve catálogo, configuração mascarada e estatísticas
 *   salvar        · cadastra ou atualiza um modelo
 *   excluir       · remove um modelo do catálogo
 *   ativar        · liga/desliga um modelo
 *   favoritar     · marca/desmarca favorito
 *   sincronizar   · consulta a API pela lista real de modelos da chave
 *   testar        · faz uma chamada curta para validar chave + modelo
 *   config        · grava chave, modelo padrão e demais parâmetros
 *
 * Somente administradores (ou quem tem "Controle de Tarefas" no acesso
 * adicional) podem alterar. Os demais apenas leem.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/gemini.php';
api_iniciar();

$acao = entrada('acao', 'listar');

/* Leitura é liberada; escrita exige POST + CSRF + permissão. */
if ($acao !== 'listar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder_erro('Método não permitido.', 405);
    }
    csrf_validar();
    if (!usuario_ve_tudo()) {
        responder_erro('Somente administradores podem alterar a configuração da IA.', 403);
    }
}

if (!db_tem_tabela('tarefas_ia_modelos') || !db_tem_tabela('tarefas_ia_config')) {
    responder_erro('As tabelas da IA ainda não existem. Execute migracao_v2.php.', 409,
        array('migracao' => true));
}

switch ($acao) {

/* ------------------------------------------------------------------ */
case 'listar':

    $cfg = ia_config();

    responder_ok(array(
        'modelos' => ia_modelos(),
        'config'  => array(
            'tem_chave'         => trim($cfg['api_key']) !== '',
            'chave_mascarada'   => ia_chave_mascarada(),
            'modelo_padrao'     => $cfg['modelo_padrao'],
            'ativo'             => $cfg['ativo'] === '1',
            'temperatura'       => (float) $cfg['temperatura'],
            'max_tokens'        => (int) $cfg['max_tokens'],
            'timeout'           => (int) $cfg['timeout'],
            'contexto_cartorio' => $cfg['contexto_cartorio'],
        ),
        'estatisticas' => ia_estatisticas(),
        'pode_editar'  => usuario_ve_tudo(),
    ));

/* ------------------------------------------------------------------ */
case 'salvar':

    $r = ia_modelo_salvar(
        entrada('modelo_id', '', $_POST),
        entrada('apelido', '', $_POST),
        entrada('descricao', '', $_POST),
        entrada('ativo', '1', $_POST) === '1',
        entrada('suporta_arquivos', '1', $_POST) === '1',
        'manual'
    );

    if (!$r['success']) {
        responder_erro($r['error']);
    }

    responder_ok(array('mensagem' => 'Modelo salvo.', 'modelo_id' => $r['modelo_id'], 'modelos' => ia_modelos()));

/* ------------------------------------------------------------------ */
case 'excluir':

    $r = ia_modelo_excluir(entrada('modelo_id', '', $_POST));
    if (!$r['success']) {
        responder_erro($r['error']);
    }
    responder_ok(array('mensagem' => 'Modelo excluído do catálogo.', 'modelos' => ia_modelos()));

/* ------------------------------------------------------------------ */
case 'ativar':

    $modeloId = entrada('modelo_id', '', $_POST);
    $ativo    = entrada('ativo', '1', $_POST) === '1';
    $cfg      = ia_config();

    if (!$ativo && $modeloId === $cfg['modelo_padrao']) {
        responder_erro('Não é possível desativar o modelo padrão. Escolha outro padrão primeiro.');
    }

    db_exec('UPDATE tarefas_ia_modelos SET ativo = ?, atualizado_em = NOW() WHERE modelo_id = ?',
        array($ativo ? 1 : 0, $modeloId));

    responder_ok(array('ativo' => $ativo, 'modelos' => ia_modelos()));

/* ------------------------------------------------------------------ */
case 'favoritar':

    ia_modelo_favorito(entrada('modelo_id', '', $_POST), entrada('favorito', '0', $_POST) === '1');
    responder_ok(array('modelos' => ia_modelos()));

/* ------------------------------------------------------------------ */
case 'sincronizar':

    $r = ia_sincronizar_modelos();
    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array(
        'mensagem' => $r['novos'] . ' modelo(s) novo(s) e ' . $r['atualizados']
                    . ' atualizado(s). A chave enxerga ' . $r['total_api'] . ' modelo(s) de texto.',
        'modelos'  => ia_modelos(),
    ));

/* ------------------------------------------------------------------ */
case 'testar':

    $modelo = entrada('modelo_id', '', $_POST);
    $cfg    = ia_config();

    if (trim($cfg['api_key']) === '') {
        responder_erro('Cadastre a chave da API antes de testar.');
    }

    /*
     * O teste precisa funcionar mesmo com a integração desligada — é assim
     * que o usuário valida a configuração antes de ativar.
     */
    $antes = $cfg['ativo'];
    if ($antes !== '1') {
        ia_config_salvar('ativo', '1');
    }

    // Limpa o cache estático para a nova configuração valer nesta chamada.
    $r = gemini_teste_direto($modelo);

    if ($antes !== '1') {
        ia_config_salvar('ativo', $antes);
    }

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array(
        'mensagem' => 'Conexão bem-sucedida com ' . $r['modelo'] . '.',
        'resposta' => $r['texto'],
        'ms'       => isset($r['ms']) ? $r['ms'] : 0,
    ));

/* ------------------------------------------------------------------ */
case 'config':

    $cfg = ia_config();

    /* Chave: só grava se o usuário digitou algo novo. */
    $chave = entrada('api_key', '', $_POST);
    if ($chave !== '' && strpos($chave, '•') === false) {
        ia_config_salvar('api_key', $chave);
    } elseif (entrada('remover_chave', '', $_POST) === '1') {
        ia_config_salvar('api_key', '');
        ia_config_salvar('ativo', '0');
    }

    $padrao = entrada('modelo_padrao', '', $_POST);
    if ($padrao !== '') {
        $m = ia_modelo_por_id($padrao);
        if (!$m) {
            responder_erro('O modelo padrão precisa estar cadastrado no catálogo.');
        }
        if ((int) $m['ativo'] !== 1) {
            responder_erro('O modelo padrão precisa estar ativo.');
        }
        ia_config_salvar('modelo_padrao', $padrao);
    }

    if (isset($_POST['ativo'])) {
        $ligar = entrada('ativo', '0', $_POST) === '1';
        $chaveAtual = $chave !== '' && strpos($chave, '•') === false ? $chave : $cfg['api_key'];
        if ($ligar && trim($chaveAtual) === '') {
            responder_erro('Cadastre a chave da API antes de ativar a integração.');
        }
        ia_config_salvar('ativo', $ligar ? '1' : '0');
    }

    if (isset($_POST['temperatura'])) {
        $t = (float) entrada('temperatura', '0.4', $_POST);
        ia_config_salvar('temperatura', (string) max(0, min(2, $t)));
    }
    if (isset($_POST['max_tokens'])) {
        $t = (int) entrada('max_tokens', '2048', $_POST);
        ia_config_salvar('max_tokens', (string) max(256, min(32000, $t)));
    }
    if (isset($_POST['timeout'])) {
        $t = (int) entrada('timeout', '60', $_POST);
        ia_config_salvar('timeout', (string) max(10, min(300, $t)));
    }
    if (isset($_POST['contexto_cartorio'])) {
        ia_config_salvar('contexto_cartorio', mb_substr(entrada('contexto_cartorio', '', $_POST), 0, 4000));
    }

    responder_ok(array('mensagem' => 'Configuração salva.'));

/* ------------------------------------------------------------------ */
default:
    responder_erro('Ação desconhecida: ' . $acao, 400);
}

/**
 * Chamada de teste que não depende do cache estático de ia_config().
 * Lê a configuração direto do banco e monta a requisição na hora.
 */
function gemini_teste_direto($modeloId)
{
    $cfg = array();
    foreach (db_all('SELECT chave, valor FROM tarefas_ia_config') as $l) {
        $cfg[$l['chave']] = (string) $l['valor'];
    }

    $chave  = isset($cfg['api_key']) ? trim($cfg['api_key']) : '';
    $modelo = $modeloId !== '' ? $modeloId : (isset($cfg['modelo_padrao']) ? $cfg['modelo_padrao'] : 'gemini-3.5-flash');

    if ($chave === '') {
        return array('success' => false, 'error' => 'Chave da API não cadastrada.');
    }

    $corpo = json_encode(array(
        'contents' => array(array('role' => 'user', 'parts' => array(array(
            'text' => 'Responda exatamente com a frase: Integração do módulo de Tarefas do Atlas funcionando.',
        )))),
        'generationConfig' => array('temperature' => 0, 'maxOutputTokens' => 200),
    ), JSON_UNESCAPED_UNICODE);

    $inicio = microtime(true);
    $url = GEMINI_BASE_URL . '/models/' . rawurlencode($modelo) . ':generateContent?key=' . urlencode($chave);
    $resp = gemini_http('POST', $url, $corpo, 45);
    $ms = (int) round((microtime(true) - $inicio) * 1000);

    if (!$resp['success']) {
        ia_registrar_uso($modelo, 'teste', 0, false, $resp['error'], $ms);
        return $resp;
    }

    $dados = json_decode($resp['corpo'], true);
    $texto = '';
    if (isset($dados['candidates'][0]['content']['parts'])) {
        foreach ($dados['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) {
                $texto .= $p['text'];
            }
        }
    }

    if (trim($texto) === '') {
        ia_registrar_uso($modelo, 'teste', 0, false, 'resposta vazia', $ms);
        return array('success' => false, 'error' => 'O modelo respondeu, mas sem texto. Verifique se "' . $modelo . '" ainda está ativo.');
    }

    ia_registrar_uso($modelo, 'teste', isset($dados['usageMetadata']['totalTokenCount']) ? (int) $dados['usageMetadata']['totalTokenCount'] : 0, true, '', $ms);

    return array('success' => true, 'texto' => trim($texto), 'modelo' => $modelo, 'ms' => $ms);
}
