<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginRedirectResolver;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, $id, $hash): RedirectResponse
    {
        $useAccounts = config('identity.use_accounts');

        if ($useAccounts) {
            $account = Account::findOrFail($id);

            if (!hash_equals((string) sha1($account->getEmailForVerification()), (string) $hash)) {
                abort(403);
            }

            if ($account->hasVerifiedEmail()) {
                return $this->redirectAfterVerification($account);
            }

            if ($account->markEmailAsVerified()) {
                event(new Verified($account));
            }

            return $this->redirectAfterVerification($account);
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->redirectAfterVerification($user);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectAfterVerification($user);
    }

    private function redirectAfterVerification(User|Account $authenticatable): RedirectResponse
    {
        $purpose = request()->query('purpose');

        if ($authenticatable instanceof Account && $purpose === 'customer') {
            $membership = $authenticatable->memberships()->with('tenant')->first();
            if ($membership && $membership->tenant) {
                return redirect()->route('storefront.customer.email-verified', ['store_slug' => $membership->tenant->slug])
                    ->with('status', 'email-verified');
            }
        }

        if ($authenticatable instanceof Account && $purpose === 'merchant_onboarding') {
            // Mark any pending intent as ready_for_setup.
            \App\Models\MerchantOnboardingIntent::where('account_id', $authenticatable->id)
                ->where('status', 'pending_verification')
                ->update([
                    'status' => 'ready_for_setup',
                    'verified_at' => now(),
                ]);

            return redirect()->route('onboarding.store-setup')->with('status', 'email-verified');
        }

        $url = app(LoginRedirectResolver::class)->resolveAfterEmailVerification($authenticatable);

        if ($authenticatable instanceof Account) {
            return redirect()->to($url)->with('status', 'email-verified');
        }

        if ($url === route('login')) {
            return redirect()->to($url)->with('status', 'email-verified');
        }

        return redirect()->to($url);
    }
}
