<?php
/**
 * Atlas · Tarefas — recursos de inteligência artificial (Gemini).
 *
 * Todos os recursos partem do contexto real da tarefa (título, descrição,
 * prazo, comentários e anexos) e devolvem texto pronto para o usuário
 * revisar. Nada é gravado automaticamente sem o aceite dele, com uma única
 * exceção: o resumo, que fica em cache na coluna `ia_resumo` para não gastar
 * chamada à API a cada abertura da tarefa.
 *
 * Regra importante: a IA nunca decide sozinha. Ela sugere; o usuário aplica.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/gemini.php';
api_iniciar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_erro('Método não permitido.', 405);
}
csrf_validar();

if (!ia_disponivel()) {
    responder_erro('A integração com o Gemini não está configurada ou está desativada. '
        . 'Acesse Configurações da IA para ajustar.', 409, array('config' => true));
}

$recurso = entrada('recurso', '', $_POST);
$modelo  = entrada('modelo', '', $_POST);
$u       = usuario_atual();

/* ------------------------------------------------------------------ */
/* Contexto da tarefa                                                  */
/* ------------------------------------------------------------------ */

/**
 * Monta um bloco de texto com tudo que a IA precisa saber sobre a tarefa.
 *
 * @return array{tarefa:array,texto:string}
 */
function contexto_tarefa($tarefaId)
{
    $t = db_one(
        'SELECT t.*, c.titulo AS categoria_titulo, o.titulo AS origem_titulo
           FROM tarefas t
           LEFT JOIN categorias c ON t.categoria = c.id
           LEFT JOIN origem o ON t.origem = o.id
          WHERE t.id = ? LIMIT 1',
        array((int) $tarefaId)
    );

    if (!$t) {
        responder_erro('Tarefa não encontrada.', 404);
    }

    if (!usuario_ve_tudo()) {
        $u = usuario_atual();
        $meu = $t['funcionario_responsavel'] === $u['nome']
            || $t['revisor'] === $u['nome']
            || $t['criado_por'] === $u['usuario'];
        if (!$meu) {
            responder_erro('Sem permissão para usar a IA nesta tarefa.', 403);
        }
    }

    $linhas = array();
    $linhas[] = 'Protocolo geral: ' . $t['id'];
    $linhas[] = 'Título: ' . $t['titulo'];
    $linhas[] = 'Categoria: ' . ($t['categoria_titulo'] !== null ? $t['categoria_titulo'] : '(não informada)');
    $linhas[] = 'Origem: ' . ($t['origem_titulo'] !== null ? $t['origem_titulo'] : '(não informada)');
    $linhas[] = 'Status atual: ' . $t['status'];
    $linhas[] = 'Prioridade: ' . $t['nivel_de_prioridade'];
    $linhas[] = 'Criada em: ' . data_br($t['data_criacao']);
    $linhas[] = 'Prazo: ' . (data_br($t['data_limite']) !== '' ? data_br($t['data_limite']) : '(sem prazo)');
    $linhas[] = 'Responsável: ' . $t['funcionario_responsavel'];
    if (!empty($t['revisor'])) {
        $linhas[] = 'Revisor: ' . $t['revisor'];
    }
    if (!empty($t['numero_oficio'])) {
        $linhas[] = 'Ofício vinculado: ' . $t['numero_oficio'];
    }
    $linhas[] = '';
    $linhas[] = 'Descrição:';
    $linhas[] = trim((string) $t['descricao']) !== '' ? $t['descricao'] : '(sem descrição)';

    /* Comentários — a linha do tempo é onde está o andamento real. */
    try {
        $coms = db_all(
            'SELECT comentario, funcionario, data_comentario
               FROM comentarios
              WHERE hash_tarefa = ? OR id_tarefa_principal = ?
              ORDER BY data_comentario ASC LIMIT 60',
            array($t['token'], (int) $t['id'])
        );
        if ($coms) {
            $linhas[] = '';
            $linhas[] = 'Andamento registrado (mais antigo primeiro):';
            foreach ($coms as $c) {
                $txt = trim((string) $c['comentario']);
                if ($txt === '') {
                    continue;
                }
                $linhas[] = '- [' . data_br($c['data_comentario']) . ' · ' . $c['funcionario'] . '] '
                          . mb_substr($txt, 0, 800);
            }
        }
    } catch (Exception $e) {
        error_log('[tarefas] ia contexto comentarios: ' . $e->getMessage());
    }

    /* Nomes dos anexos ajudam o modelo a entender do que se trata. */
    $anexos = anexos_lista($t['caminho_anexo']);
    if ($anexos) {
        $linhas[] = '';
        $linhas[] = 'Anexos: ' . implode(', ', array_column($anexos, 'nome'));
    }

    /* Subtarefas. */
    try {
        $subs = db_all(
            "SELECT id, titulo, status FROM tarefas
              WHERE id_tarefa_principal = ? AND sub_categoria = 'Sim' ORDER BY id",
            array((int) $t['id'])
        );
        if ($subs) {
            $linhas[] = '';
            $linhas[] = 'Subtarefas:';
            foreach ($subs as $s) {
                $linhas[] = '- #' . $s['id'] . ' ' . $s['titulo'] . ' (' . $s['status'] . ')';
            }
        }
    } catch (Exception $e) {
        // segue sem subtarefas
    }

    return array('tarefa' => $t, 'texto' => implode("\n", $linhas));
}

