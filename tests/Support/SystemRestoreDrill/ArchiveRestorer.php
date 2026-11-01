<?php

namespace Tests\Support\SystemRestoreDrill;

use App\Services\Backup\SystemBackupValidator;
use PDO;
use RuntimeException;
use ZipArchive;

class ArchiveRestorer
{
    public function __construct(private readonly SystemBackupValidator $validator) {}

    /**
     * @param array<string, mixed> $connection
     * @param array<string, mixed> $validation
     */
    public function restoreDatabase(string $archivePath, array $connection, array $validation): void
    {
        $entry = (string) ($validation['database_entry'] ?? '');

        if (!str_starts_with($entry, 'database/')) {
            throw new RuntimeException('The archive has no validated database entry.');
        }

        $sql = $this->readEntry($archivePath, $entry);

        if (trim($sql) === '') {
            throw new RuntimeException('The database entry is empty.');
        }

        $pdo = $this->connect($connection);

        if ($this->tableCount($pdo) !== 0) {
            throw new RuntimeException('Refusing to restore into a database that already contains tables.');
        }

        $pdo->exec($sql);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $validation
     * @return array<string, array{path: string, bytes: int, sha256: string}>
     */
    public function restoreMedia(string $archivePath, string $targetDirectory, array $manifest, array $validation): array
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open the archive for media restoration.');
        }

        $restored = [];

        try {
            foreach ((array) ($validation['media_entries'] ?? []) as $entry) {
                $entry = (string) $entry;

                if (!str_starts_with($entry, 'media/')) {
                    throw new RuntimeException('The archive lists an unsupported media entry: ' . $entry);
                }

                $path = $this->validator->mediaTargetPath($entry, $targetDirectory);
                $directory = dirname($path);

                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Unable to create the media restore directory.');
                }

                $stream = $zip->getStream($entry);

                if ($stream === false) {
                    throw new RuntimeException('Unable to read the media entry: ' . $entry);
                }

                $handle = fopen($path, 'wb');

                if ($handle === false) {
                    fclose($stream);
                    throw new RuntimeException('Unable to write the restored media file.');
                }

                stream_copy_to_stream($stream, $handle);
                fclose($stream);
                fclose($handle);

                $bytes = filesize($path);
                $sha256 = hash_file('sha256', $path);
                $meta = $manifest['files'][$entry] ?? null;

                if (!is_array($meta) || $bytes !== (int) ($meta['bytes'] ?? -1) || !is_string($sha256) || !hash_equals((string) ($meta['sha256'] ?? ''), $sha256)) {
                    @unlink($path);
                    throw new RuntimeException('The restored media file failed checksum verification: ' . $entry);
                }

                $restored[$entry] = ['path' => $path, 'bytes' => (int) $bytes, 'sha256' => $sha256];
            }
        } finally {
            $zip->close();
        }

        return $restored;
    }

    private function readEntry(string $archivePath, string $entry): string
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open the archive for database restoration.');
        }

        try {
            $contents = $zip->getFromName($entry);
        } finally {
            $zip->close();
        }

        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read the database entry: ' . $entry);
        }

        return $contents;
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function connect(array $connection): PDO
    {
        $socket = (string) ($connection['unix_socket'] ?? '');
        $charset = (string) ($connection['charset'] ?? 'utf8mb4');

        $dsn = $socket !== ''
            ? 'mysql:unix_socket=' . $socket . ';dbname=' . (string) ($connection['database'] ?? '') . ';charset=' . $charset
            : 'mysql:host=' . (string) ($connection['host'] ?? '127.0.0.1') . ';port=' . (string) ($connection['port'] ?? '3306') . ';dbname=' . (string) ($connection['database'] ?? '') . ';charset=' . $charset;

        return new PDO($dsn, (string) ($connection['username'] ?? ''), (string) ($connection['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }

    private function tableCount(PDO $pdo): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    }
}
