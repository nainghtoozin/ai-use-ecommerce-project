<?php

namespace App\Services\Backup;

use ZipArchive;

class SystemBackupValidator
{
    private const MANIFEST_ENTRY = 'manifest.json';

    /**
     * @return array{manifest: array<string, mixed>, files: array<string, array{bytes: int, sha256: string}>, media_entries: array<int, string>, database_entry: string, archive_bytes: int}
     */
    public function validate(string $archivePath, ?string $targetDirectory = null): array
    {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new BackupValidationException('The system backup archive is missing or unreadable.', 'unreadable_archive');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new BackupValidationException('Unable to open the system backup archive.', 'unreadable_archive');
        }

        try {
            $entries = $this->entries($zip);
            $manifest = $this->manifest($zip, $entries);

            $this->assertSupportedVersions($manifest);
            $this->assertEntrySet($entries, $manifest);
            $this->assertDatabaseEntry($manifest);
            $this->verifyFiles($zip, $manifest);

            $mediaEntries = array_values(array_filter(
                array_keys($manifest['files']),
                static fn (string $entry) => str_starts_with($entry, 'media/')
            ));

            if ($targetDirectory !== null) {
                foreach (array_keys($manifest['files']) as $entry) {
                    $this->mediaTargetPath($entry, $targetDirectory);
                }
            }

            return [
                'manifest' => $manifest,
                'files' => $manifest['files'],
                'media_entries' => $mediaEntries,
                'database_entry' => (string) $manifest['database']['entry'],
                'archive_bytes' => (int) (filesize($archivePath) ?: 0),
            ];
        } finally {
            $zip->close();
        }
    }

    public function assertSafeEntryName(string $entry): string
    {
        if ($entry === '' || str_contains($entry, "\0") || str_contains($entry, '\\') || str_starts_with($entry, '/')) {
            throw new BackupValidationException('The archive contains an unsafe entry name.', 'invalid_entry');
        }

        if (preg_match('/^[A-Za-z]:/', $entry) === 1 || preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:\/\//', $entry) === 1) {
            throw new BackupValidationException('The archive contains an absolute or remote entry name.', 'invalid_entry');
        }

        if (str_ends_with($entry, '/')) {
            throw new BackupValidationException('The archive contains a directory entry.', 'invalid_entry');
        }

        foreach (explode('/', $entry) as $segment) {
            if ($segment === '..') {
                throw new BackupValidationException('The archive contains a path traversal entry.', 'path_traversal');
            }

            if ($segment === '.' || $segment === '') {
                throw new BackupValidationException('The archive contains a malformed entry name.', 'invalid_entry');
            }
        }

        return $entry;
    }

    public function mediaTargetPath(string $entry, string $targetDirectory): string
    {
        $entry = $this->assertSafeEntryName($entry);
        $target = $this->normalizePath($targetDirectory);

        if ($target === '') {
            throw new BackupValidationException('The restore target directory is not configured.', 'invalid_target_directory');
        }

        $candidate = $this->normalizePath($target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry));
        $comparedTarget = $this->comparisonPath($target);
        $comparedCandidate = $this->comparisonPath($candidate);

        if ($comparedCandidate !== $comparedTarget && !str_starts_with($comparedCandidate, $comparedTarget . DIRECTORY_SEPARATOR)) {
            throw new BackupValidationException('The archive entry escapes the restore directory: ' . $entry, 'path_traversal');
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    private function entries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (!is_string($name)) {
                throw new BackupValidationException('The archive contains an unreadable entry.', 'unreadable_archive');
            }

            $entries[] = $this->assertSafeEntryName($name);
        }

        if ($entries === []) {
            throw new BackupValidationException('The archive is empty.', 'malformed_manifest');
        }

        return $entries;
    }

    /**
     * @param array<int, string> $entries
     * @return array<string, mixed>
     */
    private function manifest(ZipArchive $zip, array $entries): array
    {
        if (!in_array(self::MANIFEST_ENTRY, $entries, true)) {
            throw new BackupValidationException('The archive has no manifest.', 'missing_manifest');
        }

        $raw = $zip->getFromName(self::MANIFEST_ENTRY);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($manifest)) {
            throw new BackupValidationException('The archive manifest is not valid JSON.', 'malformed_manifest');
        }

        foreach (['type', 'schema_version', 'archive_version', 'generated_at', 'app', 'database', 'media', 'files'] as $key) {
            if (!array_key_exists($key, $manifest)) {
                throw new BackupValidationException('The archive manifest is missing the "' . $key . '" field.', 'malformed_manifest');
            }
        }

        if ($manifest['type'] !== 'system_backup') {
            throw new BackupValidationException('The archive is not a system backup artifact.', 'unsupported_archive_type');
        }

        if (!is_array($manifest['database']) || !isset($manifest['database']['entry'], $manifest['database']['driver'], $manifest['database']['method'])) {
            throw new BackupValidationException('The archive manifest is missing database metadata.', 'malformed_manifest');
        }

        if (!is_int($manifest['schema_version']) || !is_int($manifest['archive_version'])) {
            throw new BackupValidationException('The archive manifest has non-numeric version fields.', 'malformed_manifest');
        }

        if (!is_array($manifest['files']) || $manifest['files'] === []) {
            throw new BackupValidationException('The archive manifest lists no files.', 'malformed_manifest');
        }

        foreach ($manifest['files'] as $entry => $meta) {
            if (!is_string($entry)) {
                throw new BackupValidationException('The archive manifest lists an invalid file name.', 'malformed_manifest');
            }

            $this->assertSafeEntryName($entry);

            if ($entry === self::MANIFEST_ENTRY) {
                throw new BackupValidationException('The archive manifest must not hash itself.', 'malformed_manifest');
            }

            if (!str_starts_with($entry, 'database/') && !str_starts_with($entry, 'media/')) {
                throw new BackupValidationException('The archive manifest lists an unsupported entry: ' . $entry, 'unexpected_entry');
            }

            if (!is_array($meta) || !isset($meta['bytes'], $meta['sha256']) || !is_int($meta['bytes']) || $meta['bytes'] < 0 || !is_string($meta['sha256']) || strlen($meta['sha256']) !== 64) {
                throw new BackupValidationException('The archive manifest has invalid checksum metadata for ' . $entry, 'malformed_manifest');
            }
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assertSupportedVersions(array $manifest): void
    {
        $supportedSchemas = array_map('intval', (array) config('backups.supported_schema_versions', [1]));
        $supportedArchives = array_map('intval', (array) config('backups.supported_archive_versions', [1]));

        if (!in_array((int) $manifest['schema_version'], $supportedSchemas, true)) {
            throw new BackupValidationException('Unsupported system backup schema version: ' . $manifest['schema_version'], 'unsupported_schema_version');
        }

        if (!in_array((int) $manifest['archive_version'], $supportedArchives, true)) {
            throw new BackupValidationException('Unsupported system backup archive version: ' . $manifest['archive_version'], 'unsupported_archive_version');
        }
    }

    /**
     * @param array<int, string> $entries
     * @param array<string, mixed> $manifest
     */
    private function assertEntrySet(array $entries, array $manifest): void
    {
        $expected = array_keys($manifest['files']);
        $expected[] = self::MANIFEST_ENTRY;

        sort($expected);
        $actual = $entries;
        sort($actual);

        if ($actual !== $expected) {
            $missing = array_values(array_diff($expected, $actual));
            $unexpected = array_values(array_diff($actual, $expected));

            if ($missing !== []) {
                throw new BackupValidationException('The archive is missing listed entries: ' . implode(', ', $missing), 'missing_entry');
            }

            throw new BackupValidationException('The archive contains unexpected entries: ' . implode(', ', $unexpected), 'unexpected_entry');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function assertDatabaseEntry(array $manifest): void
    {
        $entry = (string) $manifest['database']['entry'];

        if (!str_starts_with($entry, 'database/') || !array_key_exists($entry, $manifest['files'])) {
            throw new BackupValidationException('The archive manifest has no usable database entry.', 'malformed_manifest');
        }

        $databaseEntries = array_filter(
            array_keys($manifest['files']),
            static fn (string $name) => str_starts_with($name, 'database/')
        );

        if (count($databaseEntries) !== 1) {
            throw new BackupValidationException('The archive must contain exactly one database snapshot.', 'malformed_manifest');
        }

        if ((string) $manifest['database']['driver'] === '' || (string) $manifest['database']['method'] === '') {
            throw new BackupValidationException('The archive manifest has incomplete database compatibility metadata.', 'malformed_manifest');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function verifyFiles(ZipArchive $zip, array $manifest): void
    {
        foreach ($manifest['files'] as $entry => $meta) {
            $stream = $zip->getStream($entry);

            if ($stream === false) {
                throw new BackupValidationException('Unable to read the archive entry: ' . $entry, 'missing_entry');
            }

            $context = hash_init('sha256');
            $bytes = hash_update_stream($context, $stream);
            fclose($stream);

            if ($bytes !== (int) $meta['bytes']) {
                throw new BackupValidationException('The archive entry size does not match the manifest: ' . $entry, 'checksum_mismatch');
            }

            if (!hash_equals((string) $meta['sha256'], hash_final($context))) {
                throw new BackupValidationException('The archive entry checksum does not match the manifest: ' . $entry, 'checksum_mismatch');
            }
        }
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
        return DIRECTORY_SEPARATOR === '\\' ? strtolower(rtrim($path, DIRECTORY_SEPARATOR)) : rtrim($path, DIRECTORY_SEPARATOR);
    }
}
