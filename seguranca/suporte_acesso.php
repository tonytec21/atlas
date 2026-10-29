<?php
/**
 * Acesso da equipe de suporte à página seguranca/suporte.php.
 * Guarda só o hash da senha (bcrypt). Para trocar a senha, gere um novo hash e substitua abaixo:
 *     php -r "echo password_hash('NOVA-SENHA', PASSWORD_BCRYPT, ['cost' => 12]);"
 * e publique a atualização. Vale para todas as serventias.
 */
return [
    'senha_hash' => '$2y$12$5RbCdJkgxq339qRXfkHm9OBwIqPvuD1spiEQFNOUW3xPUs3bndDxu',
];
