<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PromotionAccountUsageTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Account $account;
    private User $twinUser;
    private Promotion $promotion;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'PU Store', 'slug' => 'pu-store', 'status' => 'active']);
        Tenant::setCurrent($this->tenant);

        $this->twinUser = User::create([
            'name' => 'PU Customer',
            'email' => 'pu-customer@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->account = Account::where('email', 'pu-customer@test.com')->firstOrFail();
        DB::table('accounts')->where('id', $this->account->id)->update(['id' => $this->twinUser->id]);
        $this->account = Account::find($this->twinUser->id);

        $this->promotion = Promotion::create([
            'name' => 'PU Promo',
            'code' => 'PU-TEST',
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => 10,
            'is_active' => true,
            'is_automatic' => false,
            'applies_to' => Promotion::APPLIES_ALL,
        ]);

        $this->order = Order::create([
            'user_id' => $this->account->id,
            'user_type' => $this->account->getMorphClass(),
            'first_name' => 'PU',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'address' => 'Test address',
            'city' => 'Yangon',
            'subtotal' => 100000,
            'total_amount' => 90000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        app()->forgetInstance('current.tenant');
    }

    /** @test */
    public function record_usage_accepts_account_and_stores_morph_reference(): void
    {
        $usage = $this->promotion->recordUsage($this->order, $this->account, 10000);

        $this->assertInstanceOf(PromotionUsage::class, $usage);
        $this->assertSame($this->account->id, (int) $usage->user_id);
        $this->assertSame($this->account->getMorphClass(), $usage->user_type);
        $this->assertSame($this->order->id, (int) $usage->order_id);
        $this->assertSame(1, (int) $this->promotion->fresh()->usage_count);
    }

    /** @test */
    public function shared_order_path_records_usage_for_account_customer(): void
    {
        $this->order->setRelation('user', $this->account);

        app(PromotionService::class)->applyPromotionToOrder($this->order, $this->promotion, 10000);

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $this->promotion->id,
            'order_id' => $this->order->id,
            'user_id' => $this->account->id,
            'user_type' => $this->account->getMorphClass(),
        ]);
    }

    /** @test */
    public function per_customer_limit_is_scoped_by_identity_type(): void
    {
        $this->promotion->update(['per_customer_limit' => 1]);
        $this->promotion->recordUsage($this->order, $this->account, 10000);

        $this->assertFalse($this->promotion->canBeUsedBy($this->account));
        $this->assertTrue($this->promotion->canBeUsedBy($this->twinUser));
    }

    /** @test */
    public function coupon_validation_resolves_account_customer_for_limits(): void
    {
        $this->actingAs($this->account, 'accounts');
        $this->promotion->update(['per_customer_limit' => 1]);

        $service = app(PromotionService::class);
        $result = $service->validateCouponCode('PU-TEST', collect([]), $this->account->id, 0);
        $this->assertTrue($result['valid']);

        $this->promotion->recordUsage($this->order, $this->account, 10000);

        $again = $service->validateCouponCode('PU-TEST', collect([]), $this->account->id, 0);
        $this->assertFalse($again['valid']);
    }
}
