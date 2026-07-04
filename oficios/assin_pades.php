<?php
/**
 * oficios/assin_pades.php
 * ---------------------------------------------------------------------------
 * Assinatura PAdES com assinatura EXTERNA (token A3 via Assinador SERPRO em
 * modo hash). Sem dependências além de FPDI/TCPDF já presentes no projeto.
 *
 * Estratégia:
 *  - O TCPDF cria toda a estrutura de assinatura (AcroForm, /ByteRange e o
 *    placeholder /Contents) usando um certificado "dummy".
 *  - Trocamos o /SubFilter para ETSI.CAdES.detached (mesmo tamanho de string,
 *    não desloca o /ByteRange).
 *  - Calculamos o digest do /ByteRange, montamos os signedAttributes (CAdES-BES
 *    com signingCertificateV2), o TOKEN assina esse hash, montamos o CMS e
 *    injetamos no lugar do placeholder.
 *
 * Dois modos:
 *  - Modo A (recomendado): certificado conhecido ANTES de assinar → PAdES-BES
 *    completo (signingCertificateV2 + SubFilter ETSI.CAdES.detached).
 *  - Modo B (fallback): certificado só chega junto com a assinatura →
 *    PKCS#7 (adbe.pkcs7.detached), sem signingCertificateV2. Ainda é válido.
 * ---------------------------------------------------------------------------
 */

/**
 * Localiza (ou cria) um openssl.cnf utilizável. Necessário no XAMPP/Windows,
 * onde openssl_pkey_new()/openssl_csr_*() falham sem o config.
 * Use assim: openssl_pkey_new(['config'=>atlas_openssl_conf(), ...]).
 */
if (!function_exists('atlas_openssl_conf')) {
    function atlas_openssl_conf()
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $env = getenv('OPENSSL_CONF');
        if ($env && is_file($env)) return $cached = $env;

        $cands = [
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/bin/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ];
        foreach ($cands as $c) if (@is_file($c)) return $cached = $c;

        // Gera um mínimo em temp
        $tmp = rtrim(sys_get_temp_dir(), '/\\') . '/atlas_openssl.cnf';
        if (!is_file($tmp)) {
            @file_put_contents($tmp,
                "[ req ]\n" .
                "default_bits = 2048\n" .
                "default_md = sha256\n" .
                "distinguished_name = req_dn\n" .
                "[ req_dn ]\n"
            );
        }
        return $cached = $tmp;
    }
}

/* ============================ ASN.1 / DER ============================ */
final class AtlasDer
{
    public static function len($n)
    {
        if ($n < 0x80) return chr($n);
        $b = '';
        while ($n > 0) { $b = chr($n & 0xFF) . $b; $n >>= 8; }
        return chr(0x80 | strlen($b)) . $b;
    }
    public static function tlv($tag, $c) { return chr($tag) . self::len(strlen($c)) . $c; }
    public static function seq($c)   { return self::tlv(0x30, $c); }
    public static function set($c)   { return self::tlv(0x31, $c); }
    public static function octet($c) { return self::tlv(0x04, $c); }
    public static function null_()   { return "\x05\x00"; }
    public static function ctx($num, $constructed, $c)
    {
        return self::tlv(0x80 | ($constructed ? 0x20 : 0) | ($num & 0x1F), $c);
    }
    public static function oid($dotted)
    {
        $p = array_map('intval', explode('.', $dotted));
        $b = chr(40 * $p[0] + $p[1]);
        for ($i = 2, $n = count($p); $i < $n; $i++) {
            $v = $p[$i]; $s = chr($v & 0x7F); $v >>= 7;
            while ($v > 0) { $s = chr(($v & 0x7F) | 0x80) . $s; $v >>= 7; }
            $b .= $s;
        }
        return self::tlv(0x06, $b);
    }
    public static function utcTime($ts) { return self::tlv(0x17, gmdate('ymdHis', $ts) . 'Z'); }
    public static function intVal($i)
    {
        if ($i === 0) return self::tlv(0x02, "\x00");
        $b = ''; $n = $i;
        while ($n > 0) { $b = chr($n & 0xFF) . $b; $n >>= 8; }
        if (ord($b[0]) & 0x80) $b = "\x00" . $b;
        return self::tlv(0x02, $b);
    }
}

