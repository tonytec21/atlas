<?php
/**
 * Atlas · Tarefas — funções de apoio compartilhadas.
 *
 * Formatação, tratamento de anexos, upload seguro, cálculo de prazo,
 * registro de auditoria e leitura de listas auxiliares.
 */

/* ================================================================== */
/* Entrada                                                            */
/* ================================================================== */

/** Lê e limpa um parâmetro de texto. */
function entrada($chave, $padrao = '', $fonte = null)
{
    $fonte = $fonte === null ? $_REQUEST : $fonte;
    if (!isset($fonte[$chave])) {
        return $padrao;
    }
    $v = $fonte[$chave];
    if (is_array($v)) {
        return $v;
    }
    return trim((string) $v);
}

/** Lê um parâmetro inteiro. */
function entrada_int($chave, $padrao = 0, $fonte = null)
{
    $fonte = $fonte === null ? $_REQUEST : $fonte;
    return isset($fonte[$chave]) ? (int) $fonte[$chave] : $padrao;
}

/** Escapa para saída em HTML. */
function e($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/* ================================================================== */
/* Datas                                                              */
/* ================================================================== */

/** Converte datas do banco para o formato brasileiro. */
function data_br($valor, $comHora = true)
{
    if (empty($valor) || $valor === '0000-00-00 00:00:00' || $valor === '0000-00-00') {
        return '';
    }
    $ts = strtotime((string) $valor);
    if ($ts === false) {
        return '';
    }
    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

/**
 * Normaliza uma data vinda de <input type="datetime-local"> ou do formato
 * brasileiro para o formato do MySQL.
 *
 * @return string|null
 */
function data_para_mysql($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    $formatos = array('Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i',
                      'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y');

    foreach ($formatos as $f) {
        $dt = DateTime::createFromFormat($f, $valor);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $ts = strtotime($valor);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

/**
 * Situação do prazo de uma tarefa.
 *
 * @return array{codigo:string,rotulo:string,dias:float|null}
 */
function situacao_prazo($dataLimite, $status)
{
    if (in_array((string) $status, tarefas_status_encerrados(), true)) {
        return array('codigo' => 'encerrada', 'rotulo' => 'Encerrada', 'dias' => null);
    }
    if (empty($dataLimite)) {
        return array('codigo' => 'sem-prazo', 'rotulo' => 'Sem prazo', 'dias' => null);
    }

    $ts = strtotime((string) $dataLimite);
    if ($ts === false) {
        return array('codigo' => 'sem-prazo', 'rotulo' => 'Sem prazo', 'dias' => null);
    }

    $dias = ($ts - time()) / 86400;

    if ($dias < 0) {
        return array('codigo' => 'vencida', 'rotulo' => 'Vencida', 'dias' => $dias);
    }
    if ($dias <= 1) {
        return array('codigo' => 'hoje', 'rotulo' => 'Vence hoje', 'dias' => $dias);
    }
    if ($dias <= 3) {
        return array('codigo' => 'proxima', 'rotulo' => 'Vence em breve', 'dias' => $dias);
    }
    return array('codigo' => 'no-prazo', 'rotulo' => 'No prazo', 'dias' => $dias);
}

/** Peso numérico da prioridade, para ordenação. */
function peso_prioridade($prioridade)
{
    $mapa = tarefas_prioridades();
    $p = (string) $prioridade;
    return isset($mapa[$p]) ? $mapa[$p]['peso'] : 0;
}

/** Cor associada a um status. */
function cor_status($status)
{
    $mapa = tarefas_status_catalogo();
    $s = (string) $status;
    return isset($mapa[$s]) ? $mapa[$s]['cor'] : '#94a3b8';
}

/** Transforma um texto em slug utilizável como classe CSS. */
function slug($texto)
{
    $t = (string) $texto;
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($conv !== false) {
            $t = $conv;
        }
    }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim((string) $t, '-');
}

/* ================================================================== */
/* Anexos                                                             */
/* ================================================================== */

/**
 * Converte o campo `caminho_anexo` (lista separada por ponto e vírgula)
 * em um array de anexos utilizáveis pelo front-end.
 *
 * O acervo antigo gravou os caminhos de várias formas diferentes ao longo
 * do tempo — "/arquivos/token/arq.pdf", "arquivos/token/arq.pdf" e até com
 * barra invertida. Esta função normaliza todas elas para o mesmo formato
 * relativo à pasta do módulo, sem alterar nada no banco.
 *
 * @return array<int,array{nome:string,url:string,rel:string,ext:string,existe:bool,tamanho:int}>
 */
function anexos_lista($caminhoAnexo)
{
    $bruto = (string) $caminhoAnexo;
    if (trim($bruto) === '') {
        return array();
    }

    $itens = array();
    foreach (preg_split('/[;\r\n]+/', $bruto) as $parte) {
        $p = trim($parte);
        if ($p === '') {
            continue;
        }

        $rel = str_replace('\\', '/', $p);
        $rel = ltrim($rel, '/');
        // Remove prefixos usados por versões anteriores.
        $rel = preg_replace('#^(\./)+#', '', $rel);
        if (strpos($rel, 'tarefas/') === 0) {
            $rel = substr($rel, strlen('tarefas/'));
        }
        if (strpos($rel, 'arquivos/') !== 0) {
            $rel = 'arquivos/' . ltrim($rel, '/');
        }

        // Bloqueia qualquer tentativa de sair da pasta de anexos.
        if (strpos($rel, '..') !== false) {
            continue;
        }

        $absoluto = TAREFAS_DIR . '/' . $rel;
        $nome     = basename($rel);

        $itens[] = array(
            'nome'    => $nome,
            'rel'     => $rel,
            'url'     => $rel,
            'original' => $p,
            'ext'     => strtolower((string) pathinfo($nome, PATHINFO_EXTENSION)),
            'existe'  => is_file($absoluto),
            'tamanho' => is_file($absoluto) ? (int) filesize($absoluto) : 0,
        );
    }

    return $itens;
}

/** Formata bytes de forma legível. */
function tamanho_humano($bytes)
{
    $bytes = (float) $bytes;
    if ($bytes <= 0) {
        return '—';
    }
    $unidades = array('B', 'KB', 'MB', 'GB');
    $i = (int) floor(log($bytes, 1024));
    $i = max(0, min($i, count($unidades) - 1));
    return round($bytes / pow(1024, $i), $i === 0 ? 0 : 1) . ' ' . $unidades[$i];
}

/**
 * Nome de arquivo seguro: sem diretórios, sem caracteres problemáticos e
 * com extensão validada.
 *
 * @return string|false
 */
function nome_arquivo_seguro($nome)
{
    $nome = basename(str_replace('\\', '/', (string) $nome));
    $nome = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $nome);
    $nome = trim((string) $nome, ". \t\n\r\0\x0B");

    if ($nome === '') {
        return false;
    }

    $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
    $permitidas = explode(',', TAREFAS_EXT_PERMITIDAS);
    if ($ext === '' || !in_array($ext, $permitidas, true)) {
        return false;
    }

    if (mb_strlen($nome) > 180) {
        $base = mb_substr((string) pathinfo($nome, PATHINFO_FILENAME), 0, 150);
        $nome = $base . '.' . $ext;
    }

    return $nome;
}

/**
 * Salva os arquivos de um campo <input type="file" multiple> na pasta do
 * token informado e devolve os caminhos relativos gravados.
 *
 * Diferenças em relação ao código antigo: valida extensão, evita
 * sobrescrever arquivos de mesmo nome e respeita o limite de tamanho.
 *
 * @return array{caminhos:array<int,string>,erros:array<int,string>}
 */
function salvar_uploads($campo, $token)
{
    $resultado = array('caminhos' => array(), 'erros' => array());

    if (empty($_FILES[$campo]) || empty($_FILES[$campo]['name'])) {
        return $resultado;
    }

    $token = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $token);
    if ($token === '') {
        $resultado['erros'][] = 'Token da tarefa inválido para o envio de anexos.';
        return $resultado;
    }

    $nomes = (array) $_FILES[$campo]['name'];
    $tmps  = (array) $_FILES[$campo]['tmp_name'];
    $erros = (array) $_FILES[$campo]['error'];
    $sizes = (array) $_FILES[$campo]['size'];

    $pasta = TAREFAS_DIR_ARQUIVOS . '/' . $token;
    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        $resultado['erros'][] = 'Não foi possível criar a pasta de anexos.';
        return $resultado;
    }

    $limite = TAREFAS_UPLOAD_MAX_MB * 1024 * 1024;

    foreach ($nomes as $i => $nomeBruto) {
        if ($nomeBruto === '' || !isset($tmps[$i])) {
            continue;
        }
        if (!empty($erros[$i]) && $erros[$i] !== UPLOAD_ERR_OK) {
            $resultado['erros'][] = 'Falha no envio de "' . $nomeBruto . '".';
            continue;
        }
        if (isset($sizes[$i]) && $sizes[$i] > $limite) {
            $resultado['erros'][] = '"' . $nomeBruto . '" excede ' . TAREFAS_UPLOAD_MAX_MB . ' MB.';
            continue;
        }

        $nome = nome_arquivo_seguro($nomeBruto);
        if ($nome === false) {
            $resultado['erros'][] = 'Tipo de arquivo não permitido: "' . $nomeBruto . '".';
            continue;
        }

        // Evita sobrescrever um anexo já existente com o mesmo nome.
        $destino = $pasta . '/' . $nome;
        if (file_exists($destino)) {
            $base = (string) pathinfo($nome, PATHINFO_FILENAME);
            $ext  = (string) pathinfo($nome, PATHINFO_EXTENSION);
            $n = 1;
            do {
                $nome    = $base . '-' . $n . '.' . $ext;
                $destino = $pasta . '/' . $nome;
                $n++;
            } while (file_exists($destino) && $n < 500);
        }

        if (@move_uploaded_file($tmps[$i], $destino)) {
            @chmod($destino, 0664);
            $resultado['caminhos'][] = 'arquivos/' . $token . '/' . $nome;
        } else {
            $resultado['erros'][] = 'Não foi possível gravar "' . $nomeBruto . '".';
        }
    }

    return $resultado;
}

