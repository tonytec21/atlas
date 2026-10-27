<?php
/**
 * =====================================================================
 * auditoria/lib.php — Auditoria central do Atlas (todos os módulos)
 * ---------------------------------------------------------------------
 * Duas trilhas, gravadas em tabelas próprias e seladas em cadeia de hash:
 *
 *   1) atlas_auditoria ........ uma linha por OPERAÇÃO (requisição):
 *                               quem, quando, de onde (IP/navegador), em
 *                               qual módulo e tela, a ação, os parâmetros
 *                               enviados (senhas e chaves mascaradas), o
 *                               resultado (sucesso, falha, negado) e o tempo.
 *
 *   2) atlas_auditoria_dados .. uma linha por REGISTRO alterado no banco,
 *                               gravada por gatilhos (triggers) do MySQL:
 *                               tabela, chave, operação (inclusão, alteração,
 *                               exclusão) e os valores ANTES e DEPOIS.
 *                               Cada linha aponta para a operação que a gerou.
 *
 * Integridade: as linhas são seladas (SHA-256 encadeado) logo depois de
 * gravadas. Alterar ou apagar uma linha direto no banco quebra a cadeia, e
 * a âncora gravada em arquivo acusa a remoção das últimas linhas.
 *
 * Regra de ouro: a auditoria NUNCA impede o uso do sistema. Toda falha
 * daqui é engolida e vai para o error_log; os gatilhos têm tratador de erro
 * próprio, então uma falha na gravação da auditoria não desfaz a operação.
 * =====================================================================
 */

if (defined('ATLAS_AUD_LIB')) {
    return;
}
define('ATLAS_AUD_LIB', '1.0.0');
define('ATLAS_AUD_DIR', __DIR__);
define('ATLAS_AUD_DADOS_DIR', __DIR__ . '/dados');

/* ============================ Conexão própria ============================ */

/** Conexão exclusiva da auditoria (não interfere nas conexões do sistema). */
function aud_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = aud_cfg_conexao();
    $pdo = new PDO(
        'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['banco'] . ';charset=utf8mb4',
        $cfg['usuario'],
        $cfg['senha'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]
    );
    // Esta conexão não é auditada pelos gatilhos (ela só grava a própria auditoria).
    $pdo->exec("SET @atlas_aud_off = 1, time_zone = '" . aud_fuso() . "'");
    // Nunca segurar travas por muito tempo nem disputar com as transações do sistema
    try {
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 5, SESSION lock_wait_timeout = 5');
    } catch (Throwable $e) {
    }
    return $pdo;
}

/**
 * Dados de conexão. Usa os mesmos do sistema (localhost/root/atlas); se a
 * serventia tiver outro, basta criar auditoria/dados/conexao.php retornando
 * ['host'=>..,'banco'=>..,'usuario'=>..,'senha'=>..].
 */
function aud_cfg_conexao(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = ['host' => 'localhost', 'banco' => 'atlas', 'usuario' => 'root', 'senha' => ''];
    $f = ATLAS_AUD_DADOS_DIR . '/conexao.php';
    if (is_file($f)) {
        $x = include $f;
        if (is_array($x)) {
            $c = array_merge($c, $x);
        }
    }
    return $c;
}

/** Fuso da serventia como deslocamento numérico (o XAMPP não traz as tabelas de fuso do MySQL). */
function aud_fuso(): string
{
    return '-03:00';
}

function aud_agora(): string
{
    $d = new DateTime('now', new DateTimeZone('America/Fortaleza'));
    return $d->format('Y-m-d H:i:s.v');
}

/* ============================ Estrutura ============================ */

