<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\AccountRegistrationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SecuritySettingsController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [AccountRegistrationController::class, 'create'])
        ->name('register');

    Route::post('register', [AccountRegistrationController::class, 'store'])
        ->middleware('throttle:auth');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.store');

    // Google OAuth
    Route::get('auth/google/redirect/{purpose}', [GoogleAuthController::class, 'redirect'])
        ->whereIn('purpose', ['superadmin', 'merchant', 'register'])
        ->name('auth.google.redirect')
        ->middleware('throttle:auth');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback')
        ->middleware('throttle:auth');
});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth:web,accounts')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('password/create', [PasswordController::class, 'create'])
        ->name('password.create')
        ->middleware('throttle:auth');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Google account linking
    Route::get('auth/google/redirect/link', [GoogleAuthController::class, 'redirect'])
        ->defaults('purpose', 'link')
        ->name('auth.google.link');

    // 2FA
    Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])
        ->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'verify'])
        ->name('two-factor.verify')
        ->middleware('throttle:auth');

    Route::get('two-factor/setup', [TwoFactorSetupController::class, 'show'])
        ->name('two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorSetupController::class, 'enable'])
        ->name('two-factor.enable')
        ->middleware('throttle:auth');
    Route::post('two-factor/disable', [TwoFactorSetupController::class, 'disable'])
        ->name('two-factor.disable')
        ->middleware('throttle:auth');
    Route::post('two-factor/recovery-codes/regenerate', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes.regenerate')
        ->middleware('throttle:auth');

    Route::post('auth/google/unlink', [GoogleAuthController::class, 'unlink'])
        ->name('auth.google.unlink')
        ->middleware('throttle:auth');

    // Security settings
    Route::get('security', [SecuritySettingsController::class, 'show'])
        ->name('security.settings');
});
