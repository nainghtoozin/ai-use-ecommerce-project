<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Crypt;

trait HasAuthSecurity
{
    public function hasGoogleLinked(): bool
    {
        return !empty($this->google_id);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret) && !empty($this->two_factor_confirmed_at);
    }

    public function requiresTwoFactor(): bool
    {
        if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
            return true;
        }

        if ($this->hasTwoFactorEnabled()) {
            return true;
        }

        return (bool) ($this->two_factor_required ?? false);
    }

    public function getTwoFactorSecretDecrypted(): ?string
    {
        if (empty($this->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setTwoFactorSecret(?string $secret): void
    {
        $this->two_factor_secret = $secret ? Crypt::encryptString($secret) : null;
    }

    public function getRecoveryCodes(): array
    {
        if (empty($this->two_factor_recovery_codes)) {
            return [];
        }

        try {
            $decoded = Crypt::decryptString($this->two_factor_recovery_codes);
            $codes = json_decode($decoded, true);
            return is_array($codes) ? $codes : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setRecoveryCodes(array $hashedCodes): void
    {
        $this->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($hashedCodes)));
    }

    public function consumeRecoveryCode(string $code): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($code) {
            $fresh = static::lockForUpdate()->find($this->id);
            if (!$fresh) {
                return false;
            }
            $codes = $fresh->getRecoveryCodes();
            foreach ($codes as $index => $hash) {
                if (password_verify(str_replace(['-', ' '], '', strtoupper(trim($code))), $hash)) {
                    unset($codes[$index]);
                    $fresh->setRecoveryCodes(array_values($codes));
                    $fresh->save();
                    // sync current instance
                    $this->two_factor_recovery_codes = $fresh->two_factor_recovery_codes;
                    return true;
                }
            }
            return false;
        });
    }

    public function remainingRecoveryCodes(): int
    {
        return count($this->getRecoveryCodes());
    }
}
