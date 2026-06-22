<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Category;
use Inertia\Inertia;

class AdminCategoryController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('categories.view')) {
            abort(403, 'Unauthorized');
        }

        $categories = Category::forCurrentTenant()->latest()->paginate(10);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('categories.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Categories/Create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('categories.create')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => ['required', 'max:255', Rule::unique('categories', 'name')->where('tenant_id', tenant()?->id)],
            'description' => 'nullable|string',
        ]);

        Category::create($request->only(['name', 'description']));

        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category created successfully!');
    }
    
    public function edit(Category $category)
    {
        if (!auth()->user()->can('categories.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        if (!auth()->user()->can('categories.update')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => ['required', 'max:255', Rule::unique('categories', 'name')->where('tenant_id', tenant()?->id)->ignore($category->id)],
            'description' => 'nullable|string',
        ]);

        $category->update($request->only(['name', 'description']));

        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        if (!auth()->user()->can('categories.delete')) {
            abort(403, 'Unauthorized');
        }

        $category->delete();
        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category deleted successfully!');
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('categories.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');

        $categories = Category::forCurrentTenant()
            ->where('name', 'like', "%{$query}%")
            ->latest()
            ->paginate(10);

        $categories->appends(['query' => $query]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'query' => $query,
        ]);
    }
    
}
