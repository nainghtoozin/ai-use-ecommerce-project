<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WebsiteInfo;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function __construct(private GoogleOAuthService $google) {}

    public function redirect(Request $request, ?string $purpose = null)
    {
        if ($purpose === null) {
            $purpose = 'link';
        }
        if (!in_array($purpose, ['superadmin', 'merchant', 'register', 'link'], true)) {
            abort(404);
        }

        if ($purpose === 'link' && !Auth::check()) {
            return redirect()->route('login');
        }

        if ($purpose === 'link') {
            $useAccounts = config('identity.use_accounts');
            $guard = $useAccounts ? 'accounts' : 'web';
            $user = Auth::guard($guard)->user();
            if ($user && !empty($user->password)) {
                $confirmedAt = $request->session()->get('auth.password_confirmed_at');
                if (!$confirmedAt || now()->diffInMinutes($confirmedAt) > 15) {
                    return redirect()->route('password.confirm');
                }
            }
        }

        if ($purpose === 'register') {
            $settings = WebsiteInfo::getSettings();
            if (!$settings->allow_registration || !(PlatformSetting::current()->registration_enabled ?? true)) {
                return redirect()->route('registration.unavailable');
            }
        }

        $state = $this->google->generateState();
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_purpose', $purpose);
        if ($purpose === 'link') {
            $useAccounts = config('identity.use_accounts');
            $guard = $useAccounts ? 'accounts' : 'web';
            $request->session()->put('google_oauth_user_id', Auth::guard($guard)->id());
        }

        $tenantSlug = $request->input('tenant_slug') ?: $request->input('store_slug');
        if ($tenantSlug) {
            $request->session()->put('google_oauth_tenant_slug', $tenantSlug);
        }

        return redirect()->away($this->google->authUrl($state));
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication was cancelled or denied.']);
        }

        $state = $request->input('state');
        if (!$state || $state !== $request->session()->get('google_oauth_state')) {
            return redirect()->route('login')->withErrors(['email' => 'Invalid OAuth state. Please try again.']);
        }

        $purpose = $request->session()->get('google_oauth_purpose');
        $tenantSlug = $request->session()->get('google_oauth_tenant_slug');
        $request->session()->forget(['google_oauth_state', 'google_oauth_purpose', 'google_oauth_tenant_slug']);

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication was cancelled.']);
        }

        try {
            $tokenData = $this->google->exchangeCode($code);
            $profile = $this->google->fetchUser($tokenData['access_token'] ?? '');
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication failed.']);
        }

        $googleId = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        $verified = (bool) ($profile['email_verified'] ?? false);
        $name = $profile['name'] ?? ($profile['given_name'] ?? 'User');
        $avatar = $profile['picture'] ?? null;

        if (!$googleId || !$email) {
            return redirect()->route('login')->withErrors(['email' => 'Google profile incomplete.']);
        }

        $useAccounts = config('identity.use_accounts');

        if ($purpose === 'link') {
            return $this->handleLink($request, $googleId, $email, $verified, $name, $avatar, $useAccounts);
        }

        if ($purpose === 'superadmin') {
            return $this->handleSuperAdminLogin($request, $googleId, $email, $verified, $useAccounts);
        }

        if ($purpose === 'register') {
            return $this->handleRegister($request, $googleId, $email, $verified, $name, $avatar, $useAccounts, $tenantSlug);
        }

        return $this->handleMerchantLogin($request, $googleId, $email, $verified, $name, $avatar, $useAccounts, $tenantSlug);
    }

    protected function handleSuperAdminLogin(Request $request, string $googleId, string $email, bool $verified, bool $useAccounts)
    {
        if (!$verified) {
            return redirect()->route('superadmin.login')->withErrors(['email' => 'Google email is not verified.']);
        }

        $modelClass = $useAccounts ? Account::class : User::class;
        $user = $modelClass::where('google_id', $googleId)->first();

        if (!$user) {
            $user = $modelClass::where('email', $email)->first();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                $user->google_id = $googleId;
                $user->google_email = $email;
                $user->google_verified = $verified;
                $user->save();
            } else {
                $user = null;
            }
        }

        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            return redirect()->route('superadmin.login')->withErrors(['email' => 'This Google account is not authorized for SuperAdmin access.']);
        }

        $guard = $useAccounts ? 'accounts' : 'web';
        Auth::guard($guard)->login($user);
        $request->session()->regenerate();
        $this->setTwoFactorSession($request, $user);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('superadmin.dashboard');
    }

    protected function handleMerchantLogin(Request $request, string $googleId, string $email, bool $verified, string $name, ?string $avatar, bool $useAccounts, ?string $tenantSlug = null)
    {
        if (!$verified) {
            return redirect()->route('login')->withErrors(['email' => 'Google email is not verified.']);
        }

        if ($useAccounts) {
            $account = Account::where('google_id', $googleId)->first();
            if (!$account) {
                $account = Account::where('email', $email)->first();
                if ($account) {
                    if ($account->google_id && $account->google_id !== $googleId) {
                        return redirect()->route('login')->withErrors(['email' => 'This email is already linked to another Google account.']);
                    }
                    $account->google_id = $googleId;
                    $account->google_email = $email;
                    $account->google_verified = $verified;
                    if ($avatar) $account->google_avatar = $avatar;
                    $account->save();
                }
            }

            if (!$account) {
                return redirect()->route('login')->withErrors(['email' => 'No account found for this Google identity. Please register first.']);
            }

            if (!$account->isActive()) {
                return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
            }

            if ($tenantSlug) {
                $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
                if (!$tenant) {
                    return redirect()->route('login')->withErrors(['email' => 'Store not found.']);
                }
                $membership = \App\Models\TenantMembership::where('account_id', $account->id)
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->first();
                if (!$membership) {
                    return redirect()->route('login')->withErrors(['email' => 'Your account does not have access to this store.']);
                }
                $request->session()->put('current_tenant_slug', $tenant->slug);
            }

            Auth::guard('accounts')->login($account);
            $request->session()->regenerate();
            $this->setTwoFactorSession($request, $account);

            if ($verified && !$account->hasVerifiedEmail()) {
                $account->markEmailAsVerified();
            }

            if (!$account->hasVerifiedEmail()) {
                return redirect()->route('verification.notice')
                    ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
            }

            $redirectResolver = app(\App\Auth\LoginRedirectResolver::class);
            $tenant = $redirectResolver->resolveTenant($account);
            if ($tenant) {
                $request->session()->put('current_tenant_slug', $tenant->slug);
            }
            return redirect()->to($redirectResolver->resolveLogin($account, $tenant));
        }

        $user = User::where('google_id', $googleId)->first();
        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                if ($user->google_id && $user->google_id !== $googleId) {
                    return redirect()->route('login')->withErrors(['email' => 'This email is already linked to another Google account.']);
                }
                $user->google_id = $googleId;
                $user->google_email = $email;
                $user->google_verified = $verified;
                if ($avatar) $user->google_avatar = $avatar;
                $user->save();
            }
        }

        if (!$user) {
            return redirect()->route('login')->withErrors(['email' => 'No account found for this Google identity. Please register first.']);
        }

        if (!$user->isActive()) {
            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }

        if ($tenantSlug) {
            $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
            if (!$tenant || ($user->tenant_id && $user->tenant_id !== $tenant->id)) {
                return redirect()->route('login')->withErrors(['email' => 'Your account does not have access to this store.']);
            }
            if (!$user->tenant_id) {
                $user->update(['tenant_id' => $tenant->id]);
            }
            $request->session()->put('current_tenant_slug', $tenant->slug);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->setTwoFactorSession($request, $user);

        if ($verified && !$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
        }

        $redirectResolver = app(\App\Auth\LoginRedirectResolver::class);
        $tenant = $redirectResolver->resolveTenant($user);
        if ($tenant) {
            $request->session()->put('current_tenant_slug', $tenant->slug);
        }
        return redirect()->to($redirectResolver->resolveLogin($user, $tenant));
    }

    protected function handleRegister(Request $request, string $googleId, string $email, bool $verified, string $name, ?string $avatar, bool $useAccounts, ?string $tenantSlug = null)
    {
        if (!$verified) {
            return redirect()->route('register')->withErrors(['email' => 'Google email is not verified.']);
        }

        if ($useAccounts) {
            $account = Account::where('google_id', $googleId)->first();
            if (!$account) {
                $account = Account::where('email', $email)->first();
            }

            if ($account) {
                if ($account->google_id && $account->google_id !== $googleId) {
                    return redirect()->route('register')->withErrors(['email' => 'This email is already registered. Please sign in.']);
                }
                $account->google_id = $googleId;
                $account->google_email = $email;
                $account->google_verified = $verified;
                if ($avatar) $account->google_avatar = $avatar;
                if ($verified && !$account->hasVerifiedEmail()) {
                    $account->markEmailAsVerified();
                }
                $account->save();
            } else {
                $account = Account::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(str()->random(32)),
                    'status' => Account::STATUS_ACTIVE,
                    'google_id' => $googleId,
                    'google_email' => $email,
                    'google_avatar' => $avatar,
                    'google_verified' => $verified,
                    'two_factor_required' => false,
                    'notification_preferences' => [
                        'email' => true,
                        'browser' => true,
                        'telegram' => false,
                        'marketing' => false,
                        'order_updates' => true,
                        'system_alerts' => true,
                    ],
                ]);
                if ($verified) {
                    $account->markEmailAsVerified();
                } else {
                    event(new Registered($account));
                }
            }

            if ($tenantSlug) {
                $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
                if ($tenant) {
                    $existingMembership = \App\Models\TenantMembership::where('account_id', $account->id)
                        ->where('tenant_id', $tenant->id)
                        ->exists();
                    if (!$existingMembership) {
                        $customerRole = app(\App\Services\TenantBootstrapService::class)->ensureCustomerRole($tenant);
                        $membership = \App\Models\TenantMembership::create([
                            'account_id' => $account->id,
                            'tenant_id' => $tenant->id,
                            'role_id' => $customerRole->id,
                            'is_owner' => false,
                            'status' => 'active',
                            'invited_at' => now(),
                            'joined_at' => now(),
                        ]);
                        \App\Models\CustomerProfile::firstOrCreate(
                            ['tenant_membership_id' => $membership->id],
                            ['name' => $name]
                        );
                        $account->assignRole($customerRole);
                    }
                    $request->session()->put('current_tenant_slug', $tenant->slug);
                    Auth::guard('accounts')->login($account);
                    $request->session()->regenerate();
                    $this->setTwoFactorSession($request, $account);
                    if (!$account->hasVerifiedEmail()) {
                        return redirect()->route('verification.notice')
                    ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
                    }
                    return redirect()->to(app(\App\Auth\LoginRedirectResolver::class)->resolveAfterRegistration($account, $tenant));
                }
            }

            Auth::guard('accounts')->login($account);
            $request->session()->regenerate();
            $this->setTwoFactorSession($request, $account);

            if (!$account->hasVerifiedEmail()) {
                return redirect()->route('verification.notice')
                    ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
            }

            return redirect()->route('onboarding.create-password');
        }

        $user = User::where('google_id', $googleId)->first();
        if (!$user) {
            $user = User::where('email', $email)->first();
        }
        if ($user) {
            if ($user->google_id && $user->google_id !== $googleId) {
                return redirect()->route('register')->withErrors(['email' => 'This email is already registered. Please sign in.']);
            }
            $user->google_id = $googleId;
            $user->google_email = $email;
            $user->google_verified = $verified;
            if ($avatar) $user->google_avatar = $avatar;
            if ($verified && !$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            $user->save();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(str()->random(32)),
                'status' => 'active',
                'google_id' => $googleId,
                'google_email' => $email,
                'google_avatar' => $avatar,
                'google_verified' => $verified,
                'two_factor_required' => false,
            ]);
            if ($verified) {
                $user->markEmailAsVerified();
            } else {
                event(new Registered($user));
            }
        }

        if ($tenantSlug) {
            $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
            if ($tenant) {
                if (!$user->tenant_id) {
                    $user->update(['tenant_id' => $tenant->id]);
                }
                $customerRole = app(\App\Services\TenantBootstrapService::class)->ensureCustomerRole($tenant);
                $user->assignRole($customerRole);
                $request->session()->put('current_tenant_slug', $tenant->slug);
                Auth::login($user);
                $request->session()->regenerate();
                $this->setTwoFactorSession($request, $user);
                if (!$user->hasVerifiedEmail()) {
                    return redirect()->route('verification.notice')
                    ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
                }
                return redirect()->to(app(\App\Auth\LoginRedirectResolver::class)->resolveAfterRegistration($user, $tenant));
            }
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->setTwoFactorSession($request, $user);

        return redirect()->route('verification.notice')
                    ->with('status', 'Your Google sign-in was successful, but your email address still needs to be verified for this platform. Please open your Gmail inbox and click the verification link we sent you before continuing.');
    }

    protected function handleLink(Request $request, string $googleId, string $email, bool $verified, string $name, ?string $avatar, bool $useAccounts)
    {
        if (!$verified) {
            return redirect()->route('profile.edit')->withErrors(['google' => 'Google email is not verified.']);
        }

        $userId = $request->session()->get('google_oauth_user_id');
        $request->session()->forget('google_oauth_user_id');

        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user || $user->id != $userId) {
            return redirect()->route('login');
        }

        // Prevent linking if google identity already linked to another account
        $modelClass = $useAccounts ? Account::class : User::class;
        $existing = $modelClass::where('google_id', $googleId)->where('id', '!=', $user->id)->first();
        if ($existing) {
            return redirect()->route('security.settings')->withErrors(['google' => 'This Google account is already linked to another account.']);
        }

        if ($user->google_id && $user->google_id !== $googleId) {
            return redirect()->route('security.settings')->withErrors(['google' => 'Your account is already linked to a different Google identity. Unlink first.']);
        }

        $user->google_id = $googleId;
        $user->google_email = $email;
        $user->google_verified = $verified;
        if ($avatar) $user->google_avatar = $avatar;
        $user->save();

        return redirect()->route('security.settings')->with('status', 'Google account linked successfully.');
    }

    public function unlink(Request $request)
    {
        $useAccounts = config('identity.use_accounts');
        $guard = $useAccounts ? 'accounts' : 'web';
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!empty($user->password)) {
            if (!$request->filled('password') || !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                return redirect()->route('security.settings')->withErrors(['password' => 'Invalid password.']);
            }
        } elseif ($user->hasTwoFactorEnabled()) {
            $code = $request->input('code');
            $secret = $user->getTwoFactorSecretDecrypted();
            $ok = false;
            if ($code && $secret && app(\App\Services\TwoFactorService::class)->verify($secret, $code)) {
                $ok = true;
            } elseif ($code && $user->consumeRecoveryCode($code)) {
                $ok = true;
            }
            if (!$ok) {
                return redirect()->route('security.settings')->withErrors(['code' => 'Invalid authentication code.']);
            }
        } else {
            return redirect()->route('security.settings')->withErrors(['google' => 'Cannot unlink Google without another login method.']);
        }

        $user->google_id = null;
        $user->google_email = null;
        $user->google_avatar = null;
        $user->google_verified = false;
        $user->save();

        return redirect()->route('security.settings')->with('status', 'Google account unlinked.');
    }

    protected function setTwoFactorSession(Request $request, $user): void
    {
        $request->session()->put('2fa_verified', !$user->hasTwoFactorEnabled());
        $request->session()->put('2fa_enrollment_pending', $user->requiresTwoFactor() && !$user->hasTwoFactorEnabled());
    }
}
