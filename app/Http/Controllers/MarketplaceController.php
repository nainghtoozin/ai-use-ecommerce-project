<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::where('status', 'active')
            ->whereNotNull('activated_at');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Category filter (via products)
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('products', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'name_az':
                $query->orderBy('name', 'asc');
                break;
            case 'name_za':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $stores = $query->select([
            'id', 'name', 'slug', 'logo', 'status',
        ])->withCount(['products' => function ($q) {
            $q->withoutTenantScope()->where('status', \App\Models\Product::STATUS_ACTIVE);
        }])->paginate(12)->withQueryString();

        // Add description from settings and product count
        $stores->getCollection()->transform(function ($store) {
            $settings = $store->settings ?? [];
            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'logo_url' => $store->logo_url,
                'description' => $settings['description'] ?? '',
                'products_count' => (int) ($store->products_count ?? 0),
                'store_url' => '/store/' . $store->slug,
            ];
        });

        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Public/StoreDirectory', [
            'stores' => $stores,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category_id' => $request->input('category_id'),
                'sort' => $sort,
            ],
        ]);
    }
}
