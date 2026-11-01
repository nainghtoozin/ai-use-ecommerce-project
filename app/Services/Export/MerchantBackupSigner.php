<?php

namespace App\Services\Export;

use RuntimeException;

final class MerchantBackupSigner
{
    public const ALGORITHM = 'hmac-sha256';

    public const DEFAULT_MINIMUM_KEY_LENGTH = 32;

    public function __construct(
        private readonly ?string $key,
        private readonly string $keyId,
        private readonly int $minimumKeyLength = self::DEFAULT_MINIMUM_KEY_LENGTH,
    ) {}

    public static function fromConfig(): self
    {
        $key = config('exports.backup.signing_key');

        return new self(
            is_string($key) && $key !== '' ? $key : null,
            (string) config('exports.backup.key_id', 'merchant-backup-v1'),
            (int) config('exports.backup.minimum_key_length', self::DEFAULT_MINIMUM_KEY_LENGTH),
        );
    }

    public function isConfigured(): bool
    {
        return $this->key !== null && strlen($this->key) >= $this->minimumKeyLength;
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function sign(array $manifest): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Merchant backup signing key is not configured securely.');
        }

        return [
            'algorithm' => self::ALGORITHM,
            'key_id' => $this->keyId,
            'value' => hash_hmac('sha256', $this->canonicalize($manifest), $this->key),
            'signed_at' => now()->toIso8601String(),
        ];
    }

    public function verify(array $manifest): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $signature = $manifest['signature'] ?? null;

        if (! is_array($signature)) {
            return false;
        }

        if (($signature['algorithm'] ?? null) !== self::ALGORITHM) {
            return false;
        }

        $value = $signature['value'] ?? null;

        if (! is_string($value) || $value === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $this->canonicalize($manifest), $this->key), $value);
    }

    public function canonicalize(array $manifest): string
    {
        unset($manifest['signature']);

        $json = json_encode(
            $this->sortRecursive($manifest),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException('Unable to canonicalise the merchant backup manifest.');
        }

        return $json;
    }

    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
