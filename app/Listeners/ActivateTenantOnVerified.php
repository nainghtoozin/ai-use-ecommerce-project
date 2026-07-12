<?php

namespace App\Listeners;

use App\Models\Account;
use App\Models\Tenant;
use App\Notifications\WelcomeOwner;
use App\Services\SubscriptionAuditService;
use Illuminate\Auth\Events\Verified;

class ActivateTenantOnVerified
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user instanceof Account) {
            $membership = $user->memberships()->where('is_owner', true)->first();
            if (!$membership) {
                return;
            }
            $tenant = $membership->tenant;
            if ($tenant && $tenant->status === 'pending') {
                $tenant->status = 'active';
                $tenant->activated_at = now();
                $tenant->save();
                Tenant::clearDefaultCache();

                $subscription = $tenant->subscription;
                if ($subscription && $subscription->status === 'pending') {
                    $subscription->status = 'active';
                    $subscription->starts_at = now();
                    $subscription->save();

                    SubscriptionAuditService::log($subscription, 'activated', [
                        'old_status' => 'pending',
                        'reason' => 'Email verified',
                    ]);
                }

                $user->notify(new WelcomeOwner($tenant));
            }
            return;
        }

        if ($user->is_owner && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && $tenant->status === 'pending') {
                $tenant->status = 'active';
                $tenant->activated_at = now();
                $tenant->save();
                Tenant::clearDefaultCache();

                $subscription = $tenant->subscription;
                if ($subscription && $subscription->status === 'pending') {
                    $subscription->status = 'active';
                    $subscription->starts_at = now();
                    $subscription->save();

                    SubscriptionAuditService::log($subscription, 'activated', [
                        'old_status' => 'pending',
                        'reason' => 'Email verified',
                    ]);
                }

                $user->notify(new WelcomeOwner($tenant));
            }
        }
    }
}
