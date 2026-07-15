<?php
/** Subclasse do FPDI para permitir aumentar o placeholder da assinatura. */
use setasign\Fpdi\Tcpdf\Fpdi;
if (!class_exists('AtlasFpdiSig')) {
    class AtlasFpdiSig extends Fpdi
    {
        public function setSigMaxLength($n) { $this->signature_max_length = (int)$n; }
    }
}
