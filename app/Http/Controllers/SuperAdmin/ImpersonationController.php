<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImpersonationController extends Controller
{
    public function start(User $user)
    {
        $impersonator = auth()->user();

        if (!$impersonator || !$impersonator->isSuperAdmin()) {
            abort(403, 'Only SuperAdmin can impersonate users.');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Cannot impersonate another SuperAdmin.');
        }

        if (!$user->tenant_id) {
            return redirect()->back()->with('error', 'User does not belong to any tenant.');
        }

        if (session()->has('impersonator_id')) {
            return redirect()->back()->with('error', 'Already impersonating. Leave current impersonation first.');
        }

        if ($user->isSuspended()) {
            return redirect()->back()->with('error', 'Cannot impersonate a suspended user.');
        }

        if ($user->isBanned()) {
            return redirect()->back()->with('error', 'Cannot impersonate a banned user.');
        }

        if ($user->isInactive()) {
            return redirect()->back()->with('error', 'Cannot impersonate an inactive user.');
        }

        if (!$user->hasRole('admin')) {
            return redirect()->back()->with('error', 'Target user does not have admin access. Impersonation would result in a 403 on admin pages.');
        }

        if ($user->tenant && $user->tenant->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot impersonate a user whose tenant is not active.');
        }

        $batchUuid = (string) Str::uuid();

        session()->put('impersonator_id', $impersonator->id);
        session()->put('impersonator_name', $impersonator->name);
        session()->put('impersonation_batch_uuid', $batchUuid);

        ActivityLog::create([
            'log_name' => 'impersonation',
            'description' => "SuperAdmin {$impersonator->name} started impersonating {$user->name}",
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $impersonator->id,
            'impersonator_id' => $impersonator->id,
            'impersonated_user_id' => $user->id,
            'properties' => [
                'impersonator_id' => $impersonator->id,
                'impersonator_name' => $impersonator->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'target_tenant_id' => $user->tenant_id,
            ],
            'event' => 'impersonation_started',
            'batch_uuid' => $batchUuid,
        ]);

        request()->attributes->set('_impersonation_transition', true);

        auth()->login($user);

        request()->session()->regenerate();

        $tenant = $user->tenant;
        if ($tenant) {
            return redirect()->route('storefront.admin.dashboard', ['store_slug' => $tenant->slug])
                ->with('success', "Logged in as {$user->name}.");
        }
        return redirect()->route('admin.dashboard')
            ->with('success', "Logged in as {$user->name}.");
    }

    public function leave()
    {
        $impersonatorId = session()->pull('impersonator_id');
        $impersonatorName = session()->pull('impersonator_name');
        $batchUuid = session()->pull('impersonation_batch_uuid');

        if (!$impersonatorId) {
            return redirect()->route('superadmin.dashboard')
                ->with('error', 'No active impersonation session.');
        }

        $impersonator = User::find($impersonatorId);

        if (!$impersonator) {
            auth()->logout();
            return redirect()->route('login')
                ->with('error', 'Original SuperAdmin account not found. Please log in manually.');
        }

        $impersonatedUser = auth()->user();

        ActivityLog::create([
            'log_name' => 'impersonation',
            'description' => "SuperAdmin {$impersonatorName} stopped impersonating {$impersonatedUser->name}",
            'subject_type' => User::class,
            'subject_id' => $impersonatedUser->id,
            'causer_type' => User::class,
            'causer_id' => $impersonator->id,
            'impersonator_id' => $impersonator->id,
            'impersonated_user_id' => $impersonatedUser->id,
            'properties' => [
                'impersonator_id' => $impersonator->id,
                'impersonator_name' => $impersonatorName,
                'target_user_id' => $impersonatedUser->id,
                'target_user_name' => $impersonatedUser->name,
                'target_tenant_id' => $impersonatedUser->tenant_id,
            ],
            'event' => 'impersonation_stopped',
            'batch_uuid' => $batchUuid,
        ]);

        request()->attributes->set('_impersonation_transition', true);

        auth()->logout();
        auth()->login($impersonator);

        request()->session()->regenerate();

        return redirect()->route('superadmin.dashboard')
            ->with('success', 'Returned to SuperAdmin account.');
    }
}
