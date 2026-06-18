<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminPromotionController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('promotions.view')) {
            abort(403, 'Unauthorized');
        }

        $promotions = Promotion::withCount(['products', 'categories'])
            ->latest()
            ->paginate(15);

        $stats = [
            'total' => Promotion::count(),
            'active' => Promotion::where('is_active', true)->count(),
            'expired' => Promotion::where('ends_at', '<', now())->count(),
            'auto' => Promotion::where('is_automatic', true)->count(),
        ];

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('promotions.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Promotions/Create', [
            'categories' => Category::all(['id', 'name']),
            'products' => Product::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('promotions.create')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('promotions', 'code')->where('tenant_id', tenant()?->id),
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
            'is_automatic' => 'boolean',
            'applies_to' => 'required|in:all,products,categories',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $data['created_by'] = auth()->id();

        if ($request->boolean('auto_generate') && empty($data['code'])) {
            $data['code'] = Promotion::generateCode();
        }

        $promotion = Promotion::create($data);

        if (!empty($data['product_ids'])) {
            $promotion->products()->sync($data['product_ids']);
        }

        if (!empty($data['category_ids'])) {
            $promotion->categories()->sync($data['category_ids']);
        }

        return admin_redirect('admin.promotions.index')
            ->with('success', 'Promotion created successfully.');
    }

    public function edit(Promotion $promotion)
    {
        if (!auth()->user()->can('promotions.update')) {
            abort(403, 'Unauthorized');
        }

        $promotion->load(['products', 'categories']);

        return Inertia::render('Admin/Promotions/Edit', [
            'promotion' => $promotion,
            'categories' => Category::all(['id', 'name']),
            'products' => Product::all(['id', 'name']),
        ]);
    }

    public function update(Request $request, Promotion $promotion)
    {
        if (!auth()->user()->can('promotions.update')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('promotions', 'code')->where('tenant_id', tenant()?->id)->ignore($promotion->id),
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
            'is_automatic' => 'boolean',
            'applies_to' => 'sometimes|in:all,products,categories',
            'priority' => 'integer|min:0',
            'stackable' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $promotion->update($data);

        if ($request->has('product_ids')) {
            $promotion->products()->sync($data['product_ids'] ?? []);
        }

        if ($request->has('category_ids')) {
            $promotion->categories()->sync($data['category_ids'] ?? []);
        }

        return admin_redirect('admin.promotions.index')
            ->with('success', 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion)
    {
        if (!auth()->user()->can('promotions.delete')) {
            abort(403, 'Unauthorized');
        }

        $promotion->products()->detach();
        $promotion->categories()->detach();
        $promotion->usages()->delete();
        $promotion->delete();

        return admin_redirect('admin.promotions.index')
            ->with('success', 'Promotion deleted successfully.');
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('promotions.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');

        $promotions = Promotion::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%")
                  ->orWhere('type', 'like', "%{$query}%");
            })
            ->withCount(['products', 'categories'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        $promotions->appends(['query' => $query]);

        $stats = [
            'total' => Promotion::count(),
            'active' => Promotion::where('is_active', true)->count(),
            'expired' => Promotion::where('ends_at', '<', now())->count(),
            'auto' => Promotion::where('is_automatic', true)->count(),
        ];

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
            'stats' => $stats,
            'query' => $query,
        ]);
    }

    public function toggle(Promotion $promotion)
    {
        if (!auth()->user()->can('promotions.update')) {
            abort(403, 'Unauthorized');
        }

        $promotion->update(['is_active' => !$promotion->is_active]);

        return admin_redirect('admin.promotions.index')
            ->with('success', 'Promotion status toggled.');
    }

    public function duplicate(Promotion $promotion)
    {
        if (!auth()->user()->can('promotions.create')) {
            abort(403, 'Unauthorized');
        }

        $newPromotion = $promotion->replicate();
        $newPromotion->code = Promotion::generateCode();
        $newPromotion->name = $promotion->name . ' (Copy)';
        $newPromotion->usage_count = 0;
        $newPromotion->save();

        return admin_redirect('admin.promotions.edit', $newPromotion->id)
            ->with('success', 'Promotion duplicated successfully.');
    }
}