/** Instrução de sistema comum a todos os recursos. */
function instrucao_base()
{
    return "Você é um assistente de um cartório extrajudicial brasileiro, integrado ao sistema Atlas. "
        . "Escreva em português do Brasil, com objetividade e linguagem formal de serventia. "
        . "Baseie-se apenas nas informações fornecidas. "
        . "Quando faltar informação, diga expressamente o que falta em vez de supor. "
        . "Nunca invente número de lei, provimento, matrícula, processo, protocolo ou data. "
        . "Não repita a pergunta nem escreva preâmbulos como 'Claro' ou 'Aqui está'.";
}

/* ------------------------------------------------------------------ */
/* Recursos                                                            */
/* ------------------------------------------------------------------ */

switch ($recurso) {

/* ---------------- Resumo executivo -------------------------------- */
case 'resumir':

    $tarefaId = entrada_int('tarefa_id', 0, $_POST);
    $forcar   = entrada('forcar', '', $_POST) === '1';

    /* Cache: evita gastar chamada a cada abertura da mesma tarefa. */
    if (!$forcar && db_tem_coluna('tarefas', 'ia_resumo')) {
        $cache = db_one(
            'SELECT ia_resumo, ia_resumo_em, data_atualizacao FROM tarefas WHERE id = ?',
            array($tarefaId)
        );
        if ($cache && trim((string) $cache['ia_resumo']) !== '') {
            $valido = true;
            if (!empty($cache['data_atualizacao']) && !empty($cache['ia_resumo_em'])) {
                $valido = strtotime($cache['ia_resumo_em']) >= strtotime($cache['data_atualizacao']);
            }
            if ($valido) {
                responder_ok(array(
                    'texto'    => $cache['ia_resumo'],
                    'cache'    => true,
                    'gerado_em' => data_br($cache['ia_resumo_em']),
                ));
            }
        }
    }

    $ctx = contexto_tarefa($tarefaId);

    $r = gemini_gerar(
        $ctx['texto'] . "\n\n"
        . "Produza um resumo executivo desta tarefa em no máximo 6 linhas, na seguinte forma:\n"
        . "1) Do que se trata, em uma frase.\n"
        . "2) Em que ponto está o andamento.\n"
        . "3) O que falta para concluir.\n"
        . "4) Riscos de prazo, se houver.\n"
        . "Não use marcadores nem títulos: escreva em parágrafo corrido e direto.",
        array('modelo' => $modelo, 'recurso' => 'resumir', 'instrucao_sistema' => instrucao_base(), 'max_tokens' => 800)
    );

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    if (db_tem_coluna('tarefas', 'ia_resumo')) {
        db_exec('UPDATE tarefas SET ia_resumo = ?, ia_resumo_em = NOW() WHERE id = ?',
            array($r['texto'], $tarefaId));
    }

    responder_ok(array('texto' => $r['texto'], 'modelo' => $r['modelo'], 'tokens' => $r['tokens'], 'cache' => false));

/* ---------------- Próximos passos --------------------------------- */
case 'proximos_passos':

    $ctx = contexto_tarefa(entrada_int('tarefa_id', 0, $_POST));

    $r = gemini_gerar_json(
        $ctx['texto'] . "\n\n"
        . "Indique os próximos passos concretos para concluir esta tarefa na serventia. "
        . "Responda em JSON com este formato exato:\n"
        . '{"passos":[{"titulo":"...","detalhe":"...","urgente":true}],'
        . '"pendencias":["..."],"observacao":"..."}' . "\n"
        . "Máximo de 6 passos. 'pendencias' lista o que precisa ser solicitado a terceiros. "
        . "'observacao' pode ficar vazia.",
        array('modelo' => $modelo, 'recurso' => 'proximos_passos',
              'instrucao_sistema' => instrucao_base(), 'max_tokens' => 1400)
    );

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array('dados' => $r['dados'], 'modelo' => $r['modelo']));

