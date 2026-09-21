<?php

namespace Tests\Feature;

use App\Events\TestBroadcastEvent;
use App\Jobs\RetryBroadcast;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueWorkerSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_queue_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    public function test_queue_timeouts_fit_retry_window(): void
    {
        $this->assertSame('database', config('queue.connections.database.driver'));
        $this->assertSame('jobs', config('queue.connections.database.table'));

        $retryAfter = (int) config('queue.connections.database.retry_after', 90);
        $this->assertGreaterThanOrEqual(60, $retryAfter);

        $this->assertSame('failed_jobs', config('queue.failed.table'));
    }

    public function test_broadcasts_queue_processes(): void
    {
        config()->set('queue.default', 'database');

        RetryBroadcast::dispatch(new TestBroadcastEvent(1))->onQueue('broadcasts');

        $this->assertSame(1, DB::table('jobs')->where('queue', 'broadcasts')->count());

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'broadcasts']);

        $this->assertSame(0, DB::table('jobs')->where('queue', 'broadcasts')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_scheduler_is_separate_from_workers(): void
    {
        $summaries = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->getSummaryForDisplay())
            ->values()
            ->all();

        $this->assertNotEmpty($summaries);

        foreach ($summaries as $summary) {
            $this->assertStringNotContainsStringIgnoringCase('queue:work', $summary);
        }

        $this->assertTrue(
            collect($summaries)->contains(fn ($s) => str_contains($s, 'subscriptions:send-reminders'))
        );
    }
}
