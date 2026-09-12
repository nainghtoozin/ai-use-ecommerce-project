<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $couponTypeMap = [
            'percentage' => 'percentage',
            'fixed_amount' => 'fixed',
            'free_shipping' => 'free_shipping',
        ];

        $coupons = DB::table('coupons')->where('tenant_id', '>', 0)->get();

        foreach ($coupons as $coupon) {
            $mappedType = $couponTypeMap[$coupon->type] ?? $coupon->type;

            $promotionId = DB::table('promotions')->insertGetId([
                'tenant_id' => $coupon->tenant_id,
                'name' => $coupon->name,
                'code' => $coupon->code,
                'description' => $coupon->description,
                'type' => $mappedType,
                'value' => $coupon->discount_value,
                'max_discount_amount' => $coupon->discount_cap,
                'minimum_order_amount' => $coupon->min_order_amount,
                'starts_at' => $coupon->starts_at,
                'ends_at' => $coupon->expires_at,
                'usage_limit' => $coupon->usage_limit,
                'usage_count' => $coupon->used_count,
                'per_customer_limit' => $coupon->per_customer_limit,
                'is_active' => $coupon->is_active,
                'is_automatic' => $coupon->code === null,
                'applies_to' => 'all',
                'priority' => $coupon->priority,
                'stackable' => $coupon->is_stackable,
                'created_by' => null,
                'created_by_type' => null,
                'created_at' => $coupon->created_at,
                'updated_at' => $coupon->updated_at,
            ]);

            $products = DB::table('coupon_product')
                ->where('coupon_id', $coupon->id)
                ->pluck('product_id')
                ->toArray();
            if (!empty($products)) {
                $hasSpecificProducts = true;
                DB::table('promotions')->where('id', $promotionId)->update(['applies_to' => 'products']);
                foreach ($products as $productId) {
                    DB::table('promotion_product')->insert([
                        'promotion_id' => $promotionId,
                        'product_id' => $productId,
                        'tenant_id' => $coupon->tenant_id,
                    ]);
                }
            }

            $categories = DB::table('coupon_category')
                ->where('coupon_id', $coupon->id)
                ->pluck('category_id')
                ->toArray();
            if (!empty($categories)) {
                DB::table('promotions')->where('id', $promotionId)->update(['applies_to' => 'categories']);
                foreach ($categories as $categoryId) {
                    DB::table('promotion_category')->insert([
                        'promotion_id' => $promotionId,
                        'category_id' => $categoryId,
                        'tenant_id' => $coupon->tenant_id,
                    ]);
                }
            }

            if (!empty($products) && !empty($categories)) {
                $productCount = count($products);
                $categoryCount = count($categories);
                if ($productCount <= $categoryCount) {
                    DB::table('promotions')->where('id', $promotionId)->update(['applies_to' => 'products']);
                } else {
                    DB::table('promotions')->where('id', $promotionId)->update(['applies_to' => 'categories']);
                }
            }

            DB::table('order_coupon')
                ->where('coupon_id', $coupon->id)
                ->update(['promotion_id' => $promotionId]);
        }
    }

    public function down(): void
    {
        $migratedFromCoupons = DB::table('order_coupon')
            ->whereNotNull('promotion_id')
            ->pluck('promotion_id')
            ->unique()
            ->toArray();

        if (!empty($migratedFromCoupons)) {
            DB::table('order_coupon')->whereIn('promotion_id', $migratedFromCoupons)->update(['promotion_id' => null]);
        }

        $migratedPromotionIds = DB::table('promotions')
            ->whereIn('id', $migratedFromCoupons)
            ->pluck('id')
            ->toArray();

        if (!empty($migratedPromotionIds)) {
            DB::table('promotion_product')->whereIn('promotion_id', $migratedPromotionIds)->delete();
            DB::table('promotion_category')->whereIn('promotion_id', $migratedPromotionIds)->delete();
            DB::table('promotion_usages')->whereIn('promotion_id', $migratedPromotionIds)->delete();
            DB::table('promotions')->whereIn('id', $migratedPromotionIds)->delete();
        }
    }
};
