<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService
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
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);
        $productId = $request->product_id;
        $quantity = $request->quantity;

        // Only fetch needed product data
        $product = Product::select(['id', 'name', 'price', 'photo1'])->findOrFail($productId);
        $productName = $product->name;

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'photo1' => $product->photo1,
                'quantity' => $quantity,
            ];
        }

        session()->put('cart', $cart);
        
        $cartCount = array_sum(array_column($cart, 'quantity'));

        if ($request->header('X-Inertia')) {
            return redirect()->route('cart.index')
                ->with('success', '"' . $productName . '" added to cart successfully.');
        }

        return response()->json([
            'success' => '"' . $productName . '" added to cart successfully.',
            'cart_count' => $cartCount,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            if ($request->quantity <= 0) {
                $itemName = $cart[$id]['name'] ?? 'Item';
                unset($cart[$id]);
                session()->put('cart', $cart);
                
                $cartItems = $this->formatCartItems($cart);
                $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

                return response()->json([
                    'success' => '"' . $itemName . '" removed from cart.',
                    'cartItems' => $cartItems,
                    'subtotal' => $subtotal,
                ]);
            } else {
                $cart[$id]['quantity'] = $request->quantity;
                session()->put('cart', $cart);
                
                $cartItems = $this->formatCartItems($cart);
                $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

                return response()->json([
                    'cartItems' => $cartItems,
                    'subtotal' => $subtotal,
                ]);
            }
        }

        return response()->json(['error' => 'Item not found in cart.'], 422);
    }

    public function destroy($id)
    {
        $cart = session()->get('cart', []);
        
        if (isset($cart[$id])) {
            $itemName = $cart[$id]['name'] ?? 'Item';
            unset($cart[$id]);
            session()->put('cart', $cart);
            
            $cartItems = $this->formatCartItems($cart);
            $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

            return response()->json([
                'success' => '"' . $itemName . '" removed from cart.',
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
            ]);
        }

        return response()->json(['error' => 'Item not found in cart.'], 422);
    }

    public function clear(Request $request)
    {
        session()->forget('cart');

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Cart cleared successfully.');
        }

        return response()->json([
            'success' => 'Cart cleared successfully.',
            'cartItems' => [],
            'subtotal' => 0,
        ]);
    }

    public function count()
    {
        $cart = session()->get('cart', []);
        $count = array_sum(array_column($cart, 'quantity'));
        return response()->json(['count' => $count]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $cart = session()->get('cart', []);
        $cartItems = collect($this->formatCartItems($cart));
        $deliveryFee = (float) $request->input('delivery_fee', 0);

        $result = $this->couponService->validateCoupon(
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

        session()->put('applied_coupon', [
            'code' => $result['coupon']->code,
            'coupon_id' => $result['coupon']->id,
            'type' => $result['coupon']->type,
            'discount' => $result['discount'],
            'name' => $result['coupon']->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon_code' => $result['coupon']->code,
            'coupon_name' => $result['coupon']->name,
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

    private function formatCartItems(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        // Get all product IDs and fetch in single query
        $productIds = array_keys($cart);
        $products = Product::select(['id', 'name', 'price', 'photo1'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($cart as $id => $item) {
            $product = $products->get($id);
            if ($product) {
                $items[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'photo1' => $product->photo1,
                    'quantity' => $item['quantity'],
                ];
            }
        }
        return $items;
    }
}