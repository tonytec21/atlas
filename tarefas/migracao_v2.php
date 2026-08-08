<?php
/**
 * Atlas · Tarefas — migração para a versão 2 do módulo.
 *
 * O que faz:
 *   1. Cria as tabelas novas (histórico, checklist, IA e uso da IA).
 *   2. Acrescenta colunas novas em `tarefas` sem tocar nas existentes.
 *   3. Cria índices que faltavam — a busca com muitos registros fica bem
 *      mais rápida.
 *   4. Semeia o catálogo de modelos Gemini com os identificadores ativos.
 *
 * O que NÃO faz: nenhum DROP, nenhum UPDATE destrutivo, nenhuma alteração
 * de coluna já existente. Todo o acervo cadastrado permanece intacto.
 *
 * Execute uma única vez pelo navegador:
 *   http://localhost/atlas/tarefas/migracao_v2.php
 *
 * É seguro rodar de novo: cada passo verifica antes se já foi aplicado.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/html; charset=utf-8');

$log = array();

function passo(&$log, $status, $texto)
{
    $log[] = array('status' => $status, 'texto' => $texto);
}

function existeTabela(PDO $pdo, $tabela)
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute(array($tabela));
    return $st->fetch() !== false;
}

function existeColuna(PDO $pdo, $tabela, $coluna)
{
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute(array($tabela, $coluna));
        return $st->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function existeIndice(PDO $pdo, $tabela, $indice)
{
    try {
        $st = $pdo->query('SHOW INDEX FROM `' . str_replace('`', '', $tabela) . '`');
        foreach ($st->fetchAll() as $l) {
            if (strcasecmp($l['Key_name'], $indice) === 0) {
                return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

$pdo = db();

/* ================================================================== */
/* 1. Tabelas novas                                                   */
/* ================================================================== */