/* ---------------- Classificação automática ------------------------ */
case 'classificar':

    /*
     * Usada na tela de criação: recebe título e descrição digitados e sugere
     * categoria (dentre as cadastradas), prioridade, prazo e etiquetas.
     */
    $titulo    = entrada('titulo', '', $_POST);
    $descricao = entrada('descricao', '', $_POST);

    if ($titulo === '' && $descricao === '') {
        responder_erro('Escreva ao menos o título antes de pedir a sugestão.');
    }

    $categorias = array();
    foreach (listar_categorias() as $c) {
        $categorias[] = $c['titulo'];
    }
    $origens = array();
    foreach (listar_origens() as $o) {
        $origens[] = $o['titulo'];
    }

    $prompt = "Nova tarefa a ser cadastrada na serventia.\n"
        . "Título: $titulo\n"
        . "Descrição: $descricao\n\n"
        . "Categorias disponíveis: " . implode(' | ', $categorias) . "\n"
        . "Origens disponíveis: " . implode(' | ', $origens) . "\n"
        . "Prioridades possíveis: Baixa, Média, Alta, Crítica.\n\n"
        . "Sugira a classificação. Responda em JSON:\n"
        . '{"categoria":"exatamente um dos títulos listados ou vazio",'
        . '"origem":"exatamente uma das origens listadas ou vazio",'
        . '"prioridade":"Baixa|Média|Alta|Crítica",'
        . '"prazo_dias":número inteiro de dias úteis sugerido,'
        . '"tags":["etiqueta1","etiqueta2"],'
        . '"justificativa":"uma frase curta"}';

    $r = gemini_gerar_json($prompt, array(
        'modelo' => $modelo, 'recurso' => 'classificar',
        'instrucao_sistema' => instrucao_base(), 'max_tokens' => 700, 'temperatura' => 0.2,
    ));

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    /* Converte o título sugerido no ID real da categoria/origem. */
    $d = $r['dados'];
    $d['categoria_id'] = '';
    $d['origem_id']    = '';

    if (!empty($d['categoria'])) {
        foreach (listar_categorias() as $c) {
            if (mb_strtolower(trim($c['titulo'])) === mb_strtolower(trim((string) $d['categoria']))) {
                $d['categoria_id'] = $c['id'];
                break;
            }
        }
    }
    if (!empty($d['origem'])) {
        foreach (listar_origens() as $o) {
            if (mb_strtolower(trim($o['titulo'])) === mb_strtolower(trim((string) $d['origem']))) {
                $d['origem_id'] = $o['id'];
                break;
            }
        }
    }

    if (!empty($d['prazo_dias'])) {
        $dias = max(1, min(365, (int) $d['prazo_dias']));
        $d['prazo_sugerido'] = date('Y-m-d\TH:i', strtotime('+' . $dias . ' weekday 17:00'));
        $d['prazo_sugerido_br'] = date('d/m/Y H:i', strtotime($d['prazo_sugerido']));
    }

    responder_ok(array('dados' => $d, 'modelo' => $r['modelo']));