function aud_estrutura(?PDO $pdo = null): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $pdo = $pdo ?: aud_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS atlas_auditoria_meta (
        chave VARCHAR(80) NOT NULL PRIMARY KEY,
        valor MEDIUMTEXT NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Não confia só na versão gravada: um backup restaurado sem as tabelas da auditoria
    // deixaria os gatilhos apontando para uma tabela inexistente.
    $existem = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('atlas_auditoria','atlas_auditoria_dados','atlas_auditoria_navegacao')")->fetchColumn();
    $temIndice = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'atlas_auditoria_dados' AND INDEX_NAME = 'ix_conexao'")->fetchColumn();
    if ($existem === 3 && $temIndice > 0 && aud_meta_get('estrutura_versao', '', $pdo) === ATLAS_AUD_LIB) {
        $ok = true;
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS atlas_auditoria (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        criado_em DATETIME(3) NOT NULL,
        req_id CHAR(16) NOT NULL,
        usuario VARCHAR(100) NULL,
        nome VARCHAR(150) NULL,
        nivel VARCHAR(30) NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        sessao CHAR(16) NULL,
        modulo VARCHAR(40) NOT NULL DEFAULT 'inicio',
        pagina VARCHAR(190) NOT NULL DEFAULT '',
        metodo VARCHAR(8) NOT NULL DEFAULT 'GET',
        acao VARCHAR(190) NOT NULL DEFAULT '',
        categoria VARCHAR(20) NOT NULL DEFAULT 'outros',
        severidade VARCHAR(10) NOT NULL DEFAULT 'info',
        resultado VARCHAR(10) NOT NULL DEFAULT 'sucesso',
        mensagem VARCHAR(500) NULL,
        alvo VARCHAR(255) NULL,
        parametros MEDIUMTEXT NULL,
        status_http SMALLINT NULL,
        duracao_ms INT NULL,
        dados_resumo VARCHAR(1000) NULL,
        dados_linhas INT NOT NULL DEFAULT 0,
        fora_expediente TINYINT(1) NOT NULL DEFAULT 0,
        selo_seq BIGINT UNSIGNED NULL,
        hash_anterior CHAR(64) NULL,
        hash CHAR(64) NULL,
        KEY ix_criado (criado_em),
        KEY ix_usuario (usuario, criado_em),
        KEY ix_modulo (modulo, criado_em),
        KEY ix_categoria (categoria, criado_em),
        KEY ix_req (req_id),
        KEY ix_selo (selo_seq),
        KEY ix_pendente (hash(1), id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS atlas_auditoria_dados (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        criado_em DATETIME(3) NOT NULL,
        banco VARCHAR(64) NOT NULL DEFAULT '',
        tabela VARCHAR(64) NOT NULL,
        operacao CHAR(1) NOT NULL,
        registro_id VARCHAR(190) NULL,
        usuario VARCHAR(100) NULL,
        req_id CHAR(16) NULL,
        ip VARCHAR(45) NULL,
        modulo VARCHAR(40) NULL,
        conexao BIGINT UNSIGNED NULL,
        campos VARCHAR(2000) NULL,
        antes LONGTEXT NULL,
        depois LONGTEXT NULL,
        selo_seq BIGINT UNSIGNED NULL,
        hash_anterior CHAR(64) NULL,
        hash CHAR(64) NULL,
        KEY ix_criado (criado_em),
        KEY ix_tabela (tabela, registro_id),
        KEY ix_req (req_id),
        KEY ix_conexao (conexao, req_id),
        KEY ix_usuario (usuario, criado_em),
        KEY ix_selo (selo_seq),
        KEY ix_pendente (hash(1), id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS atlas_auditoria_navegacao (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        criado_em DATETIME(3) NOT NULL,
        req_id CHAR(16) NOT NULL,
        usuario VARCHAR(100) NULL,
        nome VARCHAR(150) NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        sessao CHAR(16) NULL,
        modulo VARCHAR(40) NOT NULL DEFAULT 'inicio',
        pagina VARCHAR(190) NOT NULL DEFAULT '',
        consulta VARCHAR(300) NULL,
        duracao_ms INT NULL,
        status_http SMALLINT NULL,
        fora_expediente TINYINT(1) NOT NULL DEFAULT 0,
        selo_seq BIGINT UNSIGNED NULL,
        hash_anterior CHAR(64) NULL,
        hash CHAR(64) NULL,
        KEY ix_criado (criado_em),
        KEY ix_usuario (usuario, criado_em),
        KEY ix_modulo (modulo, criado_em),
        KEY ix_selo (selo_seq),
        KEY ix_pendente (hash(1), id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!(int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'atlas_auditoria_dados' AND INDEX_NAME = 'ix_conexao'")->fetchColumn()) {
        $pdo->exec('ALTER TABLE atlas_auditoria_dados ADD KEY ix_conexao (conexao, req_id)');
    }
    aud_meta_set('estrutura_versao', ATLAS_AUD_LIB, $pdo);
    if (aud_meta_get('instalado_em', '', $pdo) === '') {
        aud_meta_set('instalado_em', aud_agora(), $pdo);
    }
    $ok = true;
}

function aud_meta_get(string $chave, $padrao = null, ?PDO $pdo = null)
{
    try {
        $st = ($pdo ?: aud_pdo())->prepare('SELECT valor FROM atlas_auditoria_meta WHERE chave = ?');
        $st->execute([$chave]);
        $v = $st->fetchColumn();
        return $v === false ? $padrao : $v;
    } catch (Throwable $e) {
        return $padrao;
    }
}

function aud_meta_set(string $chave, $valor, ?PDO $pdo = null): void
{
    $st = ($pdo ?: aud_pdo())->prepare(
        'INSERT INTO atlas_auditoria_meta (chave, valor, atualizado_em) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()'
    );
    $st->execute([$chave, is_scalar($valor) || $valor === null ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE)]);
}

/* ============================ Configuração ============================ */

function aud_config_padrao(): array
{
    return [
        'ativa'                => 1,      // trilha de operações
        'navegacao'            => 1,      // registrar as telas abertas (GET de página)
        'dados'                => 1,      // gatilhos com antes/depois dos registros
        'retencao_dias'        => 0,      // 0 = guardar para sempre
        'retencao_navegacao'   => 180,    // telas abertas guardadas por N dias
        'tabelas_excluidas'    => [],     // tabelas sem captura de dados (além das de log)
        'expediente_inicio'    => '07:00',
        'expediente_fim'       => '19:00',
        'expediente_dias'      => [1, 2, 3, 4, 5, 6],   // 1 = segunda ... 7 = domingo
    ];
}

function aud_config(bool $recarregar = false): array
{
    static $cfg = null;
    if ($cfg !== null && !$recarregar) {
        return $cfg;
    }
    $cfg = aud_config_padrao();
    $j = json_decode((string) aud_meta_get('config', ''), true);
    if (is_array($j)) {
        foreach ($cfg as $k => $v) {
            if (array_key_exists($k, $j)) {
                $cfg[$k] = $j[$k];
            }
        }
    }
    return $cfg;
}

function aud_config_salvar(array $novo): array
{
    $cfg = aud_config(true);
    $p = aud_config_padrao();
    foreach ($p as $k => $padrao) {
        if (!array_key_exists($k, $novo)) {
            continue;
        }
        $v = $novo[$k];
        if (is_int($padrao)) {
            $v = max(0, (int) $v);
        } elseif (is_array($padrao)) {
            $v = array_values(array_filter(array_map('strval', (array) $v), 'strlen'));
            if ($k === 'expediente_dias') {
                $v = array_values(array_unique(array_map('intval', $v)));
            }
        } else {
            $v = preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : $padrao;
        }
        $cfg[$k] = $v;
    }
    aud_meta_set('config', json_encode($cfg, JSON_UNESCAPED_UNICODE));
    return aud_config(true);
}

/** A data/hora informada cai fora do expediente configurado? */
function aud_fora_expediente(string $quando, ?array $cfg = null): bool
{
    $cfg = $cfg ?: aud_config();
    $t = strtotime($quando);
    if (!$t) {
        return false;
    }
    $dia = (int) date('N', $t);
    if (!in_array($dia, array_map('intval', (array) $cfg['expediente_dias']), true)) {
        return true;
    }
    $hm = date('H:i', $t);
    return $hm < $cfg['expediente_inicio'] || $hm >= $cfg['expediente_fim'];
}

/* ============================ Módulos ============================ */

/** Rótulos dos módulos (chave = pasta do Atlas). */
function aud_modulos(): array
{
    return [
        'acesso'                   => 'Acesso ao sistema',
        'inicio'                   => 'Tela inicial',
        'funcionarios'             => 'Funcionários e permissões',
        'serventia'                => 'Dados da serventia',
        'os'                       => 'Ordens de Serviço',
        'caixa'                    => 'Fluxo de Caixa',
        'contas_a_pagar'           => 'Contas a Pagar',
        'tarefas'                  => 'Tarefas',
        'pedidos_certidao'         => 'Pedidos de Certidão',
        'nota_devolutiva'          => 'Notas Devolutivas',
        'oficios'                  => 'Ofícios',
        'arquivamento'             => 'Arquivamento',
        'indexador'                => 'Indexador',
        'iris'                     => 'Iris',
        'signum'                   => 'Signum',
        'vertex'                   => 'Vertex',
        'dimensor'                 => 'Vertex (Dimensor)',
        'gnomon'                   => 'Gnômon (ponto)',
        'prov213'                  => 'Provimento 213',
        'agendamento'              => 'Agendamento',
        'checklist'                => 'Checklists',
        'guia_de_recebimento'      => 'Guia de Recebimento',
        'provimentos'              => 'Provimentos e Resoluções',
        'suas_notas'               => 'Anotações',
        'relatorios'               => 'Relatórios e Livros',
        'manuais'                  => 'Vídeos tutoriais',
        'tabela_de_emolumentos'    => 'Tabela de Emolumentos',
        'selador'                  => 'Selador',
        'reurb'                    => 'REURB',
        'requerimento_retificacao' => 'Requerimento de Retificação',
        'oraculo'                  => 'Oráculo',
        'forja'                    => 'Forja',
        'kb'                       => 'Base de conhecimento',
        'analitico'                => 'Analítico de selos',
        'gerador_de_registro'      => 'Gerador de Registro',
        'casamento'                => 'Casamento',
        'obito'                    => 'Óbito',
        'ocr'                      => 'OCR',
        'agenda'                   => 'Agenda',
        'painel_admin'             => 'Painel do Administrador',
        '2fa'                      => 'Verificação em duas etapas',
        'update_atlas'             => 'Atualização do sistema',
        'sistema'                  => 'Sistema',
    ];
}

function aud_modulo_rotulo(string $m): string
{
    $l = aud_modulos();
    return $l[$m] ?? ucfirst(str_replace('_', ' ', $m));
}

/** Módulo de um script (caminho relativo à pasta do Atlas). */
function aud_modulo_do_script(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if (strpos($rel, '/') === false) {
        $b = strtolower(basename($rel, '.php'));
        if (in_array($b, ['login', 'check_login', 'logout', 'verificar-2fa'], true)) {
            return 'acesso';
        }
        if (preg_match('/funcionario|save_user|register|credenciais|checar_acesso/', $b)) {
            return 'funcionarios';
        }
        if (strpos($b, 'serventia') !== false) {
            return 'serventia';
        }
        if (strpos($b, 'tarefa') !== false || strpos($b, 'recorrente') !== false) {
            return 'tarefas';
        }
        if ($b === 'liberar_os') {
            return 'os';
        }
        return 'inicio';
    }
    $m = strtolower(strtok($rel, '/'));
    return $m === '' ? 'inicio' : $m;
}

/** Módulo dono de uma tabela do banco (para a trilha de dados). */
function aud_modulo_da_tabela(string $t): string
{
    $t = strtolower($t);
    $regras = [
        '/^(ordens_de_servico|pagamento_os|atos_(manuais_)?liquidados|devolucao_os|repasse_credor|os_|anexos_os|nfse_|pe_|api_|modelos_de_orcamento|configuracao_os|recibos_de_entrega)/' => 'os',
        '/^(caixa|deposito_caixa|saidas_despesas|transporte_saldo_caixa)/' => 'caixa',
        '/^(contas_|conta_)/' => 'contas_a_pagar',
        '/^(tarefas|comentarios|categorias|origem|equipe)/' => 'tarefas',
        '/^(pedidos_certidao|pedido_)/' => 'pedidos_certidao',
        '/^(notas_devolutivas|nota_anexos)/' => 'nota_devolutiva',
        '/^oficios/' => 'oficios',
        '/^(arquivamentos|selos_arquivamentos)/' => 'arquivamento',
        '/^indexador_/' => 'indexador',
        '/^iris_/' => 'iris',
        '/^assinatura_/' => 'signum',
        '/^memoriais_/' => 'vertex',
        '/^funcionarios/' => 'funcionarios',
        '/^cadastro_serventia/' => 'serventia',
        '/^tabela_emolumentos/' => 'tabela_de_emolumentos',
        '/^(provimentos|decisao)/' => 'provimentos',
        '/^agendamento/' => 'agendamento',
        '/^checklist/' => 'checklist',
        '/^(guia_de_recebimento|triagem_comunitario)/' => 'guia_de_recebimento',
        '/^manuais/' => 'manuais',
        '/^(requerimentos|selos_requerimentos)/' => 'requerimento_retificacao',
        '/^(relatorios_analiticos|selos)/' => 'analitico',
        '/^(pt_|ponto_|gnomon)/' => 'gnomon',
        '/^(p213|prov213)/' => 'prov213',
        '/^(kb_)/' => 'kb',
        '/^(chave_api_oraculo|oraculo)/' => 'oraculo',
        '/^(conexao_selador|selador)/' => 'selador',
        '/^(autotutela|registros_cedulas)/' => 'oficios',
        '/^painel_/' => 'painel_admin',
    ];
    foreach ($regras as $re => $m) {
        if (preg_match($re, $t)) {
            return $m;
        }
    }
    return 'sistema';
}

/** Tabelas que não recebem gatilho (são as próprias trilhas, logs, filas ou cache). */
function aud_tabela_ignorada(string $t): bool
{
    $t = strtolower($t);
    return (bool) preg_match(
        '/^(atlas_auditoria|atlas_migracoes|logs_|modo_usuario|api_log|api_outbox|api_idempotencia|iris_chamadas|tarefas_ia_uso|tarefas_ia_conversas|painel_ia_)|(_log|_logs|_auditoria|_auditoria_meta|_historico|_cache|_sessoes|_backup)$/',
        $t
    );
}

/* ============================ Classificação das operações ============================ */

/** Dicionário para transformar nomes de arquivo em rótulos legíveis. */
function aud_termos(): array
{
    return [
        'save' => 'salvar', 'salvar' => 'salvar', 'delete' => 'excluir', 'excluir' => 'excluir', 'remove' => 'remover',
        'remover' => 'remover', 'update' => 'atualizar', 'atualizar' => 'atualizar', 'edit' => 'editar', 'editar' => 'editar',
        'add' => 'adicionar', 'adicionar' => 'adicionar', 'create' => 'criar', 'criar' => 'criar', 'new' => 'novo',
        'get' => 'consultar', 'load' => 'carregar', 'list' => 'listar', 'listar' => 'listar', 'buscar' => 'buscar',
        'search' => 'pesquisar', 'upload' => 'enviar', 'download' => 'baixar', 'export' => 'exportar', 'print' => 'imprimir',
        'task' => 'tarefa', 'tasks' => 'tarefas', 'sub' => 'sub', 'comment' => 'comentário', 'attachment' => 'anexo',
        'category' => 'categoria', 'status' => 'status', 'user' => 'usuário', 'order' => 'ordem', 'mode' => 'modo',
        'os' => 'O.S.', 'pdf' => 'PDF', 'ia' => 'IA', 'modelos' => 'modelos', 'analisar' => 'analisar', 'gatilhos' => 'gatilhos', 'reinstalar' => 'reinstalar', 'expurgar' => 'expurgar', 'csv' => 'CSV', 'xml' => 'XML', 'nfse' => 'NFS-e', 'config' => 'configuração',
        'payment' => 'pagamento', 'file' => 'arquivo', 'image' => 'imagem', 'manual' => 'manual', 'note' => 'anotação',
        'check' => 'verificar', 'login' => 'login', 'logout' => 'saída', 'tfa' => '2FA', '2fa' => '2FA',
    ];
}

function aud_rotulo_script(string $rel): string
{
    $b = basename($rel, '.php');
    $partes = preg_split('/[_\-\s]+/', $b);
    $t = aud_termos();
    $out = [];
    foreach ($partes as $p) {
        if ($p === '') {
            continue;
        }
        $k = strtolower($p);
        $out[] = $t[$k] ?? $p;
    }
    $s = implode(' ', $out);
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/**
 * Classifica uma operação a partir do script, do método e dos parâmetros.
 * Devolve [categoria, severidade, rótulo da ação].
 */
function aud_classificar(string $rel, string $metodo, array $params, string $modulo): array
{
    $b = strtolower(basename($rel, '.php'));
    $acaoParam = '';
    foreach (['acao', 'action', 'op', 'operacao', 'tipo_acao', 'cmd', 'modo_acao'] as $k) {
        if (isset($params[$k]) && is_scalar($params[$k]) && $params[$k] !== '') {
            $acaoParam = mb_substr((string) $params[$k], 0, 60);
            break;
        }
    }
    $texto = $b . ' ' . strtolower($acaoParam);

    if ($acaoParam !== '' && preg_match('/^(api|ajax|acoes|actions|acao|index|controller|router|endpoint|processa|processar)$/', $b)) {
        $rotulo = aud_rotulo_script(str_replace('-', '_', $acaoParam) . '.php');
    } else {
        $rotulo = aud_rotulo_script($rel) . ($acaoParam !== '' ? ' · ' . $acaoParam : '');
    }

    if ($modulo === 'acesso') {
        return ['acesso', 'info', $rotulo];
    }
    $regras = [
        // [padrão, categoria, severidade]
        ['/senha|password|credenc|2fa|tfa|totp|permiss|nivel_acesso|acesso_adicional|masterkey|api_key|chave_api/', 'seguranca', 'critico'],
        ['/estorn|desfazer|reabrir|liberar|desbloque|cancelar_fechamento/', 'estorno', 'critico'],
        ['/excluir|exclui|delete|remov|apagar|lixeira|purge|limpar|descartar/', 'exclusao', 'critico'],
        ['/cancel/', 'cancelamento', 'critico'],
        ['/config|configur|parametr|preferenc|ajuste/', 'configuracao', 'aviso'],
        ['/export|download|baixar|csv|xlsx|planilha|imprimir|print|pdf|livro|relatorio|espelho|comprovante|recibo|gerar_arquivo|carga/', 'documento', 'info'],
        ['/assin|selar|selo|signum|carimb/', 'documento', 'aviso'],
        ['/pagamento|pagar|caixa|deposito|saida|despesa|devolu|repasse|transfer|liquid|fatur|nfse|boleto|pix|conta/', 'financeiro', 'aviso'],
        ['/edit|update|atualiz|alter|salvar_edicao|save_task_edit|status|mover|reordenar|ordem/', 'alteracao', 'info'],
        ['/save|salvar|cadastr|criar|create|add|adicionar|inserir|insert|novo|nova|registrar|enviar|upload|importar/', 'criacao', 'info'],
    ];
    foreach ($regras as [$re, $cat, $sev]) {
        if (preg_match($re, $texto)) {
            if ($cat === 'documento' && $metodo !== 'GET' && preg_match('/salvar|save|cadastr|upload|enviar/', $texto)) {
                $cat = 'criacao';
            }
            return [$cat, $sev, $rotulo];
        }
    }
    if ($metodo === 'GET') {
        return ['navegacao', 'info', $rotulo];
    }
    return ['outros', 'info', $rotulo];
}

/** Categorias (rótulo, ícone Font Awesome 5, cor). */
function aud_categorias(): array
{
    return [
        'acesso'       => ['Acesso', 'fa-sign-in-alt', '#6366f1'],
        'navegacao'    => ['Navegação', 'fa-mouse-pointer', '#94a3b8'],
        'criacao'      => ['Inclusão', 'fa-plus-circle', '#10b981'],
        'alteracao'    => ['Alteração', 'fa-pen', '#3b82f6'],
        'exclusao'     => ['Exclusão', 'fa-trash-alt', '#ef4444'],
        'cancelamento' => ['Cancelamento', 'fa-ban', '#f97316'],
        'estorno'      => ['Estorno / liberação', 'fa-undo-alt', '#e11d48'],
        'financeiro'   => ['Financeiro', 'fa-coins', '#f59e0b'],
        'documento'    => ['Documentos e exportações', 'fa-file-export', '#8b5cf6'],
        'configuracao' => ['Configuração', 'fa-sliders-h', '#0ea5e9'],
        'seguranca'    => ['Segurança', 'fa-shield-alt', '#dc2626'],
        'outros'       => ['Outras operações', 'fa-circle', '#64748b'],
    ];
}

/* ============================ Parâmetros ============================ */

function aud_chave_sensivel(string $k): bool
{
    return (bool) preg_match('/senha|password|passwd|pass$|^pwd|token|secret|segredo|chave|api_?key|apikey|pin$|^pin|cvv|cartao_numero|card|masterkey|tfa_secret|code$|csrf|_nonce$|cert|pfx|p12|private_key/i', $k);
}

/** Copia os parâmetros, mascarando segredos e encurtando textos longos. */
function aud_limpar_parametros($v, string $chave = '', int $nivel = 0)
{
    if ($nivel > 4) {
        return '…';
    }
    if (is_array($v)) {
        $out = [];
        $i = 0;
        foreach ($v as $k => $x) {
            if (++$i > 60) {
                $out['…'] = (count($v) - 60) . ' itens a mais';
                break;
            }
            $out[$k] = aud_chave_sensivel((string) $k) ? '••••••' : aud_limpar_parametros($x, (string) $k, $nivel + 1);
        }
        return $out;
    }
    if (is_bool($v) || $v === null || is_int($v) || is_float($v)) {
        return $v;
    }
    $s = (string) $v;
    if ($chave !== '' && aud_chave_sensivel($chave)) {
        return '••••••';
    }
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
    // Textos de editor (CKEditor etc.) chegam em HTML com acentos codificados: guarda legível
    $s = aud_html_para_texto($s);
    if (mb_strlen($s) > 600) {
        return mb_substr($s, 0, 500) . '… (' . number_format(mb_strlen($s), 0, ',', '.') . ' caracteres)';
    }
    return $s;
}

/**
 * Converte HTML (de editores de texto) em texto legível: decodifica &atilde;, &ccedil;,
 * &ordm; etc., troca parágrafos/quebras por nova linha e remove as marcas.
 * Texto comum (sem marcas nem entidades) volta inalterado.
 */
function aud_html_para_texto(string $s): string
{
    if (strpos($s, '<') === false && strpos($s, '&') === false) {
        return $s;
    }
    $temHtml = (bool) preg_match('/<\/?(p|br|div|span|strong|b|i|em|u|li|ul|ol|table|tr|td|th|h[1-6]|blockquote|a|font|sub|sup)\b[^>]*>/i', $s);
    $temEntidade = (bool) preg_match('/&(#\d+|#x[0-9a-f]+|[a-z][a-z0-9]{1,31});/i', $s);
    if (!$temHtml && !$temEntidade) {
        return $s;
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    if ($temHtml) {
        $s = preg_replace('/<\s*br\s*\/?>/i', "\n", $s);
        $s = preg_replace('/<\/(p|div|li|tr|h[1-6]|blockquote)>/i', "\n", $s);
        $s = preg_replace('/<li\b[^>]*>/i', '• ', $s);
        $s = preg_replace('/<\/t[dh]>/i', ' | ', $s);
        $s = strip_tags($s);
    }
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);                       // &nbsp;
    $s = preg_replace("/[ \t]+/u", ' ', $s);
    $s = preg_replace("/ *\n */u", "\n", $s);
    $s = preg_replace("/\n{3,}/u", "\n\n", $s);
    return trim($s);
}

/** Identificadores do registro alvo (id da O.S., da tarefa etc.). */
function aud_alvo(array $p): string
{
    $chaves = ['os_id', 'ordem_servico_id', 'ordem_de_servico_id', 'id_os', 'task_id', 'tarefa_id', 'id_tarefa', 'pedido_id',
               'nota_id', 'caixa_id', 'conta_id', 'oficio_id', 'protocolo', 'numero', 'token', 'id', 'codigo', 'usuario_id',
               'funcionario_id', 'registro_id', 'documento_id', 'extracao_id', 'arquivo', 'data_caixa'];
    $out = [];
    foreach ($chaves as $k) {
        if (isset($p[$k]) && is_scalar($p[$k]) && $p[$k] !== '' && !aud_chave_sensivel($k)) {
            $out[] = $k . '=' . mb_substr((string) $p[$k], 0, 40);
            if (count($out) >= 4) {
                break;
            }
        }
    }
    return implode(' · ', $out);
}

/* ============================ Gravação de eventos ============================ */

/**
 * Grava uma operação na trilha. Campos aceitos: os da tabela atlas_auditoria.
 * Usado pelo gancho (automático) e por eventos explícitos (ex.: acesso negado).
 */
function aud_registrar(array $ev): ?int
{
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $cfg = aud_config();
        if (empty($cfg['ativa'])) {
            return null;
        }
        $quando = $ev['criado_em'] ?? aud_agora();
        $campos = [
            'criado_em'      => $quando,
            'req_id'         => $ev['req_id'] ?? bin2hex(random_bytes(8)),
            'usuario'        => aud_txt($ev['usuario'] ?? null, 100),
            'nome'           => aud_txt($ev['nome'] ?? null, 150),
            'nivel'          => aud_txt($ev['nivel'] ?? null, 30),
            'ip'             => aud_txt($ev['ip'] ?? aud_ip(), 45),
            'user_agent'     => aud_txt($ev['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null), 255),
            'sessao'         => $ev['sessao'] ?? aud_sessao_hash(),
            'modulo'         => aud_txt($ev['modulo'] ?? 'sistema', 40),
            'pagina'         => aud_txt($ev['pagina'] ?? '', 190),
            'metodo'         => aud_txt($ev['metodo'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 8),
            'acao'           => aud_txt($ev['acao'] ?? '', 190),
            'categoria'      => aud_txt($ev['categoria'] ?? 'outros', 20),
            'severidade'     => aud_txt($ev['severidade'] ?? 'info', 10),
            'resultado'      => aud_txt($ev['resultado'] ?? 'sucesso', 10),
            'mensagem'       => aud_txt($ev['mensagem'] ?? null, 500),
            'alvo'           => aud_txt($ev['alvo'] ?? null, 255),
            'parametros'     => isset($ev['parametros']) ? (is_string($ev['parametros']) ? $ev['parametros'] : json_encode($ev['parametros'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)) : null,
            'status_http'    => isset($ev['status_http']) ? (int) $ev['status_http'] : null,
            'duracao_ms'     => isset($ev['duracao_ms']) ? (int) $ev['duracao_ms'] : null,
            'dados_resumo'   => aud_txt($ev['dados_resumo'] ?? null, 1000),
            'dados_linhas'   => (int) ($ev['dados_linhas'] ?? 0),
            'fora_expediente'=> aud_fora_expediente($quando, $cfg) ? 1 : 0,
        ];
        $cols = implode(',', array_keys($campos));
        $qs = implode(',', array_fill(0, count($campos), '?'));
        $st = $pdo->prepare("INSERT INTO atlas_auditoria ($cols) VALUES ($qs)");
        $st->execute(array_values($campos));
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] registrar: ' . $e->getMessage());
        return null;
    }
}

/** Grava uma tela aberta (trilha de navegação, mais leve). */
function aud_registrar_navegacao(array $ev): void
{
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $cfg = aud_config();
        if (empty($cfg['ativa']) || empty($cfg['navegacao'])) {
            return;
        }
        $quando = $ev['criado_em'] ?? aud_agora();
        $st = $pdo->prepare('INSERT INTO atlas_auditoria_navegacao
            (criado_em, req_id, usuario, nome, ip, user_agent, sessao, modulo, pagina, consulta, duracao_ms, status_http, fora_expediente)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $quando, $ev['req_id'], aud_txt($ev['usuario'] ?? null, 100), aud_txt($ev['nome'] ?? null, 150),
            aud_txt($ev['ip'] ?? aud_ip(), 45), aud_txt($ev['user_agent'] ?? null, 255), $ev['sessao'] ?? null,
            aud_txt($ev['modulo'] ?? 'inicio', 40), aud_txt($ev['pagina'] ?? '', 190), aud_txt($ev['consulta'] ?? null, 300),
            isset($ev['duracao_ms']) ? (int) $ev['duracao_ms'] : null, isset($ev['status_http']) ? (int) $ev['status_http'] : null,
            aud_fora_expediente($quando, $cfg) ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] navegacao: ' . $e->getMessage());
    }
}

/**
 * Atalho para módulos registrarem um evento de negócio explicitamente:
 *   atlas_auditar('Fechamento de caixa desfeito', ['categoria'=>'estorno','severidade'=>'critico','alvo'=>'caixa_id=3']);
 */
function atlas_auditar(string $acao, array $extra = []): void
{
    $s = function_exists('atlas_aud_contexto') ? atlas_aud_contexto() : [];
    aud_registrar(array_merge([
        'req_id'   => $s['req'] ?? null,
        'usuario'  => $s['usuario'] ?? ($_SESSION['username'] ?? null),
        'nome'     => $_SESSION['nome_completo'] ?? null,
        'nivel'    => $_SESSION['nivel_de_acesso'] ?? null,
        'modulo'   => $s['modulo'] ?? 'sistema',
        'pagina'   => $s['pagina'] ?? '',
        'acao'     => $acao,
        'categoria'=> 'outros',
    ], $extra));
}

function aud_txt($v, int $max): ?string
{
    if ($v === null) {
        return null;
    }
    $v = (string) $v;
    if (!mb_check_encoding($v, 'UTF-8')) {
        $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
    }
    return mb_substr($v, 0, $max);
}

function aud_ip(): ?string
{
    if (PHP_SAPI === 'cli') {
        return 'cli';
    }
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            return $ip === '::1' ? '127.0.0.1' : $ip;
        }
    }
    return null;
}

function aud_sessao_hash(): ?string
{
    $sid = session_status() === PHP_SESSION_ACTIVE ? session_id() : ($_COOKIE[session_name()] ?? '');
    return $sid ? substr(hash('sha256', 'atlas|' . $sid), 0, 16) : null;
}

/* ============================ Selagem (cadeia de hash) ============================ */

function aud_hash_linha(string $cadeia, array $r, string $anterior): string
{
    if ($cadeia === 'dados') {
        $partes = [$r['id'], $r['criado_em'], $r['banco'], $r['tabela'], $r['operacao'], $r['registro_id'], $r['usuario'],
                   $r['req_id'], $r['campos'], $r['antes'], $r['depois']];
    } elseif ($cadeia === 'navegacao') {
        $partes = [$r['id'], $r['criado_em'], $r['req_id'], $r['usuario'], $r['ip'], $r['modulo'], $r['pagina'], $r['consulta']];
    } else {
        $partes = [$r['id'], $r['criado_em'], $r['req_id'], $r['usuario'], $r['ip'], $r['modulo'], $r['pagina'], $r['metodo'],
                   $r['acao'], $r['categoria'], $r['resultado'], $r['alvo'], $r['parametros'], $r['dados_resumo']];
    }
    return hash('sha256', $anterior . '|' . implode('|', array_map(function ($x) { return (string) $x; }, $partes)));
}

function aud_tabela_cadeia(string $cadeia): string
{
    return ['dados' => 'atlas_auditoria_dados', 'navegacao' => 'atlas_auditoria_navegacao'][$cadeia] ?? 'atlas_auditoria';
}

function aud_cadeias(): array
{
    return ['operacoes' => 'Operações', 'dados' => 'Alterações de dados', 'navegacao' => 'Telas abertas'];
}

/**
 * Sela as linhas ainda sem hash, na ordem em que aparecem. Rápido quando não
 * há pendências; processa no máximo $limite linhas por chamada.
 */
function aud_selar(int $limite = 300): array
{
    $feito = ['operacoes' => 0, 'dados' => 0, 'navegacao' => 0];
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $lk = (int) $pdo->query("SELECT GET_LOCK('atlas_aud_selo', 0)")->fetchColumn();
        if ($lk !== 1) {
            return $feito;
        }
        try {
            foreach (array_keys(aud_cadeias()) as $cadeia) {
                $tab = aud_tabela_cadeia($cadeia);
                // Só sela linhas com alguns segundos de vida: uma transação ainda aberta
                // pode ter um id menor que ainda não ficou visível.
                // O corte é calculado no próprio MySQL (sessão em -03:00), igual aos gatilhos.
                // Na trilha de dados, linhas ainda sem dono (aguardando a requisição as reivindicar)
                // só são seladas depois de 5 minutos, para o selo não ser feito antes do vínculo.
                $extra = $cadeia === 'dados'
                    ? " AND (criado_em <= NOW(3) - INTERVAL 300 SECOND OR (req_id IS NOT NULL AND usuario IS NOT NULL AND usuario <> ''))"
                    : '';
                // Primeiro só os ids; depois UMA linha por vez (o antes/depois pode ser grande:
                // carregar o lote inteiro de uma vez elevava a memória da requisição em dezenas de MB).
                $ids = $pdo->query("SELECT id FROM $tab WHERE hash IS NULL AND criado_em <= NOW(3) - INTERVAL 5 SECOND$extra ORDER BY id ASC LIMIT " . (int) $limite)
                           ->fetchAll(PDO::FETCH_COLUMN);
                if (!$ids) {
                    continue;
                }
                $seq = (int) aud_meta_get("selo_{$cadeia}_seq", 0, $pdo);
                $ant = (string) aud_meta_get("selo_{$cadeia}_hash", '', $pdo);
                $up = $pdo->prepare("UPDATE $tab SET selo_seq = ?, hash_anterior = ?, hash = ? WHERE id = ? AND hash IS NULL");
                $um = $pdo->prepare("SELECT * FROM $tab WHERE id = ? AND hash IS NULL");
                $pdo->beginTransaction();
                foreach ($ids as $idLinha) {
                    $um->execute([(int) $idLinha]);
                    $r = $um->fetch();
                    $um->closeCursor();
                    if (!$r) {
                        continue;
                    }
                    $seq++;
                    $h = aud_hash_linha($cadeia, $r, $ant);
                    $up->execute([$seq, $ant, $h, $r['id']]);
                    $ant = $h;
                    $feito[$cadeia]++;
                    unset($r);
                }
                aud_meta_set("selo_{$cadeia}_seq", $seq, $pdo);
                aud_meta_set("selo_{$cadeia}_hash", $ant, $pdo);
                $pdo->commit();
                aud_ancora_gravar($cadeia, $seq, $ant);
            }
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->query("SELECT RELEASE_LOCK('atlas_aud_selo')");
        }
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] selar: ' . $e->getMessage());
    }
    return $feito;
}

/** Âncora fora do banco: guarda o último selo de cada trilha. */
function aud_ancora_gravar(string $cadeia, int $seq, string $hash): void
{
    if (!is_dir(ATLAS_AUD_DADOS_DIR)) {
        @mkdir(ATLAS_AUD_DADOS_DIR, 0775, true);
    }
    $f = ATLAS_AUD_DADOS_DIR . '/ancora.json';
    $a = is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    $a[$cadeia] = ['seq' => $seq, 'hash' => $hash, 'em' => aud_agora()];
    @file_put_contents($f, json_encode($a, JSON_PRETTY_PRINT), LOCK_EX);
}

function aud_ancora_ler(): array
{
    $f = ATLAS_AUD_DADOS_DIR . '/ancora.json';
    return is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
}

/**
 * Confere uma trilha inteira (ou a partir do início após expurgo).
 * @return array{ok:bool,total:int,quebras:array,motivo:string,ultimo_seq:int,pendentes:int}
 */
function aud_verificar(string $cadeia): array
{
    $pdo = aud_pdo();
    aud_estrutura($pdo);
    aud_selar();
    $tab = aud_tabela_cadeia($cadeia);
    $inicio = json_decode((string) aud_meta_get("inicio_{$cadeia}", ''), true) ?: ['seq' => 1, 'hash_anterior' => ''];
    $esperado = (int) $inicio['seq'];
    $ant = (string) $inicio['hash_anterior'];
    $total = 0;
    $quebras = [];
    $motivo = '';
    // Em páginas de 25 linhas: o driver do MySQL traria a trilha inteira para a memória de uma vez.
    $pg = $pdo->prepare("SELECT * FROM $tab WHERE selo_seq >= ? ORDER BY selo_seq ASC LIMIT 25");
    $desde = $esperado;
    $parar = false;
    while (!$parar) {
    $pg->execute([$desde]);
    $pagina = $pg->fetchAll();
    $pg->closeCursor();
    if (!$pagina) {
        break;
    }
    $desde = (int) end($pagina)['selo_seq'] + 1;
    foreach ($pagina as $r) {
        $total++;
        $seq = (int) $r['selo_seq'];
        if ($seq !== $esperado) {
            $quebras[] = ['id' => (int) $r['id'], 'seq' => $seq, 'motivo' => ($seq - $esperado) . ' registro(s) selado(s) antes deste foram apagados'];
            $ant = (string) $r['hash_anterior'];
        }
        if ((string) $r['hash_anterior'] !== $ant) {
            $quebras[] = ['id' => (int) $r['id'], 'seq' => $seq, 'motivo' => 'o registro anterior foi alterado ou apagado'];
        } elseif (!hash_equals((string) $r['hash'], aud_hash_linha($cadeia, $r, $ant))) {
            $quebras[] = ['id' => (int) $r['id'], 'seq' => $seq, 'motivo' => 'o conteúdo foi alterado depois de selado'];
        }
        $ant = (string) $r['hash'];
        $esperado = $seq + 1;
        if (count($quebras) >= 50) {
            $parar = true;
            break;
        }
    }
    unset($pagina);
    }
    $ultimo = $esperado - 1;
    $anc = aud_ancora_ler()[$cadeia] ?? null;
    if ($anc && (int) $anc['seq'] > $ultimo) {
        $quebras[] = ['id' => null, 'seq' => (int) $anc['seq'], 'motivo' => 'os últimos ' . ((int) $anc['seq'] - $ultimo) . ' registro(s) selado(s) foram apagados (a âncora registrou até o selo ' . (int) $anc['seq'] . ')'];
    } elseif ($anc) {
        $st = $pdo->prepare("SELECT hash FROM $tab WHERE selo_seq = ?");
        $st->execute([(int) $anc['seq']]);
        $h = $st->fetchColumn();
        if ($h !== false && !hash_equals((string) $anc['hash'], (string) $h)) {
            $quebras[] = ['id' => null, 'seq' => (int) $anc['seq'], 'motivo' => 'a trilha não confere com a âncora gravada em arquivo'];
        }
    }
    $pend = (int) $pdo->query("SELECT COUNT(*) FROM $tab WHERE hash IS NULL")->fetchColumn();
    if ($quebras) {
        $motivo = $quebras[0]['motivo'];
    }
    return ['ok' => !$quebras, 'total' => $total, 'quebras' => $quebras, 'motivo' => $motivo, 'ultimo_seq' => $ultimo, 'pendentes' => $pend];
}

/* ============================ Retenção ============================ */

/** Apaga o que passou do prazo de guarda e registra o novo início da cadeia. */
function aud_expurgar(): array
{
    $res = ['operacoes' => 0, 'navegacao' => 0, 'dados' => 0];
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $cfg = aud_config();
        aud_selar();
        $regras = [];
        if ((int) $cfg['retencao_navegacao'] > 0) {
            $regras[] = ['navegacao', (int) $cfg['retencao_navegacao']];
        }
        if ((int) $cfg['retencao_dias'] > 0) {
            $regras[] = ['operacoes', (int) $cfg['retencao_dias']];
            $regras[] = ['dados', (int) $cfg['retencao_dias']];
        }
        foreach ($regras as [$cadeia, $dias]) {
            $chave = $cadeia;
            $where = 'criado_em < ?';
            $tab = aud_tabela_cadeia($cadeia);
            $limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
            $st = $pdo->prepare("SELECT MAX(selo_seq) FROM $tab WHERE $where AND selo_seq IS NOT NULL");
            $st->execute([$limite]);
            $ate = (int) $st->fetchColumn();
            if ($ate <= 0) {
                continue;
            }
            $st = $pdo->prepare("SELECT hash FROM $tab WHERE selo_seq = ?");
            $st->execute([$ate]);
            $hashAte = (string) $st->fetchColumn();
            // Em lotes pequenos, pela chave primária: não segura travas sobre as gravações novas
            $sel = $pdo->prepare("SELECT id FROM $tab WHERE selo_seq IS NOT NULL AND selo_seq <= ? ORDER BY id LIMIT 1000");
            for ($lote = 0; $lote < 500; $lote++) {
                $sel->execute([$ate]);
                $ids = $sel->fetchAll(PDO::FETCH_COLUMN);
                if (!$ids) {
                    break;
                }
                $res[$chave] += $pdo->exec("DELETE FROM $tab WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')');
            }
            aud_meta_set("inicio_{$cadeia}", json_encode(['seq' => $ate + 1, 'hash_anterior' => $hashAte, 'em' => aud_agora()]), $pdo);
        }
        if (array_sum($res) > 0) {
            atlas_auditar('Expurgo da trilha de auditoria (prazo de guarda)', [
                'modulo' => 'painel_admin', 'categoria' => 'exclusao', 'severidade' => 'aviso',
                'mensagem' => json_encode($res), 'usuario' => 'sistema',
            ]);
        }
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] expurgo: ' . $e->getMessage());
    }
    return $res;
}

/* ============================ Gatilhos (trilha de dados) ============================ */

function aud_banco(): string
{
    return aud_cfg_conexao()['banco'];
}

/** Bancos auditados: o do Atlas e, se existir, o dos Ofícios. */
function aud_bancos_auditados(PDO $pdo): array
{
    $b = [aud_banco()];
    try {
        $st = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'oficios_db'");
        if ($st->fetchColumn()) {
            $b[] = 'oficios_db';
        }
    } catch (Throwable $e) {
    }
    return $b;
}

function aud_mysql_suporta(PDO $pdo): array
{
    $v = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $maria = stripos($v, 'mariadb') !== false;
    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $v, $m);
    $num = $m ? ((int) $m[1] * 10000 + (int) $m[2] * 100 + (int) $m[3]) : 0;
    $json = $maria ? $num >= 100203 : $num >= 50708;
    $multi = $maria ? $num >= 100203 : $num >= 50702;
    return ['versao' => $v, 'json' => $json, 'multiplos_gatilhos' => $multi];
}

/** Impressão digital das colunas auditáveis: muda quando uma tabela/coluna é criada ou alterada. */
function aud_esquema_assinatura(PDO $pdo): string
{
    $bancos = aud_bancos_auditados($pdo);
    $in = implode(',', array_fill(0, count($bancos), '?'));
    $st = $pdo->prepare("SELECT GROUP_CONCAT(CONCAT(TABLE_SCHEMA,'.',TABLE_NAME,'.',COLUMN_NAME,':',DATA_TYPE) ORDER BY TABLE_SCHEMA, TABLE_NAME, ORDINAL_POSITION SEPARATOR ';')
                         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA IN ($in)");
    $pdo->exec('SET SESSION group_concat_max_len = 16777216');
    $st->execute($bancos);
    $cfg = aud_config();
    return hash('sha256', (string) $st->fetchColumn() . '|' . json_encode($cfg['tabelas_excluidas']) . '|' . (int) $cfg['dados'] . '|' . ATLAS_AUD_LIB);
}

function aud_q(string $id): string
{
    return '`' . str_replace('`', '``', $id) . '`';
}

/** Expressão SQL do valor de uma coluna dentro do gatilho (binários viram descrição). */
function aud_expr_coluna(string $alias, array $c): string
{
    $col = $alias . '.' . aud_q($c['COLUMN_NAME']);
    $t = strtolower($c['DATA_TYPE']);
    // Segredos nunca vão para a trilha: registra só que mudou.
    $ehNomeDeConfig = !empty($c['_chave_col']) && strcasecmp($c['COLUMN_NAME'], $c['_chave_col']) === 0;
    if (!$ehNomeDeConfig && preg_match('/senha|password|passwd|pass$|_pass|^pass|pwd|tfa_secret|secret|token|api_key|apikey|_enc$|masterkey|pin_hash|chave_privada|certificado|cert_|_cert|pfx|p12|^chave$|^chave_api|private_key|client_secret/i', $c['COLUMN_NAME'])) {
        return "IF($col IS NULL, NULL, '[protegido]')";
    }
    // Tabelas de configuração no formato chave/valor: mascara o valor das chaves sensíveis.
    if (!empty($c['_chave_col']) && in_array(strtolower($c['COLUMN_NAME']), ['valor', 'value', 'conteudo'], true)) {
        $k = $alias . '.' . aud_q($c['_chave_col']);
        $base = in_array($t, ['text', 'mediumtext', 'longtext'], true) ? "LEFT($col, 60000)" : $col;
        return "IF($k REGEXP 'key|senha|password|token|secret|segredo|chave_api|credencial', IF($col IS NULL, NULL, '[protegido]'), $base)";
    }
    if (in_array($t, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary', 'geometry', 'point', 'polygon'], true)) {
        return "IF($col IS NULL, NULL, CONCAT('[binário: ', OCTET_LENGTH($col), ' bytes]'))";
    }
    if (in_array($t, ['text', 'mediumtext', 'longtext'], true)) {
        return "LEFT($col, 60000)";
    }
    return $col;
}

function aud_json_chave(string $col): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $col) . "'";
}

function aud_json_caminho(string $col): string
{
    return "'$.\"" . str_replace(["\\", "'", '"'], ["\\\\", "\\'", '\\\\"'], $col) . "\"'";
}

/** Gera o SQL dos três gatilhos (inclusão, alteração, exclusão) de uma tabela. */
function aud_sql_gatilhos(string $banco, string $tabela, array $cols, array $pk): array
{
    $destino = aud_q(aud_banco()) . '.`atlas_auditoria_dados`';
    $tb = aud_q($banco) . '.' . aud_q($tabela);
    $nomeBase = 'atlas_aud_' . substr(preg_replace('/[^a-z0-9_]/i', '_', $tabela), 0, 50);
    $offset = '-180';   // -03:00 em minutos
    $quando = "DATE_ADD(UTC_TIMESTAMP(3), INTERVAL $offset MINUTE)";
    $bancoLit = "'" . addslashes($banco) . "'";
    $tabLit = "'" . addslashes($tabela) . "'";
    $modLit = "'" . aud_modulo_da_tabela($tabela) . "'";
    $chave = function (string $alias) use ($pk, $cols) {
        $usa = $pk ?: [$cols[0]['COLUMN_NAME']];
        return 'CONCAT_WS(\'|\',' . implode(',', array_map(function ($c) use ($alias) { return $alias . '.' . aud_q($c); }, $usa)) . ')';
    };
    $objeto = function (string $alias) use ($cols) {
        $pares = [];
        foreach ($cols as $c) {
            $pares[] = aud_json_chave($c['COLUMN_NAME']) . ',' . aud_expr_coluna($alias, $c);
        }
        return 'JSON_OBJECT(' . implode(',', $pares) . ')';
    };
    $ctx = "COALESCE(@atlas_usuario, IF(@atlas_req IS NULL, NULL, '')), @atlas_req, @atlas_ip, COALESCE(@atlas_modulo, $modLit), CONNECTION_ID()";
    $insert = "INSERT INTO $destino (criado_em, banco, tabela, operacao, registro_id, usuario, req_id, ip, modulo, conexao, campos, antes, depois) VALUES ";

    $sqlI = "CREATE DEFINER = CURRENT_USER TRIGGER " . aud_q($banco) . "." . aud_q($nomeBase . '_ai') . " AFTER INSERT ON $tb FOR EACH ROW BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  IF @atlas_aud_off IS NULL THEN
    $insert($quando, $bancoLit, $tabLit, 'I', " . $chave('NEW') . ", $ctx, NULL, NULL, " . $objeto('NEW') . ");
  END IF;
END";

    $sqlD = "CREATE DEFINER = CURRENT_USER TRIGGER " . aud_q($banco) . "." . aud_q($nomeBase . '_ad') . " AFTER DELETE ON $tb FOR EACH ROW BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  IF @atlas_aud_off IS NULL THEN
    $insert($quando, $bancoLit, $tabLit, 'D', " . $chave('OLD') . ", $ctx, NULL, " . $objeto('OLD') . ", NULL);
  END IF;
END";

    $passos = [];
    foreach ($cols as $c) {
        $n = $c['COLUMN_NAME'];
        $o = 'OLD.' . aud_q($n);
        $nw = 'NEW.' . aud_q($n);
        $cam = aud_json_caminho($n);
        if (in_array(strtolower($c['DATA_TYPE']), ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'], true)) {
            $cmp = "NOT (BINARY $o <=> BINARY $nw)";   // pega mudança só de maiúsculas/acentos/espaços
        } else {
            $cmp = "NOT ($o <=> $nw)";
        }
        $passos[] = "    IF $cmp THEN
      SET a = JSON_SET(a, $cam, " . aud_expr_coluna('OLD', $c) . "), d = JSON_SET(d, $cam, " . aud_expr_coluna('NEW', $c) . "), cs = CONCAT_WS(',', cs, " . aud_json_chave($n) . ");
    END IF;";
    }
    $sqlU = "CREATE DEFINER = CURRENT_USER TRIGGER " . aud_q($banco) . "." . aud_q($nomeBase . '_au') . " AFTER UPDATE ON $tb FOR EACH ROW BEGIN
  DECLARE a LONGTEXT DEFAULT '{}';
  DECLARE d LONGTEXT DEFAULT '{}';
  DECLARE cs VARCHAR(2000) DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  IF @atlas_aud_off IS NULL THEN
" . implode("\n", $passos) . "
    IF cs IS NOT NULL THEN
      $insert($quando, $bancoLit, $tabLit, 'U', " . $chave('NEW') . ", $ctx, LEFT(cs, 2000), a, d);
    END IF;
  END IF;
END";
    return [
        $nomeBase . '_ai' => $sqlI,
        $nomeBase . '_au' => $sqlU,
        $nomeBase . '_ad' => $sqlD,
    ];
}

/**
 * Cria/atualiza os gatilhos de todas as tabelas auditáveis (ou remove todos,
 * se a trilha de dados estiver desligada). Só refaz quando o esquema mudou,
 * a menos que $forcar seja true.
 */
/**
 * Os gatilhos rodam com os privilégios do usuário que os criou (DEFINER). Se esse usuário
 * não existir mais (banco levado para outro servidor, usuário renomeado), toda gravação
 * nas tabelas falharia: nesse caso a sincronização recria os gatilhos com o usuário atual.
 */
function aud_gatilhos_dono_ok(PDO $pdo, array $bancos): bool
{
    try {
        $st = $pdo->prepare('SELECT DISTINCT DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA IN (' . implode(',', array_fill(0, count($bancos), '?')) . ") AND TRIGGER_NAME LIKE 'atlas\\_aud\\_%'");
        $st->execute($bancos);
        $donos = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$donos) {
            return true;
        }
        $chk = $pdo->prepare("SELECT COUNT(*) FROM mysql.user WHERE CONCAT(User, '@', Host) = ?");
        foreach ($donos as $d) {
            $chk->execute([str_replace('`', '', $d)]);
            if (!(int) $chk->fetchColumn()) {
                return false;
            }
        }
    } catch (Throwable $e) {
        // sem permissão para ler mysql.user: não dá para conferir, segue como está
    }
    return true;
}

function aud_gatilhos_sincronizar(bool $forcar = false): array
{
    $res = ['ok' => true, 'criadas' => 0, 'removidas' => 0, 'tabelas' => 0, 'erros' => [], 'ignorado' => false, 'mensagem' => ''];
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $sup = aud_mysql_suporta($pdo);
        if (!$sup['json']) {
            $res['ok'] = false;
            $res['mensagem'] = 'O MySQL/MariaDB desta serventia (' . $sup['versao'] . ') não tem funções JSON; a trilha de dados não pode ser ligada.';
            return $res;
        }
        $assin = aud_esquema_assinatura($pdo);
        if (!$forcar && aud_meta_get('gatilhos_assinatura', '', $pdo) === $assin) {
            // Mesmo esquema: confere se nenhum gatilho sumiu (tabela recriada por migração ou restauração de backup)
            $estado = json_decode((string) aud_meta_get('gatilhos_estado', ''), true) ?: [];
            $esperado = (int) ($estado['gatilhos'] ?? 0);
            $bancos = aud_bancos_auditados($pdo);
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA IN (' . implode(',', array_fill(0, count($bancos), '?')) . ") AND TRIGGER_NAME LIKE 'atlas\\_aud\\_%'");
            $st->execute($bancos);
            if (empty($estado['ligado']) || ((int) $st->fetchColumn() >= $esperado && aud_gatilhos_dono_ok($pdo, $bancos))) {
                $res['ignorado'] = true;
                return $res;
            }
        }
        $lk = (int) $pdo->query("SELECT GET_LOCK('atlas_aud_gatilhos', 0)")->fetchColumn();
        if ($lk !== 1) {
            $res['ignorado'] = true;
            return $res;
        }
        try {
            $cfg = aud_config();
            $excl = array_map('strtolower', (array) $cfg['tabelas_excluidas']);
            $bancos = aud_bancos_auditados($pdo);
            // Gatilhos atuais da auditoria
            $in = implode(',', array_fill(0, count($bancos), '?'));
            $st = $pdo->prepare("SELECT TRIGGER_SCHEMA, TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS
                                  WHERE TRIGGER_SCHEMA IN ($in) AND TRIGGER_NAME LIKE 'atlas\\_aud\\_%'");
            $st->execute($bancos);
            $existentes = [];
            foreach ($st->fetchAll() as $g) {
                $existentes[$g['TRIGGER_SCHEMA'] . '.' . $g['EVENT_OBJECT_TABLE']][] = $g['TRIGGER_NAME'];
            }
            $remover = function (string $chave) use (&$existentes, $pdo, &$res) {
                [$banco] = explode('.', $chave, 2);
                foreach ($existentes[$chave] ?? [] as $nomeG) {
                    try {
                        $pdo->exec('DROP TRIGGER IF EXISTS ' . aud_q($banco) . '.' . aud_q($nomeG));
                        $res['removidas']++;
                    } catch (Throwable $e) {
                        $res['erros'][] = $chave . ': não foi possível remover o gatilho (' . $e->getMessage() . ')';
                    }
                }
                unset($existentes[$chave]);
            };
            if (empty($cfg['dados'])) {
                foreach (array_keys($existentes) as $k) {
                    $remover($k);
                }
                aud_meta_set('gatilhos_assinatura', $assin, $pdo);
                aud_meta_set('gatilhos_estado', json_encode(['ligado' => false, 'tabelas' => 0, 'em' => aud_agora()]), $pdo);
                $res['mensagem'] = 'Trilha de dados desligada: gatilhos removidos.';
                return $res;
            }
            // Gatilhos de terceiros (para bancos antigos que só aceitam um gatilho por evento)
            $st = $pdo->prepare("SELECT TRIGGER_SCHEMA, EVENT_OBJECT_TABLE, EVENT_MANIPULATION FROM information_schema.TRIGGERS
                                  WHERE TRIGGER_SCHEMA IN ($in) AND TRIGGER_NAME NOT LIKE 'atlas\\_aud\\_%' AND ACTION_TIMING = 'AFTER'");
            $st->execute($bancos);
            $outros = [];
            foreach ($st->fetchAll() as $g) {
                $outros[$g['TRIGGER_SCHEMA'] . '.' . $g['EVENT_OBJECT_TABLE']][$g['EVENT_MANIPULATION']] = true;
            }

            $st = $pdo->prepare("SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES
                                  WHERE TABLE_SCHEMA IN ($in) AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_SCHEMA, TABLE_NAME");
            $st->execute($bancos);
            $tabelas = $st->fetchAll();
            $stCols = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY FROM information_schema.COLUMNS
                                      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
            $auditadas = [];
            foreach ($tabelas as $t) {
                $nome = $t['TABLE_NAME'];
                if (aud_tabela_ignorada($nome) || in_array(strtolower($nome), $excl, true)) {
                    continue;
                }
                $stCols->execute([$t['TABLE_SCHEMA'], $nome]);
                $cols = $stCols->fetchAll();
                if (!$cols) {
                    continue;
                }
                $nomesCols = array_map('strtolower', array_column($cols, 'COLUMN_NAME'));
                $chaveCol = null;
                foreach (['chave', 'parametro', 'config_key', 'nome_config'] as $kc) {
                    if (in_array($kc, $nomesCols, true)) {
                        $chaveCol = $cols[array_search($kc, $nomesCols, true)]['COLUMN_NAME'];
                        break;
                    }
                }
                if ($chaveCol && !array_intersect(['valor', 'value', 'conteudo'], $nomesCols)) {
                    $chaveCol = null;   // "chave" sem coluna de valor é a própria chave secreta (ex.: chave_api_oraculo)
                }
                if ($chaveCol) {
                    foreach ($cols as &$cc) {
                        $cc['_chave_col'] = $chaveCol;
                    }
                    unset($cc);
                }
                $pk = [];
                foreach ($cols as $c) {
                    if ($c['COLUMN_KEY'] === 'PRI') {
                        $pk[] = $c['COLUMN_NAME'];
                    }
                }
                $chaveOutros = $t['TABLE_SCHEMA'] . '.' . $nome;
                // Uma tabela por vez: remove os 3 gatilhos dela e já cria os novos (nunca fica tudo sem gatilho)
                $remover($chaveOutros);
                foreach (aud_sql_gatilhos($t['TABLE_SCHEMA'], $nome, $cols, $pk) as $nomeG => $sql) {
                    $evento = substr($nomeG, -2) === 'ai' ? 'INSERT' : (substr($nomeG, -2) === 'au' ? 'UPDATE' : 'DELETE');
                    if (!$sup['multiplos_gatilhos'] && !empty($outros[$chaveOutros][$evento])) {
                        $res['erros'][] = "$nome ($evento): já existe outro gatilho e este banco aceita apenas um por evento";
                        continue;
                    }
                    try {
                        $pdo->exec($sql);
                        $res['criadas']++;
                    } catch (Throwable $e) {
                        $res['erros'][] = "$nome ($evento): " . $e->getMessage();
                    }
                }
                $auditadas[] = $chaveOutros;
            }
            // Gatilhos de tabelas que saíram da auditoria (excluídas na configuração ou apagadas)
            foreach (array_keys($existentes) as $k) {
                $remover($k);
            }
            $res['tabelas'] = count($auditadas);
            aud_meta_set('gatilhos_assinatura', $assin, $pdo);
            aud_meta_set('gatilhos_estado', json_encode([
                'ligado' => true, 'tabelas' => count($auditadas), 'gatilhos' => $res['criadas'],
                'erros' => array_slice($res['erros'], 0, 30), 'em' => aud_agora(), 'mysql' => $sup['versao'],
            ], JSON_UNESCAPED_UNICODE), $pdo);
            $res['ok'] = !$res['erros'];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('atlas_aud_gatilhos')");
        }
    } catch (Throwable $e) {
        $res['ok'] = false;
        $res['erros'][] = $e->getMessage();
        error_log('[atlas_auditoria] gatilhos: ' . $e->getMessage());
    }
    return $res;
}

/**
 * Manutenção completa: selagem, gatilhos de tabelas novas/recriadas e prazo de guarda.
 * Roda ao abrir o Painel do Administrador (no máximo a cada 15 min) e pelo script
 * auditoria/manutencao.php (agendador do Windows). NUNCA é chamada pelas páginas
 * comuns: recriar gatilhos durante a requisição de um usuário poderia atrasá-la.
 */
function aud_manutencao(bool $forcar = false): void
{
    try {
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        aud_selar();
        $agora = time();
        $ult = (int) aud_meta_get('manutencao_em', 0, $pdo);
        if (!$forcar && $agora - $ult < 900) {
            return;
        }
        aud_meta_set('manutencao_em', $agora, $pdo);
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ignore_user_abort(true);
        aud_gatilhos_sincronizar(false);
        $ultExp = (int) aud_meta_get('expurgo_em', 0, $pdo);
        if ($forcar || $agora - $ultExp > 86400) {
            aud_meta_set('expurgo_em', $agora, $pdo);
            aud_expurgar();
        }
        for ($i = 0; $i < 20 && array_sum(aud_selar()) > 0; $i++) {
        }
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] manutencao: ' . $e->getMessage());
    }
}
