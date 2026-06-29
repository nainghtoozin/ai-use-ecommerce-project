<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use App\Services\TenantBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules;

class CreateStoreController extends Controller
{
    public function __construct(
        private readonly TenantBootstrapService $bootstrapService,
    ) {}

    public function index()
    {
        $platform = PlatformSetting::current();
        $settings = WebsiteInfo::getSettings();

        return Inertia::render('Public/CreateStore', [
            'appUrl' => config('app.url'),
            'siteName' => $platform->site_name ?: ($settings->site_name ?? 'My Store'),
            'logoUrl' => $platform->site_logo ? ImageService::url($platform->site_logo) : $settings->logo_url,
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
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
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

            return $this->bootstrapService->bootstrap($tenant, [
                'owner_name' => $validated['owner_name'],
                'owner_email' => $validated['owner_email'],
                'owner_password' => $validated['password'],
                'status' => 'pending',
            ]);
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
        $platform = PlatformSetting::current();
        $settings = WebsiteInfo::getSettings();

        return Inertia::render('Public/StoreRegistrationSuccess', [
            'storeSlug' => $request->query('store'),
            'storeUrl' => $request->query('store')
                ? url('/store/' . $request->query('store'))
                : null,
            'siteName' => $platform->site_name ?: ($settings->site_name ?? 'My Store'),
            'logoUrl' => $platform->site_logo ? ImageService::url($platform->site_logo) : $settings->logo_url,
        ]);
    }
}
