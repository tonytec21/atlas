<?php
/**
 * TOTP (RFC 6238) — autenticação em duas etapas compatível com
 * Google Authenticator e Microsoft Authenticator (SHA1, 6 dígitos, 30s).
 * Autocontido, sem dependências externas.
 */
class TOTP
{
    const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32 (RFC 4648)
    const PERIOD   = 30;
    const DIGITS   = 6;

    /** Gera um segredo base32 (padrão 16 caracteres = 80 bits). */
    public static function generateSecret($length = 16)
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    /** Decodifica base32 -> bytes binários. */
    public static function base32Decode($b32)
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') return '';
        $bits = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos(self::ALPHABET, $b32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        $blen = strlen($bits);
        for ($i = 0; $i + 8 <= $blen; $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }
        return $bytes;
    }

    /** Calcula o código para um determinado instante (default: agora). */
    public static function code($secret, $time = null)
    {
        if ($time === null) $time = time();
        $counter = (int) floor($time / self::PERIOD);
        $key = self::base32Decode($secret);
        // contador em 8 bytes big-endian (high 32 bits = 0)
        $bin = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $part = ((ord($hash[$offset]) & 0x7f) << 24)
              | ((ord($hash[$offset + 1]) & 0xff) << 16)
              | ((ord($hash[$offset + 2]) & 0xff) << 8)
              | (ord($hash[$offset + 3]) & 0xff);
        $otp = $part % pow(10, self::DIGITS);
        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica um código com janela de tolerância (em passos de 30s),
     * para compensar pequenas diferenças de relógio.
     */
    public static function verify($secret, $code, $window = 1)
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== self::DIGITS || $secret === '' || $secret === null) {
            return false;
        }
        $time = time();
        for ($i = -$window; $i <= $window; $i++) {
            $valid = self::code($secret, $time + ($i * self::PERIOD));
            if (hash_equals($valid, $code)) {
                return true;
            }
        }
        return false;
    }

    /** Monta a URI otpauth:// usada para gerar o QR Code. */
    public static function provisioningUri($secret, $label, $issuer = 'Atlas')
    {
        $path = rawurlencode($issuer) . ':' . rawurlencode($label);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return 'otpauth://totp/' . $path . '?' . $query;
    }
}