final class AtlasDerReader
{
    public static function read($bin, $off)
    {
        $tag = ord($bin[$off]); $b = ord($bin[$off + 1]);
        if ($b < 0x80) { $len = $b; $hdr = 2; }
        else {
            $num = $b & 0x7F; $len = 0;
            for ($i = 0; $i < $num; $i++) $len = ($len << 8) | ord($bin[$off + 2 + $i]);
            $hdr = 2 + $num;
        }
        return ['tag' => $tag, 'hdr' => $hdr, 'len' => $len, 'coff' => $off + $hdr, 'total' => $hdr + $len];
    }
}

/* ============================ PadesSigner ============================ */
final class AtlasPadesSigner
{
    private $certDer = null;
    private $issuerTlv = null;
    private $serialTlv = null;

    const OID_SHA256          = '2.16.840.1.101.3.4.2.1';
    const OID_RSA_ENC         = '1.2.840.113549.1.1.1';
    const OID_SIGNED_DATA     = '1.2.840.113549.1.7.2';
    const OID_DATA            = '1.2.840.113549.1.7.1';
    const OID_CONTENT_TYPE    = '1.2.840.113549.1.9.3';
    const OID_MESSAGE_DIGEST  = '1.2.840.113549.1.9.4';
    const OID_SIGNING_TIME    = '1.2.840.113549.1.9.5';
    const OID_SIGNING_CERT_V2 = '1.2.840.113549.1.9.16.2.47';

    /** @param string|null $certPem  certificado do signatário (pode ser nulo no Modo B/prepare) */
    public function __construct($certPem = null)
    {
        if ($certPem !== null && $certPem !== '') {
            $der = self::pemToDer($certPem);
            if ($der === false) throw new RuntimeException('Certificado inválido.');
            $this->certDer = $der;
            $this->extractIssuerAndSerial($der);
        }
    }

