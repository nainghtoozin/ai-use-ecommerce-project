<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Services\PostLoginRedirectService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function store(Request $request)
    {
        $authenticatable = $request->user();
        $state = app(PostLoginRedirectService::class)->getAccountState($authenticatable);

        if (!$state['needs_onboarding']) {
            return redirect()->to(
                app(PostLoginRedirectService::class)->resolveDestination($authenticatable)
            );
        }

        return redirect()->route('onboarding.store-setup');
    }
}
