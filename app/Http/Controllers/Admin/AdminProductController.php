<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\ImageService;
use App\Services\DashboardCacheService;
use App\Services\ActivityLogger;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('stock')) {
            $stockFilter = $request->input('stock');
            switch ($stockFilter) {
                case 'out_of_stock':
                    $query->where('stock', 0);
                    break;
                case 'low_stock':
                    $query->where('stock', '>', 0)->where('stock', '<', 10);
                    break;
                case 'in_stock':
                    $query->where('stock', '>=', 10);
                    break;
            }
        }

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];
        $warning = $resolved['warning'];
        
        if ($resolved['should_paginate']) {
            $products = $query->latest()->paginate($perPage)->appends($request->query());
            $showPagination = true;
        } else {
            $total = $query->count();
            $items = $query->latest()->get();
            
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $showPagination = false;
        }

        $categories = Category::all();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'showPagination' => $showPagination,
            'warning' => $warning,
            'filters' => [
                'search' => $request->input('search', ''),
                'category_id' => $request->input('category_id', ''),
                'status' => $request->input('status', ''),
                'stock' => $request->input('stock', ''),
            ],
        ]);
    }

    public function create()
    {
        $categories = Category::all();

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'base_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'status'      => 'required|in:active,inactive',
            'photo1'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'photo2'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = $request->except(['photo1', 'photo2']);
        
        if (!isset($data['status'])) {
            $data['status'] = Product::STATUS_ACTIVE;
        }

        if ($request->hasFile('photo1')) {
            $data['photo1'] = $this->imageService->upload($request->file('photo1'), 'products');
        }

        if ($request->hasFile('photo2')) {
            $data['photo2'] = $this->imageService->upload($request->file('photo2'), 'products');
        }

        Product::create($data);

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully!');
    }

    public function edit(Product $product)
    {
        $categories = Category::all();

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'base_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'status'      => 'required|in:active,inactive',
            'photo1'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'photo2'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = $request->except(['photo1', 'photo2']);

        if ($request->hasFile('photo1')) {
            $this->imageService->delete($product->photo1);
            $data['photo1'] = $this->imageService->upload($request->file('photo1'), 'products');
        }

        if ($request->hasFile('photo2')) {
            $this->imageService->delete($product->photo2);
            $data['photo2'] = $this->imageService->upload($request->file('photo2'), 'products');
        }

        $product->update($data);

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->imageService->delete($product->photo1);
        $this->imageService->delete($product->photo2);

        $product->delete();

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully!');
    }

    public function bulkDestroy(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $products = Product::whereIn('id', $ids)->get();

        $count = $products->count();
        $productNames = $products->pluck('name')->take(5)->toArray();
        $moreCount = $count > 5 ? $count - 5 : 0;

        $description = "Bulk deleted {$count} product(s): " . implode(', ', $productNames);
        if ($moreCount > 0) {
            $description .= " and {$moreCount} more";
        }

        ActivityLogger::log(
            $description,
            'product_bulk_deleted',
            null,
            ['product_ids' => $ids, 'count' => $count]
        );

        foreach ($products as $product) {
            $this->imageService->delete($product->photo1);
            $this->imageService->delete($product->photo2);
            $product->delete();
        }

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', "{$count} product(s) deleted successfully.");
    }

    public function bulkActivate(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $count = Product::whereIn('id', $ids)->update(['status' => Product::STATUS_ACTIVE]);

        ActivityLogger::log(
            "Bulk activated {$count} product(s)",
            'product_bulk_activated',
            null,
            ['product_ids' => $ids, 'count' => $count]
        );

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', "{$count} product(s) activated successfully.");
    }

    public function bulkDeactivate(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->input('ids');
        $count = Product::whereIn('id', $ids)->update(['status' => Product::STATUS_INACTIVE]);

        ActivityLogger::log(
            "Bulk deactivated {$count} product(s)",
            'product_bulk_deactivated',
            null,
            ['product_ids' => $ids, 'count' => $count]
        );

        app(DashboardCacheService::class)->clearProductRelatedCache();

        return redirect()->route('admin.products.index')
            ->with('success', "{$count} product(s) deactivated successfully.");
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $products = Product::with('category')
            ->where('name', 'like', "%{$query}%")
            ->latest()
            ->paginate(10);

        $products->appends(['query' => $query]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'query' => $query,
        ]);
    }
}
