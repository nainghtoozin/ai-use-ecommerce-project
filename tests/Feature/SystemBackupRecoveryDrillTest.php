<?php

namespace Tests\Feature;

use App\Services\Backup\SystemBackupService;
use App\Services\Backup\SystemBackupValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\MerchantRestoreDrill\DrillFixture;
use Tests\Support\SystemRestoreDrill\ArchiveRestorer;
use Tests\TestCase;

class SystemBackupRecoveryDrillTest extends TestCase
{
    private const SOURCE_DATABASE = 'ecommerc_backup_src_4b5f';
    private const TARGET_DATABASE = 'ecommerc_backup_dst_4b5f';
    private const TARGET_DATABASE_DUMP = 'ecommerc_backup_dst_4b5f_dump';

    private ?DrillFixture $fixture = null;
    private ?string $restoreDir = null;

    /** @var array<int, string> */
    private array $createdDatabases = [];

    /** @var array<int, string> */
    private array $archivePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('SYSTEM_RESTORE_DRILL') !== '1') {
            $this->markTestSkipped('Set SYSTEM_RESTORE_DRILL=1 to run the isolated system backup recovery drill.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'mysql']);

        foreach (['backup_drill_target', 'backup_drill_target_dump', 'backup_drill_source'] as $connection) {
            try {
                DB::disconnect($connection);
                DB::purge($connection);
            } catch (\Throwable) {
            }
        }

        foreach ($this->createdDatabases as $database) {
            try {
                DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `' . $database . '`');
            } catch (\Throwable) {
            }
        }

        $this->createdDatabases = [];

        if ($this->restoreDir !== null) {
            $this->deleteDirectory($this->restoreDir);
        }

        foreach ($this->archivePaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->fixture?->cleanupAsset();

        parent::tearDown();
    }

    public function test_system_backup_restores_database_and_media_into_isolated_targets(): void
    {
        $this->assertSafeSource();

        config([
            'identity.use_accounts' => false,
            'filesystems.default' => 'public',
            'backups.media.disks' => ['public'],
            'backups.database.dump_binary' => null,
        ]);

        Storage::fake('public');
        Storage::fake('local');

        $this->fixture = new DrillFixture();
        $this->createSourceDatabase();
        $this->fixture->create();

        $source = DB::connection('backup_drill_source');
        $this->assertSame(self::SOURCE_DATABASE, $source->getDatabaseName());
        $this->assertSame(1, $source->table('tenants')->where('id', $this->fixture->tenantA)->count());

        config(['backups.database.connection' => 'backup_drill_source']);

        $service = app(SystemBackupService::class);
        $snapshot = $service->create();
        $this->archivePaths[] = $snapshot['absolute'];

        $this->assertFileExists($snapshot['absolute']);
        $this->assertSame('pdo_consistent_snapshot', $snapshot['manifest']['database']['method']);
        $this->assertSame(self::SOURCE_DATABASE, $source->getDatabaseName());

        $validator = app(SystemBackupValidator::class);
        $restorer = app(ArchiveRestorer::class);

        $this->restoreDir = $this->createRestoreDirectory();
        $validation = $validator->validate($snapshot['absolute'], $this->restoreDir);
        $this->assertSame($snapshot['manifest']['files'], $validation['files']);

        [$targetConnection, $targetConfig] = $this->provisionTarget('backup_drill_target', self::TARGET_DATABASE);
        $restorer->restoreDatabase($snapshot['absolute'], $targetConfig, $validation);

        $this->assertRestoredDatabase($targetConnection);

        try {
            $restorer->restoreDatabase($snapshot['absolute'], $targetConfig, $validation);
            $this->fail('The restorer must refuse a non-empty target database.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to restore', $exception->getMessage());
        }

        $this->fixture->cleanupAsset();
        $this->assertFileDoesNotExist($this->fixture->assetAbsolute);
        $this->assertFalse(Storage::disk('public')->exists($this->fixture->assetPath));

        $restoredMedia = $restorer->restoreMedia($snapshot['absolute'], $this->restoreDir, $snapshot['manifest'], $validation);
        $this->assertRestoredMedia($restoredMedia, $snapshot);

        $binary = $this->mysqldumpBinary();

        if ($binary === null) {
            $this->markTestIncomplete('mysqldump is unavailable; the mysqldump backup path remains unverified.');
        }

        config(['backups.database.dump_binary' => $binary]);
        $dumpArchive = $service->create();
        $this->archivePaths[] = $dumpArchive['absolute'];

        $this->assertSame('mysqldump', $dumpArchive['manifest']['database']['method']);

        $dumpValidation = $validator->validate($dumpArchive['absolute']);
        [$dumpConnection, $dumpConfig] = $this->provisionTarget('backup_drill_target_dump', self::TARGET_DATABASE_DUMP);
        $restorer->restoreDatabase($dumpArchive['absolute'], $dumpConfig, $dumpValidation);

        $this->assertRestoredDatabase($dumpConnection);
    }

    private function assertRestoredDatabase(string $connection): void
    {
        $source = DB::connection('backup_drill_source');
        $target = DB::connection($connection);

        $sourceTables = $this->tableNames($source);
        $targetTables = $this->tableNames($target);
        sort($sourceTables);
        sort($targetTables);

        $this->assertSame($sourceTables, $targetTables);
        $this->assertGreaterThan(0, $source->table('migrations')->count());
        $this->assertSame($source->table('migrations')->count(), $target->table('migrations')->count());

        foreach ($sourceTables as $table) {
            $this->assertSame(
                $source->table($table)->count(),
                $target->table($table)->count(),
                'Row count mismatch after restore for table ' . $table
            );
        }

        $order = $target->table('orders')->where('id', $this->fixture->order)->first();
        $this->assertNotNull($order);
        $this->assertSame($this->fixture->tenantA, (int) $order->tenant_id);
        $this->assertSame($this->fixture->customerA, (int) $order->user_id);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertSame(53800.0, (float) $order->total_amount);
        $this->assertSame(2, $target->table('order_items')->where('order_id', $order->id)->count());
        $this->assertSame(1, $target->table('order_coupon')->where('order_id', $order->id)->count());

        $product = $target->table('products')->where('id', $this->fixture->productSimple)->first();
        $this->assertNotNull($product);
        $this->assertSame($this->fixture->categoryChild, (int) $product->category_id);
        $this->assertSame($this->fixture->brand, (int) $product->brand_id);
        $this->assertSame($this->fixture->unit, (int) $product->unit_id);
        $this->assertSame(7, (int) $product->stock);

        $variant = $target->table('product_variants')->where('id', $this->fixture->variant)->first();
        $this->assertNotNull($variant);
        $this->assertSame($this->fixture->productVariable, (int) $variant->product_id);
        $this->assertSame(4, (int) $variant->stock);

        $combo = $target->table('product_combos')->where('product_id', $this->fixture->productCombo)->first();
        $this->assertNotNull($combo);
        $this->assertSame($this->fixture->productSimple, (int) $combo->combo_product_id);

        $this->assertSame(
            7,
            (int) $target->table('stock_movements')
                ->where('tenant_id', $this->fixture->tenantA)
                ->where('product_id', $this->fixture->productSimple)
                ->whereNull('product_variant_id')
                ->sum('quantity')
        );
        $this->assertSame(
            4,
            (int) $target->table('stock_movements')
                ->where('tenant_id', $this->fixture->tenantA)
                ->where('product_variant_id', $this->fixture->variant)
                ->sum('quantity')
        );

        $child = $target->table('categories')->where('id', $this->fixture->categoryChild)->first();
        $this->assertNotNull($child);
        $this->assertSame($this->fixture->categoryParent, (int) $child->parent_id);
        $this->assertTrue($target->table('categories')->where('id', $child->parent_id)->exists());

        $storefront = $target->table('storefronts')->where('id', $this->fixture->storefront)->first();
        $this->assertNotNull($storefront);
        $this->assertSame($this->fixture->revision, (int) $storefront->published_revision_id);
        $this->assertTrue($target->table('storefront_revisions')->where('id', $storefront->published_revision_id)->exists());

        $this->assertSame(
            'SAFE-' . $this->fixture->marker,
            $target->table('settings')->where('tenant_id', $this->fixture->tenantA)->where('key', 'restore_drill_safe_setting')->value('value')
        );
        $this->assertSame(
            'SECRET-' . $this->fixture->marker,
            $target->table('settings')->where('tenant_id', $this->fixture->tenantA)->where('key', 'smtp_password')->value('value')
        );
        $this->assertSame(
            'Drill Store ' . $this->fixture->marker,
            $target->table('website_infos')->where('tenant_id', $this->fixture->tenantA)->value('site_name')
        );

        $this->assertSame(
            1,
            $target->table('products')->where('name', 'Drill Tenant B Product ' . $this->fixture->marker)->where('tenant_id', $this->fixture->tenantB)->count()
        );
    }

    /**
     * @param array<string, array{path: string, bytes: int, sha256: string}> $restored
     * @param array<string, mixed> $archive
     */
    private function assertRestoredMedia(array $restored, array $archive): void
    {
        $entry = 'media/public/' . $this->fixture->assetPath;

        $this->assertArrayHasKey($entry, $restored);
        $this->assertCount(1, $restored);

        $file = $restored[$entry];
        $this->assertFileExists($file['path']);
        $this->assertSame('Synthetic restore drill asset ' . $this->fixture->marker, file_get_contents($file['path']));
        $this->assertSame((string) $archive['manifest']['files'][$entry]['sha256'], $file['sha256']);
        $this->assertSame((int) $archive['manifest']['files'][$entry]['bytes'], $file['bytes']);
        $this->assertStringStartsWith($this->normalizePath((string) $this->restoreDir), $this->normalizePath($file['path']));

        $restoredFiles = $this->filesIn((string) $this->restoreDir);
        $this->assertCount(1, $restoredFiles);
        $this->assertSame($this->normalizePath($file['path']), $this->normalizePath($restoredFiles[0]));
    }

    private function createSourceDatabase(): void
    {
        $this->assertSafeDrillDatabase(self::SOURCE_DATABASE);
        $this->assertFalse($this->databaseExists(self::SOURCE_DATABASE), 'The drill source database already exists; refusing to reuse it.');

        DB::connection('mysql')->statement('CREATE DATABASE `' . self::SOURCE_DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createdDatabases[] = self::SOURCE_DATABASE;

        config(['database.connections.backup_drill_source' => $this->connectionConfig(self::SOURCE_DATABASE)]);
        DB::purge('backup_drill_source');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'backup_drill_source',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        config(['database.default' => 'backup_drill_source']);
        DB::purge();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function provisionTarget(string $connection, string $database): array
    {
        $this->assertSafeDrillDatabase($database);
        $this->assertFalse($this->databaseExists($database), 'The drill target database already exists; refusing to overwrite it.');

        DB::connection('mysql')->statement('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createdDatabases[] = $database;

        $config = $this->connectionConfig($database);
        config(['database.connections.' . $connection => $config]);
        DB::purge($connection);

        return [$connection, $config];
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(string $database): array
    {
        $config = (array) config('database.connections.mysql');
        $config['database'] = $database;

        return $config;
    }

    private function assertSafeSource(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('ecommerc_test', DB::connection()->getDatabaseName());
    }

    private function assertSafeDrillDatabase(string $database): void
    {
        $this->assertStringStartsWith('ecommerc_backup_', $database);
        $this->assertNotSame((string) config('database.connections.mysql.database'), $database);
        $this->assertNotSame('ecommerc_test', $database);
        $this->assertNotSame('ecommerc_db2', $database);
    }

    private function databaseExists(string $database): bool
    {
        return collect(DB::connection('mysql')->select('SHOW DATABASES'))
            ->map(fn ($row) => (string) array_values((array) $row)[0])
            ->contains($database);
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(\Illuminate\Database\ConnectionInterface $connection): array
    {
        return collect($connection->select('SHOW TABLES'))
            ->map(fn ($row) => (string) array_values((array) $row)[0])
            ->values()
            ->all();
    }

    private function createRestoreDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'system-restore-drill-' . Str::lower(Str::random(10));

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the media restore directory.');
        }

        return $directory;
    }

    /**
     * @return array<int, string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
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

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function mysqldumpBinary(): ?string
    {
        $candidates = [
            (string) config('backups.database.dump_binary'),
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
