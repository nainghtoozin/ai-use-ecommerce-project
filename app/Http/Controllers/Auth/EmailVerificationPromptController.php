<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginRedirectResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|\Inertia\Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return app(LoginRedirectResolver::class)->intended($request->user());
        }

        return Inertia::render('Auth/VerifyEmail');
    }
}
