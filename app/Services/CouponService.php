<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CouponService
{
    public function validateCoupon(?string $code, Collection $cartItems, ?int $userId = null, ?float $deliveryFee = 0): array
    {
        if (empty($code)) {
            return ['valid' => false, 'message' => 'No coupon code provided.'];
        }

        $coupon = Coupon::active()->withCode($code)->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        if (!$coupon->isValid()) {
            return ['valid' => false, 'message' => 'This coupon is no longer valid.'];
        }

        if ($userId && $coupon->hasReachedCustomerLimit($userId)) {
            return ['valid' => false, 'message' => 'You have already used this coupon the maximum number of times.'];
        }

        $subtotal = $this->calculateSubtotal($cartItems);

        if (!$coupon->meetsMinimumAmount($subtotal)) {
            return [
                'valid' => false,
                'message' => 'Minimum order amount of ' . number_format((float) $coupon->min_order_amount, 2) . ' is required.',
            ];
        }

        if (!$this->productRestrictionsMet($coupon, $cartItems)) {
            return ['valid' => false, 'message' => 'This coupon does not apply to the products in your cart.'];
        }

        if (!$this->categoryRestrictionsMet($coupon, $cartItems)) {
            return ['valid' => false, 'message' => 'This coupon does not apply to the categories in your cart.'];
        }

        $discount = $this->calculateDiscount($coupon, $subtotal, $deliveryFee);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => 'Coupon applied successfully!',
        ];
    }

    public function getApplicableAutoPromotions(Collection $cartItems, ?float $deliveryFee = 0): Collection
    {
        $promotions = Coupon::active()->autoApply()->orderBy('priority', 'desc')->get();
        $applicable = collect();

        foreach ($promotions as $promotion) {
            $subtotal = $this->calculateSubtotal($cartItems);

            if (!$promotion->meetsMinimumAmount($subtotal)) {
                continue;
            }

            if (!$this->productRestrictionsMet($promotion, $cartItems)) {
                continue;
            }

            if (!$this->categoryRestrictionsMet($promotion, $cartItems)) {
                continue;
            }

            $discount = $this->calculateDiscount($promotion, $subtotal, $deliveryFee);

            if ($discount > 0) {
                $applicable->push([
                    'coupon' => $promotion,
                    'discount' => $discount,
                ]);
            }
        }

        return $applicable;
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal, ?float $deliveryFee = 0): float
    {
        switch ($coupon->type) {
            case Coupon::TYPE_PERCENTAGE:
                $discount = $subtotal * ((float) $coupon->discount_value / 100);
                if ($coupon->discount_cap !== null) {
                    $discount = min($discount, (float) $coupon->discount_cap);
                }
                return round($discount, 2);

            case Coupon::TYPE_FIXED_AMOUNT:
                return min((float) $coupon->discount_value, $subtotal);

            case Coupon::TYPE_FREE_SHIPPING:
                return (float) ($deliveryFee ?? 0);

            default:
                return 0;
        }
    }

    public function applyCouponToOrder(Order $order, Coupon $coupon, float $discountAmount): void
    {
        $order->coupons()->attach($coupon->id, [
            'code' => $coupon->code ?? 'AUTO',
            'type' => $coupon->type,
            'discount_amount' => $discountAmount,
        ]);

        $coupon->increment('used_count');
    }

    public function productRestrictionsMet(Coupon $coupon, Collection $cartItems): bool
    {
        if ($coupon->products->isEmpty()) {
            return true;
        }

        $cartProductIds = $cartItems->pluck('product_id')->toArray();

        return $coupon->products->pluck('id')->intersect($cartProductIds)->isNotEmpty();
    }

    public function categoryRestrictionsMet(Coupon $coupon, Collection $cartItems): bool
    {
        if ($coupon->categories->isEmpty()) {
            return true;
        }

        $productIds = $cartItems->pluck('product_id')->toArray();
        $cartCategoryIds = Product::whereIn('id', $productIds)->pluck('category_id')->unique()->toArray();

        return $coupon->categories->pluck('id')->intersect($cartCategoryIds)->isNotEmpty();
    }

    public function calculateSubtotal(Collection $cartItems): float
    {
        return (float) $cartItems->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1));
    }

    public function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
