<?php

namespace App\Jobs;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldInvitations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $daysOld = 30,
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->daysOld);

        $deleted = TeamInvitation::whereIn('status', ['expired', 'revoked', 'accepted'])
            ->where('updated_at', '<', $cutoff)
            ->delete();

        if ($deleted > 0) {
            Log::info("Cleaned up {$deleted} old staff invitation(s) older than {$this->daysOld} days.");
        }
    }
}
