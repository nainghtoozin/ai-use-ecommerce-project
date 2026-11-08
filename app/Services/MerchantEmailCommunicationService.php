<?php

namespace App\Services;

use App\Jobs\ProcessMerchantEmailCampaignBatch;
use App\Jobs\SendMerchantEmailJob;
use App\Mail\Platform\MerchantCommunicationMail;
use App\Models\Account;
use App\Models\MerchantEmailCampaign;
use App\Models\MerchantEmailRecipient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MerchantEmailCommunicationService
{
    public const EMAIL_TYPES = [
        'maintenance' => 'Maintenance Announcement',
        'feature_update' => 'Feature Update',
        'important_notice' => 'Important Notice',
        'account_warning' => 'Account Warning',
        'subscription_notice' => 'Subscription Notice',
        'general' => 'General Message',
    ];

    public const RECIPIENT_MODES = ['all', 'selected', 'single'];

    public const DEFAULT_STATUSES = ['active', 'trialing'];

    public const ALL_STATUSES = ['active', 'trialing', 'pending', 'suspended', 'expired'];

    public function emailTypes(): array
    {
        return self::EMAIL_TYPES;
    }

    public function tenantStatuses(): array
    {
        return self::ALL_STATUSES;
    }

    public function resolveOwner(Tenant $tenant): array
    {
        $name = $tenant->name;
        $email = $tenant->email;

        if (config('identity.use_accounts')) {
            $account = Account::whereHas('memberships', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                    ->where('is_owner', true)
                    ->where('status', 'active');
            })->first();

            if (!$account) {
                $account = Account::whereHas('memberships', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id)
                        ->where('status', 'active');
                })->first();
            }

            if ($account) {
                $membership = $account->memberships()->where('tenant_id', $tenant->id)->first();
                if ($membership && $membership->merchantProfile && $membership->merchantProfile->business_name) {
                    $name = $membership->merchantProfile->business_name;
                } else {
                    $name = $account->name ?: $name;
                }
                $email = $account->email ?: $email;
            }
        } else {
            $user = User::where('tenant_id', $tenant->id)
                ->where('is_owner', true)
                ->first();

            if (!$user) {
                $user = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                    ->first();
            }

            if ($user) {
                $name = $user->name ?: $name;
                $email = $user->email ?: $email;
            }
        }

        $valid = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'recipient_name' => $name,
            'recipient_email' => $email,
            'valid' => (bool) $valid,
            'tenant_status' => $tenant->status,
        ];
    }

    public function matchingTenantIds(?string $search, ?string $status): array
    {
        return Tenant::query()
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->pluck('id')
            ->all();
    }

    public function resolveAll(array $statuses): array
    {
        $statuses = array_values(array_intersect($statuses, self::ALL_STATUSES));
        if (empty($statuses)) {
            $statuses = self::DEFAULT_STATUSES;
        }

        $tenants = Tenant::query()
            ->whereIn('status', $statuses)
            ->orderBy('name')
            ->get();

        $recipients = [];
        $excluded = [];
        foreach ($tenants as $tenant) {
            $resolved = $this->resolveOwner($tenant);
            if ($resolved['valid']) {
                $recipients[] = $resolved;
            } else {
                $resolved['reason'] = 'Missing or invalid owner email';
                $excluded[] = $resolved;
            }
        }

        return ['recipients' => $recipients, 'excluded' => $excluded];
    }

    public function resolveSelected(array $tenantIds): array
    {
        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->orderBy('name')
            ->get();

        $recipients = [];
        $excluded = [];
        foreach ($tenants as $tenant) {
            $resolved = $this->resolveOwner($tenant);
            if ($resolved['valid']) {
                $recipients[] = $resolved;
            } else {
                $resolved['reason'] = 'Missing or invalid owner email';
                $excluded[] = $resolved;
            }
        }

        return ['recipients' => $recipients, 'excluded' => $excluded];
    }

    public function resolveSingle(int $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        $resolved = $this->resolveOwner($tenant);

        if ($resolved['valid']) {
            return ['recipients' => [$resolved], 'excluded' => []];
        }

        $resolved['reason'] = 'Missing or invalid owner email';
        return ['recipients' => [], 'excluded' => [$resolved]];
    }

    public function preview(array $data): array
    {
        $mode = $data['recipient_mode'];
        if ($mode === 'all') {
            $result = $this->resolveAll($data['statuses'] ?? self::DEFAULT_STATUSES);
        } elseif ($mode === 'selected') {
            if (!empty($data['select_all'])) {
                $ids = $this->matchingTenantIds($data['search'] ?? null, $data['status'] ?? null);
                $result = $this->resolveSelected($ids);
            } else {
                $result = $this->resolveSelected($data['tenant_ids'] ?? []);
            }
        } else {
            $result = $this->resolveSingle((int) ($data['tenant_id'] ?? 0));
        }

        return [
            'recipient_count' => count($result['recipients']),
            'excluded_count' => count($result['excluded']),
            'recipients' => $result['recipients'],
            'excluded' => $result['excluded'],
        ];
    }

    public function createCampaign(array $data, $user): MerchantEmailCampaign
    {
        $mode = $data['recipient_mode'];

        if ($mode === 'all') {
            $result = $this->resolveAll($data['statuses'] ?? self::DEFAULT_STATUSES);
        } elseif ($mode === 'selected') {
            if (!empty($data['select_all'])) {
                $ids = $this->matchingTenantIds($data['search'] ?? null, $data['status'] ?? null);
                $result = $this->resolveSelected($ids);
            } else {
                $result = $this->resolveSelected($data['tenant_ids'] ?? []);
            }
        } else {
            $result = $this->resolveSingle((int) ($data['tenant_id'] ?? 0));
        }

        $recipients = $result['recipients'];
        if (empty($recipients)) {
            throw new \RuntimeException('No eligible recipients with valid email addresses.');
        }

        $filters = null;
        if ($mode === 'all') {
            $filters = ['statuses' => $data['statuses'] ?? self::DEFAULT_STATUSES];
        }

        $campaign = DB::transaction(function () use ($data, $user, $recipients, $mode, $filters) {
            $campaign = MerchantEmailCampaign::create([
                'created_by' => $user?->id,
                'subject' => $data['subject'],
                'email_type' => $data['email_type'],
                'message' => $data['message'],
                'recipient_mode' => $mode,
                'recipient_count' => count($recipients),
                'status' => 'queued',
                'reply_to' => $data['reply_to'] ?? null,
                'filters' => $filters,
                'started_at' => now(),
            ]);

            foreach ($recipients as $recipient) {
                MerchantEmailRecipient::firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $recipient['tenant_id'],
                        'recipient_email' => $recipient['recipient_email'],
                    ],
                    [
                        'recipient_name' => $recipient['recipient_name'],
                        'status' => 'pending',
                    ]
                );
            }

            return $campaign;
        });

        $this->dispatchCampaign($campaign);

        return $campaign;
    }

    public function dispatchCampaign(MerchantEmailCampaign $campaign): void
    {
        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'sending', 'started_at' => $campaign->started_at ?: now()]);
        }

        ProcessMerchantEmailCampaignBatch::dispatch($campaign->id)
            ->onQueue(config('merchant_email.queue', 'default'));
    }

    public function retryFailed(MerchantEmailCampaign $campaign): int
    {
        $failed = $campaign->recipients()->where('status', 'failed')->get();

        foreach ($failed as $recipient) {
            $recipient->update([
                'status' => 'pending',
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        }

        $count = $failed->count();

        if ($count === 0) {
            $this->markCampaignCompletion($campaign->id);
            return 0;
        }

        if ($count > 0) {
            $campaign->update(['status' => 'sending', 'completed_at' => null]);
            foreach ($failed->chunk(50) as $chunk) {
                foreach ($chunk as $recipient) {
                    dispatch(new SendMerchantEmailJob($recipient->id));
                }
            }
        }

        return $count;
    }

    public function stats(): array
    {
        return [
            'eligible_merchants' => Tenant::query()->get()->filter(function (Tenant $tenant) {
                return $this->resolveOwner($tenant)['valid'];
            })->count(),
            'total_campaigns' => MerchantEmailCampaign::count(),
            'total_sent' => MerchantEmailRecipient::where('status', 'sent')->count(),
            'pending' => MerchantEmailRecipient::whereIn('status', ['pending', 'processing'])->count(),
            'failed' => MerchantEmailRecipient::where('status', 'failed')->count(),
        ];
    }

    public function markCampaignCompletion(int $campaignId): void
    {
        $campaign = MerchantEmailCampaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        if (in_array($campaign->status, ['cancelled', 'archived', 'draft'], true)) {
            return;
        }

        $pending = $campaign->recipients()->whereIn('status', ['pending', 'processing'])->count();
        if ($pending > 0) {
            return;
        }

        $failedCount = $campaign->recipients()->where('status', 'failed')->count();
        $sentCount = $campaign->recipients()->where('status', 'sent')->count();
        $cancelledCount = $campaign->recipients()->where('status', 'cancelled')->count();

        if ($sentCount > 0 && $failedCount > 0) {
            $status = 'partially_failed';
        } elseif ($sentCount > 0) {
            $status = 'completed';
        } elseif ($failedCount > 0) {
            $status = 'failed';
        } elseif ($cancelledCount > 0) {
            $status = 'cancelled';
        } else {
            $status = 'completed';
        }

        MerchantEmailCampaign::where('id', $campaignId)
            ->whereNull('completed_at')
            ->update([
                'status' => $status,
                'completed_at' => now(),
            ]);
    }
}
