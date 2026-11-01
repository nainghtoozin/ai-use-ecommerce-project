<?php

namespace App\Services\Backup\Encryption;

use App\Services\Backup\BackupOperationException;
use Illuminate\Support\Facades\Storage;

class BackupKeyRing
{
    private const MIN_KEY_ID = 1;

    private const MAX_KEY_ID = 65535;

    /** @var array<int, string> */
    private array $cache = [];

    public function activeKeyId(): int
    {
        return $this->normalizeId(config('backups.encryption.key_id'));
    }

    public function hasKey(int $id): bool
    {
        $path = $this->pathFor($id);

        if ($path === null) {
            return false;
        }

        $real = realpath($path);

        return $real !== false && is_file($real) && is_readable($real);
    }

    public function key(int $id): string
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $path = $this->pathFor($id);

        if ($path === null) {
            throw new BackupOperationException(
                'No encryption key is configured for the requested backup key id.',
                'encryption_key_unavailable'
            );
        }

        $real = realpath($path);

        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new BackupOperationException(
                'The configured encryption key file for this backup key id is missing or unreadable.',
                'encryption_key_unavailable'
            );
        }

        if ($this->isInsideBackupDirectory($real) || $this->isInsidePublicRoot($real)) {
            throw new BackupOperationException(
                'The backup encryption key file must not be stored inside the backup directory or the public web root.',
                'encryption_key_invalid'
            );
        }

        $contents = @file_get_contents($real);

        if (!is_string($contents) || $contents === '') {
            throw new BackupOperationException(
                'The configured encryption key file is empty or unreadable.',
                'encryption_key_invalid'
            );
        }

        $material = $this->decode($contents);

        if ($material === null) {
            throw new BackupOperationException(
                'The configured encryption key must be 32 raw bytes, 64 hexadecimal characters, or base64 for 32 bytes.',
                'encryption_key_invalid'
            );
        }

        return $this->cache[$id] = $material;
    }

    /**
     * @return array<int, string>
     */
    public function configuredPaths(): array
    {
        $paths = [];

        $primaryId = config('backups.encryption.key_id');
        $primaryFile = config('backups.encryption.key_file');

        if (is_int($primaryId) || (is_string($primaryId) && $primaryId !== '')) {
            $id = $this->tryNormalizeId($primaryId);

            if ($id !== null && is_string($primaryFile) && $primaryFile !== '') {
                $paths[$id] = $primaryFile;
            }
        }

        $registry = config('backups.encryption.keys', []);

        if (is_array($registry)) {
            foreach ($registry as $id => $path) {
                $normalized = $this->tryNormalizeId($id);

                if ($normalized !== null && is_string($path) && $path !== '') {
                    $paths[$normalized] = $path;
                }
            }
        }

        ksort($paths);

        return $paths;
    }

    public function normalizeId(mixed $value): int
    {
        $id = $this->tryNormalizeId($value);

        if ($id === null) {
            throw new BackupOperationException(
                'Backup encryption requires a numeric key id between 1 and 65535.',
                'encryption_not_configured'
            );
        }

        return $id;
    }

    public function tryNormalizeId(mixed $value): ?int
    {
        if (is_int($value)) {
            $id = $value;
        } elseif (is_string($value) && preg_match('/^\d{1,5}$/', trim($value)) === 1) {
            $id = (int) trim($value);
        } else {
            return null;
        }

        return $id >= self::MIN_KEY_ID && $id <= self::MAX_KEY_ID ? $id : null;
    }

    private function pathFor(int $id): ?string
    {
        $paths = $this->configuredPaths();

        return $paths[$id] ?? null;
    }

    private function decode(string $contents): ?string
    {
        if (strlen($contents) === 32) {
            return $contents;
        }

        $trimmed = trim($contents);

        if (strlen($trimmed) === 32) {
            return $trimmed;
        }

        if (preg_match('/^[0-9a-fA-F]{64}$/', $trimmed) === 1) {
            $binary = hex2bin($trimmed);

            return is_string($binary) && strlen($binary) === 32 ? $binary : null;
        }

        $decoded = base64_decode($trimmed, true);

        return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
    }

    private function isInsideBackupDirectory(string $path): bool
    {
        try {
            $disk = (string) config('backups.disk', 'local');
            $directory = trim((string) config('backups.path', 'backups'), '/');

            if ($directory === '') {
                return false;
            }

            return $this->isInside(Storage::disk($disk)->path($directory), $path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isInsidePublicRoot(string $path): bool
    {
        return $this->isInside(public_path(), $path);
    }

    private function isInside(string $directory, string $path): bool
    {
        $realDirectory = realpath($directory);

        if ($realDirectory === false) {
            return false;
        }

        $realDirectory = rtrim($realDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (DIRECTORY_SEPARATOR === '\\') {
            $realDirectory = strtolower($realDirectory);
            $path = strtolower($path);
        }

        return str_starts_with($path, $realDirectory);
    }
}
