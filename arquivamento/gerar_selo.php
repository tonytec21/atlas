<?php
/**
 * OBSOLETO — mantido apenas para não quebrar includes antigos.
 *
 * Este arquivo trazia usuário e senha de homologação do Portal do Selo
 * escritos no código. As credenciais foram removidas: a emissão de selos
 * acontece em selos_arquivamentos.php, que lê a configuração da tabela
 * conexao_selador, e os valores de fallback ficam em config.local.php.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$seloHtml = '<div class="arq-nota arq-nota-alerta">'
          . 'Esta rotina foi substituída. Use a tela de selos do arquivamento.'
          . '</div>';
