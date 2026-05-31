<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
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
        return Inertia::render('SuperAdmin/Plans/Create');
    }

    public function store(Request $request)
    {
        // Inertia FormData converts JS null → empty string.
        // Convert empty strings back to null so nullable rules pass.
        $request->merge([
            'product_limit' => $request->product_limit === '' ? null : $request->product_limit,
            'staff_limit' => $request->staff_limit === '' ? null : $request->staff_limit,
            'storage_limit' => $request->storage_limit === '' ? null : $request->storage_limit,
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
            'analytics_enabled' => 'boolean',
            'custom_domain_enabled' => 'boolean',
            'status' => 'required|in:active,inactive,deprecated',
        ]);

        Plan::create($validated);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $request->merge([
            'product_limit' => $request->product_limit === '' ? null : $request->product_limit,
            'staff_limit' => $request->staff_limit === '' ? null : $request->staff_limit,
            'storage_limit' => $request->storage_limit === '' ? null : $request->storage_limit,
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
            'analytics_enabled' => 'boolean',
            'custom_domain_enabled' => 'boolean',
            'status' => 'required|in:active,inactive,deprecated',
        ]);

        $plan->update($validated);

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
}
