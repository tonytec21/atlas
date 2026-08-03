<?php
/**
 * =====================================================================
 * atualizar_base_item.php — Grava a base de cálculo editada na tabela
 * ---------------------------------------------------------------------
 * ATLAS-OS-BASECALC-BUILD: 2026-08-03-inline
 *
 * Usado pela edição in-line da coluna "Base de Cálculo" no editar_os.php,
 * do mesmo jeito que `atualizar_quantidade_item.php` atende a coluna de
 * quantidade.
 *
 * A validação contra a faixa é refeita aqui: a tela avisa na hora, mas
 * quem decide é o servidor. Um ato de faixa gravado sem base trava a
 * selagem depois, longe de quem lançou.
 *
 * ATO JÁ LIQUIDADO
 * ----------------
 * Se o ato já foi liquidado, a base não pode mudar — ela sustenta o selo
 * emitido. A alteração é recusada; o caminho é liberar o ato primeiro.
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/base_calculo_lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido.']);
    exit;
}

$item_id = (int) ($_POST['item_id'] ?? 0);
$bruto   = trim((string) ($_POST['base_de_calculo'] ?? ''));

if ($item_id <= 0) {
    echo json_encode(['error' => 'Item não informado.']);
    exit;
}

try {
    $conn = getDatabaseConnection();
    bc_migrar($conn);

    $stmt = $conn->prepare(
        "SELECT id, ato, descricao, quantidade_liquidada, base_de_calculo
           FROM ordens_de_servico_itens WHERE id = :id"
    );
    $stmt->bindParam(':id', $item_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['error' => 'Item não encontrado.']);
        exit;
    }

    /* Ato liquidado: a base já sustentou um selo. */
    if ((int) $item['quantidade_liquidada'] > 0) {
        echo json_encode([
            'error' => 'Este ato já foi liquidado (' . (int) $item['quantidade_liquidada']
                     . ' un.) e a base de cálculo não pode mais ser alterada — ela sustenta o selo '
                     . 'emitido. Para corrigir, libere o ato primeiro.'
        ]);
        exit;
    }

    $faixa = bc_extrair_faixa($item['descricao']);

    /* Ato sem faixa não guarda base. */
    if (!$faixa) {
        $nulo = null;
        $up = $conn->prepare("UPDATE ordens_de_servico_itens SET base_de_calculo = :b WHERE id = :id");
        $up->bindParam(':b', $nulo);
        $up->bindParam(':id', $item_id);
        $up->execute();

        echo json_encode(['success' => true, 'base_de_calculo' => null, 'exige_base' => false]);
        exit;
    }

    $base = ($bruto === '') ? 0.0 : bc_valor($bruto);
    $v = bc_validar($base, $faixa);

    if (!$v['ok']) {
        echo json_encode([
            'error'  => 'Ato ' . $item['ato'] . ': ' . $v['mensagem'],
            'codigo' => $v['codigo'],
            'faixa'  => $faixa['rotulo'],
        ]);
        exit;
    }

    $valor = round($base, 2);
    $up = $conn->prepare("UPDATE ordens_de_servico_itens SET base_de_calculo = :b WHERE id = :id");
    $up->bindParam(':b', $valor);
    $up->bindParam(':id', $item_id);
    $up->execute();

    echo json_encode([
        'success'         => true,
        'base_de_calculo' => $valor,
        'exige_base'      => true,
        'faixa'           => $faixa['rotulo'],
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao atualizar a base de cálculo: ' . $e->getMessage()]);
}
