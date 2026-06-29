<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $plans = Plan::query()
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%");
            }))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => $plans,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        $allFeatures = array_map(fn($def) => [
            'key' => $def['key'],
            'label' => $def['label'],
            'enabled' => false,
        ], FeatureGate::getAllFeatureDefinitions());

        return Inertia::render('SuperAdmin/Plans/Create', [
            'allFeatures' => $allFeatures,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'product_limit' => $request->product_limit === '' ? null : $request->product_limit,
            'staff_limit' => $request->staff_limit === '' ? null : $request->staff_limit,
            'storage_limit' => $request->storage_limit === '' ? null : $request->storage_limit,
            'orders_monthly_limit' => $request->orders_monthly_limit === '' ? null : $request->orders_monthly_limit,
            'coupon_limit' => $request->coupon_limit === '' ? null : $request->coupon_limit,
            'promotion_limit' => $request->promotion_limit === '' ? null : $request->promotion_limit,
            'flash_sale_limit' => $request->flash_sale_limit === '' ? null : $request->flash_sale_limit,
            'api_request_limit' => $request->api_request_limit === '' ? null : $request->api_request_limit,
            'image_limit' => $request->image_limit === '' ? null : $request->image_limit,
            'image_max_size_kb' => $request->image_max_size_kb === '' ? null : $request->image_max_size_kb,
            'branch_limit' => $request->branch_limit === '' ? null : $request->branch_limit,
            'warehouse_limit' => $request->warehouse_limit === '' ? null : $request->warehouse_limit,
            'pos_device_limit' => $request->pos_device_limit === '' ? null : $request->pos_device_limit,
            'monthly_price' => $request->monthly_price === '' ? null : $request->monthly_price,
            'yearly_price' => $request->yearly_price === '' ? null : $request->yearly_price,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:1000',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'product_limit' => 'nullable|integer|min:0',
            'staff_limit' => 'nullable|integer|min:0',
            'storage_limit' => 'nullable|integer|min:0',
            'orders_monthly_limit' => 'nullable|integer|min:0',
            'coupon_limit' => 'nullable|integer|min:0',
            'promotion_limit' => 'nullable|integer|min:0',
            'flash_sale_limit' => 'nullable|integer|min:0',
            'api_request_limit' => 'nullable|integer|min:0',
            'image_limit' => 'nullable|integer|min:0',
            'image_max_size_kb' => 'nullable|integer|min:0',
            'branch_limit' => 'nullable|integer|min:0',
            'warehouse_limit' => 'nullable|integer|min:0',
            'pos_device_limit' => 'nullable|integer|min:0',
            'analytics_enabled' => 'boolean',
            'custom_domain_enabled' => 'boolean',
            'status' => 'required|in:active,inactive,deprecated',
            'features' => 'nullable|array',
        ]);

        $features = $request->input('features', []);
        unset($validated['features']);

        $plan = Plan::create($validated);

        $this->syncFeatures($plan, $features);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        $plan->load('features');

        $allFeatures = array_map(function ($def) use ($plan) {
            $planFeature = $plan->features->firstWhere('feature_key', $def['key']);
            return [
                'key' => $def['key'],
                'label' => $def['label'],
                'enabled' => $planFeature ? $planFeature->is_enabled : false,
            ];
        }, FeatureGate::getAllFeatureDefinitions());

        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
            'allFeatures' => $allFeatures,
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $request->merge([
            'product_limit' => $request->product_limit === '' ? null : $request->product_limit,
            'staff_limit' => $request->staff_limit === '' ? null : $request->staff_limit,
            'storage_limit' => $request->storage_limit === '' ? null : $request->storage_limit,
            'orders_monthly_limit' => $request->orders_monthly_limit === '' ? null : $request->orders_monthly_limit,
            'coupon_limit' => $request->coupon_limit === '' ? null : $request->coupon_limit,
            'promotion_limit' => $request->promotion_limit === '' ? null : $request->promotion_limit,
            'flash_sale_limit' => $request->flash_sale_limit === '' ? null : $request->flash_sale_limit,
            'api_request_limit' => $request->api_request_limit === '' ? null : $request->api_request_limit,
            'image_limit' => $request->image_limit === '' ? null : $request->image_limit,
            'image_max_size_kb' => $request->image_max_size_kb === '' ? null : $request->image_max_size_kb,
            'branch_limit' => $request->branch_limit === '' ? null : $request->branch_limit,
            'warehouse_limit' => $request->warehouse_limit === '' ? null : $request->warehouse_limit,
            'pos_device_limit' => $request->pos_device_limit === '' ? null : $request->pos_device_limit,
            'monthly_price' => $request->monthly_price === '' ? null : $request->monthly_price,
            'yearly_price' => $request->yearly_price === '' ? null : $request->yearly_price,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug,' . $plan->id . '|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:1000',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'product_limit' => 'nullable|integer|min:0',
            'staff_limit' => 'nullable|integer|min:0',
            'storage_limit' => 'nullable|integer|min:0',
            'orders_monthly_limit' => 'nullable|integer|min:0',
            'coupon_limit' => 'nullable|integer|min:0',
            'promotion_limit' => 'nullable|integer|min:0',
            'flash_sale_limit' => 'nullable|integer|min:0',
            'api_request_limit' => 'nullable|integer|min:0',
            'image_limit' => 'nullable|integer|min:0',
            'image_max_size_kb' => 'nullable|integer|min:0',
            'branch_limit' => 'nullable|integer|min:0',
            'warehouse_limit' => 'nullable|integer|min:0',
            'pos_device_limit' => 'nullable|integer|min:0',
            'analytics_enabled' => 'boolean',
            'custom_domain_enabled' => 'boolean',
            'status' => 'required|in:active,inactive,deprecated',
            'features' => 'nullable|array',
        ]);

        $features = $request->input('features', []);
        unset($validated['features']);

        $plan->update($validated);

        $this->syncFeatures($plan, $features);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return redirect()->route('superadmin.plans.index')
                ->with('error', 'Cannot delete plan with active subscriptions. Set status to "deprecated" instead.');
        }

        if ($plan->slug === 'free') {
            return redirect()->route('superadmin.plans.index')
                ->with('error', 'The free plan cannot be deleted.');
        }

        $plan->delete();

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan deleted successfully.');
    }

    private function syncFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $feature) {
            $key = $feature['key'] ?? null;
            if (!$key) continue;

            $enabled = filter_var($feature['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                [
                    'is_enabled' => $enabled,
                    'display_label' => FeatureGate::getLabelStatic($key),
                    'description' => $enabled ? FeatureGate::getLabelStatic($key) : null,
                ]
            );
        }

        FeatureGate::clearCache($plan);
    }
}
