<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;

class BackupStateStore
{
    private const VERSION = 1;

    private const DIRECTORY = '.state';

    private const FILE = 'backup-state.json';

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->path();

        if ($path === null || !is_file($path)) {
            return ['version' => self::VERSION, 'uploads' => []];
        }

        $contents = @file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($decoded)) {
            return ['version' => self::VERSION, 'uploads' => []];
        }

        $decoded['uploads'] = is_array($decoded['uploads'] ?? null) ? $decoded['uploads'] : [];

        return $decoded;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed>|null $upload
     */
    public function recordSuccess(array $result, ?array $upload): void
    {
        $state = $this->read();
        $state['version'] = self::VERSION;
        $state['last_attempt_at'] = now()->toIso8601String();
        $state['last_success_at'] = now()->toIso8601String();
        $state['last_archive'] = (string) ($result['path'] ?? '');
        $state['last_bytes'] = (int) ($result['bytes'] ?? 0);
        $state['last_encrypted'] = (bool) ($result['encrypted'] ?? false);
        $state['last_key_id'] = $result['key_id'] ?? null;
        $state['last_sha256'] = $result['sha256'] ?? null;

        if ($upload !== null) {
            $state['last_offsite'] = [
                'verified_at' => now()->toIso8601String(),
                'verified_by' => $upload['verified_by'] ?? 'unknown',
                'disk' => $upload['disk'] ?? null,
                'path' => $upload['path'] ?? null,
                'sha256' => $upload['remote_sha256'] ?? $upload['sha256'] ?? null,
            ];

            $basename = basename((string) ($result['path'] ?? ''));

            if ($basename !== '') {
                $state['uploads'][$basename] = $state['last_offsite'];
            }
        }

        $this->write($state);
    }

    public function recordFailure(string $reason, ?string $message = null): void
    {
        $state = $this->read();
        $state['version'] = self::VERSION;
        $state['last_attempt_at'] = now()->toIso8601String();
        $state['last_failure_at'] = now()->toIso8601String();
        $state['last_failure_reason'] = $reason;

        if ($message !== null) {
            $state['last_failure_message'] = mb_substr($message, 0, 400);
        }

        $this->write($state);
    }

    /**
     * @param array<string, mixed> $upload
     */
    public function recordUpload(string $path, array $upload): void
    {
        $state = $this->read();
        $state['version'] = self::VERSION;
        $basename = basename($path);
        $state['last_offsite'] = [
            'verified_at' => now()->toIso8601String(),
            'verified_by' => $upload['verified_by'] ?? 'unknown',
            'disk' => $upload['disk'] ?? null,
            'path' => $upload['path'] ?? null,
            'sha256' => $upload['remote_sha256'] ?? $upload['sha256'] ?? null,
        ];
        $state['uploads'][$basename] = $state['last_offsite'];
        $this->write($state);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifiedUpload(string $path): ?array
    {
        $uploads = $this->read()['uploads'] ?? [];
        $entry = $uploads[basename($path)] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param array<int, string> $paths
     */
    public function forgetUploads(array $paths): void
    {
        $state = $this->read();
        $changed = false;

        foreach ($paths as $path) {
            $basename = basename($path);

            if (isset($state['uploads'][$basename])) {
                unset($state['uploads'][$basename]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->write($state);
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function write(array $state): void
    {
        $path = $this->path();

        if ($path === null) {
            return;
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return;
        }

        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . '.state-' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporary, $encoded, LOCK_EX) === false) {
            return;
        }

        @chmod($temporary, 0600);

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    private function path(): ?string
    {
        try {
            $disk = Storage::disk((string) config('backups.disk', 'local'));
            $directory = trim((string) config('backups.path', 'backups'), '/');

            if ($directory === '') {
                return null;
            }

            return rtrim($disk->path($directory), DIRECTORY_SEPARATOR . '/')
                . DIRECTORY_SEPARATOR . self::DIRECTORY . DIRECTORY_SEPARATOR . self::FILE;
        } catch (\Throwable) {
            return null;
        }
    }
}
