<?php

namespace App\Services\Backup;

use App\Services\Backup\Encryption\BackupKeyRing;
use App\Services\Backup\Encryption\EncryptedContainer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SystemBackupService
{
    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly SystemBackupValidator $validator,
        private readonly SystemBackupPreflight $preflight,
        private readonly EncryptedContainer $container,
        private readonly BackupKeyRing $keys,
    ) {}

    /**
     * @return array{disk: string, path: string, absolute: string, bytes: int, sha256: string|null, manifest: array<string, mixed>, encrypted: bool, container_version: int|null, key_id: int|null, payload_sha256: string|null, payload_bytes: int|null}
     */
    public function create(): array
    {
        $diskName = (string) config('backups.disk', 'local');
        $driver = (string) config('filesystems.disks.' . $diskName . '.driver', '');

        if ($driver !== 'local') {
            throw new RuntimeException('System backups require a private local storage disk.');
        }

        $directory = trim((string) config('backups.path', 'backups'), '/');
        if ($directory === '') {
            throw new RuntimeException('The system backup directory is not configured.');
        }

        $disk = Storage::disk($diskName);

        if (!$disk->exists($directory) && !$disk->makeDirectory($directory, 0775, true)) {
            throw new RuntimeException('Unable to create the system backup directory.');
        }

        $absoluteDirectory = $disk->path($directory);
        $encrypted = (bool) config('backups.encryption.enabled', false);
        $keyId = $encrypted ? $this->keys->activeKeyId() : null;

        $lock = new SystemBackupLock();
        $lock->acquire($absoluteDirectory);

        $archiveCreated = false;
        $plaintextCreated = false;
        $plaintextPath = null;
        $dumpPath = null;

        try {
            $extension = $encrypted ? SystemBackupArchive::FORMAT_ENCRYPTED : SystemBackupArchive::FORMAT_PLAIN;
            $filename = 'system-backup-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(8)) . '.' . $extension;
            $relativePath = $directory . '/' . $filename;
            $archivePath = $disk->path($relativePath);

            $media = $this->mediaFiles();
            $mediaBytes = (int) array_sum(array_column($media['files'], 'bytes'));

            $this->preflight->check($absoluteDirectory, $mediaBytes, $encrypted);
            $this->preflight->checkTemporarySpace();

            $zipTarget = $archivePath;

            if ($encrypted) {
                $workDirectory = $absoluteDirectory . DIRECTORY_SEPARATOR . '.work';

                if (!is_dir($workDirectory) && !@mkdir($workDirectory, 0700, true) && !is_dir($workDirectory)) {
                    throw new BackupOperationException('The system backup working directory cannot be created.', 'encryption_failed');
                }

                $plaintextPath = $workDirectory . DIRECTORY_SEPARATOR . 'system-backup-' . Str::lower(Str::random(16)) . '.zip';
                $zipTarget = $plaintextPath;
            }

            $dumpPath = tempnam(sys_get_temp_dir(), 'system-backup-db-');
            if ($dumpPath === false) {
                throw new RuntimeException('Unable to create the database snapshot file.');
            }

            $zip = new ZipArchive();
            $zipOpened = false;

            try {
                $database = $this->dumper->dump($dumpPath);

                if ($zip->open($zipTarget, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                    throw new RuntimeException('Unable to create the system backup archive.');
                }

                $zipOpened = true;

                if ($encrypted) {
                    $plaintextCreated = true;
                } else {
                    $archiveCreated = true;
                }

                $files = [];
                $files[$database['entry']] = $this->fileMeta($dumpPath);

                if (!$zip->addFile($dumpPath, $database['entry'])) {
                    throw new RuntimeException('Unable to add the database snapshot to the system backup archive.');
                }

                foreach ($media['files'] as $file) {
                    $entry = 'media/' . $file['disk'] . '/' . $file['path'];

                    if (!$zip->addFile($file['absolute'], $entry)) {
                        throw new RuntimeException('Unable to add a media file to the system backup archive: ' . $entry);
                    }

                    $files[$entry] = ['bytes' => $file['bytes'], 'sha256' => $file['sha256']];
                }

                $manifest = [
                    'type' => 'system_backup',
                    'schema_version' => (int) config('backups.schema_version', 1),
                    'archive_version' => (int) config('backups.archive_version', 1),
                    'generated_at' => now()->toIso8601String(),
                    'app' => [
                        'name' => config('app.name'),
                        'environment' => app()->environment(),
                        'laravel' => app()->version(),
                        'php' => PHP_VERSION,
                    ],
                    'database' => [
                        'driver' => $database['driver'],
                        'server_version' => $database['server_version'],
                        'method' => $database['method'],
                        'consistency' => $database['consistency'],
                        'tables' => $database['tables'],
                        'entry' => $database['entry'],
                    ],
                    'media' => [
                        'disks' => array_values(array_unique(array_column($media['files'], 'disk'))),
                        'files' => count($media['files']),
                        'bytes' => $mediaBytes,
                    ],
                    'files' => $files,
                    'excluded' => [
                        'paths' => array_values((array) config('backups.media.exclude', [])),
                        'skipped' => $media['skipped'],
                    ],
                    'notes' => [
                        'Operator system backup. Not a merchant business export and not a substitute for it.',
                        'No database credentials, host names or environment secrets are written to this archive.',
                        'The database snapshot is restored only into an isolated disposable database after preflight validation.',
                        ...$database['notes'],
                    ],
                ];

                $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded === false) {
                    throw new RuntimeException('Unable to encode the system backup manifest.');
                }

                $zip->addFromString('manifest.json', $encoded);

                if (!$zip->close()) {
                    throw new RuntimeException('Unable to finalize the system backup archive.');
                }

                $zipOpened = false;
            } catch (\Throwable $exception) {
                if ($zipOpened) {
                    @$zip->close();
                }

                throw $exception;
            } finally {
                if ($dumpPath !== null && is_file($dumpPath)) {
                    @unlink($dumpPath);
                }
            }

            $containerInfo = [];

            if ($encrypted) {
                $this->assertArchiveValid((string) $plaintextPath);

                $containerInfo = $this->container->encrypt((string) $plaintextPath, $archivePath, (int) $keyId);
                $archiveCreated = true;

                @unlink((string) $plaintextPath);
                $plaintextCreated = false;

                try {
                    $this->container->verify($archivePath);
                } catch (\Throwable $exception) {
                    throw new BackupOperationException(
                        'The new encrypted system backup archive failed post-creation verification: ' . $this->reasonOf($exception) . '.',
                        'encryption_verification_failed'
                    );
                }

                @chmod($archivePath, 0600);
            } else {
                $this->assertArchiveValid($archivePath);
            }

            $bytes = (int) (filesize($archivePath) ?: 0);
            $sha256 = hash_file('sha256', $archivePath) ?: null;

            return [
                'disk' => $diskName,
                'path' => $relativePath,
                'absolute' => $archivePath,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'manifest' => $manifest,
                'encrypted' => $encrypted,
                'container_version' => $containerInfo['container_version'] ?? null,
                'key_id' => $containerInfo['key_id'] ?? null,
                'payload_sha256' => $containerInfo['payload_sha256'] ?? null,
                'payload_bytes' => $containerInfo['payload_bytes'] ?? null,
            ];
        } catch (\Throwable $exception) {
            if ($plaintextCreated && $plaintextPath !== null && is_file($plaintextPath)) {
                @unlink($plaintextPath);
            }

            if ($archiveCreated && is_file($archivePath)) {
                @unlink($archivePath);
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function assertArchiveValid(string $archivePath): void
    {
        try {
            $this->validator->validate($archivePath);
        } catch (\Throwable $exception) {
            throw new BackupOperationException(
                'The new system backup archive failed post-creation verification: ' . $this->reasonOf($exception) . '.',
                'post_creation_verification_failed'
            );
        }
    }

    private function reasonOf(\Throwable $exception): string
    {
        return $exception instanceof BackupValidationException || $exception instanceof BackupOperationException
            ? $exception->reason
            : 'verification_error';
    }

    /**
     * @return array{files: array<int, array{disk: string, path: string, absolute: string, bytes: int, sha256: string}>, skipped: array<int, array{disk: string, path: string, reason: string}>}
     */
    private function mediaFiles(): array
    {
        $files = [];
        $skipped = [];

        foreach ((array) config('backups.media.disks', []) as $diskName) {
            $diskName = (string) $diskName;
            $driver = (string) config('filesystems.disks.' . $diskName . '.driver', '');

            if ($driver !== 'local') {
                $skipped[] = ['disk' => $diskName, 'path' => '*', 'reason' => 'remote_disk_not_bundled'];
                continue;
            }

            $disk = Storage::disk($diskName);
            $root = $this->normalizePath($disk->path(''));
            $rootReal = realpath($root);
            $base = $rootReal !== false ? $rootReal : $root;

            foreach ($disk->allFiles() as $path) {
                $path = str_replace('\\', '/', (string) $path);

                if ($path === '' || $this->isExcluded($path)) {
                    continue;
                }

                $real = realpath($disk->path($path));

                if ($real === false || !$this->isWithin($real, $base)) {
                    $skipped[] = ['disk' => $diskName, 'path' => $path, 'reason' => 'outside_disk_root'];
                    continue;
                }

                $bytes = filesize($real);
                $sha256 = hash_file('sha256', $real);

                if ($bytes === false || $sha256 === false) {
                    $skipped[] = ['disk' => $diskName, 'path' => $path, 'reason' => 'unreadable'];
                    continue;
                }

                $files[] = [
                    'disk' => $diskName,
                    'path' => $path,
                    'absolute' => $real,
                    'bytes' => (int) $bytes,
                    'sha256' => $sha256,
                ];
            }
        }

        return ['files' => $files, 'skipped' => $skipped];
    }

    private function isExcluded(string $path): bool
    {
        $segments = explode('/', $path);

        foreach ((array) config('backups.media.exclude', []) as $pattern) {
            $pattern = trim((string) $pattern, '/');

            if ($pattern === '') {
                continue;
            }

            if (Str::is($pattern, $path)) {
                return true;
            }

            foreach ($segments as $segment) {
                if (Str::is($pattern, $segment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{bytes: int, sha256: string}
     */
    private function fileMeta(string $path): array
    {
        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);

        if ($bytes === false || $sha256 === false) {
            throw new RuntimeException('Unable to read the database snapshot for checksumming.');
        }

        return ['bytes' => (int) $bytes, 'sha256' => $sha256];
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = $this->comparisonPath($this->normalizePath($path));
        $root = $this->comparisonPath($this->normalizePath($root));

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = substr($path, 0, 2) . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
        }

        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function comparisonPath(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }
}