/* ---------------- Redação assistida ------------------------------- */
case 'redigir':

    $tarefaId = entrada_int('tarefa_id', 0, $_POST);
    $tipo     = entrada('tipo', 'comentario', $_POST);
    $extra    = entrada('instrucao', '', $_POST);
    $tom      = entrada('tom', 'formal', $_POST);

    $ctx = contexto_tarefa($tarefaId);

    $modelos = array(
        'comentario'  => 'Escreva um registro de andamento para a linha do tempo da tarefa, em até 5 linhas, na primeira pessoa do plural ("informamos", "registramos").',
        'despacho'    => 'Redija um despacho interno objetivo, indicando a providência determinada e o responsável, em até 8 linhas.',
        'exigencia'   => 'Redija uma nota de exigência dirigida ao apresentante, listando de forma numerada exatamente o que precisa ser apresentado ou corrigido, com fundamento apenas no que consta na tarefa.',
        'email'       => 'Redija um e-mail cordial e objetivo ao interessado informando a situação do protocolo, com assunto na primeira linha no formato "Assunto: ...".',
        'whatsapp'    => 'Escreva uma mensagem curta e cordial de WhatsApp ao interessado informando a situação do protocolo, em no máximo 4 linhas.',
        'conclusao'   => 'Redija o texto de encerramento da tarefa, descrevendo o que foi praticado e a data de conclusão.',
    );

    $instrucaoTipo = isset($modelos[$tipo]) ? $modelos[$tipo] : $modelos['comentario'];

    $tons = array(
        'formal'    => 'Use registro formal de serventia.',
        'simples'   => 'Use linguagem simples e acessível ao cidadão, evitando termos técnicos sem explicação.',
        'tecnico'   => 'Use linguagem técnica registral, com precisão terminológica.',
    );
    $instrucaoTom = isset($tons[$tom]) ? $tons[$tom] : $tons['formal'];

    $prompt = $ctx['texto'] . "\n\n" . $instrucaoTipo . ' ' . $instrucaoTom;
    if ($extra !== '') {
        $prompt .= "\n\nOrientação adicional do usuário: " . $extra;
    }
    $prompt .= "\n\nEntregue apenas o texto final, sem comentários sobre o que você fez.";

    $r = gemini_gerar($prompt, array(
        'modelo' => $modelo, 'recurso' => 'redigir_' . $tipo,
        'instrucao_sistema' => instrucao_base(), 'max_tokens' => 1600, 'temperatura' => 0.5,
    ));

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array('texto' => $r['texto'], 'modelo' => $r['modelo'], 'tokens' => $r['tokens']));