    public static function pemToDer($pem)
    {
        if (strpos($pem, '-----BEGIN') === false) return $pem; // já DER
        if (!preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $pem, $m)) return false;
        return base64_decode(preg_replace('/\s+/', '', $m[1]));
    }

    private function extractIssuerAndSerial($der)
    {
        $cert = AtlasDerReader::read($der, 0);
        $tbs  = AtlasDerReader::read($der, $cert['coff']);
        $p = $tbs['coff'];
        $t = AtlasDerReader::read($der, $p);
        if ($t['tag'] === 0xA0) $p += $t['total'];        // [0] version
        $serial = AtlasDerReader::read($der, $p);
        $this->serialTlv = substr($der, $p, $serial['total']);
        $p += $serial['total'];
        $sig = AtlasDerReader::read($der, $p); $p += $sig['total']; // signature algid
        $issuer = AtlasDerReader::read($der, $p);
        $this->issuerTlv = substr($der, $p, $issuer['total']);
    }

    private function algSha256() { return AtlasDer::seq(AtlasDer::oid(self::OID_SHA256)); }

    private function signingCertV2()
    {
        $certHash = hash('sha256', $this->certDer, true);
        $essCertId = AtlasDer::seq(AtlasDer::octet($certHash));
        return AtlasDer::seq(AtlasDer::seq($essCertId));
    }

    private function attr($oid, $valueSet) { return AtlasDer::seq(AtlasDer::oid($oid) . AtlasDer::set($valueSet)); }

    private static function derSetSort(array $items)
    {
        usort($items, function ($a, $b) {
            $m = min(strlen($a), strlen($b));
            for ($i = 0; $i < $m; $i++) { $d = ord($a[$i]) - ord($b[$i]); if ($d) return $d; }
            return strlen($a) - strlen($b);
        });
        return $items;
    }

    /**
     * Monta os signedAttributes (SET, tag 0x31). Estes bytes são o que o token
     * deve assinar (RSASSA-PKCS1-v1_5 sobre SHA-256 deles).
     * @param bool $withSigningCertV2 inclui signingCertificateV2 (exige certificado) → CAdES-BES
     */
    public function buildSignedAttrs($byteRangeDigestRaw, $withSigningCertV2 = true, $signingTime = null)
    {
        $signingTime = $signingTime ?: time();
        $attrs = [];
        $attrs[] = $this->attr(self::OID_CONTENT_TYPE, AtlasDer::oid(self::OID_DATA));
        $attrs[] = $this->attr(self::OID_SIGNING_TIME, AtlasDer::utcTime($signingTime));
        $attrs[] = $this->attr(self::OID_MESSAGE_DIGEST, AtlasDer::octet($byteRangeDigestRaw));
        if ($withSigningCertV2) {
            if ($this->certDer === null) throw new RuntimeException('signingCertificateV2 exige o certificado.');
            $attrs[] = $this->attr(self::OID_SIGNING_CERT_V2, $this->signingCertV2());
        }
        $attrs = self::derSetSort($attrs);
        return AtlasDer::set(implode('', $attrs));
    }

    /** Hash (SHA-256) dos signedAttrs — é o que se envia ao token em modo hash. */
    public static function digestOfSignedAttrs($signedAttrsSet) { return hash('sha256', $signedAttrsSet, true); }

    /** Monta o ContentInfo(SignedData) final (DER). Requer certificado. */
    public function buildCms($signedAttrsSet, $signatureRaw)
    {
        if ($this->certDer === null) throw new RuntimeException('buildCms exige o certificado.');
        $signedAttrsImplicit = "\xA0" . substr($signedAttrsSet, 1); // [0] IMPLICIT no lugar do SET

        $issuerAndSerial = AtlasDer::seq($this->issuerTlv . $this->serialTlv);
        $sigAlgRsa = AtlasDer::seq(AtlasDer::oid(self::OID_RSA_ENC) . AtlasDer::null_());

        $signerInfo = AtlasDer::seq(
            AtlasDer::intVal(1) . $issuerAndSerial . $this->algSha256() .
            $signedAttrsImplicit . $sigAlgRsa . AtlasDer::octet($signatureRaw)
        );

        $signedData = AtlasDer::seq(
            AtlasDer::intVal(1) .
            AtlasDer::set($this->algSha256()) .
            AtlasDer::seq(AtlasDer::oid(self::OID_DATA)) .        // encapContentInfo detached
            AtlasDer::ctx(0, true, $this->certDer) .             // certificates [0] IMPLICIT
            AtlasDer::set($signerInfo)                           // signerInfos
        );

        return AtlasDer::seq(AtlasDer::oid(self::OID_SIGNED_DATA) . AtlasDer::ctx(0, true, $signedData));
    }
}

/* ==================== Injeção / ByteRange ==================== */
final class AtlasPadesInjector
{
    public static function readByteRange($pdf)
    {
        if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m)) {
            throw new RuntimeException('/ByteRange não encontrado.');
        }
        $a = (int)$m[1]; $len1 = (int)$m[2]; $b = (int)$m[3]; $len2 = (int)$m[4];
        $digest = hash('sha256', substr($pdf, $a, $len1) . substr($pdf, $b, $len2), true);
        return ['a' => $a, 'len1' => $len1, 'b' => $b, 'len2' => $len2,
                'digest' => $digest, 'holeStart' => $a + $len1, 'holeEnd' => $b];
    }

    public static function inject($pdf, $holeStart, $holeEnd, $cmsDer)
    {
        if ($pdf[$holeStart] !== '<' || $pdf[$holeEnd - 1] !== '>') {
            throw new RuntimeException('Delimitadores do /Contents não conferem.');
        }
        $hexLen = ($holeEnd - 1) - ($holeStart + 1);
        $hex = bin2hex($cmsDer);
        if (strlen($hex) > $hexLen) {
            throw new RuntimeException('CMS maior que o placeholder. Aumente o tamanho da assinatura.');
        }
        $hex = str_pad($hex, $hexLen, '0');
        return substr($pdf, 0, $holeStart + 1) . $hex . substr($pdf, $holeEnd - 1);
    }

    /** Troca o SubFilter para ETSI.CAdES.detached (mesmo tamanho → não desloca offsets). */
    public static function toEtsiCades($pdf)
    {
        return str_replace('/SubFilter /adbe.pkcs7.detached', '/SubFilter /ETSI.CAdES.detached', $pdf);
    }
}
