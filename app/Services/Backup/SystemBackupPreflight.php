<?php

namespace App\Services\Backup;

class SystemBackupPreflight
{
    public function __construct(private readonly DatabaseDumper $dumper) {}

    /**
     * @return array{free_bytes: int, required_bytes: int, estimated_payload_bytes: int, safety_margin: float, copies: int}
     */
    public function check(string $directory, int $mediaBytes = 0, bool $encrypted = false): array
    {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new BackupOperationException('The available disk space for the system backup target cannot be determined.', 'disk_space_unknown');
        }

        $target = $this->existingAncestor($directory);
        $free = $target === null ? false : @disk_free_space($target);

        if ($free === false) {
            throw new BackupOperationException('The available disk space for the system backup target cannot be determined.', 'disk_space_unknown');
        }

        $databaseBytes = max(0, (int) ($this->dumper->estimateBytes() ?? 0));
        $payload = max(0, $mediaBytes) + $databaseBytes;
        $margin = max(1.0, (float) config('backups.disk_space.safety_margin', 1.5));
        $minimum = max(0, (int) config('backups.disk_space.min_free_bytes', 268435456));
        $copies = $encrypted ? 2 : 1;
        $required = (int) ceil($payload * $copies * $margin) + $minimum;

        if ((float) $free < (float) $required) {
            throw new BackupOperationException(sprintf(
                'Insufficient free space for a system backup: about %s MB required, %s MB available.',
                number_format($required / 1048576, 1, '.', ''),
                number_format(((float) $free) / 1048576, 1, '.', '')
            ), 'insufficient_disk_space');
        }

        return [
            'free_bytes' => (int) $free,
            'required_bytes' => $required,
            'estimated_payload_bytes' => $payload,
            'safety_margin' => $margin,
            'copies' => $copies,
        ];
    }

    /**
     * @return array{free_bytes: int, required_bytes: int}
     */
    public function checkTemporarySpace(int $estimatedBytes = 0): array
    {
        $directory = sys_get_temp_dir();

        if ($directory === '' || str_contains($directory, "\0")) {
            throw new BackupOperationException('The available temporary disk space cannot be determined.', 'temp_disk_space_unknown');
        }

        $target = $this->existingAncestor($directory);
        $free = $target === null ? false : @disk_free_space($target);

        if ($free === false) {
            throw new BackupOperationException('The available temporary disk space cannot be determined.', 'temp_disk_space_unknown');
        }

        $margin = max(1.0, (float) config('backups.disk_space.safety_margin', 1.5));
        $minimum = max(0, (int) config('backups.disk_space.temp_min_free_bytes', 268435456));
        $databaseBytes = max(0, (int) ($this->dumper->estimateBytes() ?? 0));
        $payload = max(0, $estimatedBytes, $databaseBytes);
        $required = (int) ceil($payload * $margin) + $minimum;

        if ((float) $free < (float) $required) {
            throw new BackupOperationException(sprintf(
                'Insufficient temporary disk space for a system backup: about %s MB required, %s MB available.',
                number_format($required / 1048576, 1, '.', ''),
                number_format(((float) $free) / 1048576, 1, '.', '')
            ), 'insufficient_temp_space');
        }

        return ['free_bytes' => (int) $free, 'required_bytes' => $required];
    }

    /**
     * @return array{free_bytes: int, required_bytes: int}
     */
    public function checkWriteSpace(string $directory, int $payloadBytes, string $reason = 'insufficient_restore_space'): array
    {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new BackupOperationException('The available disk space for the restore target cannot be determined.', 'disk_space_unknown');
        }

        $target = $this->existingAncestor($directory);
        $free = $target === null ? false : @disk_free_space($target);

        if ($free === false) {
            throw new BackupOperationException('The available disk space for the restore target cannot be determined.', 'disk_space_unknown');
        }

        $margin = max(1.0, (float) config('backups.disk_space.safety_margin', 1.5));
        $minimum = max(0, (int) config('backups.disk_space.min_free_bytes', 268435456));
        $required = (int) ceil(max(0, $payloadBytes) * $margin) + $minimum;

        if ((float) $free < (float) $required) {
            throw new BackupOperationException(sprintf(
                'Insufficient free space for the restore target: about %s MB required, %s MB available.',
                number_format($required / 1048576, 1, '.', ''),
                number_format(((float) $free) / 1048576, 1, '.', '')
            ), $reason);
        }

        return ['free_bytes' => (int) $free, 'required_bytes' => $required];
    }

    private function existingAncestor(string $directory): ?string
    {
        $current = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);

        if ($current === '') {
            return null;
        }

        while (true) {
            if (preg_match('/^[A-Za-z]:$/', $current) === 1) {
                $current .= DIRECTORY_SEPARATOR;
            }

            if (is_dir($current)) {
                return $current;
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return null;
            }

            $current = $parent;
        }
    }
}
