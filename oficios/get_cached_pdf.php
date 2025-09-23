<?php
/* oficios/get_cached_pdf.php
 * Retorna JSON informando se há PDF em cache para o número informado.
 * Saída:
 *  { "exists": true, "url": "...", "filename": "...", "mtime": 1737575757 }  ou  { "exists": false }
 */

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['numero'])) {
        echo json_encode(['exists' => false, 'error' => 'Parâmetro numero é obrigatório.']);
        exit;
    }

    // Sanitização simples do número (permitir dígitos, letras, _ e -)
    $numero = preg_replace('~[^0-9A-Za-z_\-]~', '', (string)$_GET['numero']);
    if ($numero === '') {
        echo json_encode(['exists' => false, 'error' => 'Número inválido.']);
        exit;
    }

    // Diretório do cache
    $baseCacheDir = __DIR__ . '/cache/oficios';
    $dirNumero = $baseCacheDir . '/' . $numero;

    if (!is_dir($dirNumero)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    // Procura o mais recente .pdf no diretório
    $files = glob($dirNumero . '/*.pdf');
    if (!$files) {
        echo json_encode(['exists' => false]);
        exit;
    }

    usort($files, function($a, $b){ return filemtime($b) <=> filemtime($a); });
    $latest = $files[0];
    $filename = basename($latest);
    $mtime = @filemtime($latest) ?: time();

    // Monta URL pública para o arquivo
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Caminho web até /oficios (pasta deste arquivo)
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // .../oficios
    $publicUrl = $scheme . '://' . $host . $scriptDir . '/cache/oficios/' . rawurlencode($numero) . '/' . rawurlencode($filename);

    echo json_encode([
        'exists'   => true,
        'url'      => $publicUrl,
        'filename' => $filename,
        'mtime'    => $mtime
    ]);
} catch (Throwable $e) {
    echo json_encode(['exists' => false, 'error' => 'Exceção: ' . $e->getMessage()]);
}
