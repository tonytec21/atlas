<?php
/**
 * Atlas · Tarefas — compatibilidade: nível de acesso do usuário logado.
 *
 * A versão anterior interpolava o nome de usuário direto na SQL. Agora a
 * consulta é preparada, dentro de usuario_atual(). O contrato de saída é o
 * mesmo: 'administrador' para quem enxerga tudo, 'usuario' para os demais.
 */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$u = usuario_atual();
responder_json(array('nivel_de_acesso' => usuario_ve_tudo() ? 'administrador' : 'usuario'));
