<?php
/**
 * =====================================================================
 * auditoria/hook.php — Gancho da auditoria central do Atlas
 * ---------------------------------------------------------------------
 * Incluído pelos arquivos de sessão e de conexão de todos os módulos
 * (session_check.php, db_connection*.php e afins). Não altera nada do
 * comportamento da página: só observa e, no fim da requisição, grava o
 * que aconteceu na trilha de auditoria.
 *
 *   - marca as conexões com o banco (usuário, requisição, IP, módulo), para
 *     os gatilhos saberem quem alterou cada registro;
 *   - no fim da requisição, grava a operação (POST/exclusões/downloads),
 *     ou a tela aberta, conforme a configuração do Painel do Administrador.
 *
 * Desligamento de emergência: crie o arquivo auditoria/dados/DESLIGAR.
 * =====================================================================
 */

if (defined('ATLAS_AUD_HOOK')) {
    return;
}
define('ATLAS_AUD_HOOK', 1);

if (is_file(__DIR__ . '/dados/DESLIGAR')) {
    return;   // as funções abaixo continuam existindo, mas sem contexto não fazem nada
}

$GLOBALS['__atlas_aud'] = [
    't0'      => microtime(true),
    'quando'  => (new DateTime('now', new DateTimeZone('America/Fortaleza')))->format('Y-m-d H:i:s.v'),
    'req'     => bin2hex(random_bytes(8)),
    'raiz'    => str_replace('\\', '/', dirname(__DIR__)),
    'cli'     => PHP_SAPI === 'cli',
    'usuario' => null,
    'saida'   => '',
    'tags'    => [],
];

(function () {
    set_error_handler(function () { return true; });   // nenhum aviso da auditoria pode aparecer na página
    try {
        atlas_aud__iniciar();
    } catch (Throwable $e) {
    }
    restore_error_handler();
})();

function atlas_aud__iniciar(): void
{
    $c = &$GLOBALS['__atlas_aud'];
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $raiz = $c['raiz'] . '/';
    $rel = strpos($script, $raiz) === 0 ? substr($script, strlen($raiz)) : basename($script);
    $c['pagina'] = $rel;
    $b = strtolower(basename($rel, '.php'));
    if (strpos($rel, '/') === false) {
        if (in_array($b, ['login', 'check_login', 'logout', 'verificar-2fa'], true)) {
            $c['modulo'] = 'acesso';
        } elseif (preg_match('/funcionario|save_user|register|credenciais|checar_acesso/', $b)) {
            $c['modulo'] = 'funcionarios';
        } elseif (strpos($b, 'serventia') !== false) {
            $c['modulo'] = 'serventia';
        } elseif (strpos($b, 'tarefa') !== false || strpos($b, 'recorrente') !== false) {
            $c['modulo'] = 'tarefas';
        } elseif ($b === 'liberar_os') {
            $c['modulo'] = 'os';
        } else {
            $c['modulo'] = 'inicio';
        }
    } else {
        $c['modulo'] = strtolower(strtok($rel, '/'));
    }

    if ($c['cli']) {
        $c['usuario'] = 'sistema';
        return;
    }
    // Lê a sessão sem prendê-la (read_and_close): a página continua abrindo a sua normalmente.
    if (session_status() === PHP_SESSION_ACTIVE) {
        $c['usuario'] = $_SESSION['username'] ?? null;
    } elseif (session_status() === PHP_SESSION_NONE && !headers_sent() && !empty($_COOKIE[session_name()])) {
        $lim = session_cache_limiter();
        @session_cache_limiter('');
        if (@session_start(['read_and_close' => true])) {
            $c['usuario'] = $_SESSION['username'] ?? null;
        }
        @session_cache_limiter($lim);
    }
    // Observa o começo da resposta (para saber se a operação deu certo ou foi negada). Repassa tudo sem mexer.
    // Por segurança, NÃO observa scripts que mexem nos próprios buffers de saída, transmitem arquivos
    // ou geram PDF/planilhas/downloads (um nível extra de buffer poderia mudar o que eles enviam).
    $src = @file_get_contents((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''), false, null, 0, 600000);
    $arriscado = $src === false || $src === ''
        || preg_match('/\bob_(end_clean|clean|get_clean|end_flush|get_flush|flush|implicit_flush)\s*\(|\b(readfile|fpassthru|flush)\s*\(|->\s*(Output|stream|save)\s*\(|Content-(Disposition|Length)|application\/(pdf|octet|zip|vnd)|text\/(csv|event-stream)/i', $src)
        || preg_match('/pdf|download|baixar|imprimir|print|stream|export|planilha|xls|csv|zip|arquivo|anexo|imagem|thumb|view|visualizar|capa|livro|recibo|comprovante|espelho|certidao|requerimento|protocolo|guia/i', $b);
    if (!$arriscado && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
        ob_start(function ($buf) {
            $c = &$GLOBALS['__atlas_aud'];
            $c['tamanho'] = ($c['tamanho'] ?? 0) + strlen((string) $buf);
            if (strlen($c['saida']) < 3000) {
                $c['saida'] .= substr((string) $buf, 0, 3000 - strlen($c['saida']));
            }
            return $buf;
        }, 8192);
    }
}

