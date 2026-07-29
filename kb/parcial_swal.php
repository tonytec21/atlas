<?php
/**
 * atlas/kb/parcial_swal.php
 * Carrega o SweetAlert2 que acompanha o modulo (kb/vendor/).
 *
 * O caminho e resolvido a partir do DOCUMENT_ROOT, nao relativo a pagina:
 * este parcial e incluido tanto de kb/ quanto de provimentos/, e um "vendor/..."
 * relativo apontaria para a pasta errada em um dos dois.
 *
 * SweetAlert2 v11.26.25 -- MIT (vendor/LICENSE-sweetalert2.txt)
 * O .all.min.js ja traz o CSS embutido; nao precisa de <link>.
 */
$kbSwalSrc = 'vendor/sweetalert2.all.min.js';   // fallback relativo
$kbSwalAbs = __DIR__ . '/vendor/sweetalert2.all.min.js';

if (is_file($kbSwalAbs) && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $raiz = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $arq  = str_replace('\\', '/', realpath($kbSwalAbs));
    if ($raiz && $arq && strpos($arq, $raiz) === 0) {
        $kbSwalSrc = substr($arq, strlen($raiz));   // ex.: /atlas/kb/vendor/...
    }
}
?>
<script src="<?php echo htmlspecialchars($kbSwalSrc, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
// Rede de seguranca: se o arquivo nao carregar (permissao, antivirus, caminho),
// a tela continua utilizavel com dialogos nativos em vez de quebrar.
if (!window.Swal) {
    console.warn('[kb] SweetAlert2 nao carregou de "<?php echo addslashes($kbSwalSrc); ?>"; usando dialogos nativos.');
    window.Swal = {
        fire: function (a, b) {
            var o = (a && typeof a === 'object') ? a : { title: a, text: b };
            var corpo = o.text || (o.html ? String(o.html)
                .replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '') : '');
            var msg = (o.title || '') + (corpo ? '\n\n' + corpo : '');
            var confirmado = true;
            if (o.showCancelButton) { confirmado = window.confirm(msg); }
            else if (!o.timer) { window.alert(msg); }
            return { then: function (cb) {
                if (cb) { cb({ isConfirmed: confirmado, isDismissed: !confirmado }); }
                return this; } };
        }
    };
}
</script>
