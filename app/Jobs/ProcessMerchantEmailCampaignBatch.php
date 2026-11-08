<?php

namespace App\Jobs;

use App\Models\MerchantEmailCampaign;
use App\Models\MerchantEmailRecipient;
use App\Services\MerchantEmailCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMerchantEmailCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = MerchantEmailCampaign::find($this->campaignId);
        if (!$campaign) {
            return;
        }

        if (in_array($campaign->status, ['completed', 'failed'], true)) {
            return;
        }

        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'sending', 'started_at' => $campaign->started_at ?: now()]);
        }

        $batchSize = max(1, (int) config('merchant_email.batch_size', 10));

        $recipientIds = MerchantEmailRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        foreach ($recipientIds as $recipientId) {
            SendMerchantEmailJob::dispatch($recipientId)->onQueue(config('merchant_email.queue', 'default'));
        }

        $hasMore = MerchantEmailRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasMore) {
            self::dispatch($campaign->id)
                ->delay(now()->addSeconds((int) config('merchant_email.batch_delay_seconds', 5)))
                ->onQueue(config('merchant_email.queue', 'default'));
        }
    }
}