/** Contexto da requisição atual (usado por atlas_auditar()). */
function atlas_aud_contexto(): array
{
    $c = $GLOBALS['__atlas_aud'] ?? [];
    if (!$c) {
        return [];
    }
    $c['usuario'] = $_SESSION['username'] ?? ($c['usuario'] ?? null);
    return $c;
}

/**
 * Marca uma conexão (mysqli ou PDO) com quem está operando, para os gatilhos
 * do banco registrarem o autor de cada alteração. Aceita também um array
 * (ex.: get_defined_vars()) e marca todas as conexões encontradas nele.
 */
function atlas_aud_conexao($c = null): void
{
    if (empty($GLOBALS['__atlas_aud'])) {
        return;   // auditoria desligada
    }
    set_error_handler(function () { return true; });
    try {
        if (is_array($c)) {
            foreach ($c as $v) {
                if ($v instanceof mysqli || $v instanceof PDO) {
                    atlas_aud_conexao($v);
                }
            }
            return;
        }
        if (!($c instanceof mysqli) && !($c instanceof PDO)) {
            return;
        }
        $ctx = &$GLOBALS['__atlas_aud'];
        $usuario = $_SESSION['username'] ?? ($ctx['usuario'] ?? null);
        // Evita marcar a mesma conexão duas vezes sem segurar a conexão aberta:
        // mysqli pelo id da conexão no MySQL; PDO por WeakMap (PHP 8) ou sem deduplicação (PHP 7.4).
        if ($c instanceof mysqli) {
            $chave = 'm' . (int) @$c->thread_id . '|' . $usuario;
            if (isset($ctx['tags'][$chave])) {
                return;
            }
            $ctx['tags'][$chave] = true;
        } else {
            if (class_exists('WeakMap')) {
                if (!isset($ctx['pdos'])) {
                    $ctx['pdos'] = new WeakMap();
                }
                if (isset($ctx['pdos'][$c]) && $ctx['pdos'][$c] === $usuario) {
                    return;
                }
                $ctx['pdos'][$c] = $usuario;
            }
            $ctx['tags'][] = true;
        }
        $ip = $ctx['cli'] ? 'cli' : (($_SERVER['REMOTE_ADDR'] ?? '') === '::1' ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? null));
        $vals = [$usuario, $ctx['req'], $ip, $ctx['modulo'] ?? null];
        if ($c instanceof mysqli) {
            if (!empty($c->connect_errno)) {
                return;
            }
            $partes = [];
            foreach ($vals as $v) {
                $partes[] = $v === null ? 'NULL' : "'" . $c->real_escape_string((string) $v) . "'";
            }
            @$c->query('SET @atlas_usuario=' . $partes[0] . ', @atlas_req=' . $partes[1] . ', @atlas_ip=' . $partes[2] . ', @atlas_modulo=' . $partes[3]);
        } else {
            $st = $c->prepare('SET @atlas_usuario=?, @atlas_req=?, @atlas_ip=?, @atlas_modulo=?');
            $st->execute($vals);
            $st->closeCursor();
        }
    } catch (Throwable $e) {
        // nunca atrapalha a página
    } finally {
        restore_error_handler();
    }
}

