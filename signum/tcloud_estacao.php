<?php
/**
 * tcloud_estacao.php — atende o app TCloud Assinador da estação no MODO LOCAL (tcloudsign://local).
 * Ações: ticket_abrir | ticket_documento | ticket_entregar | ticket_concluir | ticket_recusar
 * Sem sessão do Atlas: a credencial é o ticket do link (256 bits, uso único, preso ao IP de quem clicou).
 */
define('ASG_SEM_SESSAO', true);
require_once __DIR__ . '/config_assinatura.php';
require_once __DIR__ . '/lib/tcloud_local.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function tcl_saida($http, $dados) { http_response_code($http); echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new AsgTclErro('Use POST.', 'metodo', 405);
    $acao = (string)($_GET['acao'] ?? '');
    if (strpos($acao, 'ticket_') !== 0) throw new AsgTclErro('Ação desconhecida.', 'acao', 404);
    $corpo = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($corpo)) throw new AsgTclErro('Pedido inválido (JSON).', 'json', 400);
    asg_ensure_schema();
    tcl_saida(200, asg_tcl_atender($acao, $corpo, (string)($_SERVER['REMOTE_ADDR'] ?? '')));
} catch (AsgTclErro $e) {
    tcl_saida($e->http, ['ok' => false, 'erro' => $e->getMessage(), 'codigo' => $e->codigo]);
} catch (Throwable $e) {
    tcl_saida(500, ['ok' => false, 'erro' => 'Falha no sistema: ' . $e->getMessage(), 'codigo' => 'erro']);
}
