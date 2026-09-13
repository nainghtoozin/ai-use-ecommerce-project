<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\FeatureGate;
use App\Services\PromotionService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService,
        private readonly ProductService $productService
    ) {}

    public function index()
    {
        $cart = session()->get('cart', []);
        $cartItems = $this->formatCartItems($cart);
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

        return Inertia::render('Client/Cart/Index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'appliedPromotion' => $appliedPromotion,
            'appliedCoupon' => $appliedCoupon,
            'totalDiscount' => $totalDiscount,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);
        $key = $this->buildCartKey($request->product_id, $request->variant_id);

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $request->quantity;
        } else {
            $product = Product::find($request->product_id);
            $price = $product->price;

            if ($request->variant_id) {
                $variant = ProductVariant::find($request->variant_id);
                if ($variant) {
                    $price = $variant->price;
                }
            }

            $cart[$key] = [
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $price,
            ];
        }

        session()->put('cart', $cart);

        $count = array_sum(array_column($cart, 'quantity'));
        return response()->json(['count' => $count]);
    }

    public function update(Request $request, string $key)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = $request->quantity;
            session()->put('cart', $cart);
        }

        $cartItems = $this->formatCartItems($cart);
        $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));
        $count = array_sum(array_column($cart, 'quantity'));
        return response()->json(['count' => $count, 'cartItems' => $cartItems, 'subtotal' => $subtotal]);
    }

    public function destroy(string $key)
    {
        $cart = session()->get('cart', []);
        unset($cart[$key]);
        session()->put('cart', $cart);

        $cartItems = $this->formatCartItems($cart);
        $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));
        $count = array_sum(array_column($cart, 'quantity'));
        return response()->json(['count' => $count, 'cartItems' => $cartItems, 'subtotal' => $subtotal]);
    }

    public function clear()
    {
        session()->forget('cart');
        session()->forget('applied_promotion');
        session()->forget('applied_coupon');

        return response()->json(['count' => 0, 'cartItems' => [], 'subtotal' => 0]);
    }

    public function applyCoupon(Request $request)
    {
        if (!FeatureGate::enabled('coupons') && !FeatureGate::enabled('promotions')) {
            return response()->json([
                'success' => false,
                'message' => 'Discount features are not available on your current plan.',
            ], 403);
        }

        $request->validate(['code' => 'required|string|max:50']);

        $cart = session()->get('cart', []);
        $cartItems = $this->formatCartItems($cart);
        $deliveryFee = (float) $request->input('delivery_fee', 0);

        $result = $this->promotionService->validateCouponCode(
            $request->code,
            collect($cartItems),
            auth()->id(),
            $deliveryFee
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        session()->put('applied_coupon', [
            'code' => $result['promotion']->code,
            'promotion_id' => $result['promotion']->id,
            'type' => $result['promotion']->type,
            'discount' => $result['discount'],
            'name' => $result['promotion']->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon_code' => $result['promotion']->code,
            'coupon_name' => $result['promotion']->name,
        ]);
    }

    public function removeCoupon()
    {
        session()->forget('applied_coupon');

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed.',
        ]);
    }

    public function applyPromotion(Request $request)
    {
        if (!FeatureGate::enabled('promotions')) {
            return response()->json([
                'success' => false,
                'message' => 'Promotions feature is not available on your current plan.',
            ], 403);
        }

        $request->validate(['code' => 'required|string|max:50']);

        $cart = session()->get('cart', []);
        $cartItems = $this->formatCartItems($cart);
        $deliveryFee = (float) $request->input('delivery_fee', 0);

        $result = $this->promotionService->validatePromotion(
            $request->code,
            $cartItems,
            auth()->id(),
            $deliveryFee
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        session()->put('applied_promotion', [
            'code' => $result['promotion']->code,
            'promotion_id' => $result['promotion']->id,
            'type' => $result['promotion']->type,
            'discount' => $result['discount'],
            'name' => $result['promotion']->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'promotion_code' => $result['promotion']->code,
            'promotion_name' => $result['promotion']->name,
        ]);
    }

    public function removePromotion()
    {
        session()->forget('applied_promotion');

        return response()->json([
            'success' => true,
            'message' => 'Promotion removed.',
        ]);
    }

    /**
     * Build a unique session key for a cart item.
     * Format: "p{product_id}_v{variant_id}" for variable, "p{product_id}" for single.
     */
    private function buildCartKey(int $productId, ?int $variantId = null): string
    {
        return 'p' . $productId . '_v' . ($variantId ?? '0');
    }

    private function formatCartItems(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;

            $product = Product::select(['id', 'name', 'price', 'photo1', 'type'])->find($productId);
            if (!$product) {
                continue;
            }

            $variantName = null;
            $price = $item['price'];

            if ($variantId) {
                $variant = ProductVariant::select(['id', 'price', 'sku', 'attributes'])->find($variantId);
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
                'price' => (float) $price,
                'photo1_url' => $product->photo1_url,
                'quantity' => $item['quantity'],
            ];
        }
        return $items;
    }
}
