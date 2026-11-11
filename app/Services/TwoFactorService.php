<?php

namespace App\Services;

class TwoFactorService
{
    private string $issuer;

    public function __construct()
    {
        $this->issuer = config('app.name', 'Laravel');
    }

    public function generateSecret(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[ord($bytes[$i]) % 32];
        }
        return $secret;
    }

    public function getOtpAuthUri(string $secret, string $accountName): string
    {
        return 'otpauth://totp/' . rawurlencode($this->issuer . ':' . $accountName)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($this->issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        return $this->verifyAndGetStep($secret, $code, $window) !== null;
    }

    public function verifyAndGetStep(string $secret, string $code, int $window = 1): ?int
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $timeSlice + $i;
            if (hash_equals($this->totp($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public function currentStep(): int
    {
        return (int) floor(time() / 30);
    }

    private function totp(string $secret, int $timeSlice): string
    {
        $secret = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % 1000000;
        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($alphabet, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $result;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    public function hashRecoveryCode(string $code): string
    {
        return password_hash(str_replace(['-', ' '], '', strtoupper(trim($code))), PASSWORD_BCRYPT);
    }

    public function verifyRecoveryCode(string $hash, string $code): bool
    {
        return password_verify(str_replace(['-', ' '], '', strtoupper(trim($code))), $hash);
    }
}
