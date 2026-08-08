<?php
/** Atlas · Tarefas — lista de categorias ativas (contrato original). */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();
while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode(listar_categorias(), JSON_UNESCAPED_UNICODE);
