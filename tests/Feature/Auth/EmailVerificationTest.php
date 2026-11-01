<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();
        $account = Account::where('email', $user->email)->firstOrFail();

        $response = $this->actingAs($account, 'accounts')->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $account = Account::where('email', $user->email)->firstOrFail();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $account->id, 'hash' => sha1($account->email)]
        );

        $response = $this->actingAs($account, 'accounts')->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($account->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('onboarding.store'));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $account = Account::where('email', $user->email)->firstOrFail();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $account->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($account, 'accounts')->get($verificationUrl);

        $this->assertFalse($account->fresh()->hasVerifiedEmail());
    }
}
