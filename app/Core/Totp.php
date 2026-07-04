<?php

declare(strict_types=1);

namespace App\Core;

/**
 * TOTP (RFC 6238) em PHP puro — compatível com Google Authenticator,
 * Microsoft Authenticator, Authy, etc. Sem dependências externas.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32 (RFC 4648)
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** Gera um segredo base32 aleatório (16 caracteres = 80 bits). */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[ord($bytes[$i]) & 31];
        }

        return $secret;
    }

    /**
     * Verifica um código de 6 dígitos, tolerando 1 janela (±30s) para
     * compensar pequenas diferenças de relógio.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $timeSlice = (int) floor(time() / self::PERIOD);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeForSlice($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** URI otpauth:// para o QR code / entrada manual na app. */
    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&period=%d&digits=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::PERIOD,
            self::DIGITS
        );
    }

    private static function codeForSlice(string $secret, int $timeSlice): string
    {
        $key = self::base32Decode($secret);
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice); // contador de 64 bits big-endian
        $hash = hash_hmac('sha1', $binaryTime, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::ALPHABET, $b32[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
