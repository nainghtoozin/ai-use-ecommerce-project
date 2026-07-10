<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerProfile;
use App\Models\TenantMembership;
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
        ]);

        $customerRole = app(TenantBootstrapService::class)->ensureCustomerRole($tenant);
        $user->assignRole($customerRole);

        event(new Registered($user));

        Auth::login($user);

        if ($user->isAdmin()) {
            return redirect()->intended(route('storefront.admin.dashboard', ['store_slug' => $tenant->slug]));
        }

        return redirect()->route('storefront.index', ['store_slug' => $tenant->slug]);
    }

    protected function storeAccount(Request $request, \App\Models\Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $account = Account::where('email', $request->email)->first();
        $isNewAccount = false;

        if (!$account) {
            $isNewAccount = true;
            $account = Account::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => Account::STATUS_ACTIVE,
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

        $account->assignRole($customerRole);

        if ($isNewAccount) {
            event(new Registered($account));
        } elseif (!$account->hasVerifiedEmail()) {
            event(new Registered($account));
        }

        Auth::guard('accounts')->login($account);

        return redirect()->route('storefront.index', ['store_slug' => $tenant->slug]);
    }
}
