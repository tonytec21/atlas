<?php
/**
 * Atlas · Arquivamento Digital
 * Validação e gravação de anexos.
 *
 * Regras aplicadas a todo arquivo recebido:
 *   1. tamanho dentro do limite e arquivo não vazio
 *   2. nome saneado e único no diretório do arquivamento
 *   3. SHA-256 gravado nos metadados para conferência de integridade
 *   4. extensão executável no servidor é gravada com sufixo ".bin"
 *
 * Com ARQ_UPLOAD_ACEITA_TUDO ligado (padrão), qualquer formato entra — é o
 * comportamento esperado num acervo de cartório. Desligando, volta a valer
 * a lista branca de arq_tipos_permitidos() com conferência de MIME real.
 *
 * Além disso, o diretório "arquivos/" tem .htaccess que desliga o motor PHP
 * e bloqueia acesso direto — todo download passa por arquivo.php autenticado.
 */

/** Mensagem legível para os códigos de erro do PHP. */
function arq_erro_upload($codigo)
{
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:  return 'arquivo maior que o limite do servidor';
        case UPLOAD_ERR_PARTIAL:    return 'envio interrompido';
        case UPLOAD_ERR_NO_FILE:    return 'nenhum arquivo enviado';
        case UPLOAD_ERR_NO_TMP_DIR: return 'diretório temporário indisponível';
        case UPLOAD_ERR_CANT_WRITE: return 'falha ao gravar no disco';
        case UPLOAD_ERR_EXTENSION:  return 'envio bloqueado por extensão do PHP';
        default:                    return 'erro desconhecido no envio';
    }
}

/** Detecta o MIME real pelo conteúdo. */
function arq_mime_real($caminho)
{
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $m = finfo_file($fi, $caminho);
            finfo_close($fi);
            if ($m) { return $m; }
        }
    }
    if (function_exists('mime_content_type')) {
        $m = mime_content_type($caminho);
        if ($m) { return $m; }
    }
    return 'application/octet-stream';
}

/**
 * Valida um arquivo temporário. Retorna ['ok'=>bool,'erro'=>string,'mime'=>string,'ext'=>string]
 */
function arq_validar_arquivo($tmp, $nomeOriginal, $tamanho)
{
    $ext = mb_strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if ($tamanho <= 0) {
        return ['ok' => false, 'erro' => 'arquivo vazio'];
    }
    if ($tamanho > ARQ_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'erro' => 'excede o limite de ' . arq_formatar_bytes(ARQ_UPLOAD_MAX_BYTES)];
    }

    $mime = arq_mime_real($tmp);

    if (ARQ_UPLOAD_ACEITA_TUDO) {
        // Qualquer formato serve. O que impede execução é o nome com que o
        // arquivo é gravado (ver arq_nome_neutralizado) e o .htaccess.
        return ['ok' => true, 'erro' => '', 'mime' => $mime, 'ext' => $ext];
    }

    // ---- Modo lista branca (ARQ_UPLOAD_ACEITA_TUDO = false) ----
    $permitidos = arq_tipos_permitidos();
    if ($ext === '' || !isset($permitidos[$ext])) {
        return ['ok' => false, 'erro' => 'tipo de arquivo não permitido (.' . $ext . ')'];
    }
    if (!in_array($mime, $permitidos[$ext], true)) {
        return ['ok' => false, 'erro' => 'conteúdo não corresponde à extensão (detectado: ' . $mime . ')'];
    }
    if (strpos($mime, 'image/') === 0) {
        $inicio = @file_get_contents($tmp, false, null, 0, 4096);
        if ($inicio !== false && stripos($inicio, '<?php') !== false) {
            return ['ok' => false, 'erro' => 'imagem com conteúdo executável embutido'];
        }
    }

    return ['ok' => true, 'erro' => '', 'mime' => $mime, 'ext' => $ext];
}

/**
 * Nome com que o arquivo será gravado no disco.
 *
 * Aceitar qualquer extensão significa que um ".php" pode entrar no acervo
 * como documento legítimo. Para que ele nunca seja executado — mesmo que o
 * .htaccess seja ignorado por AllowOverride None — a gravação acrescenta
 * ".bin". O nome original continua nos metadados e é o que o usuário vê e
 * recebe ao baixar.
 *
 * Também trata extensão dupla ("laudo.php.pdf"): a checagem olha todos os
 * segmentos do nome, não só o último.
 */
function arq_nome_neutralizado($nomeSeguro)
{
    $perigosas = arq_extensoes_executaveis();
    $partes = explode('.', mb_strtolower($nomeSeguro));
    array_shift($partes); // o primeiro segmento é o nome, não extensão

    foreach ($partes as $parte) {
        if (in_array($parte, $perigosas, true)) {
            return $nomeSeguro . '.bin';
        }
    }
    return $nomeSeguro;
}

