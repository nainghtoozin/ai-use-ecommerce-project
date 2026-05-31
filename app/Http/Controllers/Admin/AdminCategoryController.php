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
        $categories = Category::latest()->paginate(10);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Categories/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'max:255', Rule::unique('categories', 'name')->where('tenant_id', tenant()?->id)],
            'description' => 'nullable|string',
        ]);

        Category::create($request->only(['name', 'description']));

        return redirect()->route('admin.categories.index')
                         ->with('success', 'Category created successfully!');
    }
    
    public function edit(Category $category)
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => ['required', 'max:255', Rule::unique('categories', 'name')->where('tenant_id', tenant()?->id)->ignore($category->id)],
            'description' => 'nullable|string',
        ]);

        $category->update($request->only(['name', 'description']));

        return redirect()->route('admin.categories.index')
                         ->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')
                         ->with('success', 'Category deleted successfully!');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $categories = Category::where('name', 'like', "%{$query}%")
            ->latest()
            ->paginate(10);

        $categories->appends(['query' => $query]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'query' => $query,
        ]);
    }
    
}
