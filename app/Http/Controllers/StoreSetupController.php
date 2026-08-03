<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\WebsiteInfo;
use App\Services\TenantBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StoreSetupController extends Controller
{
    public function __construct(
        private readonly TenantBootstrapService $bootstrapService,
    ) {}

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Account && $user->memberships()->exists()) {
            return redirect()->route('home');
        }

        $platform = PlatformSetting::current();

        return Inertia::render('Onboarding/StoreSetup', [
            'currencies' => $this->getCurrencies(),
            'timezones' => $this->getTimezones(),
            'countries' => $this->getCountries(),
            'languages' => $this->getLanguages(),
            'defaultCurrency' => $platform->default_currency ?? 'MMK',
            'defaultTimezone' => $platform->default_timezone ?? 'Asia/Yangon',
            'defaultLanguage' => $platform->default_language ?? 'en',
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user instanceof Account && $user->memberships()->exists()) {
            return back()->withErrors(['slug' => 'You already own a store.']);
        }

        $validated = $request->validate([
            'store_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'name'),
            ],
            'store_slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/',
                'min:3',
                Rule::unique('tenants', 'slug'),
            ],
            'business_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'language' => ['required', 'string', 'max:10'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:64'],
            'country' => ['required', 'string', 'max:2'],
            'theme_color' => ['nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $user) {
                $tenant = Tenant::create([
                    'name' => $validated['store_name'],
                    'slug' => $validated['store_slug'],
                    'email' => $validated['business_email'],
                    'status' => 'pending',
                    'settings' => [
                        'language' => $validated['language'],
                        'currency' => $validated['currency'],
                        'timezone' => $validated['timezone'],
                        'country' => $validated['country'],
                        'theme_color' => $validated['theme_color'] ?? '#6366F1',
                        'theme' => 'light',
                        'notifications' => true,
                        'description' => $validated['description'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                    ],
                ]);

                Tenant::clearDefaultCache();

                $owner = $this->bootstrapService->bootstrap($tenant, [
                    'owner_name' => $user->name,
                    'owner_email' => $user->email,
                    'owner_password' => null,
                    'status' => 'active',
                    'email_verified' => true,
                ]);

                WebsiteInfo::withoutTenantScope(function () use ($tenant, $validated) {
                    WebsiteInfo::create([
                        'tenant_id' => $tenant->id,
                        'site_name' => $validated['store_name'],
                        'site_description' => $validated['description'] ?? null,
                        'theme_color' => $validated['theme_color'] ?? '#6366F1',
                        'default_language' => $validated['language'],
                        'timezone' => $validated['timezone'],
                        'currency_code' => $validated['currency'],
                        'country' => $validated['country'],
                        'contact_email' => $validated['business_email'],
                        'phone' => $validated['phone'] ?? null,
                        'allow_registration' => true,
                        'is_active' => true,
                    ]);
                });

                return [
                    'tenant' => $tenant,
                    'owner' => $owner,
                ];
            });

            $tenant = $result['tenant'];

            if ($request->user() instanceof Account) {
                $request->session()->put('current_tenant_slug', $tenant->slug);
            }

            return redirect()->route('onboarding.success', [
                'store_slug' => $tenant->slug,
            ]);
        } catch (\Throwable $e) {
            Log::error('Store setup wizard failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'slug' => 'Something went wrong while creating your store. Please try again.',
            ])->withInput();
        }
    }

    public function checkSlug(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $slug = $request->input('slug');
        $exists = Tenant::where('slug', $slug)->exists();

        return response()->json([
            'available' => !$exists,
            'slug' => $slug,
        ]);
    }

    public function success(Request $request, string $store_slug)
    {
        $tenant = Tenant::where('slug', $store_slug)->firstOrFail();

        $subscription = $tenant->subscription;
        $plan = $subscription?->plan;

        return Inertia::render('Onboarding/StoreSuccess', [
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'url' => url('/store/' . $tenant->slug),
                'admin_url' => route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]),
                'status' => $tenant->status,
            ],
            'subscription' => $subscription && $plan ? [
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'status' => $subscription->status,
                'on_trial' => $subscription->onTrial(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'days_left_in_trial' => $subscription->daysLeftInTrial(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'is_free' => $plan->isFree(),
            ] : null,
        ]);
    }

    private function getCurrencies(): array
    {
        return [
            ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'symbol' => 'K'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            ['code' => 'TWD', 'name' => 'Taiwan Dollar', 'symbol' => 'NT$'],
            ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱'],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫'],
        ];
    }

    private function getTimezones(): array
    {
        return [
            ['value' => 'Asia/Yangon', 'label' => 'Yangon (UTC+6:30)'],
            ['value' => 'Asia/Bangkok', 'label' => 'Bangkok (UTC+7)'],
            ['value' => 'Asia/Singapore', 'label' => 'Singapore (UTC+8)'],
            ['value' => 'Asia/Kuala_Lumpur', 'label' => 'Kuala Lumpur (UTC+8)'],
            ['value' => 'Asia/Jakarta', 'label' => 'Jakarta (UTC+7)'],
            ['value' => 'Asia/Manila', 'label' => 'Manila (UTC+8)'],
            ['value' => 'Asia/Ho_Chi_Minh', 'label' => 'Ho Chi Minh (UTC+7)'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo (UTC+9)'],
            ['value' => 'Asia/Seoul', 'label' => 'Seoul (UTC+9)'],
            ['value' => 'Asia/Shanghai', 'label' => 'Shanghai (UTC+8)'],
            ['value' => 'Asia/Kolkata', 'label' => 'Kolkata (UTC+5:30)'],
            ['value' => 'Asia/Dubai', 'label' => 'Dubai (UTC+4)'],
            ['value' => 'Europe/London', 'label' => 'London (UTC+0)'],
            ['value' => 'Europe/Paris', 'label' => 'Paris (UTC+1)'],
            ['value' => 'Europe/Berlin', 'label' => 'Berlin (UTC+1)'],
            ['value' => 'America/New_York', 'label' => 'New York (UTC-5)'],
            ['value' => 'America/Chicago', 'label' => 'Chicago (UTC-6)'],
            ['value' => 'America/Los_Angeles', 'label' => 'Los Angeles (UTC-8)'],
            ['value' => 'Australia/Sydney', 'label' => 'Sydney (UTC+11)'],
            ['value' => 'Pacific/Auckland', 'label' => 'Auckland (UTC+13)'],
        ];
    }

    private function getCountries(): array
    {
        return [
            ['code' => 'MM', 'name' => 'Myanmar'],
            ['code' => 'TH', 'name' => 'Thailand'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'MY', 'name' => 'Malaysia'],
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'PH', 'name' => 'Philippines'],
            ['code' => 'VN', 'name' => 'Vietnam'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'KR', 'name' => 'South Korea'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'BD', 'name' => 'Bangladesh'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'LK', 'name' => 'Sri Lanka'],
            ['code' => 'NP', 'name' => 'Nepal'],
            ['code' => 'KH', 'name' => 'Cambodia'],
            ['code' => 'LA', 'name' => 'Laos'],
            ['code' => 'BN', 'name' => 'Brunei'],
            ['code' => 'TL', 'name' => 'Timor-Leste'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'NZ', 'name' => 'New Zealand'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'SA', 'name' => 'Saudi Arabia'],
        ];
    }

    private function getLanguages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'my', 'name' => 'Myanmar'],
            ['code' => 'th', 'name' => 'Thai'],
            ['code' => 'zh', 'name' => 'Chinese'],
            ['code' => 'ja', 'name' => 'Japanese'],
            ['code' => 'ko', 'name' => 'Korean'],
            ['code' => 'vi', 'name' => 'Vietnamese'],
            ['code' => 'id', 'name' => 'Indonesian'],
            ['code' => 'ms', 'name' => 'Malay'],
            ['code' => 'fil', 'name' => 'Filipino'],
            ['code' => 'hi', 'name' => 'Hindi'],
            ['code' => 'km', 'name' => 'Khmer'],
            ['code' => 'lo', 'name' => 'Lao'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'pt', 'name' => 'Portuguese'],
            ['code' => 'ar', 'name' => 'Arabic'],
        ];
    }
}
