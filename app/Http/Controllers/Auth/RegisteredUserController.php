<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
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

        $modelClass = $useAccounts ? Account::class : User::class;

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.$modelClass],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($useAccounts) {
            $account = Account::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => Account::STATUS_ACTIVE,
            ]);

            $customerRole = app(TenantBootstrapService::class)->ensureCustomerRole($tenant);

            TenantMembership::create([
                'account_id' => $account->id,
                'tenant_id' => $tenant->id,
                'role_id' => $customerRole->id,
            ]);

            $account->assignRole($customerRole);

            event(new Registered($account));

            Auth::guard('accounts')->login($account);

            return redirect()->route('storefront.index', ['store_slug' => $tenant->slug]);
        }

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
}
