<?php
/**
 * Atlas · Arquivamento Digital
 * Subclasse do TCPDF usada pela capa.
 *
 * Este arquivo só pode ser incluído DEPOIS que a TCPDF estiver carregada —
 * quem cuida disso é arq_carregar_tcpdf(), em Capa.php.
 */

class ArqCapaPDF extends TCPDF
{
    /** Quando verdadeiro, o timbrado é impresso ocupando a página inteira. */
    public $arqTimbrado = false;

    public function Header()
    {
        if (!$this->arqTimbrado) { return; }

        $img = dirname(__DIR__) . '/../style/img/timbrado.png';
        if (!is_file($img)) { return; }

        // Mesma sequência do capa_arquivamento.php: zera margens e quebra de
        // página só enquanto desenha o timbrado, e RESTAURA em seguida.
        // Sem a restauração o conteúdo sai colado na borda do papel.
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);

        $this->Image($img, 0, 0, 210, 297, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(25, 45, 25);
        $this->SetY(35);
    }

    public function Footer()
    {
        // A capa original não tem rodapé.
    }
}