register_shutdown_function(function () {
    set_error_handler(function () { return true; });   // avisos daqui iriam parar no fim da resposta
    try {
        $ctx = &$GLOBALS['__atlas_aud'];
        // O PHP só esvazia os buffers de saída depois desta função: lê o que ainda está pendente.
        if (!$ctx['cli'] && ob_get_level() > 0) {
            $pend = (string) ob_get_contents();
            $ctx['tamanho'] = ($ctx['tamanho'] ?? 0) + strlen($pend);
            if (strlen($ctx['saida']) < 3000) {
                $ctx['saida'] .= substr($pend, 0, 3000 - strlen($ctx['saida']));
            }
        }
        $usuario = $_SESSION['username'] ?? ($ctx['usuario'] ?? null);
        $metodo = $ctx['cli'] ? 'CLI' : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $b = strtolower(basename($ctx['pagina'], '.php'));

        // Telas de interface que só guardam preferências visuais não interessam à auditoria.
        if (preg_match('/^(save_mode|load_mode|save_order|load_order|keepalive|heartbeat|ping|session_check|atlas_tempo)$/', $b)) {
            return;
        }

        // Conexões globais ainda abertas: as não marcadas na criação são reivindicadas pelo id.
        $conexoes = [];
        foreach ($GLOBALS as $k => $v) {
            if ($v instanceof mysqli) {
                try {
                    if (empty($v->connect_errno) && @$v->thread_id) {
                        $conexoes[] = (int) $v->thread_id;
                    }
                } catch (Throwable $e) {
                    // conexão já fechada pela página
                }
            } elseif ($v instanceof PDO) {
                try {
                    $conexoes[] = (int) $v->query('SELECT CONNECTION_ID()')->fetchColumn();
                } catch (Throwable $e) {
                }
            }
        }

        $headers = function_exists('headers_list') ? headers_list() : [];
        $tipo = '';
        $disposicao = '';
        $location = '';
        foreach ($headers as $h) {
            $hl = strtolower($h);
            if (strpos($hl, 'content-type:') === 0) {
                $tipo = trim(substr($hl, 13));
            } elseif (strpos($hl, 'content-disposition:') === 0) {
                $disposicao = trim(substr($h, 20));
            } elseif (strpos($hl, 'location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        $status = $ctx['cli'] ? null : (int) (http_response_code() ?: 200);
        $docNav = !$ctx['cli'] && in_array(strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''), ['document'], true)
            || (!$ctx['cli'] && !isset($_SERVER['HTTP_SEC_FETCH_DEST']) && empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strpos(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false);

        // Atalho: consulta GET que não abriu tela, não gerou arquivo e não usou o banco -> nada a registrar
        if (!$ctx['cli'] && ($metodo === 'GET' || $metodo === 'HEAD') && !$docNav && $disposicao === '' && empty($ctx['tags']) && !$conexoes
            && ($tipo === '' || preg_match('#^(text/html|application/json|text/javascript|application/javascript|text/css|text/plain)#', $tipo))) {
            return;
        }

        require_once __DIR__ . '/lib.php';
        $pdo = aud_pdo();
        aud_estrutura($pdo);
        $cfg = aud_config();
        if (empty($cfg['ativa'])) {
            return;
        }

        // Linhas de dados alteradas por esta requisição
        $dados = [];
        $linhas = 0;
        if (!empty($cfg['dados'])) {
            if ($conexoes) {
                $in = implode(',', array_map('intval', array_unique($conexoes)));
                $st = $pdo->prepare("UPDATE atlas_auditoria_dados SET req_id = ?, usuario = COALESCE(NULLIF(usuario,''), ?), modulo = COALESCE(modulo, ?)
                                      WHERE req_id IS NULL AND hash IS NULL AND conexao IN ($in) AND criado_em >= ?");
                $st->execute([$ctx['req'], $usuario, $ctx['modulo'], substr($ctx['quando'], 0, 19)]);
            }
            if ($usuario !== null) {
                $st = $pdo->prepare("UPDATE atlas_auditoria_dados SET usuario = ? WHERE req_id = ? AND hash IS NULL AND (usuario IS NULL OR usuario = '')");
                $st->execute([$usuario, $ctx['req']]);
            }
            $st = $pdo->prepare('SELECT tabela, operacao, COUNT(*) c FROM atlas_auditoria_dados WHERE req_id = ? GROUP BY tabela, operacao ORDER BY c DESC');
            $st->execute([$ctx['req']]);
            foreach ($st->fetchAll() as $r) {
                $dados[] = $r;
                $linhas += (int) $r['c'];
            }
        }

        // Parâmetros enviados
        $params = [];
        if (!$ctx['cli']) {
            $params = $_GET + $_POST;
            if (!$_POST && $metodo !== 'GET' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
                $j = json_decode((string) @file_get_contents('php://input', false, null, 0, 65536), true);
                if (is_array($j)) {
                    $params += $j;
                }
            }
        }

        $pagina = $ctx['pagina'];
        $modulo = $ctx['modulo'];
        $nome = $_SESSION['nome_completo'] ?? null;
        $nivel = $_SESSION['nivel_de_acesso'] ?? null;
        $duracao = (int) round((microtime(true) - $ctx['t0']) * 1000);
        $comum = [
            'criado_em' => $ctx['quando'], 'req_id' => $ctx['req'], 'usuario' => $usuario, 'nome' => $nome, 'nivel' => $nivel,
            'ip' => aud_ip(), 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null, 'sessao' => aud_sessao_hash(),
            'modulo' => $modulo, 'pagina' => $pagina, 'metodo' => $metodo, 'status_http' => $status, 'duracao_ms' => $duracao,
        ];

        /* ---------- Acesso ao sistema (login, 2FA, saída) ---------- */
        if ($modulo === 'acesso' && $metodo === 'POST' || $b === 'logout') {
            $ev = $comum;
            $ev['categoria'] = 'acesso';
            if ($b === 'logout') {
                $ev['acao'] = 'Saída do sistema';
                $ev['usuario'] = $ctx['usuario'] ?? $usuario;
            } elseif ($b === 'check_login' || $b === 'login') {
                $tentado = isset($_POST['username']) ? (string) $_POST['username'] : null;
                $ev['usuario'] = $usuario ?: $tentado;
                $falhou = preg_match('/[?&]error=/', $location) || ($location === '' && empty($_SESSION['username']) && empty($_SESSION['tfa_pending']));
                if (strpos($location, 'verificar-2fa') !== false) {
                    $ev['acao'] = 'Senha correta, aguardando o código de verificação (2FA)';
                } elseif (!$falhou && !empty($_SESSION['username'])) {
                    $ev['acao'] = 'Entrada no sistema';
                } elseif (!$falhou && !empty($_SESSION['tfa_pending'])) {
                    $ev['acao'] = 'Senha correta, aguardando o código de verificação (2FA)';
                } else {
                    $ev['acao'] = 'Tentativa de entrada recusada';
                    $ev['resultado'] = 'falha';
                    $ev['severidade'] = 'aviso';
                    $ev['mensagem'] = strpos($location, 'error=2') !== false ? 'Usuário inativo' : 'Usuário ou senha inválidos';
                }
            } elseif ($b === 'verificar-2fa') {
                if (isset($_POST['cancelar'])) {
                    $ev['acao'] = 'Verificação em duas etapas cancelada';
                } elseif (!empty($_SESSION['username'])) {
                    $ev['acao'] = 'Entrada no sistema (com verificação em duas etapas)';
                } else {
                    $ev['usuario'] = $_SESSION['tfa_pending']['username'] ?? $usuario;
                    $ev['acao'] = 'Código de verificação (2FA) recusado';
                    $ev['resultado'] = 'falha';
                    $ev['severidade'] = 'aviso';
                }
            } else {
                $ev['acao'] = aud_rotulo_script($pagina);
            }
            if (!empty($ev['usuario']) && empty($ev['nome'])) {
                $ev['nome'] = $_SESSION['tfa_pending']['nome_completo'] ?? null;
            }
            aud_registrar($ev);
            aud_selar(150);
            return;
        }

        [$categoria, $severidade, $acao] = aud_classificar($pagina, $metodo, $params, $modulo);

        // Downloads e documentos gerados
        $arquivo = '';
        if ($disposicao !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)/i', $disposicao, $mm)) {
            $arquivo = rawurldecode($mm[1]);
        }
        $ehArquivo = $disposicao !== ''
            || ($tipo !== '' && !preg_match('#^(text/html|application/json|text/javascript|application/javascript|text/css|image/|text/plain|text/event-stream)#', $tipo))
            || (preg_match('#^image/#', $tipo) && $docNav);

        // Resultado: código HTTP, redirecionamento com erro, ou resposta JSON com falha
        $resultado = 'sucesso';
        $mensagem = null;
        if ($status !== null && $status >= 400) {
            $resultado = $status === 403 || $status === 401 ? 'negado' : 'falha';
            $mensagem = 'HTTP ' . $status;
        }
        $saida = $ctx['saida'];
        $curta = ($ctx['tamanho'] ?? 0) < 2500;
        if ($saida !== '') {
            if ($curta && preg_match('/n[ãa]o tem permiss[ãa]o|acesso negado|acesso restrito|sem permiss[ãa]o|n[ãa]o autorizado|apenas (o |para )?administrador/iu', $saida)) {
                $resultado = 'negado';
                $mensagem = 'Acesso negado pela regra do módulo';
            } elseif (preg_match('/"(success|ok|sucesso)"\s*:\s*false|"status"\s*:\s*"(error|erro|falha)"/i', $saida)) {
                $resultado = 'falha';
                if (preg_match('/"(message|mensagem|erro|error|msg)"\s*:\s*"((?:[^"\\\\]|\\\\.){1,300})"/u', $saida, $mm)) {
                    $mensagem = json_decode('"' . $mm[2] . '"') ?? $mm[2];
                }
            }
        }
        if ($location !== '' && preg_match('/[?&](error|erro)=/', $location) && $resultado === 'sucesso') {
            $resultado = 'falha';
            $mensagem = 'Redirecionado com erro: ' . mb_substr($location, 0, 200);
        }

        // Refina a categoria pelo que de fato mudou no banco
        if ($dados) {
            $ops = array_unique(array_column($dados, 'operacao'));
            if (in_array('D', $ops, true) && !in_array($categoria, ['estorno', 'seguranca', 'cancelamento'], true)) {
                $categoria = 'exclusao';
                $severidade = 'critico';
            } elseif (in_array($categoria, ['navegacao', 'outros', 'documento'], true)) {
                $categoria = in_array('U', $ops, true) ? 'alteracao' : 'criacao';
            }
            $fin = false;
            foreach ($dados as $d) {
                if (in_array(aud_modulo_da_tabela($d['tabela']), ['os', 'caixa', 'contas_a_pagar'], true)
                    && preg_match('/pagamento|liquid|devolu|repasse|caixa|deposito|saida|transfer|conta/', $d['tabela'])) {
                    $fin = true;
                }
                if (preg_match('/config|configuracao|_cfg$/', $d['tabela']) && in_array($categoria, ['criacao', 'alteracao', 'outros'], true)) {
                    $categoria = 'configuracao';
                    $severidade = 'aviso';
                }
                if ($d['tabela'] === 'funcionarios') {
                    $categoria = 'seguranca';
                    $severidade = 'critico';
                }
            }
            if ($fin && in_array($categoria, ['criacao', 'alteracao', 'outros'], true)) {
                $categoria = 'financeiro';
                $severidade = $severidade === 'critico' ? 'critico' : 'aviso';
            }
        }
        if ($resultado === 'negado') {
            $severidade = 'aviso';
            if (!$dados) {
                $categoria = 'seguranca';
                $acao = 'Acesso negado · ' . aud_modulo_rotulo($modulo) . ' · ' . aud_rotulo_script($pagina);
            }
        }

        $resumo = null;
        if ($dados) {
            $ops = ['I' => 'incl.', 'U' => 'alt.', 'D' => 'excl.'];
            $p = [];
            foreach (array_slice($dados, 0, 12) as $d) {
                $p[] = $d['tabela'] . ' ' . $d['c'] . ' ' . ($ops[$d['operacao']] ?? $d['operacao']);
            }
            $resumo = implode(', ', $p);
        }

        $acaoP = '';
        foreach (['acao', 'action', 'op', 'operacao'] as $k) {
            if (isset($params[$k]) && is_scalar($params[$k]) && $params[$k] !== '') {
                $acaoP = strtolower((string) $params[$k]);
                break;
            }
        }
        if ($acaoP !== '') {
            $ehLeitura = (bool) preg_match('/^(get|load|list|listar|buscar|busca|consulta|consultar|pesquisar|search|obter|ler|ver|visualizar|exibir|mostrar|contar|count|stats|resumo|historico|detalhe|dados|painel|uso|status_|verificar|testar|filtr|carregar|sugerir|autocomplete)/', $acaoP);
        } else {
            $ehLeitura = (bool) preg_match('/^(get|load|list|listar|buscar|busca|consulta|consultar|pesquisar|search|filtr|carregar|verificar|check|stats|estatistica|obter|ler|ver|visualizar|exibir|mostrar|preview|autocomplete|sugest|contar|count|resumo|dashboard)|(_dados|_server_side|_json|_ajax|_lista|_grafico|_graficos|_stats|_resumo)$/', $b)
                || isset($params['draw'], $params['start'], $params['length']);
        }

        $registrar = false;
        if ($ctx['cli']) {
            $registrar = $linhas > 0;
            if ($registrar) {
                $comum['usuario'] = 'sistema';
                $acao = 'Rotina automática · ' . aud_rotulo_script($pagina);
            }
        } elseif ($linhas > 0) {
            $registrar = true;
        } elseif ($metodo === 'GET' || $metodo === 'HEAD') {
            if ($ehArquivo && $usuario) {
                $registrar = true;
                $categoria = 'documento';
                $severidade = 'info';
                $acao = ($arquivo !== '' ? 'Arquivo baixado/aberto: ' . $arquivo : 'Documento gerado · ' . aud_rotulo_script($pagina));
            } elseif ($resultado === 'negado' && $usuario) {
                $registrar = true;
            } elseif ($docNav && $usuario && $status < 400) {
                aud_registrar_navegacao($comum + ['consulta' => mb_substr((string) ($_SERVER['QUERY_STRING'] ?? ''), 0, 300)]);
                if (mt_rand(1, 20) === 1) {
                    aud_selar(150);
                }
                return;
            }
        } else {
            // POST/PUT/DELETE sem alteração no banco: registra, exceto consultas (listas, buscas, gráficos)
            $registrar = !$ehLeitura || $resultado !== 'sucesso' || $ehArquivo || !empty($_FILES);
            if ($ehArquivo && $categoria !== 'exclusao') {
                $categoria = 'documento';
                if ($arquivo !== '') {
                    $acao .= ' · ' . $arquivo;
                }
            }
        }
        if (!$registrar) {
            if (mt_rand(1, 50) === 1) {
                aud_selar(150);
            }
            return;
        }

        $limpos = aud_limpar_parametros($params);
        if (!empty($_FILES)) {
            $arqs = [];
            foreach ($_FILES as $campo => $f) {
                $nomes = (array) ($f['name'] ?? []);
                $tams = (array) ($f['size'] ?? []);
                foreach ($nomes as $i => $n) {
                    if ($n !== '' && $n !== null) {
                        $arqs[] = $n . ' (' . number_format(((int) ($tams[$i] ?? 0)) / 1024, 0, ',', '.') . ' KB)';
                    }
                }
            }
            if ($arqs) {
                $limpos['_arquivos_enviados'] = array_slice($arqs, 0, 30);
            }
        }

        aud_registrar($comum + [
            'acao' => $acao, 'categoria' => $categoria, 'severidade' => $severidade, 'resultado' => $resultado,
            'mensagem' => $mensagem, 'alvo' => aud_alvo($params) ?: null, 'parametros' => $limpos ?: null,
            'dados_resumo' => $resumo, 'dados_linhas' => $linhas,
        ]);
        aud_selar(150);
    } catch (Throwable $e) {
        error_log('[atlas_auditoria] gancho: ' . $e->getMessage());
    } finally {
        restore_error_handler();
    }
});
