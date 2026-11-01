<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;

class BackupRetention
{
    private const PATTERN = '/^system-backup-\d{8}-\d{6}-[a-z0-9]{8}\.(zip|sbx)$/';

    public function __construct(private readonly BackupStateStore $state) {}

    /**
     * @return array{candidates: array<int, array<string, mixed>>, kept: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function plan(int $keepLast, int $maxAgeDays, bool $requireOffsite): array
    {
        $keepLast = max(1, $keepLast);
        $disk = Storage::disk((string) config('backups.disk', 'local'));
        $directory = trim((string) config('backups.path', 'backups'), '/');

        $files = collect($disk->files($directory === '' ? '' : $directory))
            ->filter(static fn (string $path): bool => preg_match(self::PATTERN, basename($path)) === 1)
            ->map(function (string $path) use ($disk): array {
                $absolute = $disk->path($path);

                return [
                    'path' => $path,
                    'bytes' => (int) $disk->size($path),
                    'modified_at' => (int) $disk->lastModified($path),
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();

        $kept = [];
        $candidates = [];
        $skipped = [];

        foreach ($files as $index => $file) {
            $file['age_days'] = max(0, (int) floor((time() - (int) $file['modified_at']) / 86400));

            if ($index < $keepLast) {
                $file['reason'] = 'within_keep_last';
                $kept[] = $file;
                continue;
            }

            if ($maxAgeDays > 0 && $file['age_days'] < $maxAgeDays) {
                $file['reason'] = 'younger_than_max_age';
                $kept[] = $file;
                continue;
            }

            if ($requireOffsite && $this->state->verifiedUpload($file['path']) === null) {
                $file['reason'] = 'no_verified_offsite_copy';
                $skipped[] = $file;
                continue;
            }

            $file['reason'] = 'beyond_keep_last_and_max_age';
            $candidates[] = $file;
        }

        return ['candidates' => $candidates, 'kept' => $kept, 'skipped' => $skipped];
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array{deleted: array<int, string>, bytes: int, failed: array<int, string>}
     */
    public function delete(array $candidates): array
    {
        $disk = Storage::disk((string) config('backups.disk', 'local'));
        $deleted = [];
        $failed = [];
        $bytes = 0;

        foreach ($candidates as $candidate) {
            $path = (string) ($candidate['path'] ?? '');

            if (preg_match(self::PATTERN, basename($path)) !== 1 || str_contains($path, '..')) {
                $failed[] = $path;
                continue;
            }

            try {
                if (!$disk->exists($path)) {
                    continue;
                }

                if (!$disk->delete($path)) {
                    $failed[] = $path;
                    continue;
                }

                $deleted[] = $path;
                $bytes += (int) ($candidate['bytes'] ?? 0);
            } catch (\Throwable) {
                $failed[] = $path;
            }
        }

        if ($deleted !== []) {
            $this->state->forgetUploads($deleted);
        }

        return ['deleted' => $deleted, 'bytes' => $bytes, 'failed' => $failed];
    }
}
