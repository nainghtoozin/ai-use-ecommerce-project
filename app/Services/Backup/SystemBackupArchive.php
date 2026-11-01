<?php

namespace App\Services\Backup;

use App\Services\Backup\Encryption\EncryptedContainer;

class SystemBackupArchive
{
    public const FORMAT_PLAIN = 'zip';

    public const FORMAT_ENCRYPTED = 'sbx';

    private bool $closed = false;

    /**
     * @param array<string, mixed> $container
     */
    private function __construct(
        private readonly string $path,
        private readonly string $format,
        private readonly string $plaintextPath,
        private readonly ?string $temporaryPath,
        private readonly array $container,
    ) {}

    public static function detectFormat(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new BackupOperationException('The system backup archive is missing or unreadable.', 'container_unreadable');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new BackupOperationException('The system backup archive is missing or unreadable.', 'container_unreadable');
        }

        try {
            $magic = fread($handle, 4);
        } finally {
            fclose($handle);
        }

        if ($magic === EncryptedContainer::MAGIC) {
            return self::FORMAT_ENCRYPTED;
        }

        if (is_string($magic) && str_starts_with($magic, 'PK')) {
            return self::FORMAT_PLAIN;
        }

        throw new BackupOperationException('The file is not a supported system backup archive.', 'container_unreadable');
    }

    public static function open(string $path, EncryptedContainer $container, ?string $workDirectory = null): self
    {
        $format = self::detectFormat($path);

        if ($format === self::FORMAT_PLAIN) {
            return new self($path, $format, $path, null, []);
        }

        $directory = $workDirectory ?? storage_path('app/private/backups/.tmp');

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new BackupOperationException('The temporary restore directory cannot be created.', 'container_unreadable');
        }

        @chmod($directory, 0700);

        $temporary = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '.restore-' . bin2hex(random_bytes(8)) . '.zip';

        $metadata = $container->decryptToFile($path, $temporary);
        @chmod($temporary, 0600);

        return new self($path, $format, $temporary, $temporary, $metadata);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function isEncrypted(): bool
    {
        return $this->format === self::FORMAT_ENCRYPTED;
    }

    public function plaintextPath(): string
    {
        return $this->plaintextPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function container(): array
    {
        return $this->container;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        if ($this->temporaryPath !== null && is_file($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
