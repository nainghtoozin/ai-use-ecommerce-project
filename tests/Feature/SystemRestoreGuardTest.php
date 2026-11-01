<?php

namespace Tests\Feature;

use App\Services\Backup\BackupOperationException;
use App\Services\Backup\RestoreTargetGuard;
use App\Services\Backup\SystemRestoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemRestoreGuardTest extends TestCase
{
    use DatabaseTransactions;

    private RestoreTargetGuard $guard;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Config::set('backups.restore.allowed_prefixes', ['restore_', 'drill_']);

        $this->guard = app(RestoreTargetGuard::class);
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-guard-' . bin2hex(random_bytes(6));

        if (!mkdir($this->temporaryDirectory, 0700, true) && !is_dir($this->temporaryDirectory)) {
            $this->fail('Unable to create the restore guard test directory.');
        }
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_production_environment_is_refused_and_other_environments_pass(): void
    {
        try {
            $this->guard->assertEnvironment('production');
            $this->fail('Expected production to be refused.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('restore_production_refused', $exception->reason);
            $this->assertStringNotContainsString('password', strtolower($exception->getMessage()));
        }

        $this->guard->assertEnvironment('local');
        $this->guard->assertEnvironment('staging');
        $this->guard->assertEnvironment('testing');
    }

    public function test_target_database_names_require_the_approved_prefix_and_safe_characters(): void
    {
        $prefixes = ['restore_', 'drill_'];

        $this->assertSame('restore_2026', $this->guard->assertDatabaseName(' restore_2026 ', $prefixes));
        $this->assertSame('drill_a1', $this->guard->assertDatabaseName('drill_a1', $prefixes));

        $cases = [
            ['app_database', 'restore_target_not_allowed'],
            ['ecommerc_test', 'restore_target_not_allowed'],
            ['Restore_Upper', 'restore_target_invalid'],
            ['restore-bad', 'restore_target_invalid'],
            ['restore_x;DROP', 'restore_target_invalid'],
            ['restore_' . str_repeat('a', 60), 'restore_target_invalid'],
        ];

        foreach ($cases as [$name, $reason]) {
            try {
                $this->guard->assertDatabaseName($name, $prefixes);
                $this->fail('Expected the database name to be refused: ' . $name);
            } catch (BackupOperationException $exception) {
                $this->assertSame($reason, $exception->reason, 'Unexpected reason for ' . $name);
            }
        }
    }

    public function test_confirmation_must_repeat_the_target_exactly(): void
    {
        $this->guard->assertConfirmation('restore_alpha', 'restore_alpha');

        foreach (['', 'restore_alph', 'RESTORE_ALPHA'] as $confirmation) {
            try {
                $this->guard->assertConfirmation('restore_alpha', $confirmation);
                $this->fail('Expected the confirmation to be rejected: ' . $confirmation);
            } catch (BackupOperationException $exception) {
                $this->assertSame('restore_confirmation_mismatch', $exception->reason);
            }
        }
    }

    public function test_application_databases_are_refused(): void
    {
        $configured = strtolower((string) config('database.connections.mysql.database'));

        $this->assertNotSame('', $configured);

        try {
            $this->guard->assertNotApplicationDatabase($configured);
            $this->fail('Expected the configured application database to be refused.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('restore_target_configured_database', $exception->reason);
        }

        $this->guard->assertNotApplicationDatabase('restore_a_disposable_database');
    }

    public function test_media_target_must_be_absent_absolute_and_outside_the_application(): void
    {
        $this->assertNull($this->guard->assertMediaTarget(null, null));
        $this->assertNull($this->guard->assertMediaTarget('', null));

        $valid = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'restored-media';
        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'restored-media',
            $this->guard->assertMediaTarget($valid, $valid)
        );
        $this->assertDirectoryDoesNotExist($valid);

        $relative = 'storage' . DIRECTORY_SEPARATOR . 'restore-media';
        $insideApp = base_path('storage' . DIRECTORY_SEPARATOR . 'restore-media-guard');
        $insidePublic = public_path('restore-media-guard');
        $insideBackups = Storage::disk('local')->path('backups/restore-media-guard');
        $missingParent = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'target';

        $existing = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'existing';
        mkdir($existing, 0700, true);

        $cases = [
            [$relative, $relative, 'restore_media_target_invalid'],
            [$insideApp, $insideApp, 'restore_media_target_invalid'],
            [$insidePublic, $insidePublic, 'restore_media_target_invalid'],
            [$insideBackups, $insideBackups, 'restore_media_target_invalid'],
            [$missingParent, $missingParent, 'restore_media_target_invalid'],
            [$existing, $existing, 'restore_media_target_exists'],
            [$valid, 'wrong-path', 'restore_confirmation_mismatch'],
            [$valid, '', 'restore_confirmation_mismatch'],
        ];

        foreach ($cases as [$path, $confirmation, $reason]) {
            try {
                $this->guard->assertMediaTarget($path, $confirmation);
                $this->fail('Expected the media target to be refused: ' . $path);
            } catch (BackupOperationException $exception) {
                $this->assertSame($reason, $exception->reason, 'Unexpected reason for ' . $path);
            }
        }
    }

    public function test_restore_service_refuses_guard_violations_before_touching_anything(): void
    {
        $service = app(SystemRestoreService::class);
        $archive = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'not-a-real-archive.zip';
        file_put_contents($archive, 'PK');

        $base = [
            'archive' => $archive,
            'target_database' => 'restore_guard_probe',
            'confirm_database' => 'restore_guard_probe',
        ];

        Config::set('database.connections.restore_guard_probe', [
            'driver' => 'mysql',
            'database' => 'restore_configured_probe',
        ]);

        $cases = [
            [array_merge($base, ['target_database' => 'tenant_app', 'confirm_database' => 'tenant_app']), 'restore_target_not_allowed'],
            [array_merge($base, ['confirm_database' => 'restore_guard_probe2']), 'restore_confirmation_mismatch'],
            [array_merge($base, ['target_database' => 'restore_configured_probe', 'confirm_database' => 'restore_configured_probe']), 'restore_target_configured_database'],
            [array_merge($base, [
                'media_target' => base_path('storage/media-guard'),
                'confirm_media_target' => base_path('storage/media-guard'),
            ]), 'restore_media_target_invalid'],
        ];

        foreach ($cases as [$options, $reason]) {
            try {
                $service->restore($options);
                $this->fail('Expected the restore service to refuse: ' . $reason);
            } catch (BackupOperationException $exception) {
                $this->assertSame($reason, $exception->reason);
                $this->assertStringNotContainsString('password', strtolower($exception->getMessage()));
            }
        }

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_restore_command_requires_targets_and_confirmations_without_invoking_restore(): void
    {
        $cases = [
            [[], 'The --target-database option is required.'],
            [['--target-database' => 'restore_cmd'], 'The --confirm-database option is required'],
            [['--target-database' => 'restore_cmd', '--confirm-database' => 'restore_cmd', '--media-target' => $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'media'], 'The --confirm-media-target option is required'],
            [['--target-database' => 'restore_cmd', '--confirm-database' => 'restore_cmd', '--confirm-media-target' => 'x'], 'The --confirm-media-target option was provided without --media-target.'],
        ];

        foreach ($cases as [$options, $message]) {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('backup:restore', array_merge(['archive' => 'missing.zip'], $options));
            $output = \Illuminate\Support\Facades\Artisan::output();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString($message, $output);
        }
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
