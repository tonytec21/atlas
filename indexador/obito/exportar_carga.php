<?php
/* atlas-guarda */ if (is_file(__DIR__ . '/../../seguranca/guarda.php')) { include_once __DIR__ . '/../../seguranca/guarda.php'; }
// Tela antiga substituída pela exportação unificada.
header('Location: ../carga_crc/index.php?tipo=obito');
exit;
