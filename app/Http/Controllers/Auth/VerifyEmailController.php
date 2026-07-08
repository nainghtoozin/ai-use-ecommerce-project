<?php

namespace App\Http\Controllers\Auth;

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

    private function redirectAfterVerification($authenticatable): RedirectResponse
    {
        if ($authenticatable instanceof User && $authenticatable->tenant_id && $authenticatable->tenant) {
            return redirect()->route('storefront.onboarding.complete', [
                'store_slug' => $authenticatable->tenant->slug,
            ]);
        }

        if ($authenticatable instanceof Account) {
            $membership = $authenticatable->memberships()->with('tenant')->first();
            if ($membership && $membership->tenant) {
                return redirect()->route('storefront.onboarding.complete', [
                    'store_slug' => $membership->tenant->slug,
                ]);
            }
        }

        return redirect()->route('login')->with('status', 'email-verified');
    }
}
