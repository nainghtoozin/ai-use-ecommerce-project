<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class WorkspaceSwitchController extends Controller
{
    public function switch(string $tenantSlug, Request $request)
    {
        $user = $request->user();

        if (!$user instanceof Account) {
            abort(403, 'Workspace switching is only available for account-based users.');
        }

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $membership = $user->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            abort(403, 'You do not have access to this workspace.');
        }

        $request->session()->put('current_tenant_slug', $tenantSlug);

        ActivityLogger::log(
            "Switched workspace to {$tenant->name}",
            'workspace_switched',
            $user,
            [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenantSlug,
                'tenant_name' => $tenant->name,
                'membership_id' => $membership->id,
            ]
        );

        return redirect()->intended(route('admin.dashboard'));
    }
}
