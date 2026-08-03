<?php
/**
 * Atlas - Correcao dos oficios gravados com escapes indevidos
 * ---------------------------------------------------------------------------
 * Corrige registros cujo campo "corpo" foi gravado com barras invertidas
 * (src=\"imagens/...\"), efeito do duplo escape no cadastro de oficios.
 *
 * Uso pelo navegador:
 *   http://localhost/atlas/oficios/corrigir_escapes_corpo.php            (simulacao)
 *   http://localhost/atlas/oficios/corrigir_escapes_corpo.php?aplicar=1  (aplica)
 *
 * Uso pelo terminal:
 *   php corrigir_escapes_corpo.php
 *   php corrigir_escapes_corpo.php aplicar
 *
 * Antes de aplicar, o script cria uma copia de seguranca da tabela:
 *   oficios_backup_AAAAMMDD_HHMMSS
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/corpo_helper.php';

$cli = (php_sapi_name() === 'cli');
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$aplicar = $cli
    ? (isset($argv[1]) && $argv[1] === 'aplicar')
    : (isset($_GET['aplicar']) && $_GET['aplicar'] == '1');

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexao: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');

echo "=============================================\n";
echo " Atlas - Correcao de escapes no corpo\n";
echo " Modo: " . ($aplicar ? "APLICAR ALTERACOES" : "SIMULACAO (nada sera gravado)") . "\n";
echo "=============================================\n\n";

// ---- Levantar registros afetados ----
$res = $conn->query("SELECT id, numero, corpo FROM oficios ORDER BY id");
if (!$res) {
    die("Erro ao consultar a tabela oficios: " . $conn->error . "\n");
}

$afetados = array();
while ($row = $res->fetch_assoc()) {
    $limpo = atlasCorpoLimpo($row['corpo']);
    if ($limpo !== $row['corpo']) {
        $imagens = preg_match_all('/<img\s/i', $limpo);
        $afetados[] = array(
            'id'      => $row['id'],
            'numero'  => $row['numero'],
            'corpo'   => $limpo,
            'imagens' => $imagens
        );
    }
}
$res->free();

$total = count($afetados);
echo "Oficios com escapes indevidos: {$total}\n\n";

if ($total === 0) {
    echo "Nada a corrigir.\n";
    $conn->close();
    exit;
}

foreach ($afetados as $a) {
    echo "  #{$a['id']}  Oficio {$a['numero']}  (imagens no corpo: {$a['imagens']})\n";
}
echo "\n";

if (!$aplicar) {
    echo "Simulacao concluida. Para aplicar de fato:\n";
    echo $cli
        ? "  php corrigir_escapes_corpo.php aplicar\n"
        : "  adicione ?aplicar=1 na URL\n";
    $conn->close();
    exit;
}

// ---- Copia de seguranca ----
$backup = 'oficios_backup_' . date('Ymd_His');
if (!$conn->query("CREATE TABLE `{$backup}` AS SELECT * FROM oficios")) {
    die("Erro ao criar a copia de seguranca: " . $conn->error . "\n");
}
echo "Copia de seguranca criada: {$backup}\n\n";

// ---- Aplicar correcao ----
$stmt = $conn->prepare("UPDATE oficios SET corpo = ? WHERE id = ?");
$ok = 0;
$erros = 0;

foreach ($afetados as $a) {
    $stmt->bind_param("si", $a['corpo'], $a['id']);
    if ($stmt->execute()) {
        $ok++;
        echo "  corrigido: oficio {$a['numero']}\n";
    } else {
        $erros++;
        echo "  ERRO no oficio {$a['numero']}: " . $stmt->error . "\n";
    }
}
$stmt->close();

echo "\n---------------------------------------------\n";
echo " Corrigidos: {$ok}   Erros: {$erros}\n";
echo " Restauracao (se necessario):\n";
echo "   UPDATE oficios o JOIN `{$backup}` b ON b.id = o.id SET o.corpo = b.corpo;\n";
echo "---------------------------------------------\n";

$conn->close();