/**
 * Junta caminhos novos aos já existentes preservando o formato antigo
 * (separador ponto e vírgula).
 */
function anexos_concatenar($existente, array $novos)
{
    $lista = array();
    foreach (preg_split('/[;\r\n]+/', (string) $existente) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $lista[] = $p;
        }
    }
    foreach ($novos as $p) {
        $p = trim((string) $p);
        if ($p !== '' && !in_array($p, $lista, true)) {
            $lista[] = $p;
        }
    }
    return implode(';', $lista);
}

/* ================================================================== */
/* Listas auxiliares                                                  */
/* ================================================================== */

function listar_categorias($somenteAtivas = true)
{
    $sql = 'SELECT id, titulo FROM categorias';
    if ($somenteAtivas) {
        $sql .= " WHERE LOWER(status) = 'ativo'";
    }
    $sql .= ' ORDER BY titulo';
    return db_all($sql);
}

function listar_origens($somenteAtivas = true)
{
    $sql = 'SELECT id, titulo FROM origem';
    if ($somenteAtivas) {
        $sql .= " WHERE LOWER(status) = 'ativo'";
    }
    $sql .= ' ORDER BY titulo';
    return db_all($sql);
}

function listar_funcionarios()
{
    return db_all(
        "SELECT nome_completo FROM funcionarios WHERE LOWER(status) = 'ativo' ORDER BY nome_completo"
    );
}

