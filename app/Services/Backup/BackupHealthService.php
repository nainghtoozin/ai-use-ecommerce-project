<?php

namespace App\Services\Backup;

use App\Services\Backup\Encryption\BackupKeyRing;
use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\Offsite\RemoteBackupStore;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupHealthService
{
    public function __construct(
        private readonly BackupStateStore $state,
        private readonly EncryptedContainer $container,
        private readonly BackupKeyRing $keys,
        private readonly RemoteBackupStore $offsite,
        private readonly SystemBackupValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(bool $verify = false): array
    {
        $diskName = (string) config('backups.disk', 'local');
        $directory = trim((string) config('backups.path', 'backups'), '/');
        $disk = Storage::disk($diskName);
        $maxAgeHours = max(1, (int) config('backups.schedule.max_age_hours', 26));

        $archives = collect($disk->files($directory === '' ? '' : $directory))
            ->filter(static fn (string $path): bool => preg_match('/^system-backup-\d{8}-\d{6}-[a-z0-9]{8}\.(zip|sbx)$/', basename($path)) === 1)
            ->values()
            ->all();

        $reasons = [];
        $warnings = [];
        $newest = null;
        $newestPath = null;
        $newestMtime = 0;

        foreach ($archives as $path) {
            $mtime = (int) $disk->lastModified($path);

            if ($mtime >= $newestMtime) {
                $newestMtime = $mtime;
                $newestPath = $path;
            }
        }

        if ($newestPath === null) {
            $reasons[] = 'no_archive';
        } else {
            $absolute = $disk->path($newestPath);
            $ageHours = max(0, (int) floor((time() - $newestMtime) / 3600));
            $format = null;
            $keyAvailable = null;
            $verification = null;

            try {
                $format = SystemBackupArchive::detectFormat($absolute);
            } catch (Throwable) {
                $reasons[] = 'unreadable_archive';
            }

            if ($format === SystemBackupArchive::FORMAT_ENCRYPTED) {
                try {
                    $inspection = $this->container->inspect($absolute);
                    $keyAvailable = (bool) $inspection['key_available'];

                    if (!$keyAvailable) {
                        $reasons[] = 'key_unavailable';
                    }
                } catch (Throwable) {
                    $reasons[] = 'unreadable_archive';
                }
            }

            if ($ageHours > $maxAgeHours) {
                $reasons[] = 'stale_archive';
            }

            if ($verify && $format !== null) {
                try {
                    if ($format === SystemBackupArchive::FORMAT_ENCRYPTED) {
                        $this->container->verify($absolute);
                    } else {
                        $this->validator->validate($absolute);
                    }

                    $verification = 'verified';
                } catch (Throwable) {
                    $verification = 'failed';
                    $reasons[] = 'verification_failed';
                }
            }

            $newest = [
                'path' => $newestPath,
                'bytes' => (int) $disk->size($newestPath),
                'modified_at' => gmdate('c', $newestMtime),
                'age_hours' => $ageHours,
                'format' => $format,
                'key_available' => $keyAvailable,
                'verification' => $verification,
            ];
        }

        $state = $this->state->read();
        $lastSuccess = $state['last_success_at'] ?? null;
        $lastFailure = $state['last_failure_at'] ?? null;

        if ($lastFailure !== null && ($lastSuccess === null || strcmp((string) $lastFailure, (string) $lastSuccess) > 0)) {
            $reasons[] = 'recent_failure';
        }

        $offsiteConfigured = $this->offsite->configured();
        $lastOffsite = is_array($state['last_offsite'] ?? null) ? $state['last_offsite'] : null;

        if ($offsiteConfigured && $lastOffsite === null) {
            $warnings[] = 'offsite_unverified';
        }

        if (!$offsiteConfigured) {
            $warnings[] = 'offsite_not_configured';
        }

        if (!(bool) config('backups.encryption.enabled', false)) {
            $warnings[] = 'encryption_disabled';
        } else {
            try {
                $this->keys->activeKeyId();
            } catch (Throwable) {
                $reasons[] = 'active_key_unavailable';
            }
        }

        if (!(bool) config('backups.retention.enabled', false)) {
            $warnings[] = 'retention_disabled';
        }

        $reasons = array_values(array_unique($reasons));
        $warnings = array_values(array_unique($warnings));

        return [
            'healthy' => $reasons === [],
            'reasons' => $reasons,
            'warnings' => $warnings,
            'disk' => $diskName,
            'directory' => $directory,
            'archive_count' => count($archives),
            'newest' => $newest,
            'max_age_hours' => $maxAgeHours,
            'last_success_at' => $lastSuccess,
            'last_attempt_at' => $state['last_attempt_at'] ?? null,
            'last_failure_at' => $lastFailure,
            'last_failure_reason' => $state['last_failure_reason'] ?? null,
            'offsite_configured' => $offsiteConfigured,
            'last_offsite' => $lastOffsite,
            'encryption_enabled' => (bool) config('backups.encryption.enabled', false),
            'retention_enabled' => (bool) config('backups.retention.enabled', false),
            'schedule_enabled' => (bool) config('backups.schedule.enabled', false),
        ];
    }
}
