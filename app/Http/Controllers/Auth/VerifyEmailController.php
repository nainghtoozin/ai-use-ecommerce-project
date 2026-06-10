<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, $id, $hash): RedirectResponse
    {
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

    private function redirectAfterVerification(User $user): RedirectResponse
    {
        if ($user->tenant_id && $user->tenant) {
            return redirect()->route('storefront.onboarding.complete', [
                'store_slug' => $user->tenant->slug,
            ]);
        }

        return redirect()->route('login')->with('status', 'email-verified');
    }
}
