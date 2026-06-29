<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\FeatureGate;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminCouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    public function index()
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.view')) {
            abort(403, 'Unauthorized');
        }

        $coupons = Coupon::withCount(['products', 'categories'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
        ]);
    }

    public function create()
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Coupons/Create', [
            'categories' => Category::all(['id', 'name']),
            'products' => Product::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.create')) {
            abort(403, 'Unauthorized');
        }

        $limitService = SubscriptionLimitService::for();
        if (!$limitService->checkLimit('coupon_limit')) {
            return redirect()->back()->with('error',
                'Coupon limit reached. Please upgrade your plan to create more coupons.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('coupons', 'code')->where('tenant_id', tenant()?->id),
            ],
            'type' => 'required|in:percentage,fixed_amount,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'discount_cap' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'priority' => 'integer|min:0',
            'is_stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        if ($request->boolean('auto_generate') && empty($data['code'])) {
            $data['code'] = $this->couponService->generateCode();
        }

        $coupon = Coupon::create($data);

        if (!empty($data['product_ids'])) {
            $coupon->products()->sync($data['product_ids']);
        }

        if (!empty($data['category_ids'])) {
            $coupon->categories()->sync($data['category_ids']);
        }

        return admin_redirect('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.update')) {
            abort(403, 'Unauthorized');
        }

        $coupon->load(['products', 'categories']);

        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => $coupon,
            'categories' => Category::all(['id', 'name']),
            'products' => Product::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.update')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('coupons', 'code')->where('tenant_id', tenant()?->id)->ignore($coupon->id),
            ],
            'type' => 'sometimes|in:percentage,fixed_amount,free_shipping',
            'discount_value' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'discount_cap' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'priority' => 'integer|min:0',
            'is_stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $coupon->update($data);

        if ($request->has('product_ids')) {
            $coupon->products()->sync($data['product_ids'] ?? []);
        }

        if ($request->has('category_ids')) {
            $coupon->categories()->sync($data['category_ids'] ?? []);
        }

        return admin_redirect('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.delete')) {
            abort(403, 'Unauthorized');
        }

        $coupon->products()->detach();
        $coupon->categories()->detach();
        $coupon->delete();

        return admin_redirect('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    public function search(Request $request)
    {
        if (!FeatureGate::enabled('coupons')) {
            return redirect()->back()->with('feature_locked', [
                'feature' => FeatureGate::getLabelStatic('coupons'),
                'required_plan' => FeatureGate::getUpgradeHintStatic('coupons') ?? 'Starter',
            ]);
        }

        if (!auth()->user()->can('coupons.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');

        $coupons = Coupon::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%")
                  ->orWhere('type', 'like', "%{$query}%");
            })
            ->withCount(['products', 'categories'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        $coupons->appends(['query' => $query]);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'query' => $query,
        ]);
    }
}
