<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly ImageService $imageService,
    ) {}

    public function index()
    {
        $brands = $this->brandService->list();

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Brands/Create');
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $this->imageService->upload($request->file('logo'), 'brands');
        }

        $this->brandService->create($data);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand created successfully!');
    }

    public function edit(Brand $brand)
    {
        return Inertia::render('Admin/Brands/Edit', [
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $this->imageService->delete($brand->logo);
            $data['logo'] = $this->imageService->upload($request->file('logo'), 'brands');
        }

        $this->brandService->update($brand, $data);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand updated successfully!');
    }

    public function destroy(Brand $brand)
    {
        $this->imageService->delete($brand->logo);
        $this->brandService->delete($brand);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand deleted successfully!');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $brands = $this->brandService->search($query);
        $brands->appends(['query' => $query]);

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
            'query' => $query,
        ]);
    }
}
