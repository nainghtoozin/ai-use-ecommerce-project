<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
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

        $role = null;
        $permissions = [];

        if ($user instanceof \App\Models\User) {
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
        }

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
