<?php

namespace Tests\Feature;

use App\Models\CodRule;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\CodConsolidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodConsolidationDryRunTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private int $ruleA1;
    private int $ruleA2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Store A', 'slug' => 'cons-a',
            'store_url' => '/store/cons-a', 'status' => 'active',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Store B', 'slug' => 'cons-b',
            'store_url' => '/store/cons-b', 'status' => 'active',
        ]);

        $this->ruleA1 = CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Standard',
            'max_order_amount' => 1000000, 'is_active' => true,
        ])->id;
        $this->ruleA2 = CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'High Value',
            'min_order_amount' => 1000001, 'is_active' => true,
        ])->id;
    }

    private function map(): array
    {
        return [
            $this->tenantA->id => [
                'keep' => $this->ruleA1,
                'expected' => [$this->ruleA1, $this->ruleA2],
            ],
        ];
    }

    /** @test */
    public function correct_mapping_is_accepted_with_expected_plan(): void
    {
        $plan = app(CodConsolidationService::class)->plan($this->map());

        $this->assertEmpty($plan['errors']);
        $this->assertEquals($this->ruleA1, $plan['tenants'][$this->tenantA->id]['keep']);
        $this->assertEquals([$this->ruleA2], $plan['tenants'][$this->tenantA->id]['deactivate']);
        $this->assertContains($this->tenantB->id, $plan['noop']);
        $this->assertArrayHasKey(1000, $plan['tenants'][$this->tenantA->id]['spot_check']);
    }

    /** @test */
    public function wrong_tenant_rule_mapping_is_rejected(): void
    {
        $foreign = CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Foreign', 'is_active' => true,
        ]);

        $map = $this->map();
        $map[$this->tenantA->id]['keep'] = $foreign->id;

        $plan = app(CodConsolidationService::class)->plan($map);

        $this->assertNotEmpty($plan['errors']);
        $this->assertArrayNotHasKey($this->tenantA->id, $plan['tenants']);
    }

    /** @test */
    public function unexpected_rule_set_is_rejected(): void
    {
        CodRule::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Surprise', 'is_active' => true,
        ]);

        $plan = app(CodConsolidationService::class)->plan($this->map());

        $this->assertNotEmpty($plan['errors']);
        $this->assertArrayNotHasKey($this->tenantA->id, $plan['tenants']);
    }

    /** @test */
    public function zero_rule_tenant_mapping_is_rejected_and_untouched(): void
    {
        $map = [
            $this->tenantB->id => ['keep' => 999999, 'expected' => [999999]],
        ];

        $plan = app(CodConsolidationService::class)->plan($map);

        $this->assertNotEmpty($plan['errors']);
        $this->assertEquals(0, CodRule::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());
    }

    /** @test */
    public function dry_run_makes_no_database_changes(): void
    {
        $before = CodRule::withoutTenantScope()->orderBy('id')->get(['id', 'is_active'])->toArray();

        $this->artisan('cod:consolidate', [
            '--map' => ["{$this->tenantA->id}:{$this->ruleA1}:{$this->ruleA1},{$this->ruleA2}"],
        ])->assertSuccessful();

        $this->assertEquals(
            $before,
            CodRule::withoutTenantScope()->orderBy('id')->get(['id', 'is_active'])->toArray()
        );
    }

    /** @test */
    public function invalid_map_format_is_rejected(): void
    {
        $this->artisan('cod:consolidate', ['--map' => ['bogus']])->assertFailed();
    }

    /** @test */
    public function apply_mode_deactivates_only_mapped_superseded_rules(): void
    {
        $this->artisan('cod:consolidate', [
            '--map' => ["{$this->tenantA->id}:{$this->ruleA1}:{$this->ruleA1},{$this->ruleA2}"],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertTrue((bool) CodRule::withoutTenantScope()->find($this->ruleA1)->is_active);
        $this->assertFalse((bool) CodRule::withoutTenantScope()->find($this->ruleA2)->is_active);
        $this->assertEquals(0, Order::count());
    }
}
