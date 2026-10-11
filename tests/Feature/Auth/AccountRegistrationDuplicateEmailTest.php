<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountRegistrationDuplicateEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('identity.use_accounts', true);
    }

    public function test_existing_email_with_correct_password_is_rejected_without_login(): void
    {
        $existing = Account::create([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => Hash::make('CorrectPassword1'),
            'status' => Account::STATUS_ACTIVE,
        ]);

        $accountsBefore = Account::count();
        $tenantsBefore = Tenant::count();
        $subscriptionsBefore = Subscription::count();

        $response = $this->post('/register', [
            'name' => 'Someone Else',
            'email' => 'existing@example.com',
            'password' => 'CorrectPassword1',
            'password_confirmation' => 'CorrectPassword1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'This email already has an account. Please sign in to continue.',
            (string) session('errors')->get('email')[0]
        );
        $response->assertSessionHasInput('email', 'existing@example.com');
        $this->assertGuest('accounts');
        $this->assertGuest('web');
        $this->assertSame($accountsBefore, Account::count());
        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertSame($subscriptionsBefore, Subscription::count());
        $this->assertTrue(Hash::check('CorrectPassword1', $existing->fresh()->password));
    }

    public function test_existing_email_with_incorrect_password_is_rejected_without_login(): void
    {
        Account::create([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => Hash::make('CorrectPassword1'),
            'status' => Account::STATUS_ACTIVE,
        ]);

        $accountsBefore = Account::count();
        $tenantsBefore = Tenant::count();
        $subscriptionsBefore = Subscription::count();

        $response = $this->post('/register', [
            'name' => 'Someone Else',
            'email' => 'existing@example.com',
            'password' => 'WrongPassword1',
            'password_confirmation' => 'WrongPassword1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'This email already has an account. Please sign in to continue.',
            (string) session('errors')->get('email')[0]
        );
        $this->assertGuest('accounts');
        $this->assertGuest('web');
        $this->assertSame($accountsBefore, Account::count());
        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertSame($subscriptionsBefore, Subscription::count());
    }

    public function test_existing_legacy_user_email_is_rejected_in_account_mode(): void
    {
        User::create([
            'name' => 'Legacy',
            'email' => 'legacy@example.com',
            'password' => Hash::make('SecretPassword1'),
        ]);

        $accountsBefore = Account::count();
        $usersBefore = User::count();

        $response = $this->post('/register', [
            'name' => 'New Person',
            'email' => 'legacy@example.com',
            'password' => 'SecretPassword1',
            'password_confirmation' => 'SecretPassword1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('accounts');
        $this->assertGuest('web');
        $this->assertSame($usersBefore, User::count());
        $this->assertSame($accountsBefore, Account::count());
        $this->assertSame(1, User::where('email', 'legacy@example.com')->count());
    }

    public function test_new_email_registration_succeeds(): void
    {
        $response = $this->post('/register', [
            'name' => 'Fresh User',
            'email' => 'fresh@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $this->assertAuthenticatedAs(Account::where('email', 'fresh@example.com')->first(), 'accounts');
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseHas('accounts', ['email' => 'fresh@example.com']);
    }

    public function test_register_links_to_signin_and_login_provides_forgot_password(): void
    {
        $this->get('/login')->assertStatus(200);
        $this->get('/forgot-password')->assertStatus(200);

        $register = (string) file_get_contents(base_path('resources/js/Pages/Auth/Register.jsx'));
        $this->assertStringContainsString('/login', $register);
        $this->assertStringNotContainsString('/forgot-password', $register);

        $login = (string) file_get_contents(base_path('resources/js/Pages/Auth/Login.jsx'));
        $this->assertStringContainsString('/forgot-password', $login);
    }

    public function test_register_shows_single_accessible_duplicate_modal(): void
    {
        $register = (string) file_get_contents(base_path('resources/js/Pages/Auth/Register.jsx'));

        $this->assertStringContainsString('role="dialog"', $register);
        $this->assertStringContainsString('aria-modal="true"', $register);
        $this->assertStringContainsString('duplicate-email-title', $register);
        $this->assertStringContainsString('This email is already registered. Sign in to continue with your existing account.', $register);
        $this->assertStringContainsString('Sign In', $register);
        $this->assertStringContainsString('Close', $register);
        $this->assertStringContainsString('Escape', $register);
        $this->assertSame(1, substr_count($register, 'role="dialog"'));
        $this->assertStringNotContainsString('/forgot-password', $register);
    }

    public function test_register_support_footer_has_safe_configurable_fallback(): void
    {
        $register = (string) file_get_contents(base_path('resources/js/Pages/Auth/Register.jsx'));

        $this->assertStringContainsString('Need help creating your account?', $register);
        $this->assertStringContainsString('Contact Support Team', $register);
        $this->assertStringContainsString('support_page_url', $register);
        $this->assertStringContainsString('support_email', $register);
        $this->assertStringContainsString('mailto:', $register);
        $this->assertStringContainsString('aria-disabled', $register);
        $this->assertDoesNotMatchRegularExpression('#href="https?://[^\"]*(support|help)[^\"]*"#i', $register);
    }

    public function test_platform_settings_supports_configurable_support_page_url(): void
    {
        $this->assertTrue(Schema::hasColumn('platform_settings', 'support_page_url'));
        $this->assertContains('support_page_url', (new PlatformSetting())->getFillable());
    }
}
