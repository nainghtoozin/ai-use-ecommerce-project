<?php

namespace App\Services\Backup;

class RestoreTargetGuard
{
    private const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';

    private const RESERVED_DATABASES = [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys',
    ];

    public function assertEnvironment(string $environment): void
    {
        if (strtolower($environment) === 'production') {
            throw new BackupOperationException(
                'System backup restore is refused in the production environment. Restore into an isolated environment instead.',
                'restore_production_refused'
            );
        }
    }

    public function assertDatabaseName(string $name, array $allowedPrefixes): string
    {
        $name = trim($name);

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new BackupOperationException(
                'The restore target database must match ^[a-z0-9_]{1,64}$.',
                'restore_target_invalid'
            );
        }

        $prefixes = array_values(array_filter(array_map(
            static fn ($prefix): string => strtolower(trim((string) $prefix)),
            $allowedPrefixes
        )));

        $allowed = false;

        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new BackupOperationException(
                'The restore target database must start with one of the approved prefixes: ' . implode(', ', $prefixes) . '.',
                'restore_target_not_allowed'
            );
        }

        if (in_array($name, self::RESERVED_DATABASES, true)) {
            throw new BackupOperationException('The restore target database is a reserved system schema.', 'restore_target_not_allowed');
        }

        return $name;
    }

    public function assertConfirmation(string $name, string $confirmation): void
    {
        if (trim($confirmation) !== $name) {
            throw new BackupOperationException(
                'The database confirmation does not match the restore target database.',
                'restore_confirmation_mismatch'
            );
        }
    }

    public function assertNotApplicationDatabase(string $name): void
    {
        $name = strtolower($name);
        $blocked = [];

        foreach ((array) config('database.connections', []) as $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $database = strtolower(trim((string) ($connection['database'] ?? '')));

            if ($database !== '' && $database !== ':memory:') {
                $blocked[] = $database;
            }
        }

        if (in_array($name, $blocked, true)) {
            throw new BackupOperationException(
                'The restore target database is one of the databases configured for this application.',
                'restore_target_configured_database'
            );
        }
    }

    public function assertMediaTarget(?string $path, ?string $confirmation): ?string
    {
        $path = $path === null ? '' : trim($path);

        if ($path === '') {
            return null;
        }

        if ($this->isAbsolute($path) === false) {
            throw new BackupOperationException(
                'The restore media target must be an absolute path that does not exist yet.',
                'restore_media_target_invalid'
            );
        }

        if (file_exists($path)) {
            throw new BackupOperationException(
                'The restore media target already exists; refusing to overwrite or merge media.',
                'restore_media_target_exists'
            );
        }

        $parent = dirname($path);

        if (!is_dir($parent)) {
            throw new BackupOperationException(
                'The parent directory of the restore media target must already exist.',
                'restore_media_target_invalid'
            );
        }

        $realParent = realpath($parent);

        if ($realParent === false) {
            throw new BackupOperationException(
                'The restore media target cannot be resolved.',
                'restore_media_target_invalid'
            );
        }

        $absolute = rtrim($realParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);

        foreach ($this->protectedRoots() as $root) {
            if ($this->isInside($root, $absolute)) {
                throw new BackupOperationException(
                    'The restore media target must not be inside the application tree, a storage disk root, or the backup directory.',
                    'restore_media_target_invalid'
                );
            }
        }

        if (trim((string) $confirmation) !== $path) {
            throw new BackupOperationException(
                'The media target confirmation does not match the restore media target.',
                'restore_confirmation_mismatch'
            );
        }

        return $absolute;
    }

    /**
     * @return array<int, string>
     */
    private function protectedRoots(): array
    {
        $roots = [base_path(), public_path()];

        foreach ((array) config('filesystems.disks', []) as $disk) {
            if (!is_array($disk) || ($disk['driver'] ?? '') !== 'local') {
                continue;
            }

            $root = trim((string) ($disk['root'] ?? ''));

            if ($root !== '') {
                $roots[] = $root;
            }
        }

        try {
            $roots[] = \Illuminate\Support\Facades\Storage::disk((string) config('backups.disk', 'local'))
                ->path(trim((string) config('backups.path', 'backups'), '/'));
        } catch (\Throwable) {
            // An unusable backup disk is reported elsewhere; the remaining roots still apply.
        }

        return array_values(array_unique(array_filter($roots, static fn (string $root): bool => $root !== '')));
    }

    private function isInside(string $root, string $path): bool
    {
        $realRoot = realpath($root);

        if ($realRoot === false) {
            $realRoot = $root;
        }

        $realRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realRoot), DIRECTORY_SEPARATOR);
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (DIRECTORY_SEPARATOR === '\\') {
            $realRoot = strtolower($realRoot);
            $candidate = strtolower($candidate);
        }

        return $candidate === $realRoot || str_starts_with($candidate, $realRoot . DIRECTORY_SEPARATOR);
    }

    private function isAbsolute(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\\\');
    }
}
