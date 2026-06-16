<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Models\WebsiteInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Auth\Events\Registered;

class CreateStoreController extends Controller
{
    public function index()
    {
        $settings = WebsiteInfo::getSettings();

        return Inertia::render('Public/CreateStore', [
            'appUrl' => config('app.url'),
            'siteName' => $settings->site_name ?? 'My Store',
            'logoUrl' => $settings->logo_url,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:500',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $admin = DB::transaction(function () use ($validated) {
            $slug = $validated['slug'];

            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'domain' => $validated['domain'] ?? null,
                'store_url' => '/store/' . $slug,
                'status' => 'pending',
                'settings' => $validated['description']
                    ? ['description' => $validated['description']]
                    : null,
            ]);

            Tenant::clearDefaultCache();

            $plan = Plan::free();

            if ($plan) {
                $subscription = $tenant->subscription()->create([
                    'plan_id' => $plan->id,
                    'billing_interval' => $plan->defaultInterval(),
                    'status' => 'pending',
                    'starts_at' => null,
                    'expires_at' => null,
                ]);
            }

            foreach (['admin', 'customer'] as $roleName) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if (!$role) {
                    $role = new Role();
                    $role->name = $roleName;
                    $role->guard_name = 'web';
                    $role->tenant_id = $tenant->id;
                    $role->save();

                    $globalRole = Role::where('name', $roleName)
                        ->whereNull('tenant_id')
                        ->first();
                    if ($globalRole) {
                        $role->syncPermissions($globalRole->permissions);
                    }
                }
            }

            $admin = User::create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['password']),
                'status' => User::STATUS_ACTIVE,
            ]);

            $admin->tenant_id = $tenant->id;
            $admin->is_owner = true;
            $admin->save();

            $adminRole = Role::where('name', 'admin')
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($adminRole) {
                $admin->assignRole($adminRole);
            }

            $admin->syncPermissions(Permission::all());

            return $admin;
        });

        event(new Registered($admin));

        return redirect()->route('create-store.success', ['store' => $admin->tenant->slug]);
    }

    public function onboarding($store_slug)
    {
        $tenant = Tenant::where('slug', $store_slug)->firstOrFail();

        $subscription = $tenant->subscription;
        $plan = $subscription?->plan;

        return Inertia::render('Public/OnboardingComplete', [
            'storeName' => $tenant->name,
            'storeSlug' => $tenant->slug,
            'storeUrl' => url('/store/' . $tenant->slug),
            'adminLoginUrl' => route('storefront.admin.login', ['store_slug' => $tenant->slug]),
            'subscriptionPlan' => $plan ? $plan->name : 'Free',
            'status' => $tenant->status,
        ]);
    }

    public function success(Request $request)
    {
        $settings = WebsiteInfo::getSettings();

        return Inertia::render('Public/StoreRegistrationSuccess', [
            'storeSlug' => $request->query('store'),
            'storeUrl' => $request->query('store')
                ? url('/store/' . $request->query('store'))
                : null,
            'siteName' => $settings->site_name ?? 'My Store',
            'logoUrl' => $settings->logo_url,
        ]);
    }
}
