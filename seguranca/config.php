<?php
/**
 * Proteção do Atlas — configuração padrão.
 * Para mudar só nesta máquina, use a página do suporte (seguranca/suporte.php) ou crie
 * seguranca/config.local.php devolvendo só as chaves que quer trocar. Não edite este
 * arquivo nas máquinas: ele é atualizado pelo git.
 */
return [
    // bloquear  = recusa pedidos de fora do sistema (recomendado)
    // observar  = só registra o que seria bloqueado, sem recusar (use para conferir antes)
    // desligado = sem proteção
    'modo' => 'bloquear',

    // true: até consultas (GET) feitas por ferramentas sem navegador precisam do token.
    'estrito' => true,

    // Abre as telas dentro de uma moldura: a barra de endereço mostra só o endereço do sistema.
    'moldura' => true,

    // Esconde o endereço que aparece no canto da tela ao passar o mouse nos links.
    'ocultar_links' => true,

    // Rotas extras sem verificação nenhuma (têm credencial própria). Ex.: 'modulo/api/*'
    'livres' => [],

    // Rotas extras que dispensam login (continuam exigindo que o pedido venha do sistema).
    'entrada' => [],

    // Rotas extras que nunca abrem na moldura.
    'sem_moldura' => [],
];
