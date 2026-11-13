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
        if ($authenticatable instanceof Account) {
            $hasOwnerMembership = \App\Models\TenantMembership::where('account_id', $authenticatable->id)
                ->where('is_owner', true)
                ->exists();

            if (!$hasOwnerMembership) {
                $membership = $authenticatable->memberships()->with('tenant')->first();
                if ($membership && $membership->tenant) {
                    return redirect()->route('storefront.customer.email-verified', ['store_slug' => $membership->tenant->slug])
                        ->with('status', 'email-verified');
                }
            }
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
