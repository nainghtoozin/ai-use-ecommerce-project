<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginRedirectResolver;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use App\Services\TenantBootstrapService;

class RegisteredUserController extends Controller
{
    public function create(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        if (!$settings->allow_registration) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled.');
        }

        $tenant = \App\Models\Tenant::getCurrent();
        if (!$tenant) {
            return redirect()->route('login')
                ->with('error', 'Please register from a specific store.');
        }

        return Inertia::render('Storefront/Register', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
            ],
            'cities' => City::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        if (!$settings->allow_registration) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled.');
        }

        $tenant = \App\Models\Tenant::getCurrent();
        if (!$tenant) {
            return redirect()->route('login')
                ->with('error', 'Please register from a specific store.');
        }

        $useAccounts = config('identity.use_accounts');

        if ($useAccounts) {
            return $this->storeAccount($request, $tenant);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenant->id,
            'notification_preferences' => [
                'email' => true,
                'browser' => true,
                'telegram' => true,
                'marketing' => false,
                'order_updates' => true,
                'system_alerts' => true,
            ],
        ]);

        $customerRole = app(TenantBootstrapService::class)->ensureCustomerRole($tenant);
        $user->assignRole($customerRole);

        event(new Registered($user));

        Auth::login($user);

        return app(LoginRedirectResolver::class)->intended($user, $tenant);
    }

    protected function storeAccount(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20', 'required_with:address'],
            'address' => ['nullable', 'string', 'max:500'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'township_id' => ['nullable', 'integer', 'exists:townships,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        [$cityId, $townshipId, $postalCode] = $this->resolveRegistrationLocation($request);

        $account = Account::where('email', $request->email)->first();
        $isNewAccount = false;

        if (!$account) {
            $isNewAccount = true;
            $account = Account::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => Account::STATUS_ACTIVE,
                'notification_preferences' => [
                    'email' => true,
                    'browser' => true,
                    'telegram' => true,
                    'marketing' => false,
                    'order_updates' => true,
                    'system_alerts' => true,
                ],
            ]);
        }

        $existingMembership = TenantMembership::where('account_id', $account->id)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($existingMembership) {
            return back()->withErrors(['email' => 'This email is already registered in this store.'])
                ->onlyInput('email');
        }

        $customerRole = app(TenantBootstrapService::class)->ensureCustomerRole($tenant);

        $membership = TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'role_id' => $customerRole->id,
            'is_owner' => false,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        CustomerProfile::firstOrCreate(
            ['tenant_membership_id' => $membership->id],
            ['name' => $request->name]
        );

        $this->saveRegistrationContact($account, $tenant, $membership, $request, $cityId, $townshipId, $postalCode);

        $account->assignRole($customerRole);

        if ($isNewAccount) {
            event(new Registered($account));
        } elseif (!$account->hasVerifiedEmail()) {
            event(new Registered($account));
        }

        Auth::guard('accounts')->login($account);

        return redirect()->to(app(LoginRedirectResolver::class)->resolveAfterRegistration($account, $tenant));
    }

    protected function resolveRegistrationLocation(Request $request): array
    {
        $cityId = $request->input('city_id') ?: null;
        $townshipId = $request->input('township_id') ?: null;
        $postalCode = trim((string) $request->input('postal_code', ''));

        if ($townshipId) {
            $township = Township::find($townshipId);
            if ($township && $cityId && (int) $township->city_id !== (int) $cityId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'township_id' => 'The selected township is not valid for the chosen city.',
                ]);
            }
            if ($township) {
                $cityId = $cityId ?: (int) $township->city_id;
                $postalCode = $postalCode !== '' ? $postalCode : ($township->postal_code ?? '');
            }
        }

        return [$cityId, $townshipId, $postalCode];
    }

    protected function saveRegistrationContact(Account $account, Tenant $tenant, TenantMembership $membership, Request $request, ?int $cityId, ?int $townshipId, string $postalCode): void
    {
        $phone = trim((string) $request->input('phone', ''));
        $address = trim((string) $request->input('address', ''));

        if ($phone !== '') {
            CustomerProfile::where('tenant_membership_id', $membership->id)->update(['phone' => $phone]);
        }

        if ($address === '') {
            return;
        }

        $parts = preg_split('/\s+/', trim($request->input('name', '')), 2);
        $hasDefault = $account->addresses()->where('tenant_id', $tenant->id)->where('is_default', true)->exists();

        $account->addresses()->create([
            'tenant_id' => $tenant->id,
            'label' => 'Home',
            'first_name' => $parts[0] ?? $request->input('name', ''),
            'last_name' => $parts[1] ?? $parts[0] ?? $request->input('name', ''),
            'phone' => $phone,
            'address_line' => $address,
            'city_id' => $cityId,
            'township_id' => $townshipId,
            'postal_code' => $postalCode !== '' ? $postalCode : null,
            'is_default' => !$hasDefault,
        ]);
    }
}
