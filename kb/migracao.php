<?php
/**
 * atlas/kb/migracao.php
 * Migracao pela linha de comando. Opcional: a tela aria.php ja executa o
 * mesmo DDL automaticamente no primeiro acesso (bootstrap_kb.php).
 *
 * Uso: php migracao.php
 */

if (php_sapi_name() !== 'cli') {
    include(__DIR__ . '/../provimentos/session_check.php');
    checkSession();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once __DIR__ . '/schema_kb.php';
require_once __DIR__ . '/../provimentos/db_connection.php';

$conn = getDatabaseConnection();

echo "== Migracao da base de conhecimento ==\n\n";
foreach (kbGarantirSchema($conn) as $linha) {
    echo $linha . "\n";
}

echo "\n== Situacao ==\n";
$d = $conn->query("
    SELECT COUNT(*) docs,
           SUM(conteudo_anexo IS NOT NULL AND CHAR_LENGTH(conteudo_anexo) >= 500) com_texto
      FROM provimentos WHERE status = 'Ativo'")->fetch(PDO::FETCH_ASSOC);
printf("Documentos ativos ......: %d\n", $d['docs']);
printf("Com texto aproveitavel .: %d\n", $d['com_texto']);

$c = $conn->query("SELECT COUNT(*) total, SUM(embedding IS NOT NULL) com_vetor FROM kb_chunks")
          ->fetch(PDO::FETCH_ASSOC);
printf("Trechos ................: %d\n", $c['total']);
printf("Com embedding ..........: %d\n", $c['com_vetor']);

echo "\nIndexe pelo botao em aria.php ou rode: php ingerir.php --chunk --embed\n";
