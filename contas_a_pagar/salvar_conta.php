<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada. Recarregue a página.');
    cap_ensure_schema();
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    if ($titulo === '') throw new RuntimeException('Informe o título da conta.');
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $fornecedor = trim((string)($_POST['fornecedor'] ?? ''));
    $nota_fiscal = trim((string)($_POST['nota_fiscal'] ?? ''));
    $valor = cap_parse_money($_POST['valor'] ?? '0');
    if ($valor < 0) throw new RuntimeException('Valor inválido.');
    $venc = trim((string)($_POST['data_vencimento'] ?? ''));
    if ($venc === '' || !strtotime($venc)) throw new RuntimeException('Informe uma data de vencimento válida.');
    $venc = date('Y-m-d', strtotime($venc));
    $recorrencia = in_array(($_POST['recorrencia'] ?? 'Nenhuma'), cap_recorrencias(), true) ? $_POST['recorrencia'] : 'Nenhuma';
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $parcelar = !empty($_POST['parcelar']);
    $parcelas = (int)($_POST['parcelas'] ?? 0);
    $parcTipo = ((($_POST['parcela_tipo'] ?? 'total')) === 'parcela') ? 'parcela' : 'total';
    $func = $_SESSION['username'] ?? null;
    $agora = date('Y-m-d H:i:s');

    $conn = cap_db();

    if ($parcelar && $parcelas >= 2) {
        if ($parcelas > 120) throw new RuntimeException('Número de parcelas inválido (2 a 120).');
        if ($parcTipo === 'parcela') {
            $vBase = round($valor, 2);                          // cada parcela vale o valor informado
        } else {
            $vBase = floor(($valor / $parcelas) * 100) / 100;   // total dividido entre as parcelas
        }
        if ($vBase <= 0) throw new RuntimeException('Valor da parcela inválido.');
        $grupo = uniqid('PARC-', true);
        $ins = $conn->prepare("INSERT INTO contas_a_pagar (titulo, categoria, fornecedor, nota_fiscal, valor, data_vencimento, descricao, recorrencia, funcionario, status, parcela_num, parcela_total, parcela_grupo, created_at) VALUES (?,?,?,?,?,?,?,'Nenhuma',?,'Pendente',?,?,?,?)");
        $conn->begin_transaction();
        try {
            $ids = []; $dataParc = $venc;
            for ($i = 1; $i <= $parcelas; $i++) {
                $vParc = $vBase;
                if ($parcTipo === 'total' && $i === $parcelas) {
                    $vParc = round($valor - $vBase * ($parcelas - 1), 2); // ajuste de centavos na última
                }
                $tit = $titulo . ' (' . $i . '/' . $parcelas . ')';
                $ins->bind_param('ssssdsssiiss', $tit, $categoria, $fornecedor, $nota_fiscal, $vParc, $dataParc, $descricao, $func, $i, $parcelas, $grupo, $agora);
                if (!$ins->execute()) throw new RuntimeException('Erro ao salvar a parcela ' . $i . ': ' . $ins->error);
                $ids[] = $ins->insert_id;
                $dataParc = cap_proximo_vencimento($dataParc, 'Mensal');
            }
            $ins->close();
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
        cap_log("Parcelamento criado ($grupo): $parcelas parcela(s) de \"$titulo\" por $func");
        echo json_encode(['success'=>true,'id'=>$ids[0],'parcelas'=>$parcelas,'message'=>"Conta parcelada em $parcelas vezes cadastrada com sucesso!"], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $conn->prepare("INSERT INTO contas_a_pagar (titulo, categoria, fornecedor, nota_fiscal, valor, data_vencimento, descricao, recorrencia, funcionario, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,'Pendente',?)");
        $stmt->bind_param('ssssdsssss', $titulo, $categoria, $fornecedor, $nota_fiscal, $valor, $venc, $descricao, $recorrencia, $func, $agora);
        if (!$stmt->execute()) throw new RuntimeException('Erro ao salvar: ' . $stmt->error);
        $id = $stmt->insert_id; $stmt->close();
        cap_log("Conta criada #$id ($titulo) por $func");
        echo json_encode(['success'=>true,'id'=>$id,'message'=>'Conta cadastrada com sucesso!'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
