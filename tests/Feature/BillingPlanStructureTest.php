<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingPlanStructureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPlanSchema();
    }

    public function test_seeder_provides_starter_pro_business_structure(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlanSeeder']);

        $starter = Plan::where('slug', 'free')->firstOrFail();
        $pro = Plan::where('slug', 'starter')->firstOrFail();
        $business = Plan::where('slug', 'business')->firstOrFail();

        $this->assertSame('Starter', $starter->name);
        $this->assertSame('Pro', $pro->name);
        $this->assertSame('Business', $business->name);
    }

    public function test_starter_is_free_trial_plan_and_pro_business_are_paid(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlanSeeder']);

        $starter = Plan::where('slug', 'free')->firstOrFail();
        $pro = Plan::where('slug', 'starter')->firstOrFail();
        $business = Plan::where('slug', 'business')->firstOrFail();

        $this->assertTrue($starter->isFree());
        $this->assertEqualsWithDelta(0, (float) $starter->monthly_price, 0.001);

        $this->assertFalse($pro->isFree());
        $this->assertFalse($business->isFree());
        $this->assertEqualsWithDelta(50000, (float) $pro->monthly_price, 0.001);
        $this->assertEqualsWithDelta(1000000, (float) $business->monthly_price, 0.001);
    }

    public function test_seeder_preserves_plan_ids_on_re_run(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlanSeeder']);
        $idsBefore = Plan::whereIn('slug', ['free', 'starter', 'business'])
            ->orderBy('slug')->pluck('id', 'slug')->all();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlanSeeder']);
        $idsAfter = Plan::whereIn('slug', ['free', 'starter', 'business'])
            ->orderBy('slug')->pluck('id', 'slug')->all();

        $this->assertSame($idsBefore, $idsAfter);
        $this->assertSame('Starter', Plan::where('slug', 'free')->first()->name);
        $this->assertSame('Pro', Plan::where('slug', 'starter')->first()->name);
    }

    public function test_trial_duration_defaults_to_14_days(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlatformSettingSeeder']);

        $this->assertTrue(PlatformSetting::current()->trial_enabled);
        $this->assertSame(14, PlatformSetting::current()->trial_days);
    }

    public function test_superadmin_can_change_trial_duration_without_code(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PlatformSettingSeeder']);

        app(\App\Services\PlatformSettingService::class)->update(['trial_days' => 30]);

        $this->assertSame(30, PlatformSetting::current()->trial_days);
    }

    private function createPlanSchema(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        foreach ([
            'description' => 'text',
            'price' => 'decimal',
            'monthly_price' => 'decimal',
            'yearly_price' => 'decimal',
            'currency' => 'string',
            'interval' => 'string',
            'product_limit' => 'integer',
            'staff_limit' => 'integer',
            'storage_limit' => 'integer',
            'orders_monthly_limit' => 'integer',
            'coupon_limit' => 'integer',
            'promotion_limit' => 'integer',
            'flash_sale_limit' => 'integer',
            'api_request_limit' => 'integer',
            'image_limit' => 'integer',
            'image_max_size_kb' => 'integer',
            'branch_limit' => 'integer',
            'warehouse_limit' => 'integer',
            'pos_device_limit' => 'integer',
            'analytics_enabled' => 'boolean',
            'custom_domain_enabled' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'string',
        ] as $column => $type) {
            if (!Schema::hasColumn('plans', $column)) {
                Schema::table('plans', function ($table) use ($column, $type) {
                    match ($type) {
                        'text' => $table->text($column)->nullable(),
                        'decimal' => $table->decimal($column, 10, 2)->nullable(),
                        'integer' => $table->integer($column)->nullable(),
                        'boolean' => $table->boolean($column)->default(false),
                        default => $table->string($column)->nullable(),
                    };
                });
            }
        }

        if (!Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function ($table) {
                $table->id();
                $table->unsignedBigInteger('plan_id');
                $table->string('feature_key');
                $table->boolean('is_enabled')->default(true);
                $table->string('display_label')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['plan_id', 'feature_key']);
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('site_logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('support_email')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('registration_enabled')->default(true);
                $table->boolean('trial_enabled')->default(true);
                $table->integer('trial_days')->default(14);
                $table->boolean('allow_trial_renewal')->default(false);
                $table->integer('max_trial_renewals')->default(0);
                $table->integer('audit_retention_days')->nullable();
                $table->string('platform_currency_code')->nullable();
                $table->string('platform_currency_symbol')->nullable();
                $table->string('platform_currency_position')->nullable();
                $table->integer('platform_decimal_places')->nullable();
                $table->timestamps();
            });
        }
    }
}
