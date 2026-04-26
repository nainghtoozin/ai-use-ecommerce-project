<?php

namespace App\Console\Commands;

use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteOldMessages extends Command
{
    protected $signature = 'messages:cleanup';
    protected $description = 'Delete chat messages older than 3 days';

    public function handle(): int
    {
        $cutoffDate = Carbon::now()->subDays(3);
        $deleted = Message::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deleted} messages older than 3 days.");

        return Command::SUCCESS;
    }
}