<?php
/**
 * Atlas · Arquivamento Digital
 * Caminhos: validação de IDs e contenção de diretório (anti path traversal).
 *
 * Regra do módulo: nenhum caminho vindo do usuário toca o filesystem sem
 * passar por arq_caminho_seguro(). O ID é sempre numérico.
 */

function arq_base_dir()
{
    static $base = null;
    if ($base === null) { $base = realpath(dirname(__DIR__)); }
    return $base;
}

/** Valida o ID do arquivamento (timestamp numérico). Retorna '' se inválido. */
function arq_id_valido($id)
{
    $id = trim((string) $id);
    return preg_match('/^[0-9]{6,20}$/', $id) ? $id : '';
}

/**
 * Garante que $caminho está realmente dentro de $raiz.
 * Retorna o caminho absoluto canônico ou false.
 */
function arq_caminho_seguro($raiz, $caminhoRelativo)
{
    $raizReal = realpath($raiz);
    if ($raizReal === false) { return false; }

    $rel = str_replace('\\', '/', (string) $caminhoRelativo);
    $rel = preg_replace('~/+~', '/', $rel);
    $rel = ltrim($rel, '/');

    // Rejeita qualquer tentativa explícita de subir níveis ou byte nulo.
    if ($rel === '' || strpos($rel, "\0") !== false) { return false; }
    foreach (explode('/', $rel) as $parte) {
        if ($parte === '..' || $parte === '.') { return false; }
    }

    $alvo = $raizReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $real = realpath($alvo);
    if ($real === false) { return false; }

    // Contenção final: o caminho canônico precisa começar na raiz canônica.
    $raizNorm = rtrim($raizReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($real, $raizNorm, strlen($raizNorm)) !== 0) { return false; }

    return $real;
}

/**
 * Resolve a referência de um anexo (como guardada no JSON) para um caminho
 * absoluto legível. Aceita:
 *   - "arquivos/<id>/nome.pdf"        (anexo do próprio arquivamento)
 *   - "arquivos/<hash>/nome.pdf"      (anexo herdado do módulo de tarefas)
 * $origem retorna 'acervo' | 'tarefa' | ''
 */
function arq_resolver_anexo($referencia, &$origem = null)
{
    $origem = '';
    $ref = str_replace('\\', '/', (string) $referencia);
    $ref = ltrim(preg_replace('~/+~', '/', $ref), '/');
    if ($ref === '') { return false; }

    // 1) Dentro do próprio módulo de arquivamento
    $p = arq_caminho_seguro(arq_base_dir(), $ref);
    if ($p !== false && is_file($p)) { $origem = 'acervo'; return $p; }

    // 2) Anexos vindos do módulo de tarefas (../tarefas/arquivos/...)
    $tarefas = realpath(arq_base_dir() . '/../tarefas');
    if ($tarefas !== false) {
        $p = arq_caminho_seguro($tarefas, $ref);
        if ($p !== false && is_file($p)) { $origem = 'tarefa'; return $p; }
    }

    // 3) Lixeira (registros excluídos mantêm as referências antigas)
    $lixeira = realpath(arq_base_dir() . '/lixeira');
    if ($lixeira !== false) {
        $p = arq_caminho_seguro($lixeira, preg_replace('~^arquivos/~', '', $ref));
        if ($p !== false && is_file($p)) { $origem = 'acervo'; return $p; }
    }

    return false;
}

/** Nome de arquivo seguro para gravação em disco. */
function arq_nome_seguro($nome)
{
    $nome = (string) $nome;
    $nome = str_replace(['\\', '/'], ' ', $nome);
    $nome = basename($nome);
    $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome);
    // Remove caracteres proibidos no Windows e no shell.
    $nome = preg_replace('/[<>:"|?*`$;&]+/u', '_', $nome);
    $nome = preg_replace('/\s+/u', ' ', $nome);
    $nome = trim($nome, " .");
    if ($nome === '') { $nome = 'arquivo'; }
    if (mb_strlen($nome) > 120) {
        $ext  = pathinfo($nome, PATHINFO_EXTENSION);
        $base = pathinfo($nome, PATHINFO_FILENAME);
        $nome = mb_substr($base, 0, 110) . ($ext !== '' ? '.' . $ext : '');
    }
    return $nome;
}

/** Gera um nome livre dentro do diretório (acrescenta " (n)" se necessário). */
function arq_nome_unico($dir, $nome)
{
    $info = pathinfo($nome);
    $base = isset($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'arquivo';
    $ext  = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    $tentativa = $base . $ext;
    $i = 1;
    while (file_exists(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $tentativa)) {
        $tentativa = $base . ' (' . $i . ')' . $ext;
        $i++;
        if ($i > 999) { $tentativa = $base . '-' . bin2hex(random_bytes(4)) . $ext; break; }
    }
    return $tentativa;
}

/** Remove um diretório recursivamente, contido na raiz do módulo. */
function arq_remover_dir($dir)
{
    $real = realpath($dir);
    if ($real === false) { return false; }
    $raiz = rtrim(arq_base_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($real, $raiz, strlen($raiz)) !== 0) { return false; }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    return @rmdir($real);
}

/** Formata bytes de forma legível. */
function arq_formatar_bytes($bytes)
{
    $bytes = (float) $bytes;
    $un = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($un) - 1) { $bytes /= 1024; $i++; }
    return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $un[$i];
}
