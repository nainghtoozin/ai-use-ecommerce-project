<?php

namespace Tests\Feature;

use App\Services\Backup\SystemBackupAuditLogger;
use App\Services\Backup\SystemBackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\Backup\CollectingLogger;
use Tests\TestCase;

class SystemBackupCommandTest extends TestCase
{
    use DatabaseTransactions;

    private CollectingLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put('catalog/hero.txt', 'synthetic command backup asset');
        Storage::disk('public')->put('tmp/ignored.txt', 'temporary file that must not be bundled');

        Config::set('backups.media.disks', ['public']);
        Config::set('backups.database.connection', config('database.default'));
        Config::set('backups.database.dump_binary', null);

        $this->logger = new CollectingLogger();
        $this->app->instance(SystemBackupAuditLogger::class, new SystemBackupAuditLogger($this->logger));
    }

    public function test_command_creates_a_private_backup_and_records_an_audit_entry(): void
    {
        $exitCode = Artisan::call('backup:create', ['--label' => 'sprint-4b5g']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('System backup created.', $output);
        $this->assertStringContainsString('Path: backups/system-backup-', $output);
        $this->assertStringContainsString('SHA-256: ', $output);

        $archives = Storage::disk('local')->files('backups');
        $this->assertCount(1, $archives);
        $this->assertStringStartsWith('backups/system-backup-', $archives[0]);
        $this->assertStringEndsWith('.zip', $archives[0]);
        $this->assertGreaterThan(0, Storage::disk('local')->size($archives[0]));

        $this->assertFalse(Storage::disk('public')->exists($archives[0]));
        $this->assertSame([], Storage::disk('public')->files('backups'));
        $this->assertSame('local', Config::get('backups.disk'));

        $this->assertCount(1, $this->logger->records);
        $this->assertSame('info', $this->logger->records[0]['level']);

        $context = $this->logger->lastContext();
        $this->assertNotNull($context);

        $expectedKeys = [
            'bytes',
            'container_version',
            'database_driver',
            'database_method',
            'database_tables',
            'disk',
            'duration_ms',
            'encrypted',
            'environment',
            'event',
            'key_id',
            'label',
            'media_bytes',
            'media_files',
            'occurred_at',
            'path',
            'payload_sha256',
            'sha256',
            'status',
        ];
        $this->assertSame($expectedKeys, collect(array_keys($context))->sort()->values()->all());

        $this->assertSame('system_backup.created', $context['event']);
        $this->assertSame('success', $context['status']);
        $this->assertSame('sprint-4b5g', $context['label']);
        $this->assertSame('local', $context['disk']);
        $this->assertSame($archives[0], $context['path']);
        $this->assertSame(Storage::disk('local')->size($archives[0]), $context['bytes']);
        $this->assertSame(hash('sha256', (string) Storage::disk('local')->get($archives[0])), $context['sha256']);
        $this->assertSame('pdo_consistent_snapshot', $context['database_method']);
        $this->assertSame('mysql', $context['database_driver']);
        $this->assertSame(1, $context['media_files']);
        $this->assertFalse($context['encrypted']);
        $this->assertNull($context['container_version']);
        $this->assertNull($context['key_id']);
        $this->assertNull($context['payload_sha256']);
        $this->assertIsInt($context['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $context['duration_ms']);

        $this->assertStringContainsString('system backup created: backups/system-backup-', (string) $this->logger->lastMessage());
    }

    public function test_command_failure_is_audited_and_does_not_leak_credentials(): void
    {
        $this->mock(SystemBackupService::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new RuntimeException(
                'Unable to connect using mysql://root:sup3r-secret@127.0.0.1/ecommerc_db2 with password=sup3r-secret'
            ));
        });

        $exitCode = Artisan::call('backup:create', ['--label' => 'nightly']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('System backup failed', $output);
        $this->assertStringNotContainsString('sup3r-secret', $output);
        $this->assertStringNotContainsString('root:', $output);

        $this->assertSame([], Storage::disk('local')->files('backups'));

        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);

        $context = $this->logger->lastContext();
        $this->assertNotNull($context);

        $this->assertSame(
            ['duration_ms', 'environment', 'error_class', 'error_message', 'event', 'label', 'occurred_at', 'status'],
            collect(array_keys($context))->sort()->values()->all()
        );

        $this->assertSame('system_backup.failed', $context['event']);
        $this->assertSame('failure', $context['status']);
        $this->assertSame('nightly', $context['label']);
        $this->assertSame(RuntimeException::class, $context['error_class']);
        $this->assertStringContainsString('[redacted]', (string) $context['error_message']);
        $this->assertStringContainsString('Unable to connect', (string) $context['error_message']);

        $encoded = (string) json_encode($context);
        $this->assertStringNotContainsString('sup3r-secret', $encoded);
        $this->assertStringNotContainsString('root:', $encoded);
        $this->assertStringContainsString('[redacted]', (string) $this->logger->lastMessage() . $encoded);
    }

    public function test_audit_log_redacts_known_credential_shapes_and_truncates_errors(): void
    {
        $audit = new SystemBackupAuditLogger($this->logger);
        $audit->failure(
            new RuntimeException(
                'dsn=mysql://merchant:hunter2@db.internal/app password=hunter2 --password=hunter2 '
                . 'DB_PASSWORD=hunter2 api_key=abc123 access_token=tok-999 ' . str_repeat('x', 600)
            ),
            12,
            null
        );

        $context = $this->logger->lastContext();
        $this->assertNotNull($context);

        $encoded = strtolower((string) json_encode($context));
        $message = (string) $this->logger->lastMessage();

        foreach (['hunter2', 'abc123', 'tok-999', 'merchant:', 'db_password'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
            $this->assertStringNotContainsString($secret, $message);
        }

        $this->assertSame('failure', $context['status']);
        $this->assertNull($context['label']);
        $this->assertLessThanOrEqual(400, mb_strlen((string) $context['error_message']));
        $this->assertCount(1, $this->logger->records);
    }

    public function test_command_rejects_an_unsafe_or_oversized_label_without_creating_a_backup(): void
    {
        $exitCode = Artisan::call('backup:create', ['--label' => str_repeat('a', 101)]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('1-100 printable characters', Artisan::output());
        $this->assertSame([], Storage::disk('local')->files('backups'));

        $context = $this->logger->lastContext();
        $this->assertNotNull($context);
        $this->assertSame('system_backup.failed', $context['event']);
        $this->assertNull($context['label']);
        $this->assertStringContainsString('printable characters', (string) $context['error_message']);
    }

    public function test_backup_audit_channel_is_a_private_file_channel(): void
    {
        $this->assertSame('backups', Config::get('backups.log_channel'));

        $channel = Config::get('logging.channels.' . Config::get('backups.log_channel'));
        $this->assertIsArray($channel);
        $this->assertSame('single', $channel['driver']);
        $this->assertSame(storage_path('logs/system-backups.log'), $channel['path']);
        $this->assertFalse(str_starts_with(str_replace('\\', '/', $channel['path']), str_replace('\\', '/', public_path()) . '/'));
        $this->assertFalse($channel['replace_placeholders']);
    }
}
