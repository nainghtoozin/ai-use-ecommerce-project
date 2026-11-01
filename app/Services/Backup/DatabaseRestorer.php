<?php

namespace App\Services\Backup;

use PDO;

class DatabaseRestorer
{
    private const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';

    private const DEFAULT_INLINE_LIMIT = 268435456;

    public function __construct(private readonly ?string $connectionName = null) {}

    /**
     * @return array<string, mixed>
     */
    public function connectionConfig(): array
    {
        $name = $this->connectionName ?? config('backups.database.connection') ?? config('database.default');
        $config = config('database.connections.' . $name);

        if (!is_array($config)) {
            throw new BackupOperationException('The configured restore database connection is not defined.', 'restore_database_failed');
        }

        $driver = (string) ($config['driver'] ?? '');

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            throw new BackupOperationException(
                'System backup restore currently supports MySQL or MariaDB servers only.',
                'restore_unsupported_driver'
            );
        }

        return $config;
    }

    public function assertAbsent(string $database): void
    {
        $this->assertIdentifier($database);

        $statement = $this->serverConnection()->prepare(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE LOWER(SCHEMA_NAME) = LOWER(?)'
        );
        $statement->execute([$database]);

        if ($statement->fetchColumn() !== false) {
            throw new BackupOperationException(
                'The restore target database already exists; refusing to modify it.',
                'restore_target_exists'
            );
        }
    }

    public function create(string $database): void
    {
        $this->assertIdentifier($database);
        $this->serverConnection()->exec(
            'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    /**
     * @param resource $stream
     * @return int bytes imported
     */
    public function import(string $database, $stream): int
    {
        $this->assertIdentifier($database);

        $binary = config('backups.restore.mysql_binary');

        if (is_string($binary) && $binary !== '' && is_file($binary) && is_executable($binary) && function_exists('proc_open')) {
            return $this->importWithBinary($database, $stream, $binary);
        }

        $limit = (int) config('backups.restore.max_inline_sql_bytes', self::DEFAULT_INLINE_LIMIT);
        $sql = '';

        while (!feof($stream)) {
            $chunk = fread($stream, 1048576);

            if ($chunk === false) {
                throw new BackupOperationException('The database snapshot could not be read from the archive.', 'restore_database_failed');
            }

            $sql .= $chunk;

            if ($limit > 0 && strlen($sql) > $limit) {
                throw new BackupOperationException(
                    'The database snapshot is too large to import without a configured mysql client binary.',
                    'restore_too_large'
                );
            }
        }

        if (trim($sql) === '') {
            throw new BackupOperationException('The database snapshot in the archive is empty.', 'restore_database_failed');
        }

        $connection = $this->connectionConfig();
        $connection['database'] = $database;

        try {
            $this->connect($connection)->exec($sql);
        } catch (\Throwable $exception) {
            throw new BackupOperationException('The database snapshot could not be imported.', 'restore_database_failed');
        }

        return strlen($sql);
    }

    public function tableCount(string $database): int
    {
        $this->assertIdentifier($database);

        $statement = $this->serverConnection()->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE LOWER(table_schema) = LOWER(?) AND table_type = 'BASE TABLE'"
        );
        $statement->execute([$database]);

        return (int) $statement->fetchColumn();
    }

    public function migrationCount(string $database): ?int
    {
        $this->assertIdentifier($database);

        $connection = $this->connectionConfig();
        $connection['database'] = $database;

        $pdo = $this->connect($connection);
        $exists = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE LOWER(table_schema) = LOWER(?) AND table_name = 'migrations'"
        );
        $exists->execute([$database]);

        if ((int) $exists->fetchColumn() === 0) {
            return null;
        }

        return (int) $pdo->query('SELECT COUNT(*) FROM `migrations`')->fetchColumn();
    }

    public function drop(string $database, array $allowedPrefixes): void
    {
        $this->assertIdentifier($database);

        $allowed = false;

        foreach ($allowedPrefixes as $prefix) {
            $prefix = strtolower(trim((string) $prefix));

            if ($prefix !== '' && str_starts_with($database, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new BackupOperationException(
                'Refusing to drop a database that does not match the approved restore prefixes.',
                'restore_target_not_allowed'
            );
        }

        $this->serverConnection()->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    }

    private function assertIdentifier(string $database): void
    {
        if (preg_match(self::NAME_PATTERN, $database) !== 1) {
            throw new BackupOperationException('The restore target database name is invalid.', 'restore_target_invalid');
        }
    }

    private function serverConnection(): PDO
    {
        $connection = $this->connectionConfig();
        $connection['database'] = '';

        return $this->connect($connection);
    }

    /**
     * @param resource $stream
     */
    private function importWithBinary(string $database, $stream, string $binary): int
    {
        $connection = $this->connectionConfig();
        $defaultsFile = tempnam(sys_get_temp_dir(), 'system-restore-client-');

        if ($defaultsFile === false) {
            throw new BackupOperationException('Unable to create the database client defaults file.', 'restore_database_failed');
        }

        try {
            $lines = ['[client]'];
            $values = [
                'host' => (string) ($connection['host'] ?? ''),
                'port' => (string) ($connection['port'] ?? ''),
                'socket' => (string) ($connection['unix_socket'] ?? ''),
                'user' => (string) ($connection['username'] ?? ''),
                'password' => (string) ($connection['password'] ?? ''),
            ];

            foreach ($values as $key => $value) {
                if ($value === '') {
                    continue;
                }

                $lines[] = $key . '="' . addcslashes($value, "\\\"") . '"';
            }

            if (file_put_contents($defaultsFile, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
                throw new BackupOperationException('Unable to write the database client defaults file.', 'restore_database_failed');
            }

            @chmod($defaultsFile, 0600);

            $command = [
                $binary,
                '--defaults-extra-file=' . str_replace('\\', '/', $defaultsFile),
                '--default-character-set=utf8mb4',
                $database,
            ];

            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                throw new BackupOperationException('Unable to start the database import process.', 'restore_database_failed');
            }

            $bytes = 0;

            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1048576);

                    if ($chunk === false) {
                        throw new BackupOperationException('The database snapshot could not be read from the archive.', 'restore_database_failed');
                    }

                    if ($chunk !== '' && fwrite($pipes[0], $chunk) === false) {
                        throw new BackupOperationException('The database import process stopped accepting input.', 'restore_database_failed');
                    }

                    $bytes += strlen($chunk);
                }
            } finally {
                fclose($pipes[0]);
                stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
            }

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new BackupOperationException('The database import process failed.', 'restore_database_failed');
            }

            return $bytes;
        } finally {
            @unlink($defaultsFile);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connect(array $config): PDO
    {
        $socket = (string) ($config['unix_socket'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $database = (string) ($config['database'] ?? '');

        $dsn = $socket !== ''
            ? 'mysql:unix_socket=' . $socket . ';charset=' . $charset . ($database !== '' ? ';dbname=' . $database : '')
            : 'mysql:host=' . (string) ($config['host'] ?? '127.0.0.1') . ';port=' . (string) ($config['port'] ?? '3306') . ';charset=' . $charset . ($database !== '' ? ';dbname=' . $database : '');

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }
}
