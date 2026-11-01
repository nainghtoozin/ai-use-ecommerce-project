<?php

namespace App\Services\Backup;

use Psr\Log\LoggerInterface;
use Throwable;

class SystemBackupAuditLogger
{
    private const MAX_ERROR_LENGTH = 400;

    private const REDACTIONS = [
        '/--password(?:=|\s+)[^\s"\']+/i',
        '/--defaults-extra-file=[^\s"\']+/i',
        '/[a-z0-9_]*(?:password|passwd|pwd)\s*[=:]\s*[^\s,;"\']+/i',
        '/[a-z0-9_]*(?:api[_-]?key|api[_-]?secret|secret|token)\s*[=:]\s*[^\s,;"\']+/i',
        '/\b[a-z][a-z0-9+.\-]*:\/\/[^\s\/@]+@/i',
        "/\baccess denied for user\s+'[^']*'@'[^']*'/i",
        "/\bfor user\s+'[^']*'@'[^']*'/i",
    ];

    private const CONTEXT_KEYS = [
        'archive',
        'bytes',
        'candidates',
        'cleanup',
        'container_version',
        'database',
        'database_driver',
        'database_method',
        'database_tables',
        'deleted',
        'disk',
        'dry_run',
        'duration_ms',
        'encrypted',
        'error_class',
        'error_message',
        'healthy',
        'kept',
        'key_id',
        'label',
        'last_backup_at',
        'media_bytes',
        'media_files',
        'media_target',
        'media_restored',
        'message',
        'offsite_disk',
        'offsite_path',
        'path',
        'payload_bytes',
        'payload_sha256',
        'phase',
        'reason',
        'remote_bytes',
        'remote_sha256',
        'sha256',
        'tables_restored',
        'target_database',
    ];

    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * @param array{disk: string, path: string, bytes: int, sha256: string|null, manifest: array<string, mixed>, encrypted?: bool, container_version?: int|null, key_id?: int|null, payload_sha256?: string|null} $result
     */
    public function success(array $result, int $durationMs, ?string $label): void
    {
        $manifest = $result['manifest'];

        $this->logger->info('system backup created: ' . $result['path'], [
            'event' => 'system_backup.created',
            'status' => 'success',
            'label' => $label,
            'occurred_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'environment' => app()->environment(),
            'disk' => $result['disk'],
            'path' => $result['path'],
            'bytes' => $result['bytes'],
            'sha256' => $result['sha256'],
            'database_driver' => $manifest['database']['driver'] ?? null,
            'database_method' => $manifest['database']['method'] ?? null,
            'database_tables' => $manifest['database']['tables'] ?? null,
            'media_files' => $manifest['media']['files'] ?? null,
            'media_bytes' => $manifest['media']['bytes'] ?? null,
            'encrypted' => (bool) ($result['encrypted'] ?? false),
            'container_version' => $result['container_version'] ?? null,
            'key_id' => $result['key_id'] ?? null,
            'payload_sha256' => $result['payload_sha256'] ?? null,
        ]);
    }

    public function failure(Throwable $exception, int $durationMs, ?string $label): void
    {
        $error = $this->redact($exception->getMessage());

        $this->logger->error('system backup failed: ' . $exception::class, [
            'event' => 'system_backup.failed',
            'status' => 'failure',
            'label' => $label,
            'occurred_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'environment' => app()->environment(),
            'error_class' => $exception::class,
            'error_message' => $error,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $event, string $status, array $context = [], string $level = 'info'): void
    {
        $payload = [
            'event' => $event,
            'status' => $status,
            'occurred_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        foreach ($context as $key => $value) {
            if (!in_array($key, self::CONTEXT_KEYS, true)) {
                continue;
            }

            if (is_string($value)) {
                $value = $this->redact($value);
            }

            $payload[$key] = $value;
        }

        $this->logger->log($level, 'system backup ' . $event . ': ' . $status, $payload);
    }

    public function redact(string $value): string
    {
        $value = preg_replace(self::REDACTIONS, '[redacted]', $value) ?? '';

        return mb_substr($value, 0, self::MAX_ERROR_LENGTH);
    }
}
