<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Category;
use App\Models\Product;
use App\Services\FeatureGate;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminCouponController extends Controller
{
    private function codeBasedPromotions()
    {
        return Promotion::withoutTenantScope()
            ->where('tenant_id', tenantId())
            ->where('is_automatic', false)
            ->whereNotNull('code');
    }

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

        $coupons = $this->codeBasedPromotions()
            ->withCount(['products', 'categories'])
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
                'required', 'string', 'max:50',
                Rule::unique('promotions', 'code')->where('tenant_id', tenantId()),
            ],
            'type' => 'required|in:percentage,fixed,free_shipping',
            'value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_automatic'] = false;
        $data['created_by'] = auth()->id();

        if ($request->boolean('auto_generate') && empty($data['code'])) {
            $data['code'] = Promotion::generateCode();
        }

        $coupon = Promotion::create($data);

        if (!empty($data['product_ids'])) {
            $coupon->products()->sync($data['product_ids']);
        }

        if (!empty($data['category_ids'])) {
            $coupon->categories()->sync($data['category_ids']);
        }

        return admin_redirect('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Promotion $coupon)
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

        abort_unless(
            $coupon->tenant_id === tenantId() && !$coupon->is_automatic && $coupon->code !== null,
            404
        );

        $coupon->load(['products', 'categories']);

        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => $coupon,
            'categories' => Category::all(['id', 'name']),
            'products' => Product::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Promotion $coupon)
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

        abort_unless(
            $coupon->tenant_id === tenantId() && !$coupon->is_automatic && $coupon->code !== null,
            404
        );

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('promotions', 'code')->where('tenant_id', tenantId())->ignore($coupon->id),
            ],
            'type' => 'sometimes|in:percentage,fixed,free_shipping',
            'value' => 'sometimes|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $data['is_automatic'] = false;

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

    public function destroy(Promotion $coupon)
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

        abort_unless(
            $coupon->tenant_id === tenantId() && !$coupon->is_automatic && $coupon->code !== null,
            404
        );

        $coupon->products()->detach();
        $coupon->categories()->detach();
        $coupon->usages()->delete();
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

        $coupons = $this->codeBasedPromotions()
            ->where(function ($q) use ($query) {
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

    public function toggle(Promotion $coupon)
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

        abort_unless(
            $coupon->tenant_id === tenantId() && !$coupon->is_automatic && $coupon->code !== null,
            404
        );

        $coupon->update(['is_active' => !$coupon->is_active]);

        return admin_redirect('admin.coupons.index')
            ->with('success', 'Coupon status toggled.');
    }

    public function duplicate(Promotion $coupon)
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

        abort_unless(
            $coupon->tenant_id === tenantId() && !$coupon->is_automatic && $coupon->code !== null,
            404
        );

        $limitService = SubscriptionLimitService::for();
        if (!$limitService->checkLimit('coupon_limit')) {
            return redirect()->back()->with('error',
                'Coupon limit reached. Please upgrade your plan to duplicate coupons.');
        }

        $newCoupon = $coupon->replicate();
        $newCoupon->code = Promotion::generateCode();
        $newCoupon->name = $coupon->name . ' (Copy)';
        $newCoupon->usage_count = 0;
        $newCoupon->save();

        return admin_redirect('admin.coupons.edit', $newCoupon->id)
            ->with('success', 'Coupon duplicated successfully.');
    }
}
