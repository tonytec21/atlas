<?php
/**
 * Atlas · Tarefas — configuração central do módulo.
 *
 * Único ponto do módulo que conhece credenciais e caminhos. Todo o resto
 * (páginas, APIs, helpers) passa por aqui através de core/bootstrap.php.
 *
 * Os valores continuam iguais aos do db_connection.php original — nada muda
 * no banco nem no acervo já cadastrado. O arquivo db_connection.php foi
 * mantido e agora apenas repassa estas constantes, de modo que qualquer
 * arquivo antigo que ainda faça include dele continua funcionando.
 */

if (defined('ATLAS_TAREFAS_CONFIG')) {
    return;
}
define('ATLAS_TAREFAS_CONFIG', true);

/* ------------------------------------------------------------------ */
/* Banco de dados                                                      */
/* ------------------------------------------------------------------ */
define('TAREFAS_DB_HOST', 'localhost');
define('TAREFAS_DB_USER', 'root');
define('TAREFAS_DB_PASS', '');
define('TAREFAS_DB_NAME', 'atlas');
define('TAREFAS_DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------ */
/* Caminhos                                                            */
/* ------------------------------------------------------------------ */
define('TAREFAS_DIR',        dirname(__DIR__));           // .../atlas/tarefas
define('TAREFAS_DIR_ARQUIVOS', TAREFAS_DIR . '/arquivos'); // anexos
define('TAREFAS_URL_ARQUIVOS', 'arquivos');                // relativo à página

/* ------------------------------------------------------------------ */
/* Comportamento                                                       */
/* ------------------------------------------------------------------ */
define('TAREFAS_TIMEZONE', 'America/Sao_Paulo');
define('TAREFAS_UPLOAD_MAX_MB', 40);

/**
 * Extensões aceitas nos anexos. Bloqueia executáveis e scripts que
 * poderiam ser servidos pelo Apache a partir da pasta arquivos/.
 */
define('TAREFAS_EXT_PERMITIDAS', 'pdf,doc,docx,xls,xlsx,csv,txt,rtf,odt,ods,'
    . 'jpg,jpeg,png,gif,bmp,webp,tif,tiff,heic,'
    . 'zip,rar,7z,xml,json,p7s,eml,msg,mp3,mp4,wav,ogg,webm');

/**
 * Situações possíveis de uma tarefa.
 * A ordem define a sequência das colunas do Kanban.
 * NÃO remova nenhum item: são exatamente os mesmos textos já gravados na
 * coluna `status` das tarefas antigas.
 */
function tarefas_status_catalogo()
{
    return array(
        'Iniciada'                      => array('cor' => '#3b82f6', 'grupo' => 'aberta',    'kanban' => true),
        'Em Andamento'                  => array('cor' => '#0ea5e9', 'grupo' => 'aberta',    'kanban' => true),
        'Em Espera'                     => array('cor' => '#a855f7', 'grupo' => 'parada',    'kanban' => true),
        'Pendente'                      => array('cor' => '#f59e0b', 'grupo' => 'parada',    'kanban' => true),
        'Aguardando Pagamento'          => array('cor' => '#eab308', 'grupo' => 'parada',    'kanban' => true),
        'Prazo de Edital'               => array('cor' => '#8b5cf6', 'grupo' => 'parada',    'kanban' => true),
        'Exigência Cumprida'            => array('cor' => '#14b8a6', 'grupo' => 'aberta',    'kanban' => true),
        'Aguardando Retirada'           => array('cor' => '#22c55e', 'grupo' => 'entrega',   'kanban' => true),
        'Concluída'                     => array('cor' => '#16a34a', 'grupo' => 'encerrada', 'kanban' => true),
        'Finalizado sem prática do ato' => array('cor' => '#64748b', 'grupo' => 'encerrada', 'kanban' => false),
        'Cancelada'                     => array('cor' => '#ef4444', 'grupo' => 'encerrada', 'kanban' => false),
    );
}

/** Status que tiram a tarefa da fila de trabalho. */
function tarefas_status_encerrados()
{
    return array('Concluída', 'Cancelada', 'Finalizado sem prática do ato', 'Aguardando Retirada');
}

/** Status que marcam data de conclusão ao serem aplicados. */
function tarefas_status_conclui()
{
    return array('Concluída', 'Finalizado sem prática do ato', 'Aguardando Retirada');
}

/** Níveis de prioridade, do menor para o maior. */
function tarefas_prioridades()
{
    return array(
        'Baixa'   => array('peso' => 1, 'cor' => '#64748b'),
        'Média'   => array('peso' => 2, 'cor' => '#0ea5e9'),
        'Alta'    => array('peso' => 3, 'cor' => '#f59e0b'),
        'Crítica' => array('peso' => 4, 'cor' => '#ef4444'),
    );
}