$tabelas = array(

    'tarefas_historico' => "
        CREATE TABLE `tarefas_historico` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tarefa_id` INT NOT NULL,
            `acao` VARCHAR(60) NOT NULL,
            `descricao` VARCHAR(500) NULL,
            `valor_anterior` VARCHAR(500) NULL,
            `valor_novo` VARCHAR(500) NULL,
            `usuario` VARCHAR(100) NULL,
            `criado_em` DATETIME NOT NULL,
            INDEX `idx_hist_tarefa` (`tarefa_id`),
            INDEX `idx_hist_data` (`criado_em`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Trilha de auditoria das tarefas'",

    'tarefas_checklist' => "
        CREATE TABLE `tarefas_checklist` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tarefa_id` INT NOT NULL,
            `descricao` VARCHAR(300) NOT NULL,
            `concluido` TINYINT(1) NOT NULL DEFAULT 0,
            `ordem` INT NOT NULL DEFAULT 0,
            `origem` VARCHAR(20) NOT NULL DEFAULT 'manual',
            `concluido_por` VARCHAR(100) NULL,
            `concluido_em` DATETIME NULL,
            `criado_por` VARCHAR(100) NULL,
            `criado_em` DATETIME NOT NULL,
            INDEX `idx_check_tarefa` (`tarefa_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Itens de conferência por tarefa'",

    'tarefas_ia_config' => "
        CREATE TABLE `tarefas_ia_config` (
            `chave` VARCHAR(60) NOT NULL PRIMARY KEY,
            `valor` TEXT NULL,
            `atualizado_em` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Configuração da integração Gemini'",

    'tarefas_ia_modelos' => "
        CREATE TABLE `tarefas_ia_modelos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `modelo_id` VARCHAR(120) NOT NULL,
            `apelido` VARCHAR(120) NOT NULL,
            `descricao` VARCHAR(400) NULL,
            `ativo` TINYINT(1) NOT NULL DEFAULT 1,
            `favorito` TINYINT(1) NOT NULL DEFAULT 0,
            `suporta_arquivos` TINYINT(1) NOT NULL DEFAULT 1,
            `disponivel_api` TINYINT(1) NOT NULL DEFAULT 1,
            `origem` VARCHAR(20) NOT NULL DEFAULT 'manual',
            `criado_em` DATETIME NULL,
            `atualizado_em` DATETIME NULL,
            UNIQUE KEY `uk_modelo` (`modelo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Catálogo de modelos Gemini cadastrados'",

    'tarefas_ia_uso' => "
        CREATE TABLE `tarefas_ia_uso` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `modelo` VARCHAR(120) NULL,
            `recurso` VARCHAR(60) NULL,
            `tokens` INT NOT NULL DEFAULT 0,
            `sucesso` TINYINT(1) NOT NULL DEFAULT 1,
            `erro` VARCHAR(400) NULL,
            `duracao_ms` INT NOT NULL DEFAULT 0,
            `usuario` VARCHAR(100) NULL,
            `criado_em` DATETIME NOT NULL,
            INDEX `idx_uso_data` (`criado_em`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Registro de chamadas à API Gemini'",

    'tarefas_ia_conversas' => "
        CREATE TABLE `tarefas_ia_conversas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tarefa_id` INT NULL,
            `papel` VARCHAR(12) NOT NULL,
            `mensagem` MEDIUMTEXT NOT NULL,
            `modelo` VARCHAR(120) NULL,
            `usuario` VARCHAR(100) NULL,
            `criado_em` DATETIME NOT NULL,
            INDEX `idx_conv_tarefa` (`tarefa_id`),
            INDEX `idx_conv_usuario` (`usuario`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Histórico do assistente de IA'",
);

foreach ($tabelas as $nome => $ddl) {
    if (existeTabela($pdo, $nome)) {
        passo($log, 'ok', "Tabela <code>$nome</code> já existia.");
        continue;
    }
    try {
        $pdo->exec($ddl);
        passo($log, 'novo', "Tabela <code>$nome</code> criada.");
    } catch (Exception $e) {
        passo($log, 'erro', "Falha ao criar <code>$nome</code>: " . e($e->getMessage()));
    }
}

/* ================================================================== */
/* 2. Colunas novas em `tarefas`                                       */
/* ================================================================== */

$colunas = array(
    'ordem_kanban'    => "ADD COLUMN `ordem_kanban` INT NOT NULL DEFAULT 0 COMMENT 'Posição do cartão dentro da coluna do Kanban'",
    'tags'            => "ADD COLUMN `tags` VARCHAR(400) NULL DEFAULT NULL COMMENT 'Etiquetas livres separadas por vírgula'",
    'apresentante'    => "ADD COLUMN `apresentante` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Nome do apresentante do título/documento'",
    'ia_resumo'       => "ADD COLUMN `ia_resumo` TEXT NULL DEFAULT NULL COMMENT 'Último resumo gerado pela IA'",
    'ia_resumo_em'    => "ADD COLUMN `ia_resumo_em` DATETIME NULL DEFAULT NULL",
    'ia_risco'        => "ADD COLUMN `ia_risco` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Classificação de risco sugerida pela IA'",
    'tempo_estimado'  => "ADD COLUMN `tempo_estimado` INT NULL DEFAULT NULL COMMENT 'Estimativa em minutos'",
    'concluido_por'   => "ADD COLUMN `concluido_por` VARCHAR(150) NULL DEFAULT NULL",
);

foreach ($colunas as $nome => $ddl) {
    if (existeColuna($pdo, 'tarefas', $nome)) {
        passo($log, 'ok', "Coluna <code>tarefas.$nome</code> já existia.");
        continue;
    }
    try {
        $pdo->exec("ALTER TABLE `tarefas` $ddl");
        passo($log, 'novo', "Coluna <code>tarefas.$nome</code> criada.");
    } catch (Exception $e) {
        passo($log, 'erro', "Falha ao criar <code>tarefas.$nome</code>: " . e($e->getMessage()));
    }
}

/* ================================================================== */
/* 3. Índices                                                          */
/* ================================================================== */

$indices = array(
    array('tarefas',     'idx_tar_status',      '(`status`)'),
    array('tarefas',     'idx_tar_limite',      '(`data_limite`)'),
    array('tarefas',     'idx_tar_resp',        '(`funcionario_responsavel`)'),
    array('tarefas',     'idx_tar_token',       '(`token`)'),
    array('tarefas',     'idx_tar_principal',   '(`id_tarefa_principal`)'),
    array('comentarios', 'idx_com_hash',        '(`hash_tarefa`)'),
);

foreach ($indices as $ix) {
    list($tabela, $nome, $cols) = $ix;
    if (!existeTabela($pdo, $tabela)) {
        passo($log, 'ok', "Tabela <code>$tabela</code> não existe neste banco — índice ignorado.");
        continue;
    }
    if (existeIndice($pdo, $tabela, $nome)) {
        passo($log, 'ok', "Índice <code>$nome</code> já existia.");
        continue;
    }
    try {
        $pdo->exec("ALTER TABLE `$tabela` ADD INDEX `$nome` $cols");
        passo($log, 'novo', "Índice <code>$nome</code> criado em <code>$tabela</code>.");
    } catch (Exception $e) {
        passo($log, 'erro', "Falha ao criar <code>$nome</code>: " . e($e->getMessage()));
    }
}

/* ================================================================== */
/* 4. Configuração e catálogo de modelos                               */
/* ================================================================== */

if (existeTabela($pdo, 'tarefas_ia_config')) {
    $padroes = array(
        'api_key'           => '',
        'modelo_padrao'     => 'gemini-3.5-flash',
        'ativo'             => '0',
        'temperatura'       => '0.4',
        'max_tokens'        => '2048',
        'timeout'           => '60',
        'contexto_cartorio' => "Você apoia uma serventia extrajudicial brasileira (cartório). "
            . "Use linguagem formal, precisa e em português do Brasil. "
            . "Cite normas apenas quando tiver certeza e nunca invente números de provimento, lei ou processo.",
    );
    $novos = 0;
    foreach ($padroes as $chave => $valor) {
        $st = $pdo->prepare('SELECT 1 FROM tarefas_ia_config WHERE chave = ?');
        $st->execute(array($chave));
        if ($st->fetch() === false) {
            $ins = $pdo->prepare('INSERT INTO tarefas_ia_config (chave, valor, atualizado_em) VALUES (?, ?, NOW())');
            $ins->execute(array($chave, $valor));
            $novos++;
        }
    }
    passo($log, $novos ? 'novo' : 'ok', "Configuração da IA: $novos item(ns) criado(s), demais já existiam.");
}

if (existeTabela($pdo, 'tarefas_ia_modelos')) {
    /*
     * Catálogo inicial — apenas modelos da linha 3.x, em uso em agosto/2026.
     * A família 2.5 foi deixada de fora de propósito porque o Google anunciou
     * o desligamento dela. Nada aqui é fixo: a tela de Configurações permite
     * cadastrar, desativar e excluir modelos, e o botão "Sincronizar" lê a
     * lista real disponível para a sua chave.
     */
    $sementes = array(
        array('gemini-3.5-flash', 'Gemini 3.5 Flash',
              'Equilíbrio entre qualidade e custo. Recomendado para o uso diário do módulo.', 1),
        array('gemini-3.1-flash-lite', 'Gemini 3.1 Flash-Lite',
              'Mais barato e rápido. Ideal para classificação, etiquetas e respostas curtas.', 1),
        array('gemini-3.1-pro-preview', 'Gemini 3.1 Pro',
              'Raciocínio mais profundo, para análise de documentos e minutas complexas. Identificador de API do Gemini 3.1 Pro.', 1),
        array('gemini-3.6-flash', 'Gemini 3.6 Flash',
              'Geração mais recente da linha Flash.', 0),
        array('gemini-3.5-flash-lite', 'Gemini 3.5 Flash-Lite',
              'Versão Lite mais recente, sucessora da 3.1 Flash-Lite.', 0),
    );

    $criados = 0;
    foreach ($sementes as $s) {
        $st = $pdo->prepare('SELECT 1 FROM tarefas_ia_modelos WHERE modelo_id = ?');
        $st->execute(array($s[0]));
        if ($st->fetch() !== false) {
            continue;
        }
        /*
         * O quarto item da semente é o flag "ativo". Os dois últimos modelos
         * entram desativados de propósito: são identificadores plausíveis da
         * geração seguinte, que podem ainda não estar liberados para a sua
         * chave. Use "Sincronizar modelos" para confirmar e então ative.
         */
        $ins = $pdo->prepare(
            'INSERT INTO tarefas_ia_modelos
                (modelo_id, apelido, descricao, ativo, favorito, suporta_arquivos, disponivel_api, origem, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, ?, 1, 0, ?, NOW(), NOW())'
        );
        $ins->execute(array(
            $s[0], $s[1], $s[2],
            $s[3],
            ($s[0] === 'gemini-3.5-flash') ? 1 : 0,
            'padrao',
        ));
        $criados++;
    }
    passo($log, $criados ? 'novo' : 'ok', "Catálogo de modelos: $criados modelo(s) cadastrado(s).");
    passo($log, 'ok', 'Depois de cadastrar a chave da API, use <strong>Sincronizar modelos</strong> para confirmar quais estão realmente disponíveis.');
}

/* ================================================================== */
/* 5. Pasta de anexos                                                  */
/* ================================================================== */

if (!is_dir(TAREFAS_DIR_ARQUIVOS)) {
    if (@mkdir(TAREFAS_DIR_ARQUIVOS, 0775, true)) {
        passo($log, 'novo', 'Pasta <code>arquivos/</code> criada.');
    } else {
        passo($log, 'erro', 'Não foi possível criar a pasta <code>arquivos/</code>.');
    }
} else {
    passo($log, 'ok', 'Pasta <code>arquivos/</code> já existia.');
}

$htaccess = TAREFAS_DIR_ARQUIVOS . '/.htaccess';
if (!file_exists($htaccess)) {
    $regras = "# Impede que qualquer arquivo enviado seja executado pelo Apache.\n"
            . "php_flag engine off\n"
            . "AddType text/plain .php .php3 .php4 .php5 .phtml .pl .py .cgi .asp .shtml\n"
            . "Options -ExecCGI -Indexes\n";
    if (@file_put_contents($htaccess, $regras) !== false) {
        passo($log, 'novo', 'Proteção <code>arquivos/.htaccess</code> criada — anexos não são mais executáveis.');
    } else {
        passo($log, 'erro', 'Não foi possível gravar <code>arquivos/.htaccess</code>.');
    }
} else {
    passo($log, 'ok', 'Proteção <code>arquivos/.htaccess</code> já existia.');
}

/* ================================================================== */
/* Estatísticas                                                        */
/* ================================================================== */

$totalTarefas = 0;
$totalComentarios = 0;
try {
    $totalTarefas = (int) db_valor('SELECT COUNT(*) FROM tarefas', array(), 0);
    $totalComentarios = (int) db_valor('SELECT COUNT(*) FROM comentarios', array(), 0);
} catch (Exception $e) {
    // silencioso
}

$erros = 0;
foreach ($log as $l) {
    if ($l['status'] === 'erro') {
        $erros++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Migração do módulo de Tarefas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        body { background: #f4f6f9; padding: 40px 15px; font-family: "Segoe UI", Arial, sans-serif; }
        .painel { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 14px;
                  box-shadow: 0 6px 24px rgba(0,0,0,.08); overflow: hidden; }
        .painel-topo { background: linear-gradient(135deg, #1e3a5f, #2c5282); color: #fff; padding: 26px 30px; }
        .painel-topo h1 { font-size: 1.4rem; margin: 0; }
        .painel-topo p { margin: 8px 0 0; opacity: .85; font-size: .92rem; }
        .painel-corpo { padding: 22px 30px; max-height: 60vh; overflow-y: auto; }
        .item { display: flex; gap: 12px; align-items: flex-start; padding: 9px 0;
                border-bottom: 1px solid #eef1f4; font-size: .92rem; }
        .item:last-child { border-bottom: 0; }
        .item i { width: 18px; text-align: center; margin-top: 3px; }
        .ok i   { color: #94a3b8; }
        .novo i { color: #16a34a; }
        .erro i { color: #dc2626; }
        .erro   { color: #dc2626; }
        code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; color: #1e3a5f; }
        .rodape { background: #f8fafc; padding: 18px 30px; border-top: 1px solid #e2e8f0;
                  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .rodape small { color: #64748b; }
        .resumo { display: flex; gap: 22px; padding: 16px 30px; background: #f8fafc;
                  border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .resumo div { font-size: .85rem; color: #64748b; }
        .resumo strong { display: block; font-size: 1.4rem; color: #1e3a5f; }
    </style>
</head>
<body>
    <div class="painel">
        <div class="painel-topo">
            <h1><i class="fa fa-database"></i> Migração — Módulo de Tarefas v2</h1>
            <p>Apenas cria tabelas, colunas e índices novos. Nenhum dado existente é alterado ou removido.</p>
        </div>

        <div class="resumo">
            <div><strong><?php echo number_format($totalTarefas, 0, ',', '.'); ?></strong>tarefas na base</div>
            <div><strong><?php echo number_format($totalComentarios, 0, ',', '.'); ?></strong>comentários</div>
            <div><strong><?php echo $erros; ?></strong>erros nesta execução</div>
        </div>

        <div class="painel-corpo">
            <?php foreach ($log as $l): ?>
                <div class="item <?php echo e($l['status']); ?>">
                    <i class="fa <?php
                        echo $l['status'] === 'novo' ? 'fa-check-circle'
                           : ($l['status'] === 'erro' ? 'fa-times-circle' : 'fa-circle-o');
                    ?>"></i>
                    <span><?php echo $l['texto']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rodape">
            <small><?php echo date('d/m/Y H:i:s'); ?> · pode ser executado novamente sem risco</small>
            <span>
                <a href="configuracoes-ia.php" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-magic"></i> Configurar IA
                </a>
                <a href="index.php" class="btn btn-sm btn-primary">
                    <i class="fa fa-arrow-left"></i> Ir para as tarefas
                </a>
            </span>
        </div>
    </div>
</body>
</html>
