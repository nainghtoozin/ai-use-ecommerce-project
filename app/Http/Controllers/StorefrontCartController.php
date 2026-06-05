<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Inertia\Inertia;

class StorefrontCartController extends Controller
{
    public function index()
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        $cart = session()->get('cart', []);
        $cartItems = $this->filterCartByTenant($cart, $tenant);
        $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

        $appliedPromotion = session('applied_promotion');
        $appliedCoupon = session('applied_coupon');

        $totalDiscount = 0;
        if ($appliedPromotion) {
            $totalDiscount += (float) ($appliedPromotion['discount'] ?? 0);
        }
        if ($appliedCoupon) {
            $totalDiscount += (float) ($appliedCoupon['discount'] ?? 0);
        }

        return Inertia::render('Storefront/Cart', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'store_url' => $tenant->store_url,
                'logo' => $tenant->logo,
                'status' => $tenant->status,
            ],
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'appliedPromotion' => $appliedPromotion,
            'appliedCoupon' => $appliedCoupon,
            'totalDiscount' => $totalDiscount,
        ]);
    }

    private function filterCartByTenant(array $cart, Tenant $tenant): array
    {
        if (empty($cart)) {
            return [];
        }

        $tenantId = $tenant->id;
        $tenantProductIds = Product::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }

            if (!in_array((int) $productId, $tenantProductIds)) {
                continue;
            }

            $product = Product::select(['id', 'name', 'price', 'type', 'photo1'])->find($productId);
            if (!$product) {
                continue;
            }

            $price = (float) $product->price;
            $variantName = null;
            $variantId = $item['variant_id'] ?? null;

            if ($variantId) {
                $variant = ProductVariant::select(['id', 'price', 'attributes'])->find($variantId);
                if ($variant) {
                    $price = (float) ($variant->price ?? $product->price);
                    $variantName = $variant->label;
                }
            }

            $items[] = [
                'cart_key' => $cartKey,
                'id' => $product->id,
                'variant_id' => $variantId,
                'name' => $product->name,
                'variant_name' => $variantName,
                'price' => $price,
                'photo1' => $product->photo1,
                'quantity' => $item['quantity'],
            ];
        }

        return $items;
    }
}
