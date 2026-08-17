<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/base_calculo_lib.php';
require_once __DIR__ . '/documento_validacao.php';
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_raw   = $_POST['cliente'] ?? '';
    $cliente_sanit = preg_replace('/["\'“”‘’]/u', '', $cliente_raw);
    $cliente       = mb_strtoupper(trim($cliente_sanit), 'UTF-8');
    /* A tela de criação já valida no navegador, mas isso não protege o
       banco: a validação de front-end depende do navegador do usuário. */
    $docRes = doc_validar_apresentante($_POST['cpf_cliente'] ?? '');
    if (!$docRes['ok']) {
        echo json_encode(['error' => $docRes['erro']]);
        exit;
    }
    $cpf_cliente = $docRes['valor'];
    $total_os = str_replace(',', '.', $_POST['total_os']);
    $base_calculo = isset($_POST['base_calculo']) && $_POST['base_calculo'] !== '' ? str_replace(',', '.', $_POST['base_calculo']) : 0;
    $itens = $_POST['itens'];
    $descricao_os = $_POST['descricao_os'];
    $observacoes  = $_POST['observacoes'];
    $criado_por   = $_SESSION['username'];

    if ($cliente === '') {
        echo json_encode(['error' => 'O campo "Apresentante" é obrigatório.']);
        exit;
    }


    try {
        $conn = getDatabaseConnection();

        // ===================== AUTO UPDATE - Adiciona coluna FERRFIS se não existir =====================
        $checkColumn = $conn->query("SHOW COLUMNS FROM ordens_de_servico_itens LIKE 'ferrfis'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE ordens_de_servico_itens ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
        }

        // Inicia a transação
        $conn->beginTransaction();

        // Insere a OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("INSERT INTO ordens_de_servico (cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por, base_de_calculo) VALUES (:cliente, :cpf_cliente, :total_os, :descricao_os, :observacoes, :criado_por, :base_calculo)");
        $stmt->bindParam(':cliente', $cliente);
        $stmt->bindParam(':cpf_cliente', $cpf_cliente);
        $stmt->bindParam(':total_os', $total_os);
        $stmt->bindParam(':descricao_os', $descricao_os);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':criado_por', $criado_por);
        $stmt->bindParam(':base_calculo', $base_calculo);
        $stmt->execute();

        // Obtém o ID da OS inserida
        $os_id = $conn->lastInsertId();

        // Coluna da base por ato (idempotente).
        bc_migrar($conn);

        $stmt = $conn->prepare("INSERT INTO ordens_de_servico_itens 
            (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total, ordem_exibicao, base_de_calculo) 
            VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :ferrfis, :total, :ordem_exibicao, :base_de_calculo)");
        
        foreach ($itens as $item) {
            $ordem_servico_id = $os_id;
            $ato = $item['ato'];
            $quantidade = $item['quantidade'];
            $desconto_legal = $item['desconto_legal'];
            $descricao = $item['descricao'];
            $emolumentos = str_replace(',', '.', $item['emolumentos']);
            $ferc = str_replace(',', '.', $item['ferc']);
            $fadep = str_replace(',', '.', $item['fadep']);
            $femp = str_replace(',', '.', $item['femp']);
            $ferrfis = str_replace(',', '.', $item['ferrfis'] ?? '0');
            $total = str_replace(',', '.', $item['total']);
            $ordem_exibicao = $item['ordem_exibicao']; 

            // ---------- BASE DE CÁLCULO DO ATO ----------
            // A tela já valida, mas a checagem que vale é esta: o navegador
            // pode ser contornado, e um ato de faixa gravado sem base
            // trava a selagem depois, longe de quem lançou.
            $baseBruta = $item['base_de_calculo'] ?? '';
            $base_de_calculo = ($baseBruta === '' || $baseBruta === null)
                ? null : bc_valor($baseBruta);

            $faixaAto = bc_extrair_faixa($descricao);
            $ehIsento = stripos((string) $ato, '(isento)') !== false;

            if ($faixaAto && !$ehIsento) {
                $vb = bc_validar((float) $base_de_calculo, $faixaAto);
                if (!$vb['ok']) {
                    $conn->rollBack();
                    echo json_encode(['error' => 'Ato ' . $ato . ': ' . $vb['mensagem']]);
                    exit;
                }
            }

            if ($base_de_calculo !== null && $base_de_calculo <= 0) {
                $base_de_calculo = null;
            }
        
            $stmt->bindParam(':ordem_servico_id', $ordem_servico_id);
            $stmt->bindParam(':ato', $ato);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':desconto_legal', $desconto_legal);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':emolumentos', $emolumentos);
            $stmt->bindParam(':ferc', $ferc);
            $stmt->bindParam(':fadep', $fadep);
            $stmt->bindParam(':femp', $femp);
            $stmt->bindParam(':ferrfis', $ferrfis);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':ordem_exibicao', $ordem_exibicao); 
            $stmt->bindParam(':base_de_calculo', $base_de_calculo); 
            $stmt->execute();
        }
        // Confirma a transação
        $conn->commit();

        // ===== Rastreio: cria o pedido vinculado à O.S. e envia à API (best-effort) =====
        try {
            require_once(__DIR__ . '/../pedidos_certidao/os_rastreio_lib.php');
            os_rastreio_criar_para_os($conn, $os_id, [
                'usuario' => isset($_SESSION['username']) ? $_SESSION['username'] : null
            ]);
        } catch (Throwable $eRastreio) {
            error_log('[salvar_os][rastreio] ' . $eRastreio->getMessage());
        }

        // Modificação para retornar o ID da OS criada
        echo json_encode(['success' => true, 'id' => $os_id]);
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao salvar a Ordem de Serviço: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>