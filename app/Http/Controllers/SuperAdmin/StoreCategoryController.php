<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StoreCategoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('store-categories.view'), 403);

        $search = $request->get('search');
        $status = $request->get('status');

        $categories = StoreCategory::query()
            ->withCount('tenants')
            ->when($search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            }))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SuperAdmin/StoreCategories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('store-categories.create'), 403);

        return Inertia::render('SuperAdmin/StoreCategories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('store-categories.create'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:store_categories,slug|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = str($validated['name'])->slug()->toString();
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? (int) StoreCategory::max('sort_order') + 1;

        StoreCategory::create($validated);

        return redirect()->route('superadmin.store-categories.index')
            ->with('success', 'Store category created successfully.');
    }

    public function edit(Request $request, StoreCategory $storeCategory)
    {
        abort_unless($request->user()->can('store-categories.update'), 403);

        return Inertia::render('SuperAdmin/StoreCategories/Edit', [
            'category' => $storeCategory->loadCount('tenants'),
        ]);
    }

    public function update(Request $request, StoreCategory $storeCategory): RedirectResponse
    {
        abort_unless($request->user()->can('store-categories.update'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('store_categories', 'slug')->ignore($storeCategory->id),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = str($validated['name'])->slug()->toString();
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        if (!$validated['is_active'] && $storeCategory->is_active && $storeCategory->tenants()->exists()) {
            return redirect()->back()->withErrors([
                'is_active' => 'This category is assigned to stores. Reassign those stores to another category before deactivating it.',
            ]);
        }

        $storeCategory->update($validated);

        return redirect()->route('superadmin.store-categories.index')
            ->with('success', 'Store category updated successfully.');
    }

    public function toggle(Request $request, StoreCategory $storeCategory): RedirectResponse
    {
        abort_unless($request->user()->can('store-categories.update'), 403);

        if ($storeCategory->is_active && $storeCategory->tenants()->exists()) {
            return redirect()->back()->with(
                'error',
                'This category is assigned to ' . $storeCategory->tenants()->count() . ' store(s). Reassign those stores to another category before deactivating it.'
            );
        }

        $storeCategory->update(['is_active' => !$storeCategory->is_active]);

        return redirect()->back()->with(
            'success',
            $storeCategory->fresh()->is_active ? 'Store category activated.' : 'Store category deactivated.'
        );
    }

    public function destroy(Request $request, StoreCategory $storeCategory): RedirectResponse
    {
        abort_unless($request->user()->can('store-categories.delete'), 403);

        if ($storeCategory->tenants()->exists()) {
            return redirect()->route('superadmin.store-categories.index')
                ->with('error', 'Cannot delete a category that is assigned to stores. Reassign those stores first.');
        }

        $storeCategory->delete();

        return redirect()->route('superadmin.store-categories.index')
            ->with('success', 'Store category deleted successfully.');
    }
}
