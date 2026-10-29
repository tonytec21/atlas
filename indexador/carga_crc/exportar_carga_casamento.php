<?php
/* atlas-guarda */ if (is_file(__DIR__ . '/../../seguranca/guarda.php')) { include_once __DIR__ . '/../../seguranca/guarda.php'; }
// Tela antiga substituída pela exportação unificada (carga_crc/index.php).
header('Location: index.php?tipo=casamento');
exit;
