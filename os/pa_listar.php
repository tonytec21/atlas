<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/pagamento_anexos_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $pid = (int)($_GET['pagamento_id'] ?? 0);
    if ($pid <= 0) throw new RuntimeException('Pagamento inválido.');
    $aceitos = pa_tipos_aceitos();
    $itens = array_map(function($a) use ($aceitos) {
        $ext  = strtolower(pathinfo($a['arquivo'], PATHINFO_EXTENSION));
        $mime = $aceitos[$ext] ?? ($a['mime'] ?: 'application/octet-stream'); // extensão manda (mime pode estar corrompido em registros antigos)
        return [
            'id' => (int)$a['id'],
            'nome' => $a['nome_original'],
            'mime' => $mime,
            'tamanho' => (int)$a['tamanho'],
            'enviado_por' => $a['enviado_por'],
            'enviado_em' => $a['enviado_em'] ? date('d/m/Y H:i', strtotime($a['enviado_em'])) : '',
            'is_pdf' => ($ext === 'pdf'),
            'url' => 'pa_ver.php?id=' . (int)$a['id'],
        ];
    }, pa_lista($pid));
    echo json_encode(['success'=>true,'itens'=>$itens], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
