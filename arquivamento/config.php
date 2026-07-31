<?php
/**
 * Atlas · Arquivamento Digital
 * Configuração central do módulo.
 *
 * NÃO edite este arquivo em produção. Crie um "config.local.php" ao lado
 * dele com as credenciais reais — ele é carregado por último e sobrescreve
 * qualquer constante definida aqui, e não deve ser versionado.
 *
 * Exemplo de config.local.php:
 *
 *   <?php
 *   define('ARQ_DB_HOST', 'localhost');
 *   define('ARQ_DB_USER', 'atlas_app');
 *   define('ARQ_DB_PASS', 'senha-forte-aqui');
 *   define('ARQ_SELADOR_USER', 'producao');
 *   define('ARQ_SELADOR_PASS', '...');
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/* ------------------------------------------------------------------ *
 * Banco de dados
 * ------------------------------------------------------------------ */
defined('ARQ_DB_HOST')    or define('ARQ_DB_HOST', 'localhost');
defined('ARQ_DB_NAME')    or define('ARQ_DB_NAME', 'atlas');
defined('ARQ_DB_USER')    or define('ARQ_DB_USER', 'root');
defined('ARQ_DB_PASS')    or define('ARQ_DB_PASS', '');
defined('ARQ_DB_CHARSET') or define('ARQ_DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------ *
 * Ambiente
 * ------------------------------------------------------------------ */
// 'producao' esconde detalhes de erro do usuário; 'dev' exibe.
defined('ARQ_AMBIENTE') or define('ARQ_AMBIENTE', 'producao');
defined('ARQ_TIMEZONE') or define('ARQ_TIMEZONE', 'America/Sao_Paulo');

/* ------------------------------------------------------------------ *
 * Sessão
 * ------------------------------------------------------------------ */
// Minutos de inatividade até a sessão expirar (0 = desativado).
defined('ARQ_SESSAO_TIMEOUT_MIN') or define('ARQ_SESSAO_TIMEOUT_MIN', 240);
// Exigir HTTPS no cookie de sessão. Ative quando o Atlas estiver sob TLS.
defined('ARQ_COOKIE_SECURE') or define('ARQ_COOKIE_SECURE', false);

/* ------------------------------------------------------------------ *
 * Uploads
 * ------------------------------------------------------------------ */
// Tamanho máximo por arquivo, em bytes (padrão 60 MB).
defined('ARQ_UPLOAD_MAX_BYTES') or define('ARQ_UPLOAD_MAX_BYTES', 60 * 1024 * 1024);
// Quantidade máxima de anexos por arquivamento.
defined('ARQ_UPLOAD_MAX_ARQUIVOS') or define('ARQ_UPLOAD_MAX_ARQUIVOS', 60);

/**
 * Cartório arquiva de tudo — planilha, XML de nota, arquivo de CAD, backup.
 * Com isto ligado, qualquer extensão é aceita: a validação passa a olhar só
 * tamanho e integridade, não o formato.
 *
 * A segurança não vem mais da lista de tipos, e sim de três camadas:
 *   1. arquivos/ tem .htaccess que desliga o motor PHP e nega acesso direto;
 *   2. extensões executáveis no servidor são gravadas em disco com sufixo
 *      ".bin" (o nome original fica nos metadados), então nem que o Apache
 *      esteja com AllowOverride None o arquivo roda;
 *   3. arquivo.php serve como anexo (download) tudo que não for seguro
 *      para abrir na tela, sempre com nosniff.
 *
 * Voltar para a lista branca: defina como false no config.local.php.
 */
defined('ARQ_UPLOAD_ACEITA_TUDO') or define('ARQ_UPLOAD_ACEITA_TUDO', true);

/**
 * Extensões que o servidor poderia executar. Nunca são recusadas — apenas
 * neutralizadas com o sufixo ".bin" na hora de gravar.
 */
function arq_extensoes_executaveis()
{
    return [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'inc', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'ashx', 'jsp', 'jspx',
        'shtml', 'htaccess', 'htpasswd',
    ];
}

/**
 * Extensões aceitas => MIME types aceitos para aquela extensão.
 * A validação é dupla: extensão precisa estar aqui E o MIME real detectado
 * pelo finfo precisa bater. Qualquer coisa fora disso é rejeitada.
 */
function arq_tipos_permitidos()
{
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
        'tif'  => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'txt'  => ['text/plain'],
        'xml'  => ['text/xml', 'application/xml'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'p7s'  => ['application/pkcs7-signature', 'application/octet-stream'],
    ];
}

/* ------------------------------------------------------------------ *
 * Lixeira
 * ------------------------------------------------------------------ */
// Dias que um registro fica na lixeira antes de ser sinalizado para expurgo.
defined('ARQ_LIXEIRA_DIAS') or define('ARQ_LIXEIRA_DIAS', 90);

/* ------------------------------------------------------------------ *
 * Selador (TJMA / Portal do Selo)
 * ------------------------------------------------------------------ */
defined('ARQ_SELADOR_URL')  or define('ARQ_SELADOR_URL', 'https://selador.ma.portalselo.com.br:9443');
defined('ARQ_SELADOR_USER') or define('ARQ_SELADOR_USER', '');
defined('ARQ_SELADOR_PASS') or define('ARQ_SELADOR_PASS', '');

/* ------------------------------------------------------------------ *
 * Perfis com permissão de exclusão definitiva na lixeira.
 * Vazio = qualquer usuário logado. Preencha com os nomes de perfil do Atlas.
 * ------------------------------------------------------------------ */
function arq_perfis_expurgo()
{
    return ['ADMIN', 'ADMINISTRADOR', 'MASTER', 'OFICIAL'];
}
