<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\ImageService;
use App\Services\MasterDataImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly ImageService $imageService,
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('brands.view')) {
            abort(403, 'Unauthorized');
        }

        $filters = $request->only(['search', 'filter_active', 'filter_featured']);
        $brands = $this->brandService->list($filters);

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('brands.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Brands/Create');
    }

    public function store(StoreBrandRequest $request)
    {
        if (!auth()->user()->can('brands.create')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $this->imageService->upload($request->file('logo'), 'brands');
        }

        if ($request->hasFile('banner')) {
            $data['banner'] = $this->imageService->upload($request->file('banner'), 'brands/banners');
        }

        $this->brandService->create($data);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand created successfully!');
    }

    public function edit(Brand $brand)
    {
        if (!auth()->user()->can('brands.update')) {
            abort(403, 'Unauthorized');
        }

        $brand->append(['logo_url', 'banner_url']);

        return Inertia::render('Admin/Brands/Edit', [
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        if (!auth()->user()->can('brands.update')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $this->imageService->delete($brand->logo);
            $data['logo'] = $this->imageService->upload($request->file('logo'), 'brands');
        }

        if ($request->hasFile('banner')) {
            $this->imageService->delete($brand->banner);
            $data['banner'] = $this->imageService->upload($request->file('banner'), 'brands/banners');
        }

        if ($request->boolean('remove_logo') && !$request->hasFile('logo')) {
            $this->imageService->delete($brand->logo);
            $data['logo'] = null;
        }

        if ($request->boolean('remove_banner') && !$request->hasFile('banner')) {
            $this->imageService->delete($brand->banner);
            $data['banner'] = null;
        }

        $this->brandService->update($brand, $data);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand updated successfully!');
    }

    public function destroy(Brand $brand)
    {
        if (!auth()->user()->can('brands.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->imageService->delete($brand->logo);
        $this->imageService->delete($brand->banner);
        $this->brandService->delete($brand);

        return admin_redirect('admin.brands.index')
            ->with('success', 'Brand deleted successfully!');
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('brands.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->input('search');
        $brands = $this->brandService->list(['search' => $search]);
        $brands->appends(['search' => $search]);

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function importDefaults(MasterDataImportService $importService)
    {
        if (!auth()->user()->can('brands.create')) {
            abort(403, 'Unauthorized');
        }

        $tenantId = tenant()?->id;

        if (!$tenantId) {
            return back()->withErrors(['error' => 'No tenant context found.']);
        }

        $stats = $importService->importBrands($tenantId);

        $message = sprintf(
            '%d brands imported successfully. %d existing brands skipped.',
            $stats['imported'],
            $stats['skipped']
        );

        return back()->with('success', $message);
    }
}
