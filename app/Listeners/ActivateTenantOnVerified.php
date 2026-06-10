<?php

namespace App\Listeners;

use App\Notifications\WelcomeOwner;
use Illuminate\Auth\Events\Verified;
use App\Models\Tenant;

class ActivateTenantOnVerified
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

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
                }

                $user->notify(new WelcomeOwner($tenant));
            }
        }
    }
}
