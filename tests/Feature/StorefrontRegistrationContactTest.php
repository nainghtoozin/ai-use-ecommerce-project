<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StorefrontRegistrationContactTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private City $city;
    private Township $township;
    private Township $otherTownship;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'RG Store', 'slug' => 'rg-store', 'status' => 'active']);

        $this->city = City::create(['name' => 'Yangon', 'delivery_fee' => 3000, 'is_active' => true]);
        $otherCity = City::create(['name' => 'Mandalay', 'delivery_fee' => 4000, 'is_active' => true]);

        $this->township = Township::create([
            'city_id' => $this->city->id, 'name' => 'Kamaryut', 'postal_code' => '11041', 'is_active' => true,
        ]);
        $this->otherTownship = Township::create([
            'city_id' => $otherCity->id, 'name' => 'Chanmyathazi', 'postal_code' => '05001', 'is_active' => true,
        ]);
    }

    private function register(array $overrides = [])
    {
        return $this->post("/store/{$this->tenant->slug}/register", array_merge([
            'name' => 'RG Customer',
            'email' => 'rg-customer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides));
    }

    /** @test */
    public function required_only_registration_still_works(): void
    {
        $this->register(['phone' => '09111111111'])->assertRedirect();

        $account = Account::where('email', 'rg-customer@test.com')->first();
        $this->assertNotNull($account);
        $this->assertTrue(TenantMembership::where('account_id', $account->id)->where('tenant_id', $this->tenant->id)->exists());
        $this->assertFalse(CustomerAddress::where('user_id', $account->id)->exists());
        $this->assertAuthenticated('accounts');
    }

    /** @test */
    public function full_optional_contact_is_stored_for_checkout_reuse(): void
    {
        $this->register([
            'phone' => '09123456789',
            'address' => 'No. 123, Test Street',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
            'postal_code' => '',
        ])->assertRedirect();

        $account = Account::where('email', 'rg-customer@test.com')->first();
        $membership = TenantMembership::where('account_id', $account->id)->where('tenant_id', $this->tenant->id)->first();

        $this->assertSame('09123456789', CustomerProfile::where('tenant_membership_id', $membership->id)->first()->phone);

        $address = CustomerAddress::where('user_id', $account->id)->first();
        $this->assertNotNull($address);
        $this->assertSame('No. 123, Test Street', $address->address_line);
        $this->assertSame($this->city->id, (int) $address->city_id);
        $this->assertSame($this->township->id, (int) $address->township_id);
        $this->assertSame('11041', $address->postal_code);
        $this->assertTrue((bool) $address->is_default);
        $this->assertSame('09123456789', $address->phone);
    }

    /** @test */
    public function mismatched_township_is_rejected_without_creating_account(): void
    {
        $this->register([
            'phone' => '09123456789',
            'address' => 'No. 123, Test Street',
            'city_id' => $this->city->id,
            'township_id' => $this->otherTownship->id,
        ])->assertSessionHasErrors('township_id');

        $this->assertNull(Account::where('email', 'rg-customer@test.com')->first());
    }

    /** @test */
    public function missing_phone_is_rejected(): void
    {
        $this->register(['address' => 'No. 123, Test Street'])
            ->assertSessionHasErrors('phone');

        $this->assertNull(Account::where('email', 'rg-customer@test.com')->first());
    }

    /** @test */
    public function city_only_without_street_registers_without_address_row(): void
    {
        $this->register([
            'phone' => '09222222222',
            'city_id' => $this->city->id,
            'township_id' => $this->township->id,
        ])->assertRedirect();

        $account = Account::where('email', 'rg-customer@test.com')->first();
        $this->assertNotNull($account);
        $this->assertFalse(CustomerAddress::where('user_id', $account->id)->exists());
    }

    /** @test */
    public function duplicate_store_registration_is_rejected(): void
    {
        $this->register(['phone' => '09333333333'])->assertRedirect();

        $this->post("/store/{$this->tenant->slug}/register", [
            'name' => 'RG Customer',
            'email' => 'rg-customer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '09333333333',
        ])->assertSessionHasErrors('email');
    }
}
