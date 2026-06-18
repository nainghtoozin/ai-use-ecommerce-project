<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionBanner;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPromotionBannerController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index()
    {
        if (!auth()->user()->can('promotions.view')) {
            abort(403, 'Unauthorized');
        }

        $promotions = PromotionBanner::latest()->paginate(10);

        return Inertia::render('Admin/PromotionBanners/Index', [
            'promotions' => $promotions,
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('promotions.create')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'link' => 'required|url',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $this->imageService->upload($request->file('image'), 'promotions');
        }

        $promotion = PromotionBanner::create($data);

        return admin_redirect('admin.banners.index')
            ->with('success', 'Banner created successfully!');
    }

    public function show(PromotionBanner $promotion)
    {
        return admin_redirect('admin.banners.index');
    }

    public function update(Request $request, PromotionBanner $promotion)
    {
        if (!auth()->user()->can('promotions.update')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'link' => 'sometimes|url',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $this->imageService->delete($promotion->image);
            $data['image'] = $this->imageService->upload($request->file('image'), 'promotions');
        }

        $promotion->update($data);

        return admin_redirect('admin.banners.index')
            ->with('success', 'Banner updated successfully.');
    }

    public function destroy(PromotionBanner $promotion)
    {
        if (!auth()->user()->can('promotions.delete')) {
            abort(403, 'Unauthorized');
        }

        $this->imageService->delete($promotion->image);
        $promotion->delete();

        return admin_redirect('admin.banners.index')
            ->with('success', 'Banner deleted successfully.');
    }

    public function create()
    {
        if (!auth()->user()->can('promotions.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PromotionBanners/Create');
    }

    public function edit(PromotionBanner $promotion)
    {
        if (!auth()->user()->can('promotions.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PromotionBanners/Edit', [
            'promotion' => $promotion,
        ]);
    }

    public function search(Request $request)
    {
        if (!auth()->user()->can('promotions.view')) {
            abort(403, 'Unauthorized');
        }

        $query = $request->input('query');

        $promotions = PromotionBanner::where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $promotions->appends(['query' => $query]);

        return Inertia::render('Admin/PromotionBanners/Index', [
            'promotions' => $promotions,
            'query' => $query,
        ]);
    }
}
