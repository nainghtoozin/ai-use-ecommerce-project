<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorefrontPromotionRequest;
use App\Models\PromotionBanner;
use App\Models\Storefront;
use App\Models\StorefrontMedia;
use App\Services\StorefrontConfigurationResolver;
use App\Services\StorefrontRevisionService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontPromotionController extends Controller
{
    public function __construct(
        private readonly StorefrontConfigurationResolver $resolver,
        private readonly StorefrontRevisionService $revisionService,
    ) {}

    public function index()
    {
        $storefront = $this->storefront();
        $this->revisionService->prepareDraft($storefront);

        return Inertia::render('Admin/Storefront/Promotions', [
            'promotions' => PromotionBanner::with('storefrontMedia')->orderBy('position')->latest()->paginate(12)->withQueryString(),
            'media' => StorefrontMedia::where('storefront_id', $storefront->id)->latest()->get(['id', 'path', 'alt_text'])->append('url'),
        ]);
    }

    public function store(StorefrontPromotionRequest $request)
    {
        $storefront = $this->storefront();
        $data = $this->validatedData($request->validated(), $storefront);
        $data['tenant_id'] = tenant()->id;
        $data['image'] = $data['image'] ?? '';
        $data['link'] = $data['link'] ?? '/products';
        $data['position'] = $data['position'] ?? (int) PromotionBanner::max('position') + 1;
        PromotionBanner::create($data);
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Promotion created successfully.');
    }

    public function update(StorefrontPromotionRequest $request, PromotionBanner $promotion)
    {
        $storefront = $this->storefront();
        abort_unless((int) $promotion->tenant_id === (int) tenant()->id, 404);
        $this->revisionService->prepareDraft($storefront);
        $promotion->update($this->validatedData($request->validated(), $storefront));
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Promotion updated successfully.');
    }

    public function destroy(PromotionBanner $promotion)
    {
        $storefront = $this->storefront();
        abort_unless((int) $promotion->tenant_id === (int) tenant()->id, 404);
        $this->revisionService->prepareDraft($storefront);
        $promotion->delete();
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Promotion removed successfully.');
    }

    public function toggle(PromotionBanner $promotion)
    {
        $storefront = $this->storefront();
        abort_unless((int) $promotion->tenant_id === (int) tenant()->id, 404);
        $this->revisionService->prepareDraft($storefront);
        $promotion->update(['is_active' => !$promotion->is_active]);
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Promotion visibility updated.');
    }

    public function reorder(Request $request)
    {
        $storefront = $this->storefront();
        $this->revisionService->prepareDraft($storefront);
        abort_unless($request->user()?->can('settings.website'), 403);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        foreach ($ids as $position => $id) {
            PromotionBanner::whereKey($id)->where('tenant_id', tenant()->id)->update(['position' => $position]);
        }
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Promotion order updated.');
    }

    private function validatedData(array $data, Storefront $storefront): array
    {
        if (!empty($data['storefront_media_id'])) {
            $data['storefront_media_id'] = StorefrontMedia::where('storefront_id', $storefront->id)
                ->whereKey($data['storefront_media_id'])->value('id');
            abort_unless($data['storefront_media_id'], 422, 'The selected media does not belong to this storefront.');
        }

        if (!empty($data['link']) && !str_starts_with($data['link'], '/') && !preg_match('#^https?://#i', $data['link'])) {
            abort(422, 'The promotion link must be a storefront path or a secure web URL.');
        }

        return $data;
    }

    private function storefront(): Storefront
    {
        abort_unless(tenant() && Schema::hasTable('promotion_banners'), 404);
        $storefront = Storefront::first() ?: $this->resolver->provision(tenant());
        abort_unless($storefront && (int) $storefront->tenant_id === (int) tenant()->id, 404);
        return $storefront;
    }
}