/**
 * Processa $_FILES[$campo] (múltiplo) gravando em arquivos/<id>/.
 * Retorna ['anexos' => [...], 'erros' => ['nome.pdf: motivo', ...]]
 */
function arq_processar_uploads($campo, $id, $jaExistentes = 0)
{
    $resultado = ['anexos' => [], 'erros' => []];
    if (empty($_FILES[$campo]) || !isset($_FILES[$campo]['name'])) { return $resultado; }

    $f = $_FILES[$campo];
    if (!is_array($f['name'])) {
        $f = [
            'name'     => [$f['name']],
            'tmp_name' => [$f['tmp_name']],
            'error'    => [$f['error']],
            'size'     => [$f['size']],
        ];
    }

    $id = arq_id_valido($id);
    if ($id === '') { $resultado['erros'][] = 'identificador do arquivamento inválido'; return $resultado; }

    $dir = arq_dir_arquivos() . DIRECTORY_SEPARATOR . $id;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
        $resultado['erros'][] = 'não foi possível criar a pasta de anexos';
        return $resultado;
    }

    $total = $jaExistentes;
    $qtd   = count($f['name']);

    for ($i = 0; $i < $qtd; $i++) {
        $nomeOriginal = (string) $f['name'][$i];
        if ($nomeOriginal === '') { continue; }

        if ((int) $f['error'][$i] !== UPLOAD_ERR_OK) {
            $resultado['erros'][] = $nomeOriginal . ': ' . arq_erro_upload((int) $f['error'][$i]);
            continue;
        }
        if (!is_uploaded_file($f['tmp_name'][$i])) {
            $resultado['erros'][] = $nomeOriginal . ': origem inválida';
            continue;
        }
        if ($total >= ARQ_UPLOAD_MAX_ARQUIVOS) {
            $resultado['erros'][] = $nomeOriginal . ': limite de ' . ARQ_UPLOAD_MAX_ARQUIVOS . ' anexos atingido';
            continue;
        }

        $v = arq_validar_arquivo($f['tmp_name'][$i], $nomeOriginal, (int) $f['size'][$i]);
        if (!$v['ok']) {
            $resultado['erros'][] = arq_nome_seguro($nomeOriginal) . ': ' . $v['erro'];
            continue;
        }

        $nomeExibido = arq_nome_unico($dir, arq_nome_seguro($nomeOriginal));
        $nomeDisco   = arq_nome_neutralizado($nomeExibido);
        $dest = $dir . DIRECTORY_SEPARATOR . $nomeDisco;

        if (!@move_uploaded_file($f['tmp_name'][$i], $dest)) {
            $resultado['erros'][] = $nomeExibido . ': falha ao gravar no acervo';
            continue;
        }
        @chmod($dest, 0640);

        $resultado['anexos'][] = [
            'ref'         => 'arquivos/' . $id . '/' . $nomeDisco,
            'nome'        => $nomeExibido,
            'tamanho'     => filesize($dest),
            'mime'        => $v['mime'],
            'hash'        => hash_file('sha256', $dest),
            'origem'      => 'acervo',
            'enviado_por' => arq_usuario(),
            'enviado_em'  => date('Y-m-d H:i:s'),
        ];
        $total++;
    }

    return $resultado;
}

/**
 * Copia um anexo já existente no servidor (ex.: vindo do módulo de tarefas)
 * para a pasta do arquivamento. Devolve o objeto de anexo ou null.
 */
function arq_importar_referencia($referencia, $id)
{
    $origem = '';
    $fonte = arq_resolver_anexo($referencia, $origem);
    if ($fonte === false || !is_readable($fonte)) { return null; }

    $id = arq_id_valido($id);
    if ($id === '') { return null; }

    $v = arq_validar_arquivo($fonte, basename($fonte), filesize($fonte));
    if (!$v['ok']) { return null; }

    $dir = arq_dir_arquivos() . DIRECTORY_SEPARATOR . $id;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true)) { return null; }

    $nome = arq_nome_unico($dir, arq_nome_seguro(basename($fonte)));
    $dest = $dir . DIRECTORY_SEPARATOR . $nome;
    if (!@copy($fonte, $dest)) { return null; }
    @chmod($dest, 0640);

    return [
        'ref'         => 'arquivos/' . $id . '/' . $nome,
        'nome'        => $nome,
        'tamanho'     => filesize($dest),
        'mime'        => $v['mime'],
        'hash'        => hash_file('sha256', $dest),
        'origem'      => 'importado',
        'enviado_por' => arq_usuario(),
        'enviado_em'  => date('Y-m-d H:i:s'),
    ];
}
