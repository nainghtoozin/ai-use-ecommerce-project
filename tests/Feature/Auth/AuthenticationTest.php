<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        $account = Account::where('email', $user->email)->firstOrFail();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($account, 'accounts');
        $response->assertRedirect(route('onboarding.store', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('accounts');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $account = Account::where('email', $user->email)->firstOrFail();

        $response = $this->actingAs($account, 'accounts')->post('/logout');

        $this->assertGuest('accounts');
        $response->assertRedirect('/');
    }
}
