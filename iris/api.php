<?php
/**
 * Atlas Iris — API interna (AJAX). Todas as ações exigem sessão ativa e token CSRF.
 * Uso: POST api.php  acao=<nome>  csrf=<token>  ...   (GET apenas para baixar arquivo original)
 */
error_reporting(0); @ini_set('display_errors', '0'); @set_time_limit(0);
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/config_iris.php';

function iris_json($dados, $code = 200)
{
    if (!headers_sent()) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); }
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function iris_ok($dados = []) { iris_json(['status' => 'success'] + $dados); }

/** Lê as imagens enviadas em imagens[] validando o tipo real. @return array de ['bytes','mime'] */
function iris_imagens_upload($campo = 'imagens', $max = 16)
{
    if (empty($_FILES[$campo])) return [];
    $f = $_FILES[$campo];
    $lista = [];
    if (is_array($f['name'])) { foreach ($f['name'] as $i => $n) $lista[] = ['tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]]; }
    else $lista[] = $f;
    if (count($lista) > $max) throw new RuntimeException("Envie no máximo $max imagens por requisição.");
    $out = []; $total = 0; $fi = new finfo(FILEINFO_MIME_TYPE); $aceitos = iris_mimes_aceitos();
    foreach ($lista as $u) {
        if ($u['error'] === UPLOAD_ERR_INI_SIZE || $u['error'] === UPLOAD_ERR_FORM_SIZE)
            throw new RuntimeException('Imagem maior que o limite do servidor (upload_max_filesize do PHP).');
        if ($u['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio da imagem (código ' . $u['error'] . ').');
        $bytes = file_get_contents($u['tmp_name']);
        $mime = $fi->buffer($bytes);
        if (!isset($aceitos[$mime])) throw new RuntimeException('Formato não suportado (' . $mime . ').');
        $total += strlen($bytes);
        $out[] = ['bytes' => $bytes, 'mime' => $mime];
    }
    // A API aceita até ~20 MB por requisição (base64 incluso)
    if ($total > 14.5 * 1048576) throw new RuntimeException('O conteúdo enviado excede o limite da API (≈14 MB por requisição). Reduza a qualidade ou selecione menos páginas.');
    return $out;
}

function iris_ctx_prompt()
{
    $c = iris_config();
    return ['vocabulario' => $c['vocabulario'] ?? '', 'prompt_extra' => $c['prompt_extra'] ?? ''];
}

/** Limpa sobras comuns da resposta (cercas de código, preâmbulos). */
function iris_limpar_resposta($t)
{
    $t = str_replace("\r\n", "\n", (string)$t);
    $t = preg_replace('~^\s*```[a-z]*\s*\n|\n\s*```\s*$~i', '', $t);
    $t = preg_replace('~^(aqui está|segue|transcrição)[^\n]{0,60}:\s*\n~iu', '', ltrim($t));
    // linhas de preenchimento que o modelo às vezes reproduz (---, ___, ....)
    $t = preg_replace('~^[ \t]*[-_=.·•*–—]{3,}[ \t]*$~mu', '', $t);
    $t = preg_replace('~[_–—]{3,}|-{4,}|\.{5,}~u', ' ', $t);
    $t = preg_replace('~[ \t]{2,}~', ' ', $t);
    $t = preg_replace('~[ \t]+\n~', "\n", $t);
    $t = preg_replace("~\n{3,}~", "\n\n", $t);
    return trim($t);
}

// ---------------------------------------------------------------------------------------------
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
try {
    if (!isset($_SESSION['username'])) iris_json(['status' => 'error', 'message' => 'Sessão expirada. Faça login novamente.', 'login' => true], 401);
    $csrf = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (!iris_csrf_check($csrf)) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    iris_ensure_schema();

    switch ($acao) {

    /* ---------------------------- Extração ---------------------------- */
    case 'transcrever': {
        if (!iris_tem_chave()) throw new RuntimeException('A chave da API do Gemini ainda não foi configurada. Peça ao administrador.');
        $imgs = iris_imagens_upload('imagens', 5);
        if (!$imgs) throw new RuntimeException('Nenhuma imagem recebida.');
        $cfg = iris_config();
        $maxima = ($_POST['precisao'] ?? '') === 'maxima';
        $modelo = iris_modelo_escolhido($_POST['modelo'] ?? '');
        $ctx = iris_ctx_prompt() + ['pagina' => (int)($_POST['pagina'] ?? 0), 'total' => (int)($_POST['total'] ?? 0), 'vistas' => count($imgs) - 1];
        $partes = [];
        foreach ($imgs as $i => $im) $partes[] = ['bytes' => $im['bytes'], 'mime' => $im['mime'], 'res' => $i === 0 ? ($maxima ? 'ultra' : 'high') : 'high'];
        $reserva = $cfg['modelo_reserva'] ?? '';

        try {
            $r = iris_gemini_gerar(iris_api_key(), array_merge($partes, [['text' => iris_prompt_transcricao($ctx)]]),
                                   ['modelo' => $modelo, 'reserva' => $reserva, 'thinking' => $maxima ? 'high' : 'medium', 'resolucao' => 'high']);
            iris_registrar_chamada('transcrever', $modelo, $r);
        } catch (Throwable $e) { iris_registrar_chamada('transcrever', $modelo, null, $e->getMessage()); throw $e; }

        $texto = iris_limpar_resposta($r['texto']);
        $uso = $r['uso']; $modelos = [$r['modelo']]; $verificado = false; $aviso = '';
        $verif = trim((string)($cfg['modelo_verificacao'] ?? ''));
        if ($maxima && $texto !== '') {
            $mv = $verif !== '' && iris_modelo_por_id($verif) ? $verif : $modelo;
            try {
                $r2 = iris_gemini_gerar(iris_api_key(), array_merge($partes, [['text' => iris_prompt_verificacao($texto, $ctx)]]),
                                        ['modelo' => $mv, 'reserva' => $reserva, 'thinking' => 'high', 'resolucao' => 'high']);
                iris_registrar_chamada('verificar', $mv, $r2);
                $t2 = iris_limpar_resposta($r2['texto']);
                // salvaguarda: descarta revisão que perdeu muito conteúdo (resposta cortada/incompleta)
                if ($t2 !== '' && !$r2['truncado'] && mb_strlen($t2) >= 0.6 * mb_strlen($texto)) { $texto = $t2; $verificado = true; }
                else $aviso = 'A verificação retornou um texto incompleto; foi mantida a primeira leitura.';
                $uso['entrada'] += $r2['uso']['entrada']; $uso['saida'] += $r2['uso']['saida']; $uso['pensamento'] += $r2['uso']['pensamento'];
                $modelos[] = $r2['modelo'];
            } catch (Throwable $e) {
                iris_registrar_chamada('verificar', $mv, null, $e->getMessage());
                $aviso = 'A segunda leitura (verificação) falhou: ' . $e->getMessage() . ' — mantida a primeira leitura.';
            }
        }
        iris_ok(['texto' => $texto, 'duvidas' => iris_contar_duvidas($texto), 'modelo' => implode(' + ', array_unique($modelos)),
                 'verificado' => $verificado, 'truncado' => $r['truncado'], 'uso' => $uso, 'aviso' => $aviso]);
    }

    case 'reler_regiao': {
        if (!iris_tem_chave()) throw new RuntimeException('A chave da API do Gemini ainda não foi configurada.');
        $imgs = iris_imagens_upload('imagem', 1);
        if (!$imgs) throw new RuntimeException('Nenhuma imagem recebida.');
        $cfg = iris_config();
        $verif = trim((string)($cfg['modelo_verificacao'] ?? ''));
        $modelo = ($verif !== '' && iris_modelo_por_id($verif)) ? $verif : iris_modelo_escolhido($_POST['modelo'] ?? '');
        $prompt = iris_prompt_regiao((string)($_POST['antes'] ?? ''), (string)($_POST['depois'] ?? ''), iris_ctx_prompt());
        try {
            $r = iris_gemini_gerar(iris_api_key(), [['bytes' => $imgs[0]['bytes'], 'mime' => $imgs[0]['mime'], 'res' => 'ultra'], ['text' => $prompt]],
                                   ['modelo' => $modelo, 'reserva' => $cfg['modelo_reserva'] ?? '', 'thinking' => 'high', 'resolucao' => 'high', 'max_tokens' => 16384]);
            iris_registrar_chamada('regiao', $modelo, $r);
        } catch (Throwable $e) { iris_registrar_chamada('regiao', $modelo, null, $e->getMessage()); throw $e; }
        $texto = iris_limpar_resposta($r['texto']);
        iris_ok(['texto' => $texto, 'duvidas' => iris_contar_duvidas($texto), 'modelo' => $r['modelo'], 'uso' => $r['uso']]);
    }

    case 'estruturar': {
        if (!iris_tem_chave()) throw new RuntimeException('A chave da API do Gemini ainda não foi configurada.');
        $imgs = iris_imagens_upload('imagens', 16);
        $texto = trim((string)($_POST['texto'] ?? ''));
        if (!$imgs && $texto === '') throw new RuntimeException('Envie as páginas ou o texto transcrito.');
        $cfg = iris_config(); $ctx = iris_ctx_prompt();
        $modelo = iris_modelo_escolhido($_POST['modelo'] ?? '');
        $reserva = $cfg['modelo_reserva'] ?? '';
        $slug = trim((string)($_POST['tipo'] ?? 'auto'));
        $uso = ['entrada' => 0, 'saida' => 0, 'pensamento' => 0];
        $somar = function ($r) use (&$uso) { foreach ($uso as $k => $_) $uso[$k] += (int)($r['uso'][$k] ?? 0); };
        $partesImg = [];
        foreach ($imgs as $im) $partesImg[] = ['bytes' => $im['bytes'], 'mime' => $im['mime'], 'res' => 'medium'];
        $partesTexto = $texto !== '' ? [['text' => "TRANSCRIÇÃO DO DOCUMENTO (apoio):\n<<<\n" . mb_substr($texto, 0, 200000) . "\n>>>"]] : [];
        $classif = null;

        if ($slug === 'auto') {
            $tipos = iris_tipos(true);
            // classificação barata: texto (se houver) + até 2 primeiras páginas em baixa resolução
            $pc = [];
            foreach (array_slice($imgs, 0, 2) as $im) $pc[] = ['bytes' => $im['bytes'], 'mime' => $im['mime'], 'res' => 'medium'];
            if ($texto !== '') $pc[] = ['text' => "TRANSCRIÇÃO:\n" . mb_substr($texto, 0, 12000)];
            $pc[] = ['text' => iris_prompt_classificar($tipos, $texto !== '')];
            try {
                $rc = iris_gemini_gerar(iris_api_key(), $pc, ['modelo' => $modelo, 'reserva' => $reserva, 'json' => true, 'schema' => iris_schema_classificar(),
                                        'thinking' => 'low', 'resolucao' => 'medium', 'max_tokens' => 2048]);
                iris_registrar_chamada('classificar', $modelo, $rc); $somar($rc);
                $classif = iris_json_da_resposta($rc['texto']);
            } catch (Throwable $e) { iris_registrar_chamada('classificar', $modelo, null, $e->getMessage()); $classif = ['slug' => 'livre']; }
            $slug = (string)($classif['slug'] ?? 'livre');
            if ($slug !== 'livre' && !iris_tipo_por_slug($slug)) $slug = 'livre';
        }

        if ($slug === 'livre') {
            $prompt = iris_prompt_livre($texto !== '', $ctx); $schema = iris_schema_livre(); $tipo = null;
        } else {
            $tipo = iris_tipo_por_slug($slug);
            if (!$tipo) throw new RuntimeException('Tipo de documento não encontrado ou inativo.');
            $prompt = iris_prompt_estruturar($tipo, $texto !== '', $ctx); $schema = iris_schema_tipo($tipo);
        }
        try {
            $r = iris_gemini_gerar(iris_api_key(), array_merge($partesImg, $partesTexto, [['text' => $prompt]]),
                                   ['modelo' => $modelo, 'reserva' => $reserva, 'json' => true, 'schema' => $schema,
                                    'thinking' => 'medium', 'resolucao' => 'medium', 'max_tokens' => 32768]);
            iris_registrar_chamada('estruturar', $modelo, $r); $somar($r);
        } catch (Throwable $e) { iris_registrar_chamada('estruturar', $modelo, null, $e->getMessage()); throw $e; }
        $resp = iris_json_da_resposta($r['texto']);
        $dados = $tipo ? iris_normalizar_dados($tipo, $resp) : iris_normalizar_livre($resp);
        if ($classif) $dados['classificacao'] = ['slug' => $slug, 'confianca' => $classif['confianca'] ?? null, 'titulo' => $classif['titulo'] ?? null];
        $dados['modelo'] = $r['modelo'];
        iris_ok(['dados' => $dados, 'uso' => $uso, 'modelo' => $r['modelo']]);
    }

    case 'validar_campo': {   // revalida um valor editado pelo usuário
        $v = iris_validar_valor((string)($_POST['tipo'] ?? 'texto'), $_POST['valor'] ?? null);
        iris_ok(['validacao' => $v]);
    }

    /* ---------------------------- Histórico ---------------------------- */
    case 'salvar': {
        $d = $_POST;
        $id = iris_hist_salvar($d, $_FILES['arquivo'] ?? null);
        iris_ok(['id' => $id, 'message' => 'Salvo no histórico.']);
    }
    case 'hist_listar': iris_ok(iris_hist_listar($_POST));
    case 'hist_abrir': iris_ok(['item' => iris_hist_obter((int)($_POST['id'] ?? 0))]);
    case 'hist_excluir': iris_hist_excluir((int)($_POST['id'] ?? 0)); iris_ok(['message' => 'Extração excluída.']);
    case 'hist_hash': iris_ok(['item' => iris_hist_por_hash((string)($_POST['hash'] ?? ''))]);
    case 'hist_arquivo': {
        $row = iris_hist_obter((int)($_GET['id'] ?? $_POST['id'] ?? 0), false);
        $p = !empty($row['arquivo_path']) ? iris_dir_arquivos() . '/' . $row['arquivo_path'] : '';
        if (!$p || !is_file($p) || strpos(realpath($p), realpath(iris_dir_arquivos())) !== 0) throw new RuntimeException('O arquivo original não está mais disponível (prazo de retenção).');
        header('Content-Type: ' . ($row['arquivo_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($p));
        header('Content-Disposition: ' . (!empty($_GET['baixar']) ? 'attachment' : 'inline') . '; filename="' . str_replace(['"', "\r", "\n"], '', $row['arquivo_nome']) . '"');
        header('Cache-Control: private, max-age=600');
        readfile($p); exit;
    }

    case 'exportar_docx': {
        require_once __DIR__ . '/lib/docx.php';
        $nome = preg_replace('~[\\\\/:*?"<>|\r\n]+~', '_', trim((string)($_POST['nome'] ?? 'extracao'))) ?: 'extracao';
        $bin = iris_docx_gerar((string)($_POST['texto'] ?? ''), (string)($_POST['titulo'] ?? ''), !empty($_POST['realcar']));
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $nome . '.docx"');
        header('Content-Length: ' . strlen($bin));
        echo $bin; exit;
    }

    /* ---------------------------- Administração ---------------------------- */
    case 'config_salvar': {
        iris_require_admin();
        $campos = [];
        $novaChave = trim((string)($_POST['api_key'] ?? ''));
        if ($novaChave !== '' && strpos($novaChave, '•') === false) $campos['api_key_enc'] = iris_enc($novaChave);
        foreach (['prompt_extra', 'vocabulario'] as $k) if (isset($_POST[$k])) $campos[$k] = trim((string)$_POST[$k]);
        if (isset($_POST['precisao_padrao'])) $campos['precisao_padrao'] = $_POST['precisao_padrao'] === 'maxima' ? 'maxima' : 'padrao';
        if (isset($_POST['qualidade_px'])) $campos['qualidade_px'] = (string)max(1200, min(4000, (int)$_POST['qualidade_px']));
        if (isset($_POST['paginas_simultaneas'])) $campos['paginas_simultaneas'] = (string)max(1, min(4, (int)$_POST['paginas_simultaneas']));
        if (isset($_POST['retencao_dias'])) $campos['retencao_dias'] = (string)max(0, min(3650, (int)$_POST['retencao_dias']));
        foreach (['permitir_escolha_modelo', 'guardar_arquivos'] as $k) if (isset($_POST[$k])) $campos[$k] = $_POST[$k] === '1' ? '1' : '0';
        foreach (['modelo_verificacao', 'modelo_reserva'] as $k) if (isset($_POST[$k])) {
            $v = trim((string)$_POST[$k]);
            if ($v !== '' && !iris_modelo_por_id($v)) throw new RuntimeException('Modelo inválido em ' . $k . '.');
            $campos[$k] = $v === '' ? null : $v;
        }
        iris_config_set($campos);
        iris_ok(['message' => 'Configurações salvas.']);
    }
    case 'testar_chave': {
        iris_require_admin();
        $chave = trim((string)($_POST['api_key'] ?? ''));
        if ($chave === '' || strpos($chave, '•') !== false) $chave = iris_api_key();
        if ($chave === '') throw new RuntimeException('Informe a chave da API.');
        $lista = iris_gemini_listar_modelos($chave);
        $cad = array_column(iris_modelos(), 'identificador');
        foreach ($lista as &$m) $m['cadastrado'] = in_array($m['identificador'], $cad, true);
        iris_ok(['modelos' => $lista, 'message' => 'Conexão OK — ' . count($lista) . ' modelos disponíveis para esta chave.']);
    }
    case 'modelo_salvar': {
        iris_require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { iris_modelo_update($id, $_POST['rotulo'] ?? '', trim($_POST['descricao'] ?? '')); iris_ok(['message' => 'Modelo atualizado.']); }
        $novo = iris_modelo_add($_POST['identificador'] ?? '', $_POST['rotulo'] ?? '', trim($_POST['descricao'] ?? ''));
        iris_ok(['message' => 'Modelo cadastrado.', 'id' => $novo]);
    }
    case 'modelo_excluir': iris_require_admin(); iris_modelo_del((int)($_POST['id'] ?? 0)); iris_ok(['message' => 'Modelo excluído.']);
    case 'modelo_padrao': iris_require_admin(); iris_set_padrao((int)($_POST['id'] ?? 0)); iris_ok(['message' => 'Modelo padrão definido.']);

    case 'tipo_obter': {
        iris_require_admin();
        $st = iris_db()->prepare("SELECT * FROM iris_tipos WHERE id=?"); $id = (int)($_POST['id'] ?? 0);
        $st->bind_param('i', $id); $st->execute(); $t = iris_tipo_linha($st->get_result()->fetch_assoc()); $st->close();
        if (!$t) throw new RuntimeException('Tipo não encontrado.');
        iris_ok(['tipo' => $t]);
    }
    case 'tipo_salvar': {
        iris_require_admin();
        $d = $_POST; $d['campos'] = json_decode((string)($_POST['campos'] ?? '[]'), true);
        $id = iris_tipo_salvar($d);
        iris_ok(['id' => $id, 'message' => 'Tipo de documento salvo.']);
    }
    case 'tipo_ativar': iris_require_admin(); iris_tipo_ativar((int)($_POST['id'] ?? 0), ($_POST['ativo'] ?? '') === '1'); iris_ok();
    case 'tipo_excluir': iris_require_admin(); iris_tipo_excluir((int)($_POST['id'] ?? 0)); iris_ok(['message' => 'Tipo excluído.']);
    case 'tipos_restaurar': iris_require_admin(); iris_tipos_semear(false); iris_ok(['message' => 'Tipos de fábrica restaurados.']);
    case 'estatisticas': iris_require_admin(); iris_ok(['stats' => iris_estatisticas((int)($_POST['dias'] ?? 30))]);
    case 'limpar_arquivos': iris_require_admin(); $n = iris_limpeza_talvez(true); iris_ok(['message' => $n . ' arquivo(s) antigo(s) removido(s).']);

    default: throw new RuntimeException('Ação inválida.');
    }
} catch (Throwable $e) {
    iris_json(['status' => 'error', 'message' => $e->getMessage()]);
}
