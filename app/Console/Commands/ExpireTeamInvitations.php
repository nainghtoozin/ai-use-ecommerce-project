<?php

namespace App\Console\Commands;

use App\Models\TeamInvitation;
use Illuminate\Console\Command;

class ExpireTeamInvitations extends Command
{
    protected $signature = 'invitations:expire';
    protected $description = 'Mark expired staff invitations as expired';

    public function handle(): int
    {
        $expired = TeamInvitation::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($expired > 0) {
            $this->info("Marked {$expired} invitation(s) as expired.");
        } else {
            $this->info('No expired invitations found.');
        }

        return self::SUCCESS;
    }
}
