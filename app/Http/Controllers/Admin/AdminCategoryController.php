<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use App\Services\MasterDataImportService;
use Illuminate\Http\Request;
use App\Models\Category;
use Inertia\Inertia;

class AdminCategoryController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('categories.view')) {
            abort(403, 'Unauthorized');
        }

        $query = Category::forCurrentTenant()
            ->with(['parent', 'products'])
            ->sorted();

        if ($request->has('filter_active')) {
            $filter = $request->input('filter_active');
            if ($filter === 'active') {
                $query->where('is_active', true);
            } elseif ($filter === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('filter_featured')) {
            $filter = $request->input('filter_featured');
            if ($filter === 'featured') {
                $query->where('featured', true);
            } elseif ($filter === 'not_featured') {
                $query->where('featured', false);
            }
        }

        if ($request->has('filter_parent')) {
            $filter = $request->input('filter_parent');
            if ($filter === 'root') {
                $query->whereNull('parent_id');
            } elseif ($filter === 'child') {
                $query->whereNotNull('parent_id');
            }
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->paginate(15);

        $categories->each(function ($category) {
            $category->append('image_url');
            $category->products_count = $category->products()->count();
        });

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('categories.create')) {
            abort(403, 'Unauthorized');
        }

        $parentCategories = Category::forCurrentTenant()
            ->whereNull('parent_id')
            ->sorted()
            ->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        if (!auth()->user()->can('categories.create')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $this->imageService->upload($request->file('image'), 'categories');
        }

        $category = Category::create($data);

        ActivityLogger::log("Category '{$category->name}' created", 'category_created', $category);

        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category created successfully!');
    }

    public function edit(Category $category)
    {
        if (!auth()->user()->can('categories.update')) {
            abort(403, 'Unauthorized');
        }

        $category->append('image_url');

        $parentCategories = Category::forCurrentTenant()
            ->whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->sorted()
            ->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        if (!auth()->user()->can('categories.update')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $this->imageService->delete($category->image);
            $data['image'] = $this->imageService->upload($request->file('image'), 'categories');
        }

        if ($request->boolean('remove_image') && !$request->hasFile('image')) {
            $this->imageService->delete($category->image);
            $data['image'] = null;
        }

        $category->update($data);

        ActivityLogger::log("Category '{$category->name}' updated", 'category_updated', $category);

        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        if (!auth()->user()->can('categories.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->imageService->delete($category->image);

        ActivityLogger::log("Category '{$category->name}' deleted", 'category_deleted', $category);

        $category->delete();
        return admin_redirect('admin.categories.index')
                         ->with('success', 'Category deleted successfully!');
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('categories.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->input('search');

        $categories = Category::forCurrentTenant()
            ->with(['parent'])
            ->where('name', 'like', "%{$search}%")
            ->sorted()
            ->paginate(15);

        $categories->each(function ($category) {
            $category->append('image_url');
            $category->products_count = $category->products()->count();
        });

        $categories->appends(['search' => $search]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function importDefaults(MasterDataImportService $importService)
    {
        if (!auth()->user()->can('categories.create')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = tenant()?->id;

        if (!$tenantId) {
            return back()->withErrors(['error' => 'No tenant context found.']);
        }

        $stats = $importService->importCategories($tenantId);

        $message = sprintf(
            '%d categories imported successfully. %d existing categories skipped.',
            $stats['imported'],
            $stats['skipped']
        );

        return back()->with('success', $message);
    }
}
