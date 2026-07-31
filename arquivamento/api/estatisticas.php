<?php
/** API · Indicadores do acervo. */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();

arq_ok(['estatisticas' => arq_estatisticas()]);
