<?php

namespace App\Jobs;

use App\Mail\Platform\MerchantCommunicationMail;
use App\Models\MerchantEmailCampaign;
use App\Models\MerchantEmailRecipient;
use App\Services\MerchantEmailCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class SendMerchantEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff = 60;
    public int $timeout;

    public function __construct(public int $recipientId)
    {
        $this->tries = (int) config('merchant_email.max_attempts', 3);
        $this->timeout = (int) config('merchant_email.job_timeout', 90);
        $this->backoff = 60;
    }

    public function handle(): void
    {
        $recipient = null;
        $shouldSend = false;

        DB::transaction(function () use (&$recipient, &$shouldSend) {
            $recipient = MerchantEmailRecipient::lockForUpdate()->find($this->recipientId);
            if (!$recipient) {
                return;
            }

            if ($recipient->status === 'sent') {
                return;
            }

            $leaseMinutes = (int) config('merchant_email.processing_lease_minutes', 5);
            if ($recipient->status === 'processing' && $recipient->updated_at && $recipient->updated_at->gt(now()->subMinutes($leaseMinutes))) {
                return;
            }

            $recipient->update([
                'status' => 'processing',
                'attempts' => $recipient->attempts + 1,
            ]);
            $shouldSend = true;
        });

        if (!$recipient) {
            return;
        }

        if (!$shouldSend) {
            return;
        }

        $campaign = $recipient->campaign;
        if (!$campaign) {
            app(MerchantEmailCommunicationService::class)->markCampaignCompletion($recipient->campaign_id);
            return;
        }

        $rateKey = 'merchant-email-sends';
        $maxPerMinute = (int) config('merchant_email.rate_limit_per_minute', 30);
        if (RateLimiter::tooManyAttempts($rateKey, $maxPerMinute)) {
            $this->release(10);
            return;
        }
        RateLimiter::hit($rateKey, 60);

        try {
            $mail = new MerchantCommunicationMail(
                subjectText: $campaign->subject,
                messageText: $campaign->message,
                emailType: $campaign->email_type,
                recipientName: $recipient->recipient_name,
                replyToAddress: $campaign->reply_to,
            );

            Mail::to($recipient->recipient_email)->send($mail);

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('Merchant email send failed', [
                'recipient_id' => $recipient->id,
                'campaign_id' => $campaign->id,
            ]);

            throw $e;
        }

        app(MerchantEmailCommunicationService::class)->markCampaignCompletion($campaign->id);
    }

    public function failed(\Throwable $e): void
    {
        $recipient = MerchantEmailRecipient::find($this->recipientId);
        if ($recipient && $recipient->status !== 'sent') {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => substr($e->getMessage(), 0, 1000),
            ]);
        }

        if ($recipient) {
            app(MerchantEmailCommunicationService::class)->markCampaignCompletion($recipient->campaign_id);
        }
    }
}
