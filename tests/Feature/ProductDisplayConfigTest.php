<?php

namespace Tests\Feature;

use App\Models\Storefront;
use App\Models\StorefrontProductDisplayConfig;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductDisplayConfigTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function defaults_cover_all_setting_groups(): void
    {
        $defaults = StorefrontProductDisplayConfig::getDefaults();

        $this->assertSame(
            ['product_info', 'pricing', 'stock', 'actions'],
            array_keys($defaults)
        );
        $this->assertTrue($defaults['pricing']['show_current_price']);
        $this->assertTrue($defaults['actions']['show_buy_now']);
        $this->assertSame('status', $defaults['stock']['display_mode']);
        $this->assertSame(10, $defaults['stock']['low_stock_threshold']);
    }

    /** @test */
    public function resolve_falls_back_to_defaults_for_missing_or_corrupt_input(): void
    {
        foreach ([null, 'oops', [], ['stock' => null]] as $input) {
            $resolved = StorefrontProductDisplayConfig::resolveConfiguration($input);
            $this->assertSame(StorefrontProductDisplayConfig::getDefaults(), $resolved);
        }
    }

    /** @test */
    public function resolve_merges_partial_config_and_sanitizes_values(): void
    {
        $resolved = StorefrontProductDisplayConfig::resolveConfiguration([
            'stock' => ['display_mode' => 'hidden'],
            'actions' => ['show_wishlist' => 'false', 'show_buy_now' => 0],
        ]);

        $this->assertSame('hidden', $resolved['stock']['display_mode']);
        $this->assertSame(10, $resolved['stock']['low_stock_threshold']);
        $this->assertFalse($resolved['actions']['show_wishlist']);
        $this->assertFalse($resolved['actions']['show_buy_now']);
        $this->assertTrue($resolved['actions']['show_add_to_cart']);

        $invalid = StorefrontProductDisplayConfig::resolveConfiguration([
            'stock' => ['display_mode' => 'everything', 'low_stock_threshold' => -5],
            'pricing' => ['show_current_price' => false],
        ]);

        $this->assertSame('status', $invalid['stock']['display_mode']);
        $this->assertSame(0, $invalid['stock']['low_stock_threshold']);
        $this->assertTrue($invalid['pricing']['show_current_price']);
    }

    /** @test */
    public function tenant_configs_are_isolated_by_tenant_scope(): void
    {
        $tenantA = Tenant::create(['name' => 'PD Store A', 'slug' => 'pd-store-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'PD Store B', 'slug' => 'pd-store-b', 'status' => 'active']);

        $storefrontA = Storefront::withoutTenantScope()->create(['tenant_id' => $tenantA->id, 'status' => 'active']);

        StorefrontProductDisplayConfig::withoutTenantScope()->create([
            'tenant_id' => $tenantA->id,
            'storefront_id' => $storefrontA->id,
            'configuration' => ['stock' => ['display_mode' => 'hidden']],
        ]);

        Tenant::setCurrent($tenantB);
        $this->assertNull(StorefrontProductDisplayConfig::first());

        Tenant::setCurrent($tenantA);
        $visible = StorefrontProductDisplayConfig::first();
        $this->assertNotNull($visible);
        $this->assertSame('hidden', $visible->configuration['stock']['display_mode']);

        Tenant::setCurrent(null);
    }
}

