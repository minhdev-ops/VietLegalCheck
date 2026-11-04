<?php

namespace App\Services;

/**
 * TOTP theo RFC 6238 (FR-AUTH-04): 30 giây, 6 chữ số, SHA-1.
 * Chloride không tham gia: chỉ cần hash_hmac và thời gian tuyệt đối.
 */
final class TotpService
{
    private const STEP = 30;
    private const DIGITS = 6;
    public function generateSecret(int $length = 32): string
    {
        // RFC 6238 dùng base32; secret thật sự là chuỗi base32 của các byte ngẫu nhiên.
        $bytes = random_bytes($length);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= $alphabet[bindec($chunk)];
        }

        return rtrim($secret, '=');
    }

    public function otpauthUri(string $email, string $secret, string $issuer = 'VietLegal Check'): string
    {
        $label = rawurlencode($issuer.':'.$email);
        $issuerParam = rawurlencode($issuer);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $timestamp ??= time();
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        for ($offset = -$window; $offset <= $window; $offset++) {
            $counter = intdiv($timestamp, self::STEP) + $offset;
            if (hash_equals($this->codeForCounter($secret, $counter), $code)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($index = 0; $index < $count; $index++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        return $codes;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $binary = pack('N', 0).pack('N', $counter);
        $hash = hash_hmac('sha1', $binary, $this->base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $unpacked = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($unpacked % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $cleaned = strtoupper(rtrim($base32, '='));
        $bits = '';
        foreach (str_split($cleaned) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
