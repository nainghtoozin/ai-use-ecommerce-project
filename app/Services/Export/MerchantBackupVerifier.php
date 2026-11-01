<?php

namespace App\Services\Export;

use App\Models\MerchantRecoveryClaim;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use ZipArchive;

final class MerchantBackupVerifier
{
    private const REQUIRED_METADATA_ENTRIES = ['manifest.json', 'assets.json'];

    private const SUPPORTED_SCHEMA_VERSIONS = [2];

    public function __construct(
        private readonly MerchantDataExportService $exporter
    ) {}

    /**
     * Move an uploaded archive into the private upload area under a random
     * name with restrictive permissions, purging stale uploads first.
     *
     * @return array{path: string, name: string, bytes: int}
     */
    public function storeUpload(UploadedFile $file, int $tenantId): array
    {
        $this->cleanupUploads($tenantId);

        $dir = $this->uploadDir($tenantId);

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new MerchantBackupVerificationException('storage_unavailable', 'Unable to store the uploaded archive.');
        }

        @chmod($dir, 0700);

        $name = 'upload-' . Str::uuid() . '.zip';
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        $file->move($dir, $name);

        if (!is_file($path)) {
            throw new MerchantBackupVerificationException('storage_unavailable', 'The uploaded archive could not be stored.');
        }

        @chmod($path, 0600);

