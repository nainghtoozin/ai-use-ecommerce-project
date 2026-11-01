<?php

namespace App\Services\Backup;

use App\Services\Backup\Encryption\EncryptedContainer;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class SystemRestoreService
{
    public function __construct(
        private readonly SystemBackupValidator $validator,
        private readonly EncryptedContainer $container,
        private readonly DatabaseRestorer $restorer,
        private readonly RestoreTargetGuard $guard,
        private readonly SystemBackupPreflight $preflight,
    ) {}

    /**
     * @param array{archive: string, target_database: string, confirm_database: string, media_target?: string|null, confirm_media_target?: string|null, dry_run?: bool} $options
     * @return array<string, mixed>
     */
    public function restore(array $options): array
    {
        $environment = app()->environment();
        $this->guard->assertEnvironment($environment);

        $prefixes = array_values((array) config('backups.restore.allowed_prefixes', ['restore_', 'drill_']));
        $target = $this->guard->assertDatabaseName((string) ($options['target_database'] ?? ''), $prefixes);
        $this->guard->assertConfirmation($target, (string) ($options['confirm_database'] ?? ''));
        $this->guard->assertNotApplicationDatabase($target);

        $mediaOption = isset($options['media_target']) && $options['media_target'] !== null
            ? (string) $options['media_target']
            : null;
        $mediaConfirmation = isset($options['confirm_media_target']) && $options['confirm_media_target'] !== null
            ? (string) $options['confirm_media_target']
            : null;
        $mediaTarget = $this->guard->assertMediaTarget($mediaOption, $mediaConfirmation);

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $archivePath = (string) ($options['archive'] ?? '');

        if ($archivePath === '' || !is_file($archivePath)) {
            throw new BackupOperationException('The system backup archive is missing or unreadable.', 'container_unreadable');
        }

        $diskName = (string) config('backups.disk', 'local');
        $directory = trim((string) config('backups.path', 'backups'), '/');
        $disk = Storage::disk($diskName);

        if ($directory === '') {
            throw new BackupOperationException('The system backup directory is not configured.', 'restore_failed');
        }

        if (!$disk->exists($directory) && !$disk->makeDirectory($directory, 0775, true)) {
            throw new BackupOperationException('Unable to create the system backup directory.', 'restore_failed');
        }

        $absoluteDirectory = $disk->path($directory);
        $workDirectory = $absoluteDirectory . DIRECTORY_SEPARATOR . '.tmp';

        $this->restorer->connectionConfig();
        $this->guard->assertDatabaseName($target, $prefixes);
        $this->restorer->assertAbsent($target);

        $lock = new SystemBackupLock('system-backup-restore.lock');
        $lock->acquire($absoluteDirectory);

        $archive = null;
        $phase = 'preflight';
        $createdDatabase = false;
        $staging = null;
        $moved = false;

        try {
            if (SystemBackupArchive::detectFormat($archivePath) === SystemBackupArchive::FORMAT_ENCRYPTED) {
                $inspection = $this->container->inspect($archivePath);
                $this->preflight->checkWriteSpace($workDirectory, (int) $inspection['payload_bytes']);
            }

            $archive = SystemBackupArchive::open($archivePath, $this->container, $workDirectory);
            $validation = $this->validator->validate($archive->plaintextPath());
            $manifest = $validation['manifest'];

            $this->assertCompatible($manifest, $archive->plaintextPath(), $validation);

            if ($mediaTarget !== null) {
                $this->preflight->checkWriteSpace(dirname($mediaTarget), (int) ($manifest['media']['bytes'] ?? 0));
            }

            if ($dryRun) {
                return [
                    'dry_run' => true,
                    'archive' => basename($archivePath),
                    'format' => $archive->format(),
                    'encrypted' => $archive->isEncrypted(),
                    'key_id' => $archive->container()['key_id'] ?? null,
                    'container_version' => $archive->container()['container_version'] ?? null,
                    'target_database' => $target,
                    'media_target' => $mediaTarget,
                    'database_tables' => (int) ($manifest['database']['tables'] ?? 0),
                    'media_files' => (int) ($manifest['media']['files'] ?? 0),
                    'media_bytes' => (int) ($manifest['media']['bytes'] ?? 0),
                    'generated_at' => $manifest['generated_at'] ?? null,
                    'environment' => $environment,
                    'phases' => ['preflight', 'validation', 'compatibility', 'target', 'space'],
                ];
            }

            $phase = 'database';
            $this->restorer->create($target);
            $createdDatabase = true;

            $phase = 'import';
            $imported = $this->withEntryStream(
                $archive->plaintextPath(),
                (string) $validation['database_entry'],
                fn ($stream): int => $this->restorer->import($target, $stream)
            );

            $phase = 'verify';
            $tables = $this->restorer->tableCount($target);
            $expectedTables = (int) ($manifest['database']['tables'] ?? -1);

            if ($expectedTables >= 0 && $tables !== $expectedTables) {
                throw new BackupOperationException(
                    'The restored database table count does not match the archive manifest.',
                    'restore_verification_failed'
                );
            }

            $migrations = $this->restorer->migrationCount($target);

            if ($migrations === null || $migrations === 0) {
                throw new BackupOperationException(
                    'The restored database has no migration records; refusing to report a successful restore.',
                    'restore_verification_failed'
                );
            }

            $media = null;

            if ($mediaTarget !== null) {
                $phase = 'media';
                $staging = $mediaTarget . '.staging-' . bin2hex(random_bytes(6));
                $media = $this->extractMedia($archive->plaintextPath(), $staging, $validation);

                $phase = 'commit';
                if (file_exists($mediaTarget)) {
                    throw new BackupOperationException(
                        'The restore media target appeared during the restore; refusing to overwrite it.',
                        'restore_media_target_exists'
                    );
                }

                if (!@rename($staging, $mediaTarget)) {
                    throw new BackupOperationException('The restored media could not be moved into its final target.', 'restore_media_failed');
                }

                $moved = true;
                $staging = null;
            }

            return [
                'dry_run' => false,
                'archive' => basename($archivePath),
                'format' => $archive->format(),
                'encrypted' => $archive->isEncrypted(),
                'key_id' => $archive->container()['key_id'] ?? null,
                'container_version' => $archive->container()['container_version'] ?? null,
                'target_database' => $target,
                'media_target' => $mediaTarget,
                'database_created' => true,
                'database_bytes' => $imported,
                'tables_restored' => $tables,
                'migrations' => $migrations,
                'media_files' => $media['files'] ?? 0,
                'media_bytes' => $media['bytes'] ?? 0,
                'generated_at' => $manifest['generated_at'] ?? null,
                'environment' => $environment,
                'phases' => ['preflight', 'validation', 'compatibility', 'target', 'space', 'database', 'import', 'verify', 'commit'],
            ];
        } catch (Throwable $exception) {
            $cleanup = [];

            if ($staging !== null) {
                $this->removeDirectory($staging);
                $cleanup[] = 'deleted_staging';
            }

            if ($moved && $mediaTarget !== null && is_dir($mediaTarget)) {
                $this->removeDirectory($mediaTarget);
                $cleanup[] = 'deleted_media_target';
            }

            if ($createdDatabase) {
                try {
                    $this->restorer->drop($target, $prefixes);
                    $cleanup[] = 'dropped_database';
                } catch (Throwable) {
                    $cleanup[] = 'database_drop_failed';
                }
            }

            throw $this->wrapFailure($exception, $phase, $cleanup);
        } finally {
            if ($archive !== null) {
                $archive->close();
            }

            $lock->release();
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $validation
     */
    private function assertCompatible(array $manifest, string $plaintextPath, array $validation): void
    {
        if (($manifest['database']['driver'] ?? '') !== 'mysql') {
            throw new BackupOperationException(
                'Only MySQL or MariaDB system backups can be restored by this command.',
                'restore_unsupported_archive'
            );
        }

        $entry = (string) ($validation['database_entry'] ?? '');

        if (!str_ends_with($entry, '.sql')) {
            throw new BackupOperationException(
                'The archive does not contain a SQL database snapshot.',
                'restore_unsupported_archive'
            );
        }

        $archiveMigrations = $this->migrationNames($plaintextPath, $entry);
        $diskMigrations = $this->diskMigrationNames();

        if ($archiveMigrations === []) {
            throw new BackupOperationException(
                'The archive contains no migration records, so its schema compatibility cannot be verified.',
                'restore_schema_unknown'
            );
        }

        $missingOnDisk = array_values(array_diff($archiveMigrations, $diskMigrations));

        if ($missingOnDisk !== []) {
            throw new BackupOperationException(
                'The archive was created by a newer schema than this codebase supports (' . count($missingOnDisk) . ' migration(s) missing on disk).',
                'restore_schema_newer'
            );
        }

        $missingInArchive = array_values(array_diff($diskMigrations, $archiveMigrations));

        if ($missingInArchive !== []) {
            throw new BackupOperationException(
                'The archive is older than this codebase (' . count($missingInArchive) . ' migration(s) missing in the archive); apply migrations after restore.',
                'restore_schema_older'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function migrationNames(string $archivePath, string $entry): array
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('The system backup archive is unreadable.', 'container_unreadable');
        }

        try {
            $stream = $zip->getStream($entry);

            if ($stream === false) {
                throw new BackupOperationException('The database snapshot entry is unreadable.', 'container_unreadable');
            }

            $names = [];
            $buffer = '';

            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1048576);

                    if ($chunk === false) {
                        break;
                    }

                    $buffer .= $chunk;

                    while (strlen($buffer) > 512) {
                        $safe = strlen($buffer) - 512;
                        $length = strlen($buffer);

                        while ($safe < $length && $this->isMigrationNameCharacter($buffer[$safe])) {
                            $safe++;
                        }

                        if ($safe >= $length) {
                            break;
                        }

                        $this->collectMigrationNames(substr($buffer, 0, $safe), $names);
                        $buffer = substr($buffer, $safe);
                    }
                }

                $this->collectMigrationNames($buffer, $names);
            } finally {
                fclose($stream);
            }

            $names = array_keys($names);
            sort($names);

            return $names;
        } finally {
            $zip->close();
        }
    }

    /**
     * @param array<string, bool> $names
     */
    private function collectMigrationNames(string $text, array &$names): void
    {
        if (preg_match_all('/\b(\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+)/', $text, $matches) > 0) {
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }
    }

    private function isMigrationNameCharacter(string $character): bool
    {
        return preg_match('/[A-Za-z0-9_]/', $character) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function diskMigrationNames(): array
    {
        $names = [];

        foreach (glob(database_path('migrations') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $names[] = basename($file, '.php');
        }

        sort($names);

        return $names;
    }

    /**
     * @param array<string, mixed> $validation
     * @return array{files: int, bytes: int}
     */
    private function extractMedia(string $archivePath, string $staging, array $validation): array
    {
        if (file_exists($staging)) {
            throw new BackupOperationException('The media staging directory already exists.', 'restore_media_failed');
        }

        if (!@mkdir($staging, 0700, true) && !is_dir($staging)) {
            throw new BackupOperationException('The media staging directory cannot be created.', 'restore_media_failed');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            $this->removeDirectory($staging);

            throw new BackupOperationException('The system backup archive is unreadable.', 'container_unreadable');
        }

        $files = 0;
        $bytes = 0;

        try {
            foreach ((array) ($validation['media_entries'] ?? []) as $entry) {
                $entry = (string) $entry;
                $target = $this->validator->mediaTargetPath($entry, $staging);

                if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                    throw new BackupOperationException('A restored media directory cannot be created.', 'restore_media_failed');
                }

                $stream = $zip->getStream($entry);

                if ($stream === false) {
                    throw new BackupOperationException('A media entry is unreadable.', 'container_unreadable');
                }

                $handle = @fopen($target, 'xb');

                if ($handle === false) {
                    fclose($stream);

                    throw new BackupOperationException('A restored media file cannot be written.', 'restore_media_failed');
                }

                stream_copy_to_stream($stream, $handle);
                fclose($stream);
                fclose($handle);

                $meta = $validation['files'][$entry] ?? null;
                $size = filesize($target);
                $sha = hash_file('sha256', $target);

                if (
                    !is_array($meta)
                    || $size !== (int) ($meta['bytes'] ?? -1)
                    || !is_string($sha)
                    || !hash_equals((string) ($meta['sha256'] ?? ''), $sha)
                ) {
                    @unlink($target);

                    throw new BackupOperationException(
                        'A restored media file failed checksum verification.',
                        'restore_verification_failed'
                    );
                }

                $files++;
                $bytes += (int) $size;
            }
        } catch (Throwable $exception) {
            $zip->close();
            $this->removeDirectory($staging);

            throw $exception;
        }

        $zip->close();

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * @param callable(resource): mixed $callback
     * @return mixed
     */
    private function withEntryStream(string $archivePath, string $entry, callable $callback): mixed
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new BackupOperationException('The system backup archive is unreadable.', 'container_unreadable');
        }

        try {
            $stream = $zip->getStream($entry);

            if ($stream === false) {
                throw new BackupOperationException('The database snapshot entry is unreadable.', 'container_unreadable');
            }

            try {
                return $callback($stream);
            } finally {
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $itemPath = $item->getPathname();

            if ($item->isLink() || $item->isFile()) {
                @unlink($itemPath);
            } elseif ($item->isDir()) {
                @rmdir($itemPath);
            }
        }

        @rmdir($path);
    }

    /**
     * @param array<int, string> $cleanup
     */
    private function wrapFailure(Throwable $exception, string $phase, array $cleanup): BackupOperationException
    {
        if ($exception instanceof BackupOperationException) {
            return new BackupOperationException(
                $exception->getMessage(),
                $exception->reason,
                array_merge($exception->context, ['phase' => $phase, 'cleanup' => implode(',', $cleanup)]),
                $exception
            );
        }

        return new BackupOperationException(
            'The restore failed during the ' . $phase . ' phase.',
            'restore_failed',
            ['phase' => $phase, 'cleanup' => implode(',', $cleanup), 'error_class' => $exception::class],
            $exception
        );
    }
}
