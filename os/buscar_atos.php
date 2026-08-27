<?php

declare(strict_types=1);

/**
 * buscar_atos.php — Sugestão de atos para o filtro da tela de pesquisa.
 *
 * Busca pelo código ou pela descrição, porque quem opera raramente sabe o
 * código de cabeça: procura por "procuração" e escolhe na lista.
 *
 * Devolve também quantas O.S. usam cada ato, o que ajuda a escolher entre
 * dois códigos parecidos — o que tem movimento é quase sempre o certo.
 */

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json; charset=utf-8');

$termo = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($termo) < 2) {
    echo json_encode(['atos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDatabaseConnection();

    /* Códigos começando com o termo vêm primeiro: quem digita "1.2" quer
       1.2.x antes de um ato cuja descrição por acaso contenha "1.2". */
    $stmt = $conn->prepare(
        "SELECT t.ATO AS ato, t.DESCRICAO AS descricao,
                (SELECT COUNT(DISTINCT i.ordem_servico_id)
                   FROM ordens_de_servico_itens i
                  WHERE i.ato = t.ATO) AS usos
           FROM tabela_emolumentos t
          WHERE t.ATO LIKE :ini
             OR t.ATO LIKE :meio
             OR t.DESCRICAO LIKE :desc
       ORDER BY (t.ATO LIKE :ini2) DESC, t.ATO
          LIMIT 30"
    );

    $stmt->execute([
        ':ini'  => $termo . '%',
        ':ini2' => $termo . '%',
        ':meio' => '%' . $termo . '%',
        ':desc' => '%' . $termo . '%',
    ]);

    echo json_encode(['atos' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('buscar_atos: ' . $e->getMessage());
    echo json_encode(['atos' => [], 'erro' => 'Falha ao consultar a tabela de emolumentos.'], JSON_UNESCAPED_UNICODE);
}