/* ================================================================== */
/* Auditoria                                                          */
/* ================================================================== */

/**
 * Registra um evento no histórico da tarefa.
 *
 * Silencioso por natureza: se a tabela ainda não existir (migração não
 * executada), a operação principal não pode falhar por causa do log.
 */
function registrar_historico($tarefaId, $acao, $descricao = '', $antes = null, $depois = null)
{
    if (!db_tem_tabela('tarefas_historico')) {
        return;
    }
    try {
        $u = usuario_atual();
        db_exec(
            'INSERT INTO tarefas_historico
                (tarefa_id, acao, descricao, valor_anterior, valor_novo, usuario, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            array(
                (int) $tarefaId,
                mb_substr((string) $acao, 0, 60),
                mb_substr((string) $descricao, 0, 500),
                $antes === null ? null : mb_substr((string) $antes, 0, 500),
                $depois === null ? null : mb_substr((string) $depois, 0, 500),
                mb_substr($u['usuario'], 0, 100),
            )
        );
    } catch (Exception $e) {
        error_log('[tarefas] historico: ' . $e->getMessage());
    }
}

/**
 * Fragmento SQL que restringe a visibilidade do usuário comum.
 * Devolve array com o trecho e os parâmetros.
 */
function filtro_visibilidade($alias = 't')
{
    if (usuario_ve_tudo()) {
        return array('sql' => '', 'params' => array());
    }
    $u = usuario_atual();
    return array(
        'sql' => " AND ({$alias}.status = 'Concluída'"
               . " OR {$alias}.funcionario_responsavel = ?"
               . " OR {$alias}.revisor = ?"
               . " OR {$alias}.criado_por = ?)",
        'params' => array($u['nome'], $u['nome'], $u['usuario']),
    );
}
