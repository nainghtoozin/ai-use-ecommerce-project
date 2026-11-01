<?php

namespace Tests\Feature;

use App\Services\Backup\BackupOperationException;
use App\Services\Backup\BackupValidationException;
use App\Services\Backup\SystemBackupLock;
use App\Services\Backup\SystemBackupPreflight;
use App\Services\Backup\SystemBackupService;
use App\Services\Backup\SystemBackupValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBackupHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put('catalog/hero.txt', 'synthetic hardening asset');
        Storage::disk('public')->put('tmp/ignored.txt', 'temporary file that must not be bundled');

        Config::set('backups.media.disks', ['public']);
        Config::set('backups.database.connection', config('database.default'));
        Config::set('backups.database.dump_binary', null);
        Config::set('backups.disk_space.safety_margin', 1.5);
        Config::set('backups.disk_space.min_free_bytes', 1048576);
    }

    public function test_preflight_fails_safely_when_free_space_is_below_the_safety_margin(): void
    {
        Config::set('backups.disk_space.min_free_bytes', 1000000000000000);
        $tempBefore = $this->tempDumps();

        try {
            app(SystemBackupService::class)->create();
            $this->fail('Expected the disk space preflight to refuse the backup.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('insufficient_disk_space', $exception->reason);
            $this->assertStringContainsString('Insufficient free space for a system backup', $exception->getMessage());
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]:[\\\\\/]/', $exception->getMessage());
        }

        $this->assertSame([], $this->archiveFiles());
        $this->assertSame($tempBefore, $this->tempDumps());

        $lock = new SystemBackupLock();
        $lock->acquire(Storage::disk('local')->path('backups'));
        $lock->release();

        $this->assertSame([], $this->archiveFiles());
    }

    public function test_preflight_fails_closed_when_free_space_cannot_be_checked(): void
    {
        try {
            app(SystemBackupPreflight::class)->check("invalid\0target");
            $this->fail('Expected the disk space preflight to fail closed.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('disk_space_unknown', $exception->reason);
            $this->assertStringContainsString('cannot be determined', $exception->getMessage());
        }
    }

    public function test_post_creation_verification_failure_removes_only_the_new_archive(): void
    {
        Storage::disk('local')->put('backups/previous.zip', 'previous archive');
        $tempBefore = $this->tempDumps();

        $this->mock(SystemBackupValidator::class, function ($mock): void {
            $mock->shouldReceive('validate')->once()->andThrow(new BackupValidationException('tampered', 'checksum_mismatch'));
        });

        try {
            app(SystemBackupService::class)->create();
            $this->fail('Expected post-creation verification to reject the new archive.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('post_creation_verification_failed', $exception->reason);
            $this->assertStringContainsString('checksum_mismatch', $exception->getMessage());
        }

        $this->assertSame(['backups/previous.zip'], $this->archiveFiles());
        $this->assertSame('previous archive', Storage::disk('local')->get('backups/previous.zip'));
        $this->assertSame($tempBefore, $this->tempDumps());

        $lock = new SystemBackupLock();
        $lock->acquire(Storage::disk('local')->path('backups'));
        $lock->release();
    }

    public function test_lock_contention_is_refused_and_the_lock_is_released_after_success(): void
    {
        Storage::disk('local')->put('backups/previous.zip', 'previous archive');

        $lock = new SystemBackupLock();
        $lock->acquire(Storage::disk('local')->path('backups'));

        try {
            app(SystemBackupService::class)->create();
            $this->fail('Expected a concurrent backup operation to be refused.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('backup_in_progress', $exception->reason);
            $this->assertStringContainsString('already running', $exception->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(['backups/previous.zip'], $this->archiveFiles());

        $result = app(SystemBackupService::class)->create();

        $this->assertFileExists($result['absolute']);
        $this->assertCount(2, $this->archiveFiles());
        $this->assertSame($result['sha256'], hash_file('sha256', $result['absolute']));
        $this->assertSame('previous archive', Storage::disk('local')->get('backups/previous.zip'));

        $reacquired = new SystemBackupLock();
        $reacquired->acquire(Storage::disk('local')->path('backups'));
        $reacquired->release();
    }

    public function test_successful_backup_is_verified_and_keeps_the_lock_out_of_the_archive_listing(): void
    {
        $result = app(SystemBackupService::class)->create();

        $this->assertSame([$result['path']], $this->archiveFiles());
        $this->assertTrue(Storage::disk('local')->exists('backups/.locks/system-backup.lock'));
        $this->assertSame('system_backup', $result['manifest']['type']);

        $validation = app(SystemBackupValidator::class)->validate($result['absolute']);
        $this->assertSame($result['manifest']['files'], $validation['files']);
    }

    /**
     * @return array<int, string>
     */
    private function archiveFiles(): array
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(static fn (string $path): bool => str_ends_with($path, '.zip'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function tempDumps(): array
    {
        $files = glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'system-backup-db-*') ?: [];
        sort($files);

        return $files;
    }
}
