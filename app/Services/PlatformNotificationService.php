<?php

namespace App\Services;

use App\Auth\IdentityResolver;
use App\Mail\Platform\NewRecoveredStoreActivationMail;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdminNewRecoveredStoreNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class PlatformNotificationService
{
    private const NOTIFICATION_TYPE = SuperAdminNewRecoveredStoreNotification::class;

    public function notifyNewRecoveredStore(Tenant $newTenant, Tenant $sourceTenant, array $result, string $archiveId): void
    {
        try {
            $alreadyNotified = DB::table('notifications')
                ->where('type', self::NOTIFICATION_TYPE)
                ->get(['data'])
                ->contains(function ($row) use ($archiveId) {
                    $data = json_decode($row->data ?? '', true);

                    return is_array($data) && ($data['archive_id'] ?? null) === $archiveId;
                });

            if ($alreadyNotified) {
                return;
            }

            $superAdminIds = IdentityResolver::resolveSuperAdmins();

            $modelClass = config('identity.use_accounts') ? Account::class : User::class;

            $superAdmins = $superAdminIds->isNotEmpty()
                ? $modelClass::whereIn('id', $superAdminIds)->get()
                : collect();

            $newTenant->loadMissing('subscription.plan');

            $data = [
                'store_name' => $newTenant->name,
                'store_slug' => $newTenant->slug,
                'tenant_id' => $newTenant->id,
                'source_tenant_id' => $sourceTenant->id,
                'source_store_name' => $sourceTenant->name,
                'merchant_name' => $result['owner_email'] ?? null,
                'merchant_email' => $result['owner_email'] ?? null,
                'assigned_plan' => $result['plan_name'] ?? $newTenant->subscription?->plan?->name,
                'plan_name' => $result['plan_name'] ?? $newTenant->subscription?->plan?->name,
                'tenant_status' => $newTenant->status,
                'subscription_status' => $newTenant->subscription?->status,
                'recovery_date' => now()->toDateTimeString(),
                'recovery_timestamp' => now()->toIso8601String(),
                'archive_id' => $archiveId,
                'action_url' => route('superadmin.tenants.show', $newTenant),
                'review_url' => route('superadmin.tenants.show', $newTenant),
            ];

            if ($superAdmins->isNotEmpty()) {
                Notification::send($superAdmins, new SuperAdminNewRecoveredStoreNotification($data));
            }

            $this->sendEmail($archiveId, $data, $result);
        } catch (\Throwable $e) {
            Log::warning('Platform new recovered store notification failed.', [
                'tenant_id' => $newTenant->id,
                'archive_id' => $archiveId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(string $archiveId, array $data, array $result): void
    {
        $cacheKey = 'platform:new-recovered-store:email:' . $archiveId;

        if (!Cache::add($cacheKey, true, now()->addDays(365))) {
            return;
        }

        try {
            $recipients = app(BillingEmailService::class)->resolveReviewerEmails();

            if (empty($recipients)) {
                Log::warning('New recovered store email skipped: no SuperAdmin recipients resolved.', [
                    'tenant_id' => $data['tenant_id'] ?? null,
                    'archive_id' => $archiveId,
                ]);

                return;
            }

            foreach ($recipients as $email) {
                Mail::to($email)->queue(new NewRecoveredStoreActivationMail(array_merge($data, [
                    'app_name' => (string) config('app.name'),
                    'store_name' => $data['store_name'],
                    'store_slug' => $data['store_slug'],
                    'plan_name' => $data['plan_name'],
                    'tenant_status' => $data['tenant_status'],
                    'subscription_status' => $data['subscription_status'],
                    'recovery_date' => $data['recovery_date'],
                    'review_url' => $data['review_url'],
                ])));
            }
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);

            Log::warning('New recovered store email failed.', [
                'tenant_id' => $data['tenant_id'] ?? null,
                'archive_id' => $archiveId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
