<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index()
    {
        $products = Product::with('category')
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
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
            'photo1'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'photo2'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = $request->except(['photo1', 'photo2']);

        if ($request->hasFile('photo1')) {
            $data['photo1'] = $this->imageService->upload($request->file('photo1'), 'products');
        }

        if ($request->hasFile('photo2')) {
            $data['photo2'] = $this->imageService->upload($request->file('photo2'), 'products');
        }

        Product::create($data);

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

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->imageService->delete($product->photo1);
        $this->imageService->delete($product->photo2);

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully!');
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
