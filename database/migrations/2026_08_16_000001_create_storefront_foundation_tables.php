<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('themes')) {
            Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('version', 32)->default('1.0.0');
            $table->json('default_tokens');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('storefronts')) {
            Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique('tenant_id');
            $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('storefront_theme_configs')) {
            Schema::create('storefront_theme_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique('storefront_id');
            $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('storefront_design_tokens')) {
            Schema::create('storefront_design_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->json('tokens');
            $table->timestamps();
            $table->unique('storefront_id');
            $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('storefront_homepage_sections')) {
            Schema::create('storefront_homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('variant')->default('default');
            $table->boolean('enabled')->default(true);
            $table->boolean('desktop_visible')->default(true);
            $table->boolean('mobile_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'storefront_id', 'enabled', 'position'], 'shs_tenant_store_enabled_pos_idx');
            });
        }

        if (!Schema::hasTable('storefront_media')) {
            Schema::create('storefront_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'key']);
            });
        }

        if (!Schema::hasTable('storefront_contents')) {
            Schema::create('storefront_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->json('labels')->nullable();
            $table->timestamps();
            $table->unique('storefront_id');
            $table->index('tenant_id');
            });
        }

        $tokens = json_encode([
            'color' => [
                'primary' => '#3B82F6',
                'surface' => '#FFFFFF',
                'background' => '#F9FAFB',
                'text' => '#111827',
                'muted' => '#6B7280',
                'border' => '#E5E7EB',
            ],
            'typography' => ['font_family' => 'Figtree', 'base_size' => '16px'],
            'spacing' => ['section' => '2rem', 'card' => '1rem', 'control' => '0.625rem'],
            'radius' => ['button' => '0.5rem', 'card' => '0.75rem', 'input' => '0.5rem'],
            'borders' => ['width' => '1px', 'style' => 'solid'],
            'shadows' => ['card' => '0 1px 3px 0 rgb(0 0 0 / 0.1)'],
            'buttons' => ['primary_style' => 'solid'],
            'cards' => ['style' => 'bordered'],
            'inputs' => ['style' => 'outlined'],
        ], JSON_THROW_ON_ERROR);

        DB::table('themes')->insertOrIgnore([
            'slug' => 'commerce-default',
            'name' => 'Commerce Default',
            'version' => '1.0.0',
            'default_tokens' => $tokens,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $themeId = DB::table('themes')->where('slug', 'commerce-default')->value('id');
        $labelDefaults = json_encode([
            'add_to_cart' => 'Add to Cart',
            'buy_now' => 'Buy Now',
            'buy' => 'Buy',
            'shop_now' => 'Shop Now',
            'view_product' => 'View Product',
            'view_cart' => 'View Cart',
            'checkout' => 'Checkout',
            'place_order' => 'Place Order',
            'continue_shopping' => 'Continue Shopping',
        ], JSON_THROW_ON_ERROR);

        DB::table('tenants')->orderBy('id')->each(function ($tenant) use ($themeId, $tokens, $labelDefaults) {
            $storefrontId = DB::table('storefronts')->where('tenant_id', $tenant->id)->value('id');
            if (!$storefrontId) {
                $storefrontId = DB::table('storefronts')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'theme_id' => $themeId,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('storefront_theme_configs')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'storefront_id' => $storefrontId,
                'theme_id' => $themeId,
                'configuration' => json_encode(['hero_variant' => 'auto'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('storefront_design_tokens')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'storefront_id' => $storefrontId,
                'tokens' => $tokens,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('storefront_contents')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'storefront_id' => $storefrontId,
                'labels' => $labelDefaults,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_contents');
        Schema::dropIfExists('storefront_media');
        Schema::dropIfExists('storefront_homepage_sections');
        Schema::dropIfExists('storefront_design_tokens');
        Schema::dropIfExists('storefront_theme_configs');
        Schema::dropIfExists('storefronts');
        Schema::dropIfExists('themes');
    }
};
