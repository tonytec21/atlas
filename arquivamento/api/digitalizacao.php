<?php
/**
 * API · Digitalização pelo scanner — lado do navegador (exige sessão).
 *
 *   POST acao=criar      opcoes (JSON)  → { token, link, expira }
 *   GET  acao=estado     t              → resumo do pedido e lista de páginas
 *   GET  acao=pagina     t, n           → imagem da página (JPEG/PNG)
 *   POST acao=descartar  t              → apaga o pedido e as páginas
 *
 * A estação (TCloud Scanner) fala com api/scanner.php, não com este arquivo.
 * Ver lib/Digitalizacao.php para o desenho completo.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Digitalizacao.php';
arq_exige_login();

if (defined('ARQ_SCANNER_ATIVO') && !ARQ_SCANNER_ATIVO) {
    arq_erro('A digitalização pelo scanner está desativada nesta serventia.', 403);
}

$acao    = isset($_REQUEST['acao']) ? (string) $_REQUEST['acao'] : '';
$usuario = arq_usuario();

/** Carrega o pedido e confere se é do usuário logado. */
function arq_dig_do_usuario($token, $usuario)
{
    $token = arq_dig_token_valido($token);
    if ($token === '') { arq_erro('Pedido de digitalização inválido.', 400); }
    $p = arq_dig_ler($token);
    if (!$p) { arq_erro('Pedido de digitalização não encontrado ou expirado.', 404); }
    if (!hash_equals((string) $p['usuario'], (string) $usuario)) {
        arq_erro('Este pedido de digitalização é de outro usuário.', 403);
    }
    return $p;
}

switch ($acao) {

    /* ------------------------------------------------------------ */
    case 'criar':
        arq_exige_post_seguro();
        if (!arq_limite_taxa('digitalizacao', 30, 300)) {
            arq_erro('Muitos pedidos de digitalização seguidos. Aguarde alguns minutos.', 429);
        }
        $opcoes = json_decode(isset($_POST['opcoes']) ? (string) $_POST['opcoes'] : '{}', true);
        $token  = arq_dig_criar($usuario, $opcoes);
        if ($token === '') {
            arq_erro('Não foi possível preparar a digitalização (sem permissão de escrita em digitalizacoes/).', 500);
        }
        $p = arq_dig_ler($token);
        session_write_close();

        $link = 'tcloudscan://digitalizar/?u=' . rawurlencode(arq_dig_endpoint_estacao())
              . '&t=' . $token;

        arq_auditar('digitalizar', $token, ['etapa' => 'pedido', 'opcoes' => $p['opcoes']]);
        arq_ok([
            'token'  => $token,
            'link'   => $link,
            'expira' => (int) $p['expira'],
            'opcoes' => $p['opcoes'],
        ]);
        break;

    /* ------------------------------------------------------------ */
    case 'estado':
        $p = arq_dig_do_usuario(isset($_GET['t']) ? $_GET['t'] : '', $usuario);
        // A tela consulta a cada segundo: libera a sessão para não enfileirar
        // as outras requisições do usuário atrás desta.
        session_write_close();
        arq_ok(arq_dig_resumo($p));
        break;

    /* ------------------------------------------------------------ */
    case 'pagina':
        $p = arq_dig_do_usuario(isset($_GET['t']) ? $_GET['t'] : '', $usuario);
        session_write_close();
        $n = isset($_GET['n']) ? (int) $_GET['n'] : 0;
        $pagina = null;
        foreach ($p['paginas'] as $pg) {
            if ((int) $pg['n'] === $n) { $pagina = $pg; break; }
        }
        if (!$pagina) { arq_erro('Página não encontrada.', 404); }

        $pasta = arq_dig_pasta($p['token']);
        if (!preg_match('/^p\d{4}\.(jpg|png)$/', (string) $pagina['arquivo'])) { arq_erro('Página inválida.', 400); }
        $caminho = $pasta . DIRECTORY_SEPARATOR . $pagina['arquivo'];
        if (!is_file($caminho)) { arq_erro('Página indisponível.', 404); }

        header('Content-Type: ' . ($pagina['tipo'] === 'png' ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($caminho));
        header('Cache-Control: private, max-age=3600');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        readfile($caminho);
        exit;

    /* ------------------------------------------------------------ */
    case 'descartar':
        arq_exige_post_seguro();
        $token = isset($_POST['t']) ? $_POST['t'] : '';
        $p = arq_dig_do_usuario($token, $usuario);
        session_write_close();
        $anexado = isset($_POST['anexado']) && $_POST['anexado'] === '1';
        arq_dig_alterar($p['token'], function (&$x) { $x['estado'] = 'descartado'; return true; });
        arq_dig_excluir($p['token']);
        arq_auditar('digitalizar', $p['token'], [
            'etapa'   => $anexado ? 'anexado' : 'descartado',
            'paginas' => count($p['paginas']),
            'anexadas' => isset($_POST['paginas']) ? (int) $_POST['paginas'] : null,
            'scanner' => isset($p['estacao']['scanner']) ? $p['estacao']['scanner'] : '',
        ]);
        arq_ok();
        break;

    default:
        arq_erro('Ação desconhecida.', 400);
}
