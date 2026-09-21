<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPruneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPruneSchema();
        PlatformSetting::clearCache();
    }

    public function test_old_notifications_are_removed(): void
    {
        $this->seedRetentionDays(30);

        $this->makeRow(now()->subDays(31), null, 11);
        $this->makeRow(now()->subDays(60), now()->subDays(31), 12);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')
            ->whereIn('notifiable_id', [11, 12])
            ->count());
    }

    public function test_recent_notifications_remain(): void
    {
        $this->seedRetentionDays(30);

        $this->makeRow(now()->subDay(), null, 21);
        $this->makeRow(now()->subDays(29), now()->subDays(2), 22);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 21)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 22)->count());
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->seedRetentionDays(30);

        $this->makeRow(now()->subDays(90), null, 31);

        $this->artisan('notifications:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 31)->count());
    }

    public function test_zero_retention_disables_pruning(): void
    {
        $this->seedRetentionDays(0);

        $this->makeRow(now()->subDays(365), null, 41);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 41)->count());
    }

    public function test_empty_table_is_safe(): void
    {
        $this->seedRetentionDays(30);

        DB::table('notifications')->delete();

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_command_is_registered_and_scheduled(): void
    {
        $this->assertContains('notifications:prune', array_keys(Artisan::all()));

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->getSummaryForDisplay())
            ->filter(fn ($summary) => str_contains($summary, 'notifications:prune'));

        $this->assertTrue($scheduled->isNotEmpty());
    }

    private function seedRetentionDays(int $days): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create(['notification_retention_days' => $days]);
        PlatformSetting::clearCache();
    }

    private function makeRow($createdAt, $readAt, int $notifiableId): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SubscriptionExpired',
            'notifiable_type' => 'App\\Models\\Account',
            'notifiable_id' => $notifiableId,
            'data' => json_encode(['title' => 'Old']),
            'read_at' => $readAt?->toDateTimeString(),
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
            'tenant_id' => null,
        ]);
    }

    private function createPruneSchema(): void
    {
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('notifications', 'tenant_id')) {
            Schema::table('notifications', function ($table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->unsignedInteger('notification_retention_days')->default(90);
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('platform_settings', 'notification_retention_days')) {
            Schema::table('platform_settings', function ($table) {
                $table->unsignedInteger('notification_retention_days')->default(90);
            });
        }
    }
}
