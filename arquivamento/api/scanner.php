<?php
/**
 * API · Digitalização pelo scanner — lado da estação (TCloud Scanner).
 *
 * Não usa a sessão do Atlas: a estação se autentica só com o token do pedido
 * (256 bits aleatórios, criado pela tela de cadastro de um usuário logado,
 * válido por ARQ_DIG_VALIDADE_MIN minutos). Sem token válido, nada acontece.
 *
 *   GET  ?acao=info               → opções escolhidas na tela e estado do pedido
 *   POST ?acao=inicio   (JSON)    → { estacao, versao, scanner }
 *   POST ?acao=pagina   (binário) → corpo = JPEG ou PNG; cabeçalhos X-Dpi e X-Giro
 *   POST ?acao=fim      (JSON)    → { estado: concluido|cancelado|erro, mensagem }
 *
 * Token: cabeçalho X-TCloud-Token (preferido) ou parâmetro t.
 */

// Sem bootstrap.php de propósito: ele abre sessão, e cada chamada da estação
// criaria um arquivo de sessão vazio no servidor.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Digitalizacao.php';

date_default_timezone_set(ARQ_TIMEZONE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(__DIR__ . '/../logs')) { ini_set('error_log', __DIR__ . '/../logs/php-error.log'); }

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header_remove('X-Powered-By');

function arq_scn_json($dados, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function arq_scn_erro($msg, $status = 400) { arq_scn_json(['ok' => false, 'erro' => $msg], $status); }

/** Trilha de auditoria sem sessão: o usuário é o dono do pedido. */
function arq_scn_auditar($p, $etapa, $extra = [])
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) { return; }
    $reg = [
        'ts'       => date('c'),
        'usuario'  => (string) $p['usuario'],
        'nome'     => '',
        'ip'       => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
        'acao'     => 'digitalizar',
        'alvo'     => (string) $p['token'],
        'detalhes' => array_merge(['etapa' => $etapa, 'origem' => 'TCloud Scanner'], $extra),
    ];
    @file_put_contents($dir . '/auditoria-' . date('Y-m') . '.jsonl',
        json_encode($reg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (defined('ARQ_SCANNER_ATIVO') && !ARQ_SCANNER_ATIVO) {
    arq_scn_erro('A digitalização pelo scanner está desativada nesta serventia.', 403);
}

$token = isset($_SERVER['HTTP_X_TCLOUD_TOKEN']) ? $_SERVER['HTTP_X_TCLOUD_TOKEN']
       : (isset($_GET['t']) ? $_GET['t'] : '');
$token = arq_dig_token_valido($token);
if ($token === '') { arq_scn_erro('Token ausente ou inválido.', 401); }

$p = arq_dig_ler($token);
if (!$p) { arq_scn_erro('Pedido de digitalização não encontrado. Gere outro pelo Atlas.', 404); }
if (!arq_dig_vigente($p)) { arq_scn_erro('Este pedido de digitalização expirou ou foi encerrado. Gere outro pelo Atlas.', 410); }

$acao   = isset($_GET['acao']) ? (string) $_GET['acao'] : '';
$metodo = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

/** Corpo JSON da requisição (limitado a 16 KB). */
function arq_scn_corpo_json()
{
    $raw = (string) file_get_contents('php://input', false, null, 0, 16384);
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function arq_scn_texto($v, $max = 120)
{
    $v = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $v);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

switch ($acao) {

    case 'info':
        $p = arq_dig_alterar($token, function (&$x) { $x['contato'] = time(); return true; });
        arq_scn_json([
            'ok'      => true,
            'sistema' => 'Atlas · Arquivamento Digital',
            'versao'  => defined('ARQ_VERSAO') ? ARQ_VERSAO : '',
            'estado'  => $p['estado'],
            'opcoes'  => $p['opcoes'],
            'paginas' => count($p['paginas']),
            'limite'  => ARQ_DIG_MAX_PAGINAS,
            'expira'  => (int) $p['expira'],
        ]);
        break;

    case 'inicio':
        if ($metodo !== 'POST') { arq_scn_erro('Use POST.', 405); }
        $c = arq_scn_corpo_json();
        $p = arq_dig_alterar($token, function (&$x) use ($c) {
            $x['estado']   = 'digitalizando';
            $x['mensagem'] = '';
            $x['contato']  = time();
            $x['estacao']  = [
                'nome'    => arq_scn_texto(isset($c['estacao']) ? $c['estacao'] : ''),
                'versao'  => arq_scn_texto(isset($c['versao']) ? $c['versao'] : '', 20),
                'scanner' => arq_scn_texto(isset($c['scanner']) ? $c['scanner'] : ''),
            ];
            return true;
        });
        arq_scn_auditar($p, 'inicio', ['estacao' => $p['estacao']]);
        arq_scn_json(['ok' => true, 'paginas' => count($p['paginas'])]);
        break;

    case 'pagina':
        if ($metodo !== 'POST') { arq_scn_erro('Use POST.', 405); }
        $tamanho = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($tamanho > ARQ_DIG_MAX_BYTES_PAGINA) { arq_scn_erro('Página maior que o limite do servidor.', 413); }
        $bytes = (string) file_get_contents('php://input');
        if ($bytes === '') {
            arq_scn_erro('Página vazia. Se a imagem for grande, confira post_max_size no php.ini do servidor.', 400);
        }
        $dpi  = isset($_SERVER['HTTP_X_DPI']) ? (int) $_SERVER['HTTP_X_DPI'] : (int) $p['opcoes']['dpi'];
        $giro = isset($_SERVER['HTTP_X_GIRO']) ? (int) $_SERVER['HTTP_X_GIRO'] : 0;
        $r = arq_dig_receber_pagina($token, $bytes, $dpi > 0 ? $dpi : (int) $p['opcoes']['dpi'], $giro);
        if (!$r['ok']) { arq_scn_erro($r['erro'], 422); }
        arq_scn_json(['ok' => true, 'n' => $r['n']]);
        break;

    case 'fim':
        if ($metodo !== 'POST') { arq_scn_erro('Use POST.', 405); }
        $c = arq_scn_corpo_json();
        $estado = isset($c['estado']) ? (string) $c['estado'] : 'concluido';
        if (!in_array($estado, ['concluido', 'cancelado', 'erro'], true)) { $estado = 'concluido'; }
        $msg = arq_scn_texto(isset($c['mensagem']) ? $c['mensagem'] : '', 400);
        $p = arq_dig_alterar($token, function (&$x) use ($estado, $msg) {
            $x['estado']   = $estado;
            $x['mensagem'] = $msg;
            $x['contato']  = time();
            return true;
        });
        arq_scn_auditar($p, $estado, ['paginas' => count($p['paginas']), 'mensagem' => $msg]);
        arq_scn_json(['ok' => true, 'paginas' => count($p['paginas'])]);
        break;

    default:
        arq_scn_erro('Ação desconhecida.', 400);
}
