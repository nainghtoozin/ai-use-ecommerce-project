<?php

namespace App\Services\Backup;

use PDO;
use RuntimeException;

class DatabaseDumper
{
    private const INSERT_BATCH_SIZE = 200;

    public function __construct(private readonly ?string $connectionName = null) {}

    /**
     * @return array{driver: string, server_version: string, method: string, consistency: string, tables: int, entry: string, notes: array<int, string>}
     */
    public function dump(string $destination): array
    {
        $config = $this->connectionConfig();
        $driver = (string) ($config['driver'] ?? '');

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $this->dumpMysql($config, $destination);
        }

        if ($driver === 'sqlite') {
            return $this->dumpSqlite($config, $destination);
        }

        throw new RuntimeException('Unsupported database driver for a system backup: ' . ($driver === '' ? 'unknown' : $driver));
    }

    public function estimateBytes(): ?int
    {
        try {
            $config = $this->connectionConfig();
            $driver = (string) ($config['driver'] ?? '');

            if ($driver === 'sqlite') {
                $database = (string) ($config['database'] ?? '');

                if ($database === '' || $database === ':memory:' || !is_file($database)) {
                    return null;
                }

                $bytes = filesize($database);

                return $bytes === false ? null : (int) $bytes;
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $pdo = $this->connect($config);
                $bytes = $pdo->query(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()'
                )->fetchColumn();

                return $bytes === false ? null : (int) $bytes;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        $name = $this->connectionName ?? config('backups.database.connection') ?? config('database.default');
        $config = config('database.connections.' . $name);

        if (!is_array($config)) {
            throw new RuntimeException('The configured system backup database connection is not defined.');
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{driver: string, server_version: string, method: string, consistency: string, tables: int, entry: string, notes: array<int, string>}
     */
    private function dumpMysql(array $config, string $destination): array
    {
        $binary = config('backups.database.dump_binary');

        if (is_string($binary) && $binary !== '' && is_file($binary) && is_executable($binary) && function_exists('proc_open')) {
            return $this->dumpWithMysqldump($config, $destination, $binary);
        }

        return $this->dumpWithConsistentSnapshot($config, $destination);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{driver: string, server_version: string, method: string, consistency: string, tables: int, entry: string, notes: array<int, string>}
     */
    private function dumpWithMysqldump(array $config, string $destination, string $binary): array
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('The system backup database name is not configured.');
        }

        $defaultsFile = tempnam(sys_get_temp_dir(), 'system-backup-client-');
        if ($defaultsFile === false) {
            throw new RuntimeException('Unable to create the database client defaults file.');
        }

        try {
            $lines = ['[client]'];
            $values = [
                'host' => (string) ($config['host'] ?? ''),
                'port' => (string) ($config['port'] ?? ''),
                'socket' => (string) ($config['unix_socket'] ?? ''),
                'user' => (string) ($config['username'] ?? ''),
                'password' => (string) ($config['password'] ?? ''),
            ];

            foreach ($values as $key => $value) {
                if ($value === '') {
                    continue;
                }
                $lines[] = $key . '="' . addcslashes($value, "\\\"") . '"';
            }

            if (file_put_contents($defaultsFile, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
                throw new RuntimeException('Unable to write the database client defaults file.');
            }

            @chmod($defaultsFile, 0600);

            $command = [
                $binary,
                '--defaults-extra-file=' . str_replace('\\', '/', $defaultsFile),
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--no-tablespaces',
                '--routines',
                '--events',
                '--triggers',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--result-file=' . str_replace('\\', '/', $destination),
                $database,
            ];

            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start the database dump process.');
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException('The database dump process failed with exit code ' . $exitCode . '.');
            }

            if (!is_file($destination) || (int) filesize($destination) === 0) {
                throw new RuntimeException('The database dump process produced no output.');
            }
        } finally {
            @unlink($defaultsFile);
        }

        $pdo = $this->connect($config);

        return [
            'driver' => 'mysql',
            'server_version' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'method' => 'mysqldump',
            'consistency' => 'innodb_consistent_snapshot',
            'tables' => count($this->tables($pdo)),
            'entry' => 'database/database.sql',
            'notes' => ['mysqldump --single-transaction is consistent for InnoDB tables; non-transactional tables are not guaranteed.'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{driver: string, server_version: string, method: string, consistency: string, tables: int, entry: string, notes: array<int, string>}
     */
    private function dumpWithConsistentSnapshot(array $config, string $destination): array
    {
        $pdo = $this->connect($config);
        $serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $tables = $this->tables($pdo);

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write the database snapshot file.');
        }

        try {
            $this->write($handle, "-- System backup database snapshot\n-- generated_at: " . now()->toIso8601String() . "\n-- driver: mysql\n-- server_version: " . $serverVersion . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            foreach ($tables as $table) {
                $ddl = $this->createTableStatement($pdo, $table);
                $this->write($handle, 'DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table) . ";\n" . $ddl . ";\n\n");
                $this->writeRows($pdo, $handle, $table);
            }

            $pdo->exec('COMMIT');
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($destination);
            throw $exception;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return [
            'driver' => 'mysql',
            'server_version' => $serverVersion,
            'method' => 'pdo_consistent_snapshot',
            'consistency' => 'innodb_consistent_snapshot',
            'tables' => count($tables),
            'entry' => 'database/database.sql',
            'notes' => ['A REPEATABLE READ consistent snapshot is consistent for InnoDB tables; non-transactional tables, views, routines and triggers are not included.'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{driver: string, server_version: string, method: string, consistency: string, tables: int, entry: string, notes: array<int, string>}
     */
    private function dumpSqlite(array $config, string $destination): array
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('A sqlite system backup requires a file-backed database.');
        }

        if (is_file($destination)) {
            @unlink($destination);
        }

        $pdo = $this->connect($config);
        $pdo->exec('VACUUM INTO ' . $pdo->quote(str_replace('\\', '/', $destination)));

        if (!is_file($destination) || (int) filesize($destination) === 0) {
            throw new RuntimeException('The sqlite backup produced no output.');
        }

        return [
            'driver' => 'sqlite',
            'server_version' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'method' => 'sqlite_vacuum',
            'consistency' => 'consistent',
            'tables' => (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn(),
            'entry' => 'database/database.sqlite',
            'notes' => ['VACUUM INTO writes a consistent point-in-time copy of the sqlite database.'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connect(array $config): PDO
    {
        $driver = (string) ($config['driver'] ?? '');

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $socket = (string) ($config['unix_socket'] ?? '');
            $charset = (string) ($config['charset'] ?? 'utf8mb4');

            $dsn = $socket !== ''
                ? 'mysql:unix_socket=' . $socket . ';dbname=' . (string) ($config['database'] ?? '') . ';charset=' . $charset
                : 'mysql:host=' . (string) ($config['host'] ?? '127.0.0.1') . ';port=' . (string) ($config['port'] ?? '3306') . ';dbname=' . (string) ($config['database'] ?? '') . ';charset=' . $charset;

            return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        if ($driver === 'sqlite') {
            return new PDO('sqlite:' . (string) ($config['database'] ?? ''), null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        throw new RuntimeException('Unsupported database driver for a system backup: ' . ($driver === '' ? 'unknown' : $driver));
    }

    /**
     * @return array<int, string>
     */
    private function tables(PDO $pdo): array
    {
        $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $tables = [];

        foreach ($rows as $row) {
            if (strtoupper((string) ($row[1] ?? '')) === 'BASE TABLE') {
                $tables[] = (string) $row[0];
            }
        }

        sort($tables);

        return $tables;
    }

    private function createTableStatement(PDO $pdo, string $table): string
    {
        $row = $pdo->query('SHOW CREATE TABLE ' . $this->quoteIdentifier($table))->fetch(PDO::FETCH_NUM);
        $ddl = (string) ($row[1] ?? '');

        if ($ddl === '') {
            throw new RuntimeException('Unable to read the table definition for ' . $table . '.');
        }

        return $ddl;
    }

    /**
     * @param resource $handle
     */
    private function writeRows(PDO $pdo, $handle, string $table): void
    {
        $statement = $pdo->query('SELECT * FROM ' . $this->quoteIdentifier($table));
        $columns = null;
        $batch = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($columns === null) {
                $columns = array_map(fn (string $column) => $this->quoteIdentifier($column), array_keys($row));
            }

            $batch[] = '(' . implode(', ', array_map(
                fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                $row
            )) . ')';

            if (count($batch) >= self::INSERT_BATCH_SIZE) {
                $this->writeInsert($handle, $table, $columns, $batch);
                $batch = [];
            }
        }

        if ($columns === null) {
            return;
        }

        if ($batch !== []) {
            $this->writeInsert($handle, $table, $columns, $batch);
        }
    }

    /**
     * @param resource $handle
     * @param array<int, string> $columns
     * @param array<int, string> $rows
     */
    private function writeInsert($handle, string $table, array $columns, array $rows): void
    {
        $this->write($handle, 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $columns) . ") VALUES\n" . implode(",\n", $rows) . ";\n\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param resource $handle
     */
    private function write($handle, string $contents): void
    {
        if (fwrite($handle, $contents) === false) {
            throw new RuntimeException('Unable to write the database snapshot file.');
        }
    }
}
