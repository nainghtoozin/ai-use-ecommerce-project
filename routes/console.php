<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('messages:cleanup')->daily();

// ── Activity logs cleanup ──
Schedule::command('activity-logs:cleanup')->dailyAt('03:00');

// ── Database notifications retention ──
Schedule::command('notifications:prune')->dailyAt('04:00');

// ── Team invitations ──
Schedule::command('invitations:expire')->everyFifteenMinutes();
Schedule::job(new \App\Jobs\CleanupOldInvitations(30))->daily();

// ── Subscription lifecycle ──
// Process lifecycle transitions every 5 minutes during business hours,
// hourly otherwise (prevents subscriptions lingering past thresholds).
Schedule::command('subscriptions:process-expired')->everyFiveMinutes();
Schedule::command('subscriptions:send-reminders')->dailyAt('09:00');
Schedule::command('subscriptions:apply-scheduled-changes')->hourly();

// ── System backups (opt-in; disabled until configured) ──
// Requires a running queue worker and scheduler cron. The backup command uses
// its own lock; withoutOverlapping additionally guards against overlapping runs.
$backupScheduleTime = (string) config('backups.schedule.time', '02:30');
$backupHealthTime = (string) config('backups.schedule.health_time', '07:00');

if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $backupScheduleTime) !== 1) {
    $backupScheduleTime = '02:30';
}

if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $backupHealthTime) !== 1) {
    $backupHealthTime = '07:00';
}

if ((bool) config('backups.schedule.enabled', false)) {
    $backupCreateCommand = (bool) config('backups.offsite.enabled', false)
        && trim((string) config('backups.offsite.disk', '')) !== ''
        ? 'backup:create --upload'
        : 'backup:create';

    Schedule::command($backupCreateCommand)->dailyAt($backupScheduleTime)->withoutOverlapping(240);
    Schedule::command('backup:health')->dailyAt($backupHealthTime);
}

if ((bool) config('backups.retention.enabled', false) && (bool) config('backups.retention.scheduled', false)) {
    Schedule::command('backup:prune --confirm')->dailyAt('04:30');
}

// ── Merchant backup upload retention ──
// Removes expired merchant backup uploads from the private upload area. The
// command holds a cache lock and skips archives owned by an active recovery,
// so it is safe to run on every node. Requires the scheduler cron:
//   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('backup:cleanup-uploads')->hourly()->withoutOverlapping(30);
