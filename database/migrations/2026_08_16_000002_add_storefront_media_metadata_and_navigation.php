<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_media', function (Blueprint $table) {
            if (!Schema::hasColumn('storefront_media', 'original_name')) {
                $table->string('original_name')->nullable()->after('path');
            }
            if (!Schema::hasColumn('storefront_media', 'mime_type')) {
                $table->string('mime_type', 100)->nullable()->after('original_name');
            }
            if (!Schema::hasColumn('storefront_media', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mime_type');
            }
        });

        if (!Schema::hasTable('storefront_navigations')) {
            Schema::create('storefront_navigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('storefront_id');
            $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('storefront_navigation_items')) {
            Schema::create('storefront_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('navigation_id')->constrained('storefront_navigations')->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('path', 255);
            $table->string('icon', 50)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['navigation_id', 'key']);
                $table->index(['tenant_id', 'navigation_id', 'enabled', 'position'], 'storefront_nav_items_tenant_pos_idx');
            });
        } elseif (!Schema::hasIndex('storefront_navigation_items', 'storefront_nav_items_tenant_pos_idx')) {
            Schema::table('storefront_navigation_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'navigation_id', 'enabled', 'position'], 'storefront_nav_items_tenant_pos_idx');
            });
        }

        $storefronts = DB::table('storefronts')->orderBy('id')->get(['id', 'tenant_id']);
        foreach ($storefronts as $storefront) {
            $navigationId = DB::table('storefront_navigations')->where('storefront_id', $storefront->id)->value('id');
            if (!$navigationId) {
                $navigationId = DB::table('storefront_navigations')->insertGetId([
                    'tenant_id' => $storefront->tenant_id,
                    'storefront_id' => $storefront->id,
                    'settings' => json_encode(['show_store_name' => true, 'show_search' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ([
                ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door'],
                ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid'],
                ['key' => 'contact', 'label' => 'Contact', 'path' => '/contact', 'icon' => 'bi-envelope'],
                ['key' => 'orders', 'label' => 'My Orders', 'path' => '/customer/orders', 'icon' => 'bi-receipt'],
            ] as $position => $item) {
                DB::table('storefront_navigation_items')->insertOrIgnore([
                    'tenant_id' => $storefront->tenant_id,
                    'navigation_id' => $navigationId,
                    ...$item,
                    'enabled' => true,
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_navigation_items');
        Schema::dropIfExists('storefront_navigations');
        Schema::table('storefront_media', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'size']);
        });
    }
};
