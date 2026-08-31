<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHomepageSectionsRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Services\StorefrontConfigurationResolver;
use App\Services\StorefrontRevisionService;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class StorefrontHomepageController extends Controller
{
    private const TYPES = ['hero', 'promotion', 'featured_categories', 'featured_products', 'product_showcase', 'store_highlights', 'brand_story', 'cta'];

    public function __construct(
        private readonly StorefrontConfigurationResolver $resolver,
        private readonly StorefrontRevisionService $revisionService,
    ) {}

    public function index()
    {
        $storefront = $this->storefront();
        $this->ensureSections($storefront);

        return Inertia::render('Admin/Storefront/Homepage', [
            'sections' => $storefront->fresh()->homepageSections->map(fn ($section) => [
                'id' => $section->id,
                'type' => $section->type,
                'variant' => $section->variant,
                'enabled' => $section->enabled,
                'desktop_visible' => $section->desktop_visible,
                'mobile_visible' => $section->mobile_visible,
                'position' => $section->position,
                'configuration' => $section->configuration ?? [],
            ])->values()->all(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'products' => Product::active()->orderBy('name')->limit(100)->get(['id', 'name', 'price'])->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'price' => $product->price])->values()->all(),
            'media' => StorefrontMedia::where('storefront_id', $storefront->id)->latest()->get(['id', 'alt_text'])->append('url'),
            'heroVariants' => \App\Services\StorefrontConfigurationResolver::heroVariants(),
        ]);
    }

    public function update(UpdateHomepageSectionsRequest $request)
    {
        $storefront = $this->storefront();
        $this->revisionService->prepareDraft($storefront);
        foreach ($request->validated()['sections'] as $position => $data) {
            $section = StorefrontHomepageSection::where('storefront_id', $storefront->id)->findOrFail($data['id']);
            $section->update([
                'enabled' => (bool) $data['enabled'],
                'desktop_visible' => (bool) $data['desktop_visible'],
                'mobile_visible' => (bool) $data['mobile_visible'],
                'position' => $position,
                'variant' => $this->sectionVariant($section->type, $data['variant'] ?? $section->variant),
                'configuration' => $this->sanitizeConfiguration($section->type, $data['configuration'] ?? [], $storefront),
            ]);
        }
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Homepage sections updated successfully.');
    }

    private function sanitizeConfiguration(string $type, array $configuration, Storefront $storefront): array
    {
        if ($type === 'promotion') {
            return ['limit' => min(max((int) ($configuration['limit'] ?? 6), 1), 12)];
        }

        if (in_array($type, ['featured_categories'], true)) {
            $ids = array_values(array_filter(array_map('intval', $configuration['category_ids'] ?? [])));
            return ['category_ids' => Category::withoutTenantScope()->where('tenant_id', tenant()->id)->whereIn('id', $ids)->pluck('id')->values()->all(), 'limit' => min(max((int) ($configuration['limit'] ?? 6), 1), 12)];
        }

        if (in_array($type, ['featured_products', 'product_showcase'], true)) {
            $ids = array_values(array_filter(array_map('intval', $configuration['product_ids'] ?? [])));
            return ['product_ids' => Product::withoutTenantScope()->where('tenant_id', tenant()->id)->active()->whereIn('id', $ids)->pluck('id')->values()->all(), 'limit' => min(max((int) ($configuration['limit'] ?? 8), 1), 24), 'title' => $this->text($configuration['title'] ?? null, 100), 'description' => $this->text($configuration['description'] ?? null, 500)];
        }

        if ($type === 'store_highlights') {
            $allowedIcons = ['star', 'truck', 'shield', 'headset', 'heart'];
            $items = collect($configuration['items'] ?? [])->take(6)->map(fn ($item) => [
                'icon' => in_array($item['icon'] ?? '', $allowedIcons, true) ? $item['icon'] : 'star',
                'title' => $this->text($item['title'] ?? null, 100),
                'description' => $this->text($item['description'] ?? null, 300),
            ])->filter(fn ($item) => $item['title'])->values()->all();
            return ['items' => $items];
        }

        if (in_array($type, ['brand_story', 'cta'], true)) {
            $mediaId = !empty($configuration['media_id'])
                ? StorefrontMedia::where('storefront_id', $storefront->id)->whereKey($configuration['media_id'])->value('id')
                : null;
            $result = [
                'title' => $this->text($configuration['title'] ?? null, 150),
                'description' => $this->text($configuration['description'] ?? null, 2000),
                'button_text' => $this->text($configuration['button_text'] ?? null, 100),
                'button_link' => $this->safePath($configuration['button_link'] ?? null),
                'media_id' => $mediaId,
            ];
            if ($type === 'brand_story') {
                unset($result['title'], $result['description']);
            }
            return $result;
        }

        return $configuration;
    }

    private function ensureSections(Storefront $storefront): void
    {
        $this->resolver->ensureHomepageSections($storefront);
    }

    private function text(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr(trim($value), 0, $length);
    }

    private function safePath(?string $value): ?string
    {
        if (!$value) return null;
        return str_starts_with($value, '/') || preg_match('#^https?://#i', $value) ? $value : null;
    }

    private function sectionVariant(string $type, ?string $variant): string
    {
        $allowed = [
            'hero' => \App\Services\StorefrontConfigurationResolver::HERO_VARIANTS,
            'featured_categories' => ['default', 'grid', 'horizontal', 'compact'],
            'featured_products' => ['default', 'grid', 'compact', 'image-focused', 'horizontal'],
            'product_showcase' => ['default', 'grid', 'compact', 'image-focused', 'horizontal'],
            'brand_story' => ['default', 'split', 'text-only'],
            'cta' => ['default', 'centered', 'full-width'],
        ];
        if ($type === 'hero') {
            return \App\Services\StorefrontConfigurationResolver::normalizeHeroVariant($variant);
        }
        return in_array($variant, $allowed[$type] ?? ['default'], true) ? $variant : 'default';
    }

    private function storefront(): Storefront
    {
        abort_unless(tenant() && Schema::hasTable('storefront_homepage_sections'), 404);
        $storefront = Storefront::first() ?: $this->resolver->provision(tenant());
        abort_unless($storefront && (int) $storefront->tenant_id === (int) tenant()->id, 404);
        return $storefront;
    }
}
