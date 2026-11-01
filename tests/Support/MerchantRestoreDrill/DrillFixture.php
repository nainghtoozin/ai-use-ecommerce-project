<?php

namespace Tests\Support\MerchantRestoreDrill;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DrillFixture
{
    public string $marker;
    public int $tenantA;
    public int $tenantB;
    public int $customerA;
    public int $customerWithoutOrders;
    public ?int $membershipA = null;
    public ?int $membershipWithoutOrders = null;
    public string $customerMorphType;

    public int $categoryParent;
    public int $categoryChild;
    public int $brand;
    public int $unit;
    public int $productSimple;
    public int $productVariable;
    public int $variant;
    public int $productCombo;
    public int $warehouse;
    public int $city;
    public int $township;
    public int $deliveryService;
    public int $paymentMethod;
    public int $packaging;
    public int $coupon;
    public int $promotion;
    public int $flashSale;
    public int $order;
    public int $storefront;
    public int $storefrontMedia;
    public int $revision;
    public int $importHistory;
    public string $assetPath;
    public string $assetAbsolute;

    public function create(): void
    {
        $this->marker = 'RESTORE-DRILL-' . Str::upper(Str::random(10));
        $now = now();

        $this->tenantA = DB::table('tenants')->insertGetId([
            'name' => 'Restore Drill Store A ' . $this->marker,
            'slug' => Str::slug($this->marker) . '-a',
            'store_url' => '/store/' . Str::slug($this->marker) . '-a',
            'email' => 'drill-a@example.test',
            'status' => 'active',
            'settings' => json_encode(['language' => 'en', 'theme' => 'light']),
            'used_storage_bytes' => 0,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->tenantB = DB::table('tenants')->insertGetId([
            'name' => 'Restore Drill Store B ' . $this->marker,
            'slug' => Str::slug($this->marker) . '-b',
            'store_url' => '/store/' . Str::slug($this->marker) . '-b',
            'email' => 'drill-b@example.test',
            'status' => 'active',
            'settings' => json_encode(['language' => 'en']),
            'used_storage_bytes' => 0,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (config('identity.use_accounts')) {
            $this->customerMorphType = Account::class;

            $customerRole = DB::table('roles')->insertGetId([
                'tenant_id' => $this->tenantA,
                'name' => 'customer',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->customerA = DB::table('accounts')->insertGetId([
                'name' => 'Drill Customer A',
                'email' => 'customer-a-' . Str::lower($this->marker) . '@example.test',
                'password' => bcrypt('password'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->membershipA = DB::table('tenant_memberships')->insertGetId([
                'account_id' => $this->customerA,
                'tenant_id' => $this->tenantA,
                'role_id' => $customerRole,
                'is_owner' => 0,
                'status' => 'active',
                'joined_at' => $now,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('customer_profiles')->insert([
                'tenant_membership_id' => $this->membershipA,
                'name' => 'Drill Customer A',
                'phone' => '09123456789',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->customerWithoutOrders = DB::table('accounts')->insertGetId([
                'name' => 'Drill Customer Without Orders',
                'email' => 'customer-no-orders-' . Str::lower($this->marker) . '@example.test',
                'password' => bcrypt('password'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->membershipWithoutOrders = DB::table('tenant_memberships')->insertGetId([
                'account_id' => $this->customerWithoutOrders,
                'tenant_id' => $this->tenantA,
                'role_id' => $customerRole,
                'is_owner' => 0,
                'status' => 'active',
                'joined_at' => $now,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('customer_profiles')->insert([
                'tenant_membership_id' => $this->membershipWithoutOrders,
                'name' => 'Drill Customer Without Orders',
                'phone' => '09999999999',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $this->customerMorphType = User::class;

            $customerRole = DB::table('roles')->insertGetId([
                'tenant_id' => $this->tenantA,
                'name' => 'customer',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->customerA = DB::table('users')->insertGetId([
                'tenant_id' => $this->tenantA,
                'name' => 'Drill Customer A',
                'email' => 'customer-a-' . Str::lower($this->marker) . '@example.test',
                'password' => bcrypt('password'),
                'status' => 'active',
                'is_owner' => 0,
                'allow_cod' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->customerWithoutOrders = DB::table('users')->insertGetId([
                'tenant_id' => $this->tenantA,
                'name' => 'Drill Customer Without Orders',
                'email' => 'customer-no-orders-' . Str::lower($this->marker) . '@example.test',
                'password' => bcrypt('password'),
                'status' => 'active',
                'is_owner' => 0,
                'allow_cod' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([$this->customerA, $this->customerWithoutOrders] as $userId) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $customerRole,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ]);
            }
        }
        $this->categoryParent = DB::table('categories')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Parent Category ' . $this->marker,
            'slug' => 'drill-parent-' . Str::lower($this->marker),
            'is_active' => 1,
            'featured' => 0,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->categoryChild = DB::table('categories')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Child Category ' . $this->marker,
            'slug' => 'drill-child-' . Str::lower($this->marker),
            'parent_id' => $this->categoryParent,
            'is_active' => 1,
            'featured' => 1,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryB = DB::table('categories')->insertGetId([
            'tenant_id' => $this->tenantB,
            'name' => 'Drill Tenant B Category ' . $this->marker,
            'slug' => 'drill-b-' . Str::lower($this->marker),
            'is_active' => 1,
            'featured' => 0,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->brand = DB::table('brands')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Brand ' . $this->marker,
            'slug' => 'drill-brand-' . Str::lower($this->marker),
            'is_active' => 1,
            'featured' => 1,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->unit = DB::table('units')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Unit ' . $this->marker,
            'short_name' => 'DRU',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->city = DB::table('cities')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill City ' . $this->marker,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->township = DB::table('townships')->insertGetId([
            'tenant_id' => $this->tenantA,
            'city_id' => $this->city,
            'name' => 'Drill Township ' . $this->marker,
            'postal_code' => 'DR-1000',
            'delivery_fee' => 1500,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->warehouse = DB::table('warehouses')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Warehouse ' . $this->marker,
            'code' => 'DRW-' . Str::upper(Str::random(6)),
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->deliveryService = DB::table('delivery_services')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Delivery ' . $this->marker,
            'code' => 'DRD-' . Str::upper(Str::random(6)),
            'base_fee' => 2000,
            'fee_per_kg' => 100,
            'min_days' => 1,
            'max_days' => 3,
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->paymentMethod = DB::table('payment_methods')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Bank Transfer ' . $this->marker,
            'display_name' => 'Drill Bank Transfer',
            'slug' => 'drill-bank-' . Str::lower($this->marker),
            'type' => 'bank_transfer',
            'account_name' => 'Drill Merchant',
            'account_number' => '000-000-000',
            'bank_name' => 'Drill Bank',
            'branch' => 'Drill Branch',
            'instructions' => 'Synthetic drill instructions',
            'currency' => 'MMK',
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->packaging = DB::table('packaging_options')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Packaging ' . $this->marker,
            'code' => 'DRP-' . Str::upper(Str::random(6)),
            'fee' => 300,
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assetPath = 'restore-drill/' . Str::lower($this->marker) . '.txt';
        $disk = Storage::disk((string) config('filesystems.default', 'public'));
        $disk->put($assetPath, 'Synthetic restore drill asset ' . $this->marker);
        $this->assetPath = $assetPath;
        $this->assetAbsolute = $disk->path($assetPath);

        $this->productSimple = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Simple Product ' . $this->marker,
            'slug' => 'drill-simple-' . Str::lower($this->marker),
            'sku' => 'DR-SIMPLE-' . Str::upper(Str::random(6)),
            'price' => 12000,
            'sale_price' => 11000,
            'base_price' => 10000,
            'cost_price' => 9000,
            'stock' => 7,
            'low_stock_alert' => 2,
            'category_id' => $this->categoryChild,
            'brand_id' => $this->brand,
            'unit_id' => $this->unit,
            'type' => 'single',
            'status' => 'active',
            'featured' => 1,
            'sort_order' => 1,
            'photo1' => $assetPath,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('products')->insert([
            'tenant_id' => $this->tenantB,
            'name' => 'Drill Tenant B Product ' . $this->marker,
            'slug' => 'drill-b-product-' . Str::lower($this->marker),
            'price' => 99000,
            'base_price' => 99000,
            'stock' => 99,
            'low_stock_alert' => 1,
            'category_id' => $categoryB,
            'type' => 'single',
            'status' => 'active',
            'featured' => 0,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productVariable = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Variable Product ' . $this->marker,
            'slug' => 'drill-variable-' . Str::lower($this->marker),
            'sku' => 'DR-VARIABLE-' . Str::upper(Str::random(6)),
            'price' => 25000,
            'base_price' => 25000,
            'stock' => 0,
            'low_stock_alert' => 2,
            'category_id' => $this->categoryChild,
            'brand_id' => $this->brand,
            'unit_id' => $this->unit,
            'type' => 'variable',
            'status' => 'active',
            'featured' => 0,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->variant = DB::table('product_variants')->insertGetId([
            'tenant_id' => $this->tenantA,
            'product_id' => $this->productVariable,
            'sku' => 'DR-VARIANT-' . Str::upper(Str::random(6)),
            'price' => 26000,
            'compare_price' => 28000,
            'cost_price' => 18000,
            'stock' => 4,
            'low_stock_threshold' => 2,
            'attributes' => json_encode(['size' => 'M', 'color' => 'Blue']),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productCombo = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Combo Product ' . $this->marker,
            'slug' => 'drill-combo-' . Str::lower($this->marker),
            'sku' => 'DR-COMBO-' . Str::upper(Str::random(6)),
            'price' => 32000,
            'base_price' => 36000,
            'stock' => 0,
            'low_stock_alert' => 1,
            'category_id' => $this->categoryChild,
            'brand_id' => $this->brand,
            'unit_id' => $this->unit,
            'type' => 'combo',
            'status' => 'active',
            'featured' => 0,
            'sort_order' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('product_combos')->insert([
            'tenant_id' => $this->tenantA,
            'product_id' => $this->productCombo,
            'combo_product_id' => $this->productSimple,
            'linked_variant_id' => null,
            'quantity' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('stock_movements')->insert([
            [
                'tenant_id' => $this->tenantA,
                'product_id' => $this->productSimple,
                'product_variant_id' => null,
                'warehouse_id' => $this->warehouse,
                'type' => 'opening_stock',
                'adjustment_number' => 'DR-OPEN-' . Str::upper(Str::random(6)),
                'quantity' => 10,
                'unit_price' => 9000,
                'reference_type' => null,
                'reference_id' => null,
                'description' => 'Synthetic opening stock',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->tenantA,
                'product_id' => $this->productSimple,
                'product_variant_id' => null,
                'warehouse_id' => $this->warehouse,
                'type' => 'sale',
                'adjustment_number' => 'DR-SALE-' . Str::upper(Str::random(6)),
                'quantity' => -3,
                'unit_price' => 11000,
                'reference_type' => 'order',
                'reference_id' => null,
                'description' => 'Synthetic sale movement',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->tenantA,
                'product_id' => $this->productVariable,
                'product_variant_id' => $this->variant,
                'warehouse_id' => $this->warehouse,
                'type' => 'opening_stock',
                'adjustment_number' => 'DR-VOPEN-' . Str::upper(Str::random(6)),
                'quantity' => 4,
                'unit_price' => 18000,
                'reference_type' => null,
                'reference_id' => null,
                'description' => 'Synthetic variant opening stock',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('delivery_pricing')->insert([
            'delivery_service_id' => $this->deliveryService,
            'township_id' => $this->township,
            'min_days' => 1,
            'max_days' => 3,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('cod_rules')->insert([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill COD Rule ' . $this->marker,
            'is_active' => 1,
            'min_order_amount' => 1000,
            'max_order_amount' => 100000,
            'allowed_city_ids' => json_encode([$this->city]),
            'excluded_city_ids' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->coupon = DB::table('coupons')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Coupon ' . $this->marker,
            'description' => 'Synthetic coupon',
            'code' => 'DRCOUP-' . Str::upper(Str::random(6)),
            'type' => 'fixed_amount',
            'discount_value' => 500,
            'min_order_amount' => 1000,
            'discount_cap' => 500,
            'usage_limit' => 10,
            'per_customer_limit' => 1,
            'used_count' => 1,
            'is_active' => 1,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addMonth(),
            'priority' => 1,
            'is_stackable' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('coupon_product')->insert([
            'coupon_id' => $this->coupon,
            'product_id' => $this->productSimple,
            'tenant_id' => $this->tenantA,
        ]);

        DB::table('coupon_category')->insert([
            'coupon_id' => $this->coupon,
            'category_id' => $this->categoryChild,
            'tenant_id' => $this->tenantA,
        ]);

        $this->promotion = DB::table('promotions')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Promotion ' . $this->marker,
            'code' => 'DRPROMO-' . Str::upper(Str::random(6)),
            'description' => 'Synthetic promotion',
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => 1000,
            'minimum_order_amount' => 1000,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addMonth(),
            'usage_limit' => 20,
            'usage_count' => 1,
            'per_customer_limit' => 1,
            'is_active' => 1,
            'is_automatic' => 0,
            'applies_to' => 'all',
            'priority' => 1,
            'stackable' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('promotion_product')->insert([
            'promotion_id' => $this->promotion,
            'product_id' => $this->productSimple,
            'tenant_id' => $this->tenantA,
        ]);

        DB::table('promotion_category')->insert([
            'promotion_id' => $this->promotion,
            'category_id' => $this->categoryChild,
            'tenant_id' => $this->tenantA,
        ]);

        $this->flashSale = DB::table('flash_sales')->insertGetId([
            'tenant_id' => $this->tenantA,
            'name' => 'Drill Flash Sale ' . $this->marker,
            'description' => 'Synthetic flash sale',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'max_discount_amount' => 500,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addDay(),
            'is_active' => 1,
            'priority' => 1,
            'usage_limit' => 5,
            'usage_count' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $flashSaleProductId = DB::table('flash_sale_products')->insertGetId([
            'flash_sale_id' => $this->flashSale,
            'product_id' => $this->productSimple,
            'variant_id' => null,
            'flash_price' => 10500,
            'quantity_limit' => 5,
            'quantity_sold' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->order = DB::table('orders')->insertGetId([
            'tenant_id' => $this->tenantA,
            'user_id' => $this->customerA,
            'user_type' => $this->customerMorphType,
            'tenant_membership_id' => $this->membershipA,
            'invoice_number' => 'DR-INV-' . Str::upper(Str::random(8)),
            'customer_name' => 'Drill Customer A',
            'first_name' => 'Drill',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'customer-a-' . Str::lower($this->marker) . '@example.test',
            'address' => 'Synthetic Drill Address',
            'city_id' => $this->city,
            'township_id' => $this->township,
            'postal_code' => 'DR-1000',
            'payment_method_id' => $this->paymentMethod,
            'delivery_service_id' => $this->deliveryService,
            'packaging_id' => $this->packaging,
            'packaging_fee' => 300,
            'cod_fee' => 0,
            'delivery_days_min' => 1,
            'delivery_days_max' => 3,
            'subtotal' => 52500,
            'discount_amount' => 1000,
            'delivery_fee' => 2000,
            'total_amount' => 53800,
            'paid_amount' => 53800,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'stock_reduced' => 1,
            'promotion_id' => $this->promotion,
            'promotion_code' => DB::table('promotions')->where('id', $this->promotion)->value('code'),
            'payment_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_items')->insert([
            [
                'tenant_id' => $this->tenantA,
                'order_id' => $this->order,
                'product_id' => $this->productSimple,
                'variant_id' => null,
                'flash_sale_id' => $flashSaleProductId ? $this->flashSale : null,
                'original_price' => 12000,
                'quantity' => 1,
                'price' => 11000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->tenantA,
                'order_id' => $this->order,
                'product_id' => $this->productVariable,
                'variant_id' => $this->variant,
                'flash_sale_id' => null,
                'original_price' => 28000,
                'quantity' => 1,
                'price' => 26000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('order_coupon')->insert([
            'tenant_id' => $this->tenantA,
            'order_id' => $this->order,
            'coupon_id' => $this->coupon,
            'promotion_id' => $this->promotion,
            'code' => DB::table('coupons')->where('id', $this->coupon)->value('code'),
            'type' => 'fixed_amount',
            'discount_amount' => 500,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_override_logs')->insert([
            'tenant_id' => $this->tenantA,
            'order_id' => $this->order,
            'user_id' => $this->customerA,
            'user_type' => $this->customerMorphType,
            'field' => 'phone',
            'old_value' => '09000000000',
            'new_value' => '09123456789',
            'reason' => 'Synthetic override',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('promotion_usages')->insert([
            'tenant_id' => $this->tenantA,
            'promotion_id' => $this->promotion,
            'user_id' => $this->customerA,
            'user_type' => $this->customerMorphType,
            'order_id' => $this->order,
            'discount_amount' => 500,
            'used_at' => $now,
        ]);

        DB::table('customer_addresses')->insert([
            'tenant_id' => $this->tenantA,
            'user_id' => $this->customerA,
            'user_type' => $this->customerMorphType,
            'tenant_membership_id' => $this->membershipA,
            'label' => 'Home',
            'first_name' => 'Drill',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'address_line' => 'Synthetic Customer Address',
            'city_id' => $this->city,
            'township_id' => $this->township,
            'postal_code' => 'DR-1000',
            'is_default' => 1,
            'notes' => 'Synthetic address note',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('wishlists')->insert([
            'tenant_id' => $this->tenantA,
            'user_id' => $this->customerA,
            'user_type' => $this->customerMorphType,
            'tenant_membership_id' => $this->membershipA,
            'product_id' => $this->productSimple,

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->storefront = DB::table('storefronts')->insertGetId([
            'tenant_id' => $this->tenantA,
            'theme_id' => null,
            'status' => 'active',
            'draft_revision_id' => null,
            'published_revision_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->storefrontMedia = DB::table('storefront_media')->insertGetId([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'key' => 'hero-' . Str::lower($this->marker),
            'path' => $assetPath,
            'original_name' => 'drill-hero-' . Str::lower($this->marker) . '.txt',
            'mime_type' => 'text/plain',
            'size' => 32,
            'alt_text' => 'Synthetic drill media',
            'metadata' => json_encode(['source' => 'synthetic']),
            'is_visible' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_contents')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'labels' => json_encode(['home' => 'Home', 'shop' => 'Shop']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_design_tokens')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'tokens' => json_encode(['primary' => '#123456', 'surface' => '#ffffff']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_homepage_sections')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'type' => 'hero',
            'variant' => 'default',
            'enabled' => 1,
            'desktop_visible' => 1,
            'mobile_visible' => 1,
            'position' => 1,
            'configuration' => json_encode(['title' => 'Synthetic drill hero']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $navigationId = DB::table('storefront_navigations')->insertGetId([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'settings' => json_encode(['sticky' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_navigation_items')->insert([
            'tenant_id' => $this->tenantA,
            'navigation_id' => $navigationId,
            'key' => 'home',
            'group' => 'main',
            'label' => 'Home',
            'path' => '/',
            'icon' => 'home',
            'enabled' => 1,
            'position' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->revision = DB::table('storefront_revisions')->insertGetId([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'revision_number' => 1,
            'status' => 'published',
            'configuration' => json_encode(['synthetic' => true]),
            'created_by_type' => $this->customerMorphType,
            'created_by_id' => $this->customerA,
            'published_at' => $now,
            'published_by_type' => $this->customerMorphType,
            'published_by_id' => $this->customerA,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefronts')->where('id', $this->storefront)->update([
            'draft_revision_id' => $this->revision,
            'published_revision_id' => $this->revision,
            'updated_at' => $now,
        ]);

        DB::table('storefront_theme_configs')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'theme_id' => null,
            'configuration' => json_encode(['layout' => 'synthetic']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_checkout_configs')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'configuration' => json_encode(['guest_checkout' => false]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('storefront_product_display_configs')->insert([
            'tenant_id' => $this->tenantA,
            'storefront_id' => $this->storefront,
            'configuration' => json_encode(['show_stock' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('promotion_banners')->insert([
            'tenant_id' => $this->tenantA,
            'promotion_id' => $this->promotion,
            'title' => 'Drill Promotion Banner ' . $this->marker,
            'description' => 'Synthetic promotion banner',
            'image' => $assetPath,
            'storefront_media_id' => $this->storefrontMedia,
            'link' => '/store/drill',
            'cta_label' => 'Shop',
            'is_active' => 1,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addMonth(),
            'position' => 1,
            'desktop_visible' => 1,
            'mobile_visible' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('website_faqs')->insert([
            'tenant_id' => $this->tenantA,
            'category' => 'General',
            'question_en' => 'Synthetic drill question?',
            'question_my' => null,
            'answer_en' => 'Synthetic drill answer.',
            'answer_my' => null,
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('website_infos')->insert([
            'tenant_id' => $this->tenantA,
            'site_name' => 'Drill Store ' . $this->marker,
            'site_tagline' => 'Synthetic restore drill',
            'theme_color' => '#123456',
            'default_language' => 'en',
            'timezone' => 'Asia/Yangon',
            'currency_code' => 'MMK',
            'currency_symbol' => 'K',
            'currency_position' => 'before',
            'decimal_places' => 0,
            'date_format' => 'Y-m-d',
            'logo' => $assetPath,
            'hero_images' => json_encode([$assetPath]),
            'footer_settings' => json_encode(['columns' => 2]),
            'contact_info' => json_encode(['phone' => '09123456789']),
            'support_info' => json_encode(['email' => 'support@example.test']),
            'address_info' => json_encode(['address' => 'Synthetic Address']),
            'robots_meta' => 'index, follow',
            'maintenance_mode' => 0,
            'allow_registration' => 1,
            'enable_reviews' => 1,
            'enable_wishlist' => 1,
            'enable_compare' => 1,
            'guest_checkout_enabled' => 0,
            'cod_enabled' => 1,
            'free_shipping_threshold' => 50000,
            'default_shipping_fee' => 2000,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('settings')->insert([
            [
                'tenant_id' => $this->tenantA,
                'key' => 'restore_drill_safe_setting',
                'value' => 'SAFE-' . $this->marker,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->tenantA,
                'key' => 'smtp_password',
                'value' => 'SECRET-' . $this->marker,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->tenantB,
                'key' => 'restore_drill_other_tenant',
                'value' => 'TENANT-B-' . $this->marker,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->importHistory = DB::table('import_histories')->insertGetId([
            'tenant_id' => $this->tenantA,
            'user_id' => $this->customerA,
            'file_name' => 'drill-import-' . Str::lower($this->marker) . '.csv',
            'file_type' => 'csv',
            'import_type' => 'products',
            'status' => 'completed',
            'import_mode' => 'create_new',
            'total_rows' => 2,
            'total_products' => 1,
            'total_variants' => 1,
            'products_created' => 1,
            'products_skipped' => 0,
            'variants_created' => 1,
            'warning_count' => 1,
            'error_count' => 0,
            'errors' => json_encode([]),
            'warnings' => json_encode(['Synthetic warning']),
            'error_report_path' => 'imports/drill-report.csv',
            'duration_ms' => 123,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function cleanupAsset(): void
    {
        if (isset($this->assetAbsolute) && is_file($this->assetAbsolute)) {
            @unlink($this->assetAbsolute);
        }
    }

    /**
     * Removes the fixture rows. The drill executes CREATE/DROP DATABASE on the
     * default connection, which implicitly commits the surrounding test
     * transaction, so the fixture must delete its own rows.
     */
    public function purge(): void
    {
        if (!isset($this->marker)) {
            return;
        }

        $marker = Str::lower($this->marker);
        $emails = [
            'customer-a-' . $marker . '@example.test',
            'customer-no-orders-' . $marker . '@example.test',
            'drill-a@example.test',
            'drill-b@example.test',
        ];

        $modelIds = [];
        foreach (['customerA', 'customerWithoutOrders'] as $property) {
            if (isset($this->{$property})) {
                $modelIds[] = $this->{$property};
            }
        }

        if ($modelIds !== []) {
            DB::table('model_has_roles')
                ->whereIn('model_id', $modelIds)
                ->whereIn('model_type', [User::class, Account::class])
                ->delete();
        }

        DB::table('users')->whereIn('email', $emails)->delete();
        DB::table('accounts')->whereIn('email', $emails)->delete();

        $tenantIds = [];
        foreach (['tenantA', 'tenantB'] as $property) {
            if (isset($this->{$property})) {
                $tenantIds[] = $this->{$property};
            }
        }

        if ($tenantIds !== []) {
            DB::table('roles')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenants')->whereIn('id', $tenantIds)->delete();
        }
    }
}