/* ---------------- Análise de anexo -------------------------------- */
case 'analisar_anexo':

    $tarefaId = entrada_int('tarefa_id', 0, $_POST);
    $arquivo  = entrada('arquivo', '', $_POST);
    $pergunta = entrada('pergunta', '', $_POST);

    $ctx = contexto_tarefa($tarefaId);

    $lista = anexos_lista($ctx['tarefa']['caminho_anexo']);
    $alvo  = null;
    foreach ($lista as $a) {
        if ($a['rel'] === $arquivo || $a['nome'] === basename($arquivo)) {
            $alvo = $a;
            break;
        }
    }

    if ($alvo === null) {
        responder_erro('Anexo não encontrado nesta tarefa.', 404);
    }
    if (!$alvo['existe']) {
        responder_erro('O arquivo do anexo não está mais no servidor.', 404);
    }

    $mimes = array(
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'txt' => 'text/plain',
    );
    if (!isset($mimes[$alvo['ext']])) {
        responder_erro('A IA só consegue ler PDF, imagem ou texto. Este anexo é ".' . $alvo['ext'] . '".');
    }

    $caminho = TAREFAS_DIR . '/' . $alvo['rel'];
    if (filesize($caminho) > 18 * 1024 * 1024) {
        responder_erro('Anexo muito grande para análise (limite de 18 MB).');
    }

    $prompt = $ctx['texto'] . "\n\n"
        . 'Analise o documento anexo à luz desta tarefa. '
        . ($pergunta !== ''
            ? 'Responda especificamente: ' . $pergunta
            : "Informe: (a) que documento é; (b) os dados essenciais identificados "
              . "(partes, datas, valores, números); (c) inconsistências ou faltas relevantes "
              . "para a prática do ato. Seja conciso.")
        . "\n\nSe algo não estiver legível no documento, diga isso em vez de supor.";

    $r = gemini_gerar($prompt, array(
        'modelo' => $modelo, 'recurso' => 'analisar_anexo',
        'instrucao_sistema' => instrucao_base(), 'max_tokens' => 2000,
        'arquivos' => array(array('caminho' => $caminho, 'mime' => $mimes[$alvo['ext']])),
    ));

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array('texto' => $r['texto'], 'arquivo' => $alvo['nome'], 'modelo' => $r['modelo']));

/* ---------------- Assistente conversacional ----------------------- */
case 'chat':

    $tarefaId = entrada_int('tarefa_id', 0, $_POST);
    $mensagem = entrada('mensagem', '', $_POST);

    if ($mensagem === '') {
        responder_erro('Escreva a sua pergunta.');
    }

    $prompt = '';
    if ($tarefaId > 0) {
        $ctx = contexto_tarefa($tarefaId);
        $prompt = $ctx['texto'] . "\n\n";
    }

    /* Últimas trocas da conversa, para dar continuidade. */
    if (db_tem_tabela('tarefas_ia_conversas')) {
        try {
            $hist = db_all(
                'SELECT papel, mensagem FROM tarefas_ia_conversas
                  WHERE usuario = ? AND ' . ($tarefaId > 0 ? 'tarefa_id = ?' : 'tarefa_id IS NULL') . '
                  ORDER BY id DESC LIMIT 8',
                $tarefaId > 0 ? array($u['usuario'], $tarefaId) : array($u['usuario'])
            );
            if ($hist) {
                $hist = array_reverse($hist);
                $prompt .= "Conversa anterior:\n";
                foreach ($hist as $h) {
                    $prompt .= ($h['papel'] === 'user' ? 'Usuário: ' : 'Assistente: ')
                             . mb_substr((string) $h['mensagem'], 0, 900) . "\n";
                }
                $prompt .= "\n";
            }
        } catch (Exception $e) {
            // segue sem histórico
        }
    }

    $prompt .= 'Pergunta do usuário: ' . $mensagem;

    $r = gemini_gerar($prompt, array(
        'modelo' => $modelo, 'recurso' => 'chat',
        'instrucao_sistema' => instrucao_base()
            . ' Se a pergunta for sobre norma jurídica e você não tiver certeza da referência exata,'
            . ' explique o conceito e recomende conferir a redação vigente, sem citar número.',
        'max_tokens' => 2000, 'temperatura' => 0.5,
    ));

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    if (db_tem_tabela('tarefas_ia_conversas')) {
        try {
            $ins = 'INSERT INTO tarefas_ia_conversas (tarefa_id, papel, mensagem, modelo, usuario, criado_em)
                    VALUES (?, ?, ?, ?, ?, NOW())';
            db_exec($ins, array($tarefaId > 0 ? $tarefaId : null, 'user', $mensagem, $r['modelo'], $u['usuario']));
            db_exec($ins, array($tarefaId > 0 ? $tarefaId : null, 'model', $r['texto'], $r['modelo'], $u['usuario']));
        } catch (Exception $e) {
            // conversa não gravada não impede a resposta
        }
    }

    responder_ok(array('texto' => $r['texto'], 'modelo' => $r['modelo'], 'tokens' => $r['tokens']));

