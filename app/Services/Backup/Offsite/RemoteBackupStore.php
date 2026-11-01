<?php

namespace App\Services\Backup\Offsite;

use App\Services\Backup\BackupOperationException;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RemoteBackupStore
{
    public function diskName(): ?string
    {
        if (!(bool) config('backups.offsite.enabled', false)) {
            return null;
        }

        $disk = trim((string) config('backups.offsite.disk', ''));

        return $disk === '' ? null : $disk;
    }

    public function configured(): bool
    {
        return $this->diskName() !== null;
    }

    /**
     * @return array{disk: string, path: string, bytes: int, sha256: string, remote_bytes: int, remote_sha256: string|null, verified_by: string}
     */
    public function upload(string $localPath, bool $encrypted): array
    {
        $diskName = $this->diskName();

        if ($diskName === null) {
            throw new BackupOperationException(
                'Off-site backup storage is not configured or not enabled.',
                'offsite_not_configured'
            );
        }

        if ((bool) config('backups.offsite.require_encryption', true) && !$encrypted) {
            throw new BackupOperationException(
                'Off-site upload requires an encrypted archive; refusing to copy a plaintext backup.',
                'offsite_encryption_required'
            );
        }

        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new BackupOperationException('The local backup archive to upload is missing or unreadable.', 'offsite_upload_failed');
        }

        $bytes = filesize($localPath);
        $sha256 = hash_file('sha256', $localPath);

        if ($bytes === false || !is_string($sha256)) {
            throw new BackupOperationException('The local backup archive could not be checksummed before upload.', 'offsite_upload_failed');
        }

        try {
            $disk = Storage::disk($diskName);
        } catch (Throwable) {
            throw new BackupOperationException(
                'The configured off-site disk is not available in this application.',
                'offsite_not_configured'
            );
        }

        $directory = trim((string) config('backups.offsite.path', 'system-backups'), '/');
        $remotePath = $directory . '/' . basename($localPath);

        if ($disk->exists($remotePath)) {
            throw new BackupOperationException(
                'The off-site backup path already exists; refusing to overwrite a remote object.',
                'offsite_target_exists'
            );
        }

        if ($directory !== '' && !$disk->exists($directory) && !$disk->makeDirectory($directory, 0700, true)) {
            throw new BackupOperationException('The off-site backup directory cannot be created.', 'offsite_upload_failed');
        }

        $stream = @fopen($localPath, 'rb');

        if ($stream === false) {
            throw new BackupOperationException('The local backup archive could not be opened for upload.', 'offsite_upload_failed');
        }

        try {
            $written = $disk->writeStream($remotePath, $stream);
        } catch (Throwable) {
            fclose($stream);
            $this->deletePartial($disk, $remotePath);

            throw new BackupOperationException('The backup archive could not be uploaded to the off-site disk.', 'offsite_upload_failed');
        }

        fclose($stream);

        if ($written === false) {
            $this->deletePartial($disk, $remotePath);

            throw new BackupOperationException('The backup archive could not be uploaded to the off-site disk.', 'offsite_upload_failed');
        }

        try {
            $remoteBytes = $disk->size($remotePath);
        } catch (Throwable) {
            $this->deletePartial($disk, $remotePath);

            throw new BackupOperationException('The uploaded backup size could not be read back.', 'offsite_verification_failed');
        }

        if ((int) $remoteBytes !== (int) $bytes) {
            $this->deletePartial($disk, $remotePath);

            throw new BackupOperationException(
                'The uploaded backup size does not match the local archive.',
                'offsite_verification_failed'
            );
        }

        if (!(bool) config('backups.offsite.verify_readback', true)) {
            return [
                'disk' => $diskName,
                'path' => $remotePath,
                'bytes' => (int) $bytes,
                'sha256' => $sha256,
                'remote_bytes' => (int) $remoteBytes,
                'remote_sha256' => null,
                'verified_by' => 'size_only',
            ];
        }

        $remoteSha = $this->remoteChecksum($disk, $remotePath);

        if ($remoteSha === null || !hash_equals($sha256, $remoteSha)) {
            $this->deletePartial($disk, $remotePath);

            throw new BackupOperationException(
                'The uploaded backup checksum does not match the local archive.',
                'offsite_verification_failed'
            );
        }

        return [
            'disk' => $diskName,
            'path' => $remotePath,
            'bytes' => (int) $bytes,
            'sha256' => $sha256,
            'remote_bytes' => (int) $remoteBytes,
            'remote_sha256' => $remoteSha,
            'verified_by' => 'size_and_sha256',
        ];
    }

    private function remoteChecksum(mixed $disk, string $remotePath): ?string
    {
        try {
            $stream = $disk->readStream($remotePath);
        } catch (Throwable) {
            return null;
        }

        if (!is_resource($stream)) {
            return null;
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function deletePartial(mixed $disk, string $remotePath): void
    {
        try {
            if ($disk->exists($remotePath)) {
                $disk->delete($remotePath);
            }
        } catch (Throwable) {
            // A leftover partial object is reported by the caller's failure audit.
        }
    }
}
