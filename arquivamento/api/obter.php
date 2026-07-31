<?php
/** API · Detalhe de um arquivamento. */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();

$id      = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
$lixeira = isset($_GET['lixeira']) && $_GET['lixeira'] === '1';

if ($id === '') { arq_erro('Identificador inválido.', 400); }

$ato = arq_obter($id, $lixeira);
if (!$ato) { arq_erro('Arquivamento não encontrado.', 404); }

// Anexos ganham URL de acesso autenticado — nunca o caminho físico.
foreach ($ato['anexos'] as $i => $a) {
    $ato['anexos'][$i]['url']          = 'arquivo.php?id=' . rawurlencode($id) . '&a=' . $i . ($lixeira ? '&lixeira=1' : '');
    $ato['anexos'][$i]['tamanho_legivel'] = arq_formatar_bytes($a['tamanho']);
    $ato['anexos'][$i]['indice']       = $i;
    unset($ato['anexos'][$i]['ref']); // o front não precisa do caminho interno
}

$ato['selos']     = arq_selos($id);
$ato['auditoria'] = arq_auditoria_recente($id, 25);

arq_auditar('ver', $id);

arq_ok(['ato' => $ato]);