        return [
            'path' => $path,
            'name' => $name,
            'bytes' => filesize($path) ?: 0,
        ];
    }

    public function forgetUpload(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    public function cleanupUploads(int $tenantId): void
    {
        $dir = $this->uploadDir($tenantId);

        if (!is_dir($dir)) {
            return;
        }

        $ttl = (int) config('exports.backup.upload_ttl_hours', 1);
        $threshold = time() - ($ttl * 3600);
        $protected = $this->protectedUploadNames($tenantId);

        foreach (glob($dir . DIRECTORY_SEPARATOR . 'upload-*.zip') ?: [] as $file) {
            if (in_array(basename($file), $protected, true)) {
                continue;
            }

            if (is_file($file) && filemtime($file) !== false && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    /**
     * Upload files that an in-flight recovery is still reading. They are kept
     * even when older than the TTL so a slow recovery is never truncated.
     *
     * @return list<string>
     */
    private function protectedUploadNames(int $tenantId): array
    {
        try {
            return MerchantRecoveryClaim::activeUploadNames($tenantId);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Verify a merchant backup archive without writing to application tables.
     *
     * @return array{manifest: array<string, mixed>, assets: array<string, mixed>, asset_summary: array<string, mixed>, bytes: int, verified_at: string}
     */
    public function verify(string $path, Tenant $tenant, ?MerchantBackupSigner $signer = null): array
    {
        $signer ??= MerchantBackupSigner::fromConfig();

        if (! $signer->isConfigured()) {
            throw new MerchantBackupVerificationException(
                'signing_not_configured',
                'Backup verification is unavailable because archive signing is not configured.'
            );
        }

        if (!is_file($path)) {
            throw new MerchantBackupVerificationException('archive_missing', 'The uploaded archive could not be found.');
        }

        $bytes = filesize($path) ?: 0;
        $maxUploadBytes = $this->maxUploadKb() * 1024;

        if ($bytes <= 0 || $bytes > $maxUploadBytes) {
            throw new MerchantBackupVerificationException('invalid_size', 'The archive is empty or larger than the permitted upload limit.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CHECKCONS);

        if ($opened !== true) {
            throw new MerchantBackupVerificationException('corrupt_archive', 'The archive is corrupt or truncated and cannot be read.');
        }

        try {
            $entries = $this->inspectEntries($zip);
            $manifest = $this->readManifest($zip, $entries);

            $this->assertSupportedVersion($manifest);
            $this->assertSignature($manifest, $signer);
            $this->assertTenantBinding($manifest, $tenant);
            $this->assertStructure($manifest);
            $assets = $this->verifyAssetManifest($zip, $manifest);
            $this->verifyDataFiles($zip, $manifest);
        } finally {
            $zip->close();
        }

        return [
            'manifest' => $manifest,
            'assets' => $assets,
            'asset_summary' => is_array($assets['summary'] ?? null) ? $assets['summary'] : [],
            'bytes' => $bytes,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{size: int, comp_size: int, index: int}>
     */
    private function inspectEntries(ZipArchive $zip): array
    {
        $count = $zip->numFiles;

        if ($count <= 0) {
            throw new MerchantBackupVerificationException('empty_archive', 'The archive contains no entries.');
        }

        if ($count > (int) config('exports.backup.max_entries', 200)) {
            throw new MerchantBackupVerificationException('too_many_entries', 'The archive contains too many entries.');
        }

        $allowed = array_fill_keys($this->expectedEntries(), true);
        $entries = [];
        $totalBytes = 0;

        for ($index = 0; $index < $count; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                throw new MerchantBackupVerificationException('corrupt_archive', 'The archive entry list is corrupt.');
            }

            $name = (string) ($stat['name'] ?? '');

            $this->assertSafeEntryName($name);

            if (isset($entries[$name])) {
                throw new MerchantBackupVerificationException('duplicate_entry', 'The archive contains duplicate entries.');
            }

            if (!isset($allowed[$name])) {
                throw new MerchantBackupVerificationException('unexpected_entry', 'The archive contains an unexpected entry.');
            }

            $opsys = 0;
            $externalAttributes = 0;

            if ($zip->getExternalAttributesName($name, $opsys, $externalAttributes)
                && ($externalAttributes >> 16 & 0170000) === 0120000) {
                throw new MerchantBackupVerificationException('symlink_entry', 'The archive contains a symbolic link entry.');
            }

            $compression = (int) ($stat['comp_method'] ?? ZipArchive::CM_STORE);

            if (!in_array($compression, [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true)) {
                throw new MerchantBackupVerificationException('unsupported_compression', 'The archive uses an unsupported compression method.');
            }

            if ((int) ($stat['encryption_method'] ?? 0) !== 0) {
                throw new MerchantBackupVerificationException('encrypted_entry', 'Encrypted archive entries are not supported.');
            }

            $size = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);

            if ($size < 0 || $size > (int) config('exports.backup.max_entry_bytes', 67108864)) {
                throw new MerchantBackupVerificationException('oversized_entry', 'An archive entry exceeds the permitted size.');
            }

            if ($size > 10485760 && $compressed > 0 && ($size / max(1, $compressed)) > (int) config('exports.backup.max_compression_ratio', 200)) {
                throw new MerchantBackupVerificationException('suspicious_compression', 'An archive entry has an unsafe compression ratio.');
            }

            $totalBytes += $size;

            if ($totalBytes > (int) config('exports.backup.max_total_bytes', 268435456)) {
                throw new MerchantBackupVerificationException('oversized_archive', 'The uncompressed archive exceeds the permitted size.');
            }

            $entries[$name] = ['size' => $size, 'comp_size' => $compressed, 'index' => $index];
        }

        foreach ($this->expectedEntries() as $expected) {
            if (!isset($entries[$expected])) {
                throw new MerchantBackupVerificationException('missing_entry', 'The archive is missing an expected entry.');
            }
        }

        return $entries;
    }

    private function assertSafeEntryName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            throw new MerchantBackupVerificationException('invalid_entry', 'The archive contains an invalid entry name.');
        }

        if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1) {
            throw new MerchantBackupVerificationException('path_traversal', 'The archive contains an absolute path.');
        }

        if (preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
            throw new MerchantBackupVerificationException('path_traversal', 'The archive contains a path traversal entry.');
        }

        if (str_ends_with($name, '/')) {
            throw new MerchantBackupVerificationException('unexpected_entry', 'The archive contains a directory entry.');
        }
    }

    /**
     * @param array<string, array{size: int, comp_size: int, index: int}> $entries
     * @return array<string, mixed>
     */
    private function readManifest(ZipArchive $zip, array $entries): array
    {
        $maxManifestBytes = (int) config('exports.backup.max_manifest_bytes', 8388608);

        if ($entries['manifest.json']['size'] > $maxManifestBytes) {
            throw new MerchantBackupVerificationException('oversized_manifest', 'The archive manifest is too large.');
        }

        $json = $zip->getFromName('manifest.json');

        if ($json === false) {
            throw new MerchantBackupVerificationException('missing_manifest', 'The archive manifest could not be read.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive manifest is not valid JSON.');
        }

        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive manifest is malformed.');
        }

        return $manifest;
    }

    private function assertSupportedVersion(array $manifest): void
    {
        $schemaVersion = $manifest['schema_version'] ?? null;

        if (!is_int($schemaVersion) || !in_array($schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            throw new MerchantBackupVerificationException('unsupported_version', 'This backup version is not supported. Only signed version 2 merchant backups can be verified.');
        }

        if (($manifest['archive_version'] ?? null) !== 2) {
            throw new MerchantBackupVerificationException('unsupported_version', 'This archive format is not supported.');
        }

        if (($manifest['purpose'] ?? null) !== 'merchant-backup') {
            throw new MerchantBackupVerificationException('unsupported_version', 'This archive is not a merchant backup.');
        }

        if (!is_string($manifest['archive_id'] ?? null) || trim($manifest['archive_id']) === '') {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive is missing its archive identifier.');
        }
    }

    private function assertSignature(array $manifest, MerchantBackupSigner $signer): void
    {
        $signature = $manifest['signature'] ?? null;

        if (!is_array($signature)) {
            throw new MerchantBackupVerificationException('missing_signature', 'The archive is not signed. Only signed backups can be verified.');
        }

        if (($signature['algorithm'] ?? null) !== MerchantBackupSigner::ALGORITHM) {
            throw new MerchantBackupVerificationException('unsupported_signature', 'The archive uses an unsupported signature algorithm.');
        }

        if (($signature['key_id'] ?? null) !== $signer->keyId()) {
            throw new MerchantBackupVerificationException('unsupported_key_id', 'The archive was signed with an unsupported key identifier.');
        }

        if (!$signer->verify($manifest)) {
            throw new MerchantBackupVerificationException('invalid_signature', 'The archive signature is invalid. The archive was modified or is not authentic.');
        }
    }

    private function assertTenantBinding(array $manifest, Tenant $tenant): void
    {
        $tenantId = $manifest['tenant']['id'] ?? null;

        if ((!is_int($tenantId) && !(is_string($tenantId) && ctype_digit($tenantId))) || (int) $tenantId !== (int) $tenant->id) {
            throw new MerchantBackupVerificationException('tenant_mismatch', 'This backup belongs to a different store and cannot be verified here.');
        }

        if (!is_string($manifest['store']['slug'] ?? null) || !is_string($manifest['tenant']['slug'] ?? null)) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive is missing its store identity.');
        }
    }

    private function assertStructure(array $manifest): void
    {
        $categories = $manifest['categories'] ?? null;
        $recordCounts = $manifest['record_counts'] ?? null;
        $files = $manifest['files'] ?? null;

        if (!is_array($categories) || array_is_list($categories) || !is_array($recordCounts) || !is_array($files)) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive manifest is missing its dataset index.');
        }

        $expectedCategories = array_keys($this->exporter->categories());
        $actualCategories = array_keys($categories);
        sort($expectedCategories);
        sort($actualCategories);

        if ($expectedCategories !== $actualCategories) {
            throw new MerchantBackupVerificationException('category_mismatch', 'The archive dataset list does not match the supported schema.');
        }

        $total = 0;

        foreach ($categories as $count) {
            if (!is_int($count) || $count < 0) {
                throw new MerchantBackupVerificationException('invalid_manifest', 'The archive contains an invalid record count.');
            }

            $total += $count;
        }

        if ($recordCounts != $categories || (int) ($manifest['total_records'] ?? -1) !== $total) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive record counts are inconsistent.');
        }

        $expectedFiles = array_map(
            fn (string $category) => $category . '.csv',
            array_keys($this->exporter->categories())
        );
        $expectedFiles[] = 'assets.json';

        $actualFiles = array_keys($files);
        sort($expectedFiles);
        sort($actualFiles);

        if ($expectedFiles !== $actualFiles) {
            throw new MerchantBackupVerificationException('file_index_mismatch', 'The archive file index does not match the supported schema.');
        }

        foreach ($files as $meta) {
            if (!is_array($meta) || !is_int($meta['bytes'] ?? null) || !is_string($meta['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $meta['sha256']) !== 1) {
                throw new MerchantBackupVerificationException('invalid_manifest', 'The archive file index is malformed.');
            }
        }

        if (!is_array($manifest['excluded'] ?? null) || $manifest['excluded'] === []) {
            throw new MerchantBackupVerificationException('invalid_manifest', 'The archive must declare its excluded data.');
        }

        if (($manifest['media']['bundled'] ?? null) !== false || ($manifest['media']['recoverable'] ?? null) !== false) {
            throw new MerchantBackupVerificationException('unsupported_media', 'This archive claims bundled media, which is not supported.');
        }

        if (($manifest['encryption']['encrypted'] ?? null) !== false) {
            throw new MerchantBackupVerificationException('unsupported_encryption', 'Encrypted merchant backups are not supported.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyAssetManifest(ZipArchive $zip, array $manifest): array
    {
        $meta = $manifest['files']['assets.json'];
        $maxAssetsBytes = (int) config('exports.backup.max_assets_bytes', 4194304);
        $stat = $zip->statName('assets.json');
        $entryBytes = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

        if ($entryBytes <= 0 || $entryBytes > $maxAssetsBytes || (int) $meta['bytes'] > $maxAssetsBytes) {
            throw new MerchantBackupVerificationException('oversized_asset_manifest', 'The asset manifest is too large to verify.');
        }

        $json = $zip->getFromName('assets.json');

        if (!is_string($json) || $json === '') {
            throw new MerchantBackupVerificationException('invalid_asset_manifest', 'The asset manifest is missing.');
        }

        if (strlen($json) !== $meta['bytes'] || !hash_equals($meta['sha256'], hash('sha256', $json))) {
            throw new MerchantBackupVerificationException('integrity_failed', 'The asset manifest does not match its recorded checksum.');
        }

        try {
            $assets = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MerchantBackupVerificationException('invalid_asset_manifest', 'The asset manifest is not valid JSON.');
        }

        if (!is_array($assets) || ($assets['bundled'] ?? null) !== false) {
            throw new MerchantBackupVerificationException('invalid_asset_manifest', 'The asset manifest is malformed or claims bundled media.');
        }

        if (!is_array($assets['assets'] ?? null) || !is_array($assets['summary'] ?? null)) {
            throw new MerchantBackupVerificationException('invalid_asset_manifest', 'The asset manifest is incomplete.');
        }

        if (($manifest['asset_summary'] ?? null) != $assets['summary']) {
            throw new MerchantBackupVerificationException('integrity_failed', 'The recorded asset summary is inconsistent.');
        }

        return $assets;
    }

    private function verifyDataFiles(ZipArchive $zip, array $manifest): void
    {
        foreach (array_keys($this->exporter->categories()) as $category) {
            $entry = $category . '.csv';
            $meta = $manifest['files'][$entry];

            $stream = $zip->getStream($entry);

            if ($stream === false) {
                throw new MerchantBackupVerificationException('unreadable_entry', 'An archive data file could not be read.');
            }

            $context = hash_init('sha256');
            $bytes = hash_update_stream($context, $stream);
            fclose($stream);

            if ($bytes !== $meta['bytes'] || !hash_equals($meta['sha256'], hash_final($context))) {
                throw new MerchantBackupVerificationException('integrity_failed', 'An archive data file does not match its recorded checksum.');
            }

            $this->verifyDataFileRows($zip, $category, $manifest['categories'][$category]);
        }
    }

    private function verifyDataFileRows(ZipArchive $zip, string $category, int $expectedRows): void
    {
        $stream = $zip->getStream($category . '.csv');

        if ($stream === false) {
            throw new MerchantBackupVerificationException('unreadable_entry', 'An archive data file could not be read.');
        }

        try {
            $header = fgetcsv($stream, 0, ',', '"', '\\');

            if (!is_array($header)) {
                throw new MerchantBackupVerificationException('malformed_csv', 'An archive data file has no header.');
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $expectedHeader = array_map(
                [MerchantDataExportService::class, 'sanitizeCell'],
                $this->exporter->headingsFor($category)
            );

            if ($header !== $expectedHeader) {
                throw new MerchantBackupVerificationException('header_mismatch', 'An archive data file has an unexpected header.');
            }

            $rows = 0;

            while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $rows++;
            }
        } finally {
            fclose($stream);
        }

        if ($rows !== $expectedRows) {
            throw new MerchantBackupVerificationException('count_mismatch', 'An archive data file does not match its recorded row count.');
        }
    }

    /**
     * @return list<string>
     */
    private function expectedEntries(): array
    {
        $entries = array_map(
            fn (string $category) => $category . '.csv',
            array_keys($this->exporter->categories())
        );

        return array_merge($entries, self::REQUIRED_METADATA_ENTRIES);
    }

    private function uploadDir(int $tenantId): string
    {
        return Storage::disk(config('exports.disk', 'local'))->path(
            trim((string) config('exports.path', 'exports'), '/') . '/' . $tenantId . '/uploads'
        );
    }

    private function maxUploadKb(): int
    {
        return max(1, (int) config('exports.backup.max_upload_kb', 51200));
    }
}
