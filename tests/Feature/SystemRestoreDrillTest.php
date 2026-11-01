<?php

namespace Tests\Feature;

use App\Services\Backup\BackupOperationException;
use App\Services\Backup\Encryption\EncryptedContainer;
use App\Services\Backup\SystemRestoreService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SystemRestoreDrillTest extends TestCase
{
    private string $temporaryDirectory;

    /** @var array<int, string> */
    private array $createdDatabases = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('SYSTEM_RESTORE_DRILL') !== '1') {
            $this->markTestSkipped('Set SYSTEM_RESTORE_DRILL=1 to run the isolated restore drill.');
        }

        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', DB::connection()->getDriverName());

        Storage::fake('local');
        Storage::fake('public');

        Config::set('backups.restore.allowed_prefixes', ['restore_', 'drill_']);
        Config::set('backups.restore.mysql_binary', null);
        Config::set('backups.database.connection', config('database.default'));

        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'system-restore-drill-' . bin2hex(random_bytes(6));

        if (!mkdir($this->temporaryDirectory, 0700, true) && !is_dir($this->temporaryDirectory)) {
            $this->fail('Unable to create the restore drill directory.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdDatabases as $database) {
            try {
                DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `' . $database . '`');
            } catch (\Throwable) {
            }
        }

        $this->createdDatabases = [];

        if (isset($this->temporaryDirectory) && is_dir($this->temporaryDirectory)) {
            $this->deleteDirectory($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_dry_run_validates_without_creating_the_target_database(): void
    {
        $archive = $this->buildArchive();
        $target = $this->targetName();

        $result = app(SystemRestoreService::class)->restore([
            'archive' => $archive,
            'target_database' => $target,
            'confirm_database' => $target,
            'dry_run' => true,
        ]);

        $this->assertTrue($result['dry_run']);
        $this->assertSame($target, $result['target_database']);
        $this->assertSame(2, $result['database_tables']);
        $this->assertFalse($this->databaseExists($target));
    }

    public function test_restore_imports_into_a_new_database_and_stages_media_into_an_absent_target(): void
    {
        $archive = $this->buildArchive();
        $target = $this->targetName();
        $mediaTarget = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'restored-media';

        $result = app(SystemRestoreService::class)->restore([
            'archive' => $archive,
            'target_database' => $target,
            'confirm_database' => $target,
            'media_target' => $mediaTarget,
            'confirm_media_target' => $mediaTarget,
        ]);

        $this->createdDatabases[] = $target;

        $this->assertFalse($result['dry_run']);
        $this->assertTrue($result['database_created']);
        $this->assertSame(2, $result['tables_restored']);
        $this->assertGreaterThan(0, $result['migrations']);
        $this->assertSame(1, $result['media_files']);
        $this->assertTrue($this->databaseExists($target));

        $tables = $this->tableNames($target);
        sort($tables);
        $this->assertSame(['drill_items', 'migrations'], $tables);

        $pdo = $this->connect($target);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM `drill_items`')->fetchColumn());
        $this->assertSame(
            'restored-row',
            (string) $pdo->query('SELECT `name` FROM `drill_items` LIMIT 1')->fetchColumn()
        );
        $this->assertSame(
            count($this->diskMigrationNames()),
            (int) $pdo->query('SELECT COUNT(*) FROM `migrations`')->fetchColumn()
        );

        $restoredMedia = $mediaTarget . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'drill' . DIRECTORY_SEPARATOR . 'asset.txt';

        $this->assertFileExists($restoredMedia);
        $this->assertSame('synthetic drill media payload', file_get_contents($restoredMedia));
        $this->assertSame([], glob($mediaTarget . '.staging-*') ?: []);
    }

    public function test_restore_accepts_an_encrypted_archive_and_removes_the_decrypted_temporary_copy(): void
    {
        $keyDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'keys';
        mkdir($keyDirectory, 0700, true);
        $keyPath = $keyDirectory . DIRECTORY_SEPARATOR . '1.key';
        file_put_contents($keyPath, random_bytes(32));

        Config::set('backups.encryption.key_id', 1);
        Config::set('backups.encryption.key_file', $keyPath);
        Config::set('backups.encryption.keys', [1 => $keyPath]);

        $plain = $this->buildArchive();
        $encrypted = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'drill.sbx';
        app(EncryptedContainer::class)->encrypt($plain, $encrypted, 1);

        $target = $this->targetName();

        $result = app(SystemRestoreService::class)->restore([
            'archive' => $encrypted,
            'target_database' => $target,
            'confirm_database' => $target,
        ]);

        $this->createdDatabases[] = $target;

        $this->assertTrue($result['encrypted']);
        $this->assertSame(1, $result['key_id']);
        $this->assertSame(2, $result['tables_restored']);
        $this->assertTrue($this->databaseExists($target));
        $this->assertSame([], glob(Storage::disk('local')->path('backups/.tmp') . DIRECTORY_SEPARATOR . '*.zip') ?: []);
    }

    public function test_existing_target_database_is_refused_and_left_untouched(): void
    {
        $archive = $this->buildArchive();
        $target = $this->targetName();

        DB::connection('mysql')->statement('CREATE DATABASE `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createdDatabases[] = $target;
        DB::connection('mysql')->statement('CREATE TABLE `' . $target . '`.`keep_me` (`id` INT NOT NULL)');

        try {
            app(SystemRestoreService::class)->restore([
                'archive' => $archive,
                'target_database' => $target,
                'confirm_database' => $target,
            ]);
            $this->fail('Expected an existing target database to be refused.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('restore_target_exists', $exception->reason);
        }

        $this->assertTrue($this->databaseExists($target));
        $this->assertSame(['keep_me'], $this->tableNames($target));
    }

    public function test_a_failed_verification_rolls_back_the_created_database_and_media_staging(): void
    {
        $archive = $this->buildArchive(3);
        $target = $this->targetName();
        $mediaTarget = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'rolled-back-media';

        try {
            app(SystemRestoreService::class)->restore([
                'archive' => $archive,
                'target_database' => $target,
                'confirm_database' => $target,
                'media_target' => $mediaTarget,
                'confirm_media_target' => $mediaTarget,
            ]);
            $this->fail('Expected the table count mismatch to fail the restore.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('restore_verification_failed', $exception->reason);
            $this->assertSame('verify', $exception->context['phase'] ?? null);
            $this->assertStringContainsString('dropped_database', (string) ($exception->context['cleanup'] ?? ''));
        }

        $this->assertFalse($this->databaseExists($target));
        $this->assertDirectoryDoesNotExist($mediaTarget);
        $this->assertSame([], glob($mediaTarget . '.staging-*') ?: []);
    }

    private function buildArchive(int $declaredTables = 2): string
    {
        $migrations = $this->diskMigrationNames();
        $this->assertGreaterThan(0, count($migrations));

        $lines = [
            '-- synthetic system restore drill snapshot',
            '-- migrations:',
        ];

        foreach ($migrations as $migration) {
            $lines[] = '-- ' . $migration;
        }

        $lines[] = 'CREATE TABLE `migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `migration` VARCHAR(255) NOT NULL, `batch` INT NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        $lines[] = 'CREATE TABLE `drill_items` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $sql = implode("\n", $lines) . "\n";

        $migrationRows = array_map(
            static fn (string $migration): string => "('" . $migration . "', 1)",
            $migrations
        );
        $sql .= 'INSERT INTO `migrations` (`migration`, `batch`) VALUES ' . implode(', ', $migrationRows) . ";\n";
        $sql .= "INSERT INTO `drill_items` (`name`) VALUES ('restored-row');\n";

        $media = 'synthetic drill media payload';

        $manifest = [
            'type' => 'system_backup',
            'schema_version' => 1,
            'archive_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'app' => [
                'name' => 'drill',
                'environment' => 'testing',
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ],
            'database' => [
                'driver' => 'mysql',
                'server_version' => '10.4',
                'method' => 'pdo_consistent_snapshot',
                'consistency' => 'synthetic_drill',
                'tables' => $declaredTables,
                'entry' => 'database/database.sql',
            ],
            'media' => [
                'disks' => ['public'],
                'files' => 1,
                'bytes' => strlen($media),
            ],
            'files' => [
                'database/database.sql' => [
                    'bytes' => strlen($sql),
                    'sha256' => hash('sha256', $sql),
                ],
                'media/public/drill/asset.txt' => [
                    'bytes' => strlen($media),
                    'sha256' => hash('sha256', $media),
                ],
            ],
            'excluded' => ['paths' => [], 'skipped' => []],
            'notes' => ['synthetic drill archive'],
        ];

        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'drill-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            $this->fail('Unable to create the synthetic drill archive.');
        }

        $zip->addFromString('database/database.sql', $sql);
        $zip->addFromString('media/public/drill/asset.txt', $media);
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (!$zip->close()) {
            $this->fail('Unable to finalize the synthetic drill archive.');
        }

        return $path;
    }

    private function targetName(): string
    {
        return 'drill_' . strtolower(bin2hex(random_bytes(6)));
    }

    private function diskMigrationNames(): array
    {
        $names = [];

        foreach (glob(database_path('migrations') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $names[] = basename($file, '.php');
        }

        sort($names);

        return $names;
    }

    private function databaseExists(string $database): bool
    {
        $statement = DB::connection('mysql')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE LOWER(SCHEMA_NAME) = LOWER(?)',
            [$database]
        );

        return $statement !== [];
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(string $database): array
    {
        return collect(DB::connection('mysql')->select(
            "SELECT table_name FROM information_schema.tables WHERE LOWER(table_schema) = LOWER(?) AND table_type = 'BASE TABLE'",
            [$database]
        ))
            ->map(fn ($row): string => (string) array_values((array) $row)[0])
            ->values()
            ->all();
    }

    private function connect(string $database): \PDO
    {
        $config = (array) config('database.connections.mysql');
        $dsn = 'mysql:host=' . (string) ($config['host'] ?? '127.0.0.1')
            . ';port=' . (string) ($config['port'] ?? '3306')
            . ';charset=utf8mb4;dbname=' . $database;

        return new \PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
