<?php
/**
 * Atlas - Corpo Helper
 * ---------------------------------------------------------------------------
 * O cadastro de oficios aplicava real_escape_string() ANTES de usar o
 * prepared statement (bind_param). Como o prepared statement ja escapa os
 * valores, o HTML acabava gravado no banco com barras invertidas:
 *
 *   <img src=\"imagens/21_2026/foto.png\" style=\"width:80%\">
 *
 * O navegador le src como \"imagens/... (caminho invalido) e mostra a imagem
 * quebrada. Na edicao (save_oficio.php) isso nao acontece, porque la existe
 * apenas o bind_param - por isso a imagem funcionava ao editar.
 *
 * A causa foi corrigida em cadastrar-oficio.php. Esta funcao e a rede de
 * seguranca para os oficios ja gravados com o problema (leitura), e o script
 * corrigir_escapes_corpo.php corrige definitivamente os registros no banco.
 * ---------------------------------------------------------------------------
 */

if (!function_exists('atlasCorpoLimpo')) {
    /**
     * Remove escapes indevidos (\" \' \\) de conteudo HTML vindo do banco.
     * So atua quando identifica o padrao tipico de atributo escapado,
     * portanto e seguro para conteudos gravados corretamente.
     *
     * @param string|null $html
     * @return string
     */
    function atlasCorpoLimpo($html) {
        if ($html === null || $html === '') {
            return '';
        }

        // Padroes que so aparecem quando o HTML foi escapado indevidamente:
        //   src=\"...   style=\"...   <\/p>   alt=\'...
        $padrao = '/(=\s*\\\\["\']|<\\\\\/[a-z])/i';

        $voltas = 0;
        while ($voltas < 3 && preg_match($padrao, $html)) {
            $html = stripslashes($html);
            $voltas++;
        }

        return $html;
    }
}