case 'chat_limpar':

    if (db_tem_tabela('tarefas_ia_conversas')) {
        $tarefaId = entrada_int('tarefa_id', 0, $_POST);
        if ($tarefaId > 0) {
            db_exec('DELETE FROM tarefas_ia_conversas WHERE usuario = ? AND tarefa_id = ?',
                array($u['usuario'], $tarefaId));
        } else {
            db_exec('DELETE FROM tarefas_ia_conversas WHERE usuario = ? AND tarefa_id IS NULL',
                array($u['usuario']));
        }
    }
    responder_ok(array('mensagem' => 'Conversa reiniciada.'));

/* ---------------- Priorização da carteira ------------------------- */
case 'priorizar':

    $vis = filtro_visibilidade('t');
    $enc = tarefas_status_encerrados();
    $inEnc = implode(',', array_fill(0, count($enc), '?'));

    $lista = db_all(
        "SELECT t.id, t.titulo, t.status, t.nivel_de_prioridade, t.data_limite,
                t.funcionario_responsavel, c.titulo AS categoria_titulo
           FROM tarefas t
           LEFT JOIN categorias c ON t.categoria = c.id
          WHERE t.status NOT IN ($inEnc) {$vis['sql']}
          ORDER BY t.data_limite ASC
          LIMIT 40",
        array_merge($enc, $vis['params'])
    );

    if (!$lista) {
        responder_ok(array('dados' => array('ordem' => array()), 'vazio' => true));
    }

    $linhas = array();
    foreach ($lista as $t) {
        $sit = situacao_prazo($t['data_limite'], $t['status']);
        $linhas[] = '#' . $t['id'] . ' | ' . $t['titulo']
            . ' | categoria: ' . ($t['categoria_titulo'] !== null ? $t['categoria_titulo'] : '-')
            . ' | status: ' . $t['status']
            . ' | prioridade: ' . $t['nivel_de_prioridade']
            . ' | prazo: ' . (data_br($t['data_limite']) !== '' ? data_br($t['data_limite']) : 'sem prazo')
            . ' | situação: ' . $sit['rotulo']
            . ' | responsável: ' . $t['funcionario_responsavel'];
    }

    $r = gemini_gerar_json(
        "Carteira de tarefas em aberto de uma serventia extrajudicial (hoje é "
        . date('d/m/Y') . "):\n" . implode("\n", $linhas) . "\n\n"
        . "Sugira a ordem de trabalho para as próximas horas, considerando prazo vencido, "
        . "prazo próximo, prioridade declarada e risco para a serventia.\n"
        . "Responda em JSON:\n"
        . '{"ordem":[{"id":123,"posicao":1,"motivo":"frase curta","risco":"alto|medio|baixo"}],'
        . '"alerta":"uma frase sobre o maior risco da carteira, ou vazio"}' . "\n"
        . "Inclua no máximo 12 tarefas, as mais críticas.",
        array('modelo' => $modelo, 'recurso' => 'priorizar',
              'instrucao_sistema' => instrucao_base(), 'max_tokens' => 2000, 'temperatura' => 0.3)
    );

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    /* Junta os dados reais da tarefa à sugestão, para a tela montar os links. */
    $indice = array();
    foreach ($lista as $t) {
        $indice[(int) $t['id']] = $t;
    }

    $ordem = array();
    if (!empty($r['dados']['ordem'])) {
        foreach ((array) $r['dados']['ordem'] as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if (!isset($indice[$id])) {
                continue;   // ignora id inventado
            }
            $ordem[] = array(
                'id'       => $id,
                'titulo'   => $indice[$id]['titulo'],
                'status'   => $indice[$id]['status'],
                'prazo_br' => data_br($indice[$id]['data_limite']),
                'motivo'   => isset($item['motivo']) ? (string) $item['motivo'] : '',
                'risco'    => isset($item['risco']) ? (string) $item['risco'] : 'medio',
            );
        }
    }

    responder_ok(array(
        'ordem'  => $ordem,
        'alerta' => isset($r['dados']['alerta']) ? (string) $r['dados']['alerta'] : '',
        'modelo' => $r['modelo'],
    ));

