<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStorefrontNavigationRequest;
use App\Models\Storefront;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Services\StorefrontConfigurationResolver;
use App\Services\StorefrontRevisionService;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class StorefrontNavigationController extends Controller
{
    public function __construct(
        private readonly StorefrontConfigurationResolver $resolver,
        private readonly StorefrontRevisionService $revisionService,
    ) {}

    public function index()
    {
        $navigation = $this->navigation();

        return Inertia::render('Admin/Storefront/Navigation', [
            'navigation' => [
                'settings' => $navigation->settings ?? [],
                'items' => $navigation->items->map(fn ($item) => [
                    'id' => $item->id,
                    'key' => $item->key,
                    'label' => $item->label,
                    'path' => $item->path,
                    'icon' => $item->icon,
                    'enabled' => $item->enabled,
                    'position' => $item->position,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(UpdateStorefrontNavigationRequest $request)
    {
        $navigation = $this->navigation();
        $storefront = $this->storefrontForNavigation($navigation);
        $this->revisionService->prepareDraft($storefront);
        $validated = $request->validated();

        $navigation->update([
            'settings' => [
                'show_store_name' => (bool) $validated['show_store_name'],
                'show_search' => (bool) $validated['show_search'],
            ],
        ]);

        foreach ($validated['items'] as $position => $itemData) {
            $item = StorefrontNavigationItem::where('navigation_id', $navigation->id)
                ->findOrFail($itemData['id']);
            $item->update([
                'label' => $itemData['label'],
                'path' => $itemData['path'],
                'enabled' => (bool) $itemData['enabled'],
                'position' => $position,
            ]);
        }
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Header and navigation updated successfully.');
    }

    private function navigation(): StorefrontNavigation
    {
        abort_unless(tenant() && Schema::hasTable('storefront_navigations'), 404);
        $storefront = Storefront::first() ?: $this->resolver->provision(tenant());
        abort_unless($storefront && (int) $storefront->tenant_id === (int) tenant()->id, 404);

        $navigation = StorefrontNavigation::with('items')->first();
        if (!$navigation) {
            $this->resolver->provision(tenant());
            $navigation = StorefrontNavigation::with('items')->first();
        }

        abort_unless($navigation && (int) $navigation->tenant_id === (int) tenant()->id, 404);
        return $navigation;
    }

    private function storefrontForNavigation(StorefrontNavigation $navigation): Storefront
    {
        return Storefront::whereKey($navigation->storefront_id)->firstOrFail();
    }
}
