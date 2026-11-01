<?php

namespace App\Services\Backup;

class SystemBackupLock
{
    private const DIRECTORY = '.locks';

    /** @var resource|null */
    private $handle = null;

    private ?string $path = null;

    public function __construct(private readonly string $file = 'system-backup.lock') {}

    public function acquire(string $backupDirectory): void
    {
        $directory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupDirectory), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::DIRECTORY;

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new BackupOperationException('Unable to create the system backup lock directory.', 'lock_unavailable');
        }

        $path = $directory . DIRECTORY_SEPARATOR . $this->file;
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            throw new BackupOperationException('Unable to create the system backup lock file.', 'lock_unavailable');
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new BackupOperationException('Another system backup operation is already running.', 'backup_in_progress');
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) getmypid());
        fflush($handle);

        $this->handle = $handle;
        $this->path = $path;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            @flock($this->handle, LOCK_UN);
            @fclose($this->handle);
        }

        $this->handle = null;
    }

    public function path(): ?string
    {
        return $this->path;
    }
}