/* ---------------- Busca em linguagem natural ---------------------- */
case 'interpretar_busca':

    $pergunta = entrada('pergunta', '', $_POST);
    if ($pergunta === '') {
        responder_erro('Escreva o que você procura.');
    }

    $cats = array();
    foreach (listar_categorias() as $c) {
        $cats[] = $c['id'] . '=' . $c['titulo'];
    }
    $funcs = array();
    foreach (listar_funcionarios() as $f) {
        $funcs[] = $f['nome_completo'];
    }

    $r = gemini_gerar_json(
        "Converta o pedido do usuário em filtros de busca de tarefas.\n"
        . "Hoje é " . date('Y-m-d') . ".\n"
        . "Categorias (id=título): " . implode(' | ', $cats) . "\n"
        . "Funcionários: " . implode(' | ', $funcs) . "\n"
        . "Status possíveis: " . implode(' | ', array_keys(tarefas_status_catalogo())) . "\n"
        . "Prioridades: Baixa, Média, Alta, Crítica.\n"
        . "Situações: vencida, hoje, semana, minhas, sem_responsavel.\n\n"
        . "Pedido: \"$pergunta\"\n\n"
        . "Responda em JSON, deixando vazias as chaves que não se aplicam:\n"
        . '{"texto":"","category":"","employee":"","revisor":"","status":"","priority":"",'
        . '"dateStart":"AAAA-MM-DD","dateEnd":"AAAA-MM-DD","situacao":"","explicacao":"frase curta"}',
        array('modelo' => $modelo, 'recurso' => 'interpretar_busca',
              'instrucao_sistema' => instrucao_base(), 'max_tokens' => 700, 'temperatura' => 0.1)
    );

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    responder_ok(array('filtros' => $r['dados'], 'modelo' => $r['modelo']));

/* ---------------- Sugerir itens de checklist ---------------------- */
case 'sugerir_checklist':

    $tarefaId = entrada_int('tarefa_id', 0, $_POST);
    $ctx = contexto_tarefa($tarefaId);

    $r = gemini_gerar_json(
        $ctx['texto'] . "\n\n"
        . "Liste de 4 a 8 etapas objetivas de conferência para esta tarefa ser concluída "
        . "com segurança na serventia. Cada etapa deve caber em uma linha e começar por verbo.\n"
        . 'Responda em JSON: {"itens":["Conferir ...","Solicitar ..."]}',
        array('modelo' => $modelo, 'recurso' => 'sugerir_checklist',
              'instrucao_sistema' => instrucao_base(), 'max_tokens' => 900, 'temperatura' => 0.3)
    );

    if (!$r['success']) {
        responder_erro($r['error'], 502);
    }

    $itens = array();
    foreach ((array) (isset($r['dados']['itens']) ? $r['dados']['itens'] : array()) as $i) {
        $t = trim((string) $i);
        if ($t !== '') {
            $itens[] = mb_substr($t, 0, 300);
        }
    }

    /* Grava somente se o usuário pediu para aplicar direto. */
    if (entrada('aplicar', '', $_POST) === '1' && db_tem_tabela('tarefas_checklist')) {
        $ordem = (int) db_valor(
            'SELECT COALESCE(MAX(ordem), -1) + 1 FROM tarefas_checklist WHERE tarefa_id = ?',
            array($tarefaId), 0
        );
        foreach ($itens as $texto) {
            db_exec(
                'INSERT INTO tarefas_checklist (tarefa_id, descricao, ordem, origem, criado_por, criado_em)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                array($tarefaId, $texto, $ordem++, 'ia', $u['usuario'])
            );
        }
        registrar_historico($tarefaId, 'checklist', count($itens) . ' item(ns) sugerido(s) pela IA');
    }

    responder_ok(array('itens' => $itens, 'modelo' => $r['modelo']));

/* ------------------------------------------------------------------ */
default:
    responder_erro('Recurso de IA desconhecido: ' . $recurso, 400);
}
