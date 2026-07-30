<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;


class ProfileController extends Controller
{
    public function edit(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $useAccounts = config('identity.use_accounts');

        $role = null;
        $permissions = [];
        $tenant = null;
        $lastLoginAt = null;

        // Resolve tenant and role based on identity type
        if ($useAccounts && $user instanceof Account) {
            // Account-based identity: use membership to resolve tenant
            $tenant = Tenant::getCurrent();
            if ($tenant) {
                $membership = TenantMembership::where('tenant_id', $tenant->id)
                    ->where('account_id', $user->id)
                    ->with('role.permissions')
                    ->first();
                if ($membership) {
                    $role = $membership->is_owner ? 'Owner' : ($membership->role?->name ? ucfirst($membership->role->name) : null);
                    $permissions = $membership->is_owner
                        ? ['*']
                        : ($membership->role?->permissions?->pluck('name')->values()->toArray() ?? []);
                }
            }
            $lastLoginAt = $user->last_login_at?->toISOString();
        } elseif ($user instanceof User) {
            // Legacy User-based identity
            $tenant = Tenant::getCurrent();
            if ($tenant) {
                $membership = TenantMembership::where('tenant_id', $tenant->id)
                    ->where('account_id', $user->id)
                    ->with('role.permissions')
                    ->first();
                if ($membership) {
                    $role = $membership->is_owner ? 'Owner' : ($membership->role?->name ? ucfirst($membership->role->name) : null);
                    $permissions = $membership->is_owner
                        ? ['*']
                        : ($membership->role?->permissions?->pluck('name')->values()->toArray() ?? []);
                }
            }
            // User model doesn't have last_login_at, try to get from Account counterpart
            $account = Account::where('email', $user->email)->first();
            $lastLoginAt = $account?->last_login_at?->toISOString();
        }

        $storeSlug = $request->route('store_slug');

        // Render admin profile page when accessed from storefront admin routes
        if ($storeSlug) {
            // Eager load subscription plan if tenant exists
            $subscriptionPlan = null;
            if ($tenant) {
                $tenant->load('subscriptionPlan');
                if ($tenant->subscriptionPlan) {
                    $subscriptionPlan = [
                        'id' => $tenant->subscriptionPlan->id,
                        'name' => $tenant->subscriptionPlan->name,
                        'price' => $tenant->subscriptionPlan->monthly_price,
                    ];
                }
            }

            return Inertia::render('Admin/Profile', [
                'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail(),
                'status' => session('status'),
                'currentRole' => $role,
                'currentPermissions' => $permissions,
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'domain' => $tenant->domain,
                    'store_url' => $tenant->store_url,
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at?->toISOString(),
                    'subscription_plan' => $subscriptionPlan,
                ] : null,
                'notificationPreferences' => $user->notification_preferences ?? [],
                'lastLoginAt' => $lastLoginAt,
            ]);
        }

        // Legacy admin profile (no store slug)
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail(),
            'status' => session('status'),
            'notificationPreferences' => $user->notification_preferences,
            'allowedNotificationTypes' => $user->getAllowedNotificationTypes(),
            'currentRole' => $role,
            'currentPermissions' => $permissions,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($request->hasFile('profile_image')) {
            $imageService = app(ImageService::class);
            $imagePath = $imageService->upload($request->file('profile_image'), 'profiles');
            if ($user->profile_image) {
                $imageService->delete($user->profile_image);
            }
            $user->profile_image = $imagePath;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $storeSlug = $request->route('store_slug');
        if ($storeSlug) {
            return Redirect::route('storefront.admin.profile.edit', ['store_slug' => $storeSlug])
                ->with('status', 'profile-updated');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
