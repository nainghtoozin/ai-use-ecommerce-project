<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBootstrapService;
use App\Services\TenantDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $tenants = Tenant::query()
            ->withCount(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'admin'))])
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('domain', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        $plans = Plan::active()->ordered()->get(['id', 'name', 'slug', 'price', 'interval']);

        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => $plans,
        ]);
    }

    public function store(
        Request $request,
        TenantBootstrapService $bootstrapService,
    ) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9\-]+$/',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,suspended,trialing',
            'plan_id' => 'nullable|exists:plans,id',
            'create_admin' => 'boolean',
            'admin_name' => 'required_if:create_admin,true|string|max:255',
            'admin_email' => 'required_if:create_admin,true|email|max:255|unique:users,email',
            'admin_password' => 'required_if:create_admin,true|string|min:8',
        ]);

        $tenant = DB::transaction(function () use ($validated, $bootstrapService) {
            $storeSlug = $validated['slug'];
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $storeSlug,
                'domain' => $validated['domain'] ?? null,
                'store_url' => '/store/' . $storeSlug,
                'email' => $validated['email'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'settings' => isset($validated['plan_id']) && $validated['plan_id'] ? ['plan_id' => $validated['plan_id']] : null,
            ]);

            Tenant::clearDefaultCache();

            $tenantStatus = $validated['status'] ?? 'active';
            $subscriptionStatus = in_array($tenantStatus, ['trialing', 'active']) ? $tenantStatus : 'active';

            $bootstrapService->bootstrap($tenant, [
                'plan_id' => $validated['plan_id'] ?? null,
                'status' => $subscriptionStatus,
                'create_owner' => !empty($validated['create_admin']),
                'owner_name' => $validated['admin_name'] ?? null,
                'owner_email' => $validated['admin_email'] ?? null,
                'owner_password' => $validated['admin_password'] ?? null,
                'email_verified' => true,
            ]);

            return $tenant;
        });

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" created successfully.");
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount([
            'users',
            'users as admin_count' => fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'admin')),
            'users as customer_count' => fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'customer')),
        ]);

        $users = User::where('tenant_id', $tenant->id)
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $productCount = DB::table('products')->where('tenant_id', $tenant->id)->count();
        $orderCount = DB::table('orders')->where('tenant_id', $tenant->id)->count();
        $revenue = DB::table('orders')->where('tenant_id', $tenant->id)
            ->where('order_status', 'delivered')
            ->sum('total_amount');

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => $tenant,
            'users' => $users,
            'stats' => [
                'products' => $productCount,
                'orders' => $orderCount,
                'revenue' => (float) $revenue,
            ],
        ]);
    }

    public function edit(Tenant $tenant)
    {
        $plans = Plan::active()->ordered()->get(['id', 'name', 'slug', 'price', 'interval']);
        $currentPlanId = $tenant->settings['plan_id'] ?? null;

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant,
            'plans' => $plans,
            'currentPlanId' => $currentPlanId,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9\-]+$/|unique:tenants,slug,' . $tenant->id,
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $tenant->id,
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,suspended,trialing',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $settings = $tenant->settings ?? [];
        $settings['plan_id'] = $validated['plan_id'] ?? null;

        $slugChanged = $validated['slug'] !== $tenant->slug;

        $tenant->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'domain' => $validated['domain'] ?? $tenant->domain,
            'store_url' => $slugChanged ? '/store/' . $validated['slug'] : $tenant->store_url,
            'email' => $validated['email'] ?? $tenant->email,
            'status' => $validated['status'] ?? $tenant->status,
            'settings' => $settings,
        ]);

        if ($validated['plan_id']) {
            $subscription = $tenant->subscription;
            if ($subscription && $subscription->isInGoodStanding()) {
                $subscription->update(['plan_id' => $validated['plan_id']]);
            }
        }

        Tenant::clearDefaultCache();

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" updated successfully.");
    }

    public function toggleStatus(Tenant $tenant)
    {
        $wasSuspended = $tenant->status === 'suspended';

        if ($wasSuspended) {
            $tenant->status = 'active';
            $tenant->save();

            if ($tenant->subscription && $tenant->subscription->isSuspended()) {
                $tenant->subscription->activate();
            }

            $action = 'activated';
        } else {
            $tenant->status = 'suspended';
            $tenant->save();

            if ($tenant->subscription && $tenant->subscription->isInGoodStanding()) {
                $tenant->subscription->suspend();
            }

            $action = 'suspended';
        }

        Tenant::clearDefaultCache();

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" {$action} successfully.");
    }

    public function destroy(Tenant $tenant, TenantDeletionService $deletionService)
    {
        if ($tenant->slug === 'default') {
            return redirect()->route('superadmin.tenants.index')
                ->with('error', 'The default tenant cannot be deleted.');
        }

        $deletionService->delete($tenant);

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" deleted successfully.");
    }

}
