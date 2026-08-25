<?php

namespace App\Services;

use App\Models\Storefront;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontThemeConfig;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\WebsiteInfo;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionBanner;
use App\Models\StorefrontRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class StorefrontConfigurationResolver
{
    private const DEFAULT_LABELS = [
        'add_to_cart' => 'Add to Cart',
        'buy_now' => 'Buy Now',
        'buy' => 'Buy',
        'shop_now' => 'Shop Now',
        'view_product' => 'View Product',
        'view_cart' => 'View Cart',
        'checkout' => 'Checkout',
        'place_order' => 'Place Order',
        'continue_shopping' => 'Continue Shopping',
        'out_of_stock' => 'Out of Stock',
        'select_options' => 'Select options',
        'clear_cart' => 'Clear Cart',
        'product_description' => 'Product Description',
        'bundle_details' => 'Bundle Details',
        'no_products_found' => 'No products found',
        'view_all_products' => 'View all products',
        'search_products' => 'Search products...',
        'all_categories' => 'All Categories',
        'categories' => 'Categories',
    ];

    public const HOMEPAGE_SECTION_TYPES = [
        'hero',
        'promotion',
        'featured_categories',
        'featured_products',
        'product_showcase',
        'store_highlights',
        'brand_story',
        'cta',
    ];

    public const LEGACY_SECTION_TYPE_MAP = [
        'product_discovery' => 'featured_products',
    ];

    public function resolve(?Tenant $tenant = null, string $context = 'published', bool $includeHomepage = true): array
    {
        $tenant = $tenant ?? Tenant::getCurrent();
        if ($tenant && $context !== 'base' && Schema::hasTable('storefront_revisions')) {
            $storefront = Storefront::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
            $revisionId = $context === 'draft'
                ? ($storefront?->draft_revision_id ?: $storefront?->published_revision_id)
                : $storefront?->published_revision_id;
            if ($revisionId) {
                $revision = StorefrontRevision::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)->whereKey($revisionId)->first();
                if ($revision?->configuration) {
                    $configuration = $this->filterRevisionConfiguration($revision->configuration, $tenant->name);
                    return $includeHomepage ? $configuration : $this->withoutHomepageData($configuration);
                }
            }
        }

        return $this->resolveBase($tenant, false, $includeHomepage);
    }

    public function resolveBase(?Tenant $tenant = null, bool $forRevision = false, bool $includeHomepage = true): array
    {
        $tenant = $tenant ?? Tenant::getCurrent();

        if (!$tenant) {
            return $this->emptyContract();
        }

        $currentTenant = Tenant::getCurrent();
        if ($currentTenant && (int) $currentTenant->id !== (int) $tenant->id) {
            abort(403, 'The storefront configuration does not belong to the current tenant.');
        }

        $legacy = $currentTenant
            ? WebsiteInfo::getSettings()
            : WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenant->id)->first();

        if (!$legacy) {
            $legacy = WebsiteInfo::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'site_name' => $tenant->name,
                'theme_color' => '#3B82F6',
                'default_language' => 'en',
                'timezone' => 'Asia/Yangon',
                'currency_code' => 'MMK',
                'currency_symbol' => 'K',
                'date_format' => 'Y-m-d',
                'allow_registration' => true,
                'maintenance_mode' => false,
            ]);
        }

        $storefront = null;
        if (Schema::hasTable('storefronts')) {
            $relations = [
                'theme',
                'themeConfig',
                'designTokens',
                'homepageSections',
                'content',
            ];
            if (Schema::hasTable('storefront_navigations')) {
                $relations[] = 'navigation.items';
            }

            $storefrontQuery = Storefront::with($relations);
            $storefront = $currentTenant
                ? $storefrontQuery->first()
                : $storefrontQuery->withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        }

        $mediaIds = collect($storefront?->homepageSections ?? [])
            ->flatMap(fn ($section) => array_merge(
                $section->configuration['media_ids'] ?? [],
                array_filter([$section->configuration['media_id'] ?? null]),
            ))
            ->filter()->unique()->values();
        if ($storefront) {
            $storefront->setRelation('media', $mediaIds->isNotEmpty()
                ? $storefront->media()->whereIn('id', $mediaIds)->get()
                : collect());
        }

        $theme = $storefront?->theme;
        $tokens = $storefront?->designTokens?->tokens
            ?? $theme?->default_tokens
            ?? $this->defaultTokens($legacy->theme_color);
        $tokens = array_replace_recursive($this->defaultTokens($legacy->theme_color), $tokens ?: []);

        $labels = array_replace(
            self::DEFAULT_LABELS,
            $storefront?->content?->labels ?? [],
        );

        return [
            'id' => $storefront?->id,
            'status' => $storefront?->status ?? 'active',
            'identity' => [
                'name' => $tenant->name,
                'store_name' => $tenant->name,
                'site_title' => $legacy->site_name ?: $tenant->name,
                'tagline' => $legacy->site_tagline,
                'description' => $legacy->site_description ?: $legacy->site_tagline,
                'logo_url' => $legacy->logo_url,
                'favicon_url' => $legacy->favicon_url,
            ],
            'theme' => [
                'id' => $theme?->id,
                'slug' => $theme?->slug ?? 'commerce-default',
                'name' => $theme?->name ?? 'Commerce Default',
                'version' => $theme?->version ?? '1.0.0',
                'configuration' => $storefront?->themeConfig?->configuration ?? [],
            ],
            'design' => $tokens,
            'navigation' => $this->navigation($storefront),
            'homepage' => [
                'sections' => $includeHomepage ? $this->homepageSections($storefront, $legacy, (int) $tenant->id, $forRevision) : [],
            ],
            'media' => [
                'logo' => $legacy->logo_url,
                'favicon' => $legacy->favicon_url,
                'og_image' => $legacy->og_image_url,
                'hero' => $legacy->hero_images_urls,
            ],
            'content' => ['labels' => $labels],
            'behavior' => [
                'allow_registration' => (bool) ($legacy->allow_registration ?? true),
                'enable_reviews' => (bool) ($legacy->enable_reviews ?? true),
                'enable_wishlist' => (bool) ($legacy->enable_wishlist ?? true),
                'enable_compare' => (bool) ($legacy->enable_compare ?? true),
            ],
            'shop' => [
                'currency_code' => $legacy->currency_code,
                'currency_symbol' => $legacy->currency_symbol,
                'currency_position' => $legacy->currency_position,
                'decimal_places' => $legacy->decimal_places,
            ],
            'checkout' => [
                'guest_checkout_enabled' => (bool) ($legacy->guest_checkout_enabled ?? true),
                'cod_enabled' => (bool) ($legacy->cod_enabled ?? true),
            ],
            'seo' => [
                'title' => $legacy->meta_title ?: $legacy->site_name,
                'description' => $legacy->meta_description ?: $legacy->site_description,
                'keywords' => $legacy->meta_keywords ?: $legacy->site_keywords,
                'canonical_url' => $legacy->canonical_url,
                'robots' => $legacy->robots_meta,
                'og_image_url' => $legacy->og_image_url,
            ],
        ];
    }

    public function resolveRevision(StorefrontRevision $revision): array
    {
        abort_unless(tenant() && (int) $revision->tenant_id === (int) tenant()->id, 404);
        abort_unless(in_array($revision->status, ['draft', 'published', 'archived'], true), 404);
        return $this->filterRevisionConfiguration($revision->configuration ?? [], tenant()?->name);
    }

    private function withoutHomepageData(array $configuration): array
    {
        $configuration['homepage'] = ['sections' => []];
        $configuration['media']['hero'] = [];
        return $configuration;
    }

    public function provision(Tenant $tenant): ?Storefront
    {
        if (!Schema::hasTable('storefronts')) {
            return null;
        }

        $theme = Theme::where('slug', 'commerce-default')->first();
        if (!$theme) {
            return null;
        }

        $storefront = Storefront::withoutTenantScope()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['theme_id' => $theme->id, 'status' => 'active'],
        );

        StorefrontThemeConfig::withoutTenantScope()->firstOrCreate(
            ['storefront_id' => $storefront->id],
            [
                'tenant_id' => $tenant->id,
                'theme_id' => $theme->id,
                'configuration' => ['hero_variant' => 'auto'],
            ],
        );

        StorefrontDesignToken::withoutTenantScope()->firstOrCreate(
            ['storefront_id' => $storefront->id],
            ['tenant_id' => $tenant->id, 'tokens' => $theme->default_tokens],
        );

        StorefrontContent::withoutTenantScope()->firstOrCreate(
            ['storefront_id' => $storefront->id],
            ['tenant_id' => $tenant->id, 'labels' => self::DEFAULT_LABELS],
        );

        $this->provisionNavigation($storefront, $tenant);
        $this->ensureHomepageSections($storefront);

        return $storefront;
    }

    public function ensureHomepageSections(Storefront $storefront): void
    {
        if (!Schema::hasTable('storefront_homepage_sections')) {
            return;
        }

        $this->normalizeHomepageSections($storefront);

        $existing = StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->pluck('type')
            ->all();

        $position = (int) StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->max('position') + 1;

        foreach (self::HOMEPAGE_SECTION_TYPES as $type) {
            if (in_array($type, $existing, true)) {
                continue;
            }

            StorefrontHomepageSection::withoutTenantScope()->create([
                'tenant_id' => $storefront->tenant_id,
                'storefront_id' => $storefront->id,
                'type' => $type,
                'variant' => 'default',
                'enabled' => true,
                'desktop_visible' => true,
                'mobile_visible' => $type !== 'brand_story',
                'position' => $position++,
                'configuration' => [],
            ]);
        }
    }

    private function normalizeHomepageSections(Storefront $storefront): void
    {
        $sections = StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->orderBy('id')
            ->get();

        foreach ($sections as $section) {
            $canonical = self::LEGACY_SECTION_TYPE_MAP[$section->type] ?? null;
            if ($canonical === null) {
                continue;
            }

            $target = StorefrontHomepageSection::withoutTenantScope()
                ->where('storefront_id', $storefront->id)
                ->where('type', $canonical)
                ->first();

            if ($target && $target->id !== $section->id) {
                $target->update([
                    'enabled' => $target->enabled || $section->enabled,
                    'desktop_visible' => $target->desktop_visible && $section->desktop_visible,
                    'mobile_visible' => $target->mobile_visible && $section->mobile_visible,
                    'position' => min($target->position, $section->position),
                    'variant' => $target->variant !== 'default' ? $target->variant : $section->variant,
                    'configuration' => $this->mergeConfiguration($target->configuration ?? [], $section->configuration ?? []),
                ]);
                $section->delete();
            } else {
                $section->update(['type' => $canonical]);
            }
        }

        $sections = StorefrontHomepageSection::withoutTenantScope()
            ->where('storefront_id', $storefront->id)
            ->orderBy('id')
            ->get()
            ->groupBy('type');

        foreach ($sections as $type => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $canonical = $group->first();
            foreach ($group->slice(1) as $duplicate) {
                $canonical->update([
                    'enabled' => $canonical->enabled || $duplicate->enabled,
                    'desktop_visible' => $canonical->desktop_visible && $duplicate->desktop_visible,
                    'mobile_visible' => $canonical->mobile_visible && $duplicate->mobile_visible,
                    'position' => min($canonical->position, $duplicate->position),
                    'variant' => $canonical->variant !== 'default' ? $canonical->variant : $duplicate->variant,
                    'configuration' => $this->mergeConfiguration($canonical->configuration ?? [], $duplicate->configuration ?? []),
                ]);
                $duplicate->delete();
            }
        }
    }

    private function mergeConfiguration(array $primary, array $secondary): array
    {
        foreach ($secondary as $key => $value) {
            if (!array_key_exists($key, $primary) || $primary[$key] === null || $primary[$key] === '' || $primary[$key] === []) {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }

    private function provisionNavigation(Storefront $storefront, Tenant $tenant): void
    {
        if (!Schema::hasTable('storefront_navigations')) {
            return;
        }

        $navigation = StorefrontNavigation::withoutTenantScope()->firstOrCreate(
            ['storefront_id' => $storefront->id],
            ['tenant_id' => $tenant->id, 'settings' => ['show_store_name' => true, 'show_search' => true]],
        );

        foreach ([
            ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door'],
            ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid'],
            ['key' => 'contact', 'label' => 'Contact', 'path' => '/contact', 'icon' => 'bi-envelope'],
            ['key' => 'orders', 'label' => 'My Orders', 'path' => '/customer/orders', 'icon' => 'bi-receipt'],
        ] as $position => $item) {
            StorefrontNavigationItem::withoutTenantScope()->firstOrCreate(
                ['navigation_id' => $navigation->id, 'key' => $item['key']],
                ['tenant_id' => $tenant->id, ...$item, 'enabled' => true, 'position' => $position],
            );
        }
    }

    private function navigation(?Storefront $storefront): array
    {
        if (!Schema::hasTable('storefront_navigations')) {
            return [
                'show_store_name' => true,
                'show_search' => true,
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door', 'position' => 0],
                    ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid', 'position' => 1],
                ],
            ];
        }

        $navigation = $storefront?->navigation;
        if (!$navigation) {
            return [
                'show_store_name' => true,
                'show_search' => true,
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'path' => '/', 'icon' => 'bi-house-door', 'position' => 0],
                    ['key' => 'products', 'label' => 'Products', 'path' => '/products', 'icon' => 'bi-grid', 'position' => 1],
                ],
            ];
        }

        $items = collect($navigation?->items ?? [])
            ->where('enabled', true)
            ->map(fn ($item) => [
                'key' => $item->key,
                'label' => $item->label,
                'path' => $item->path,
                'icon' => $item->icon,
                'position' => $item->position,
            ])->values()->all();

        return [
            'show_store_name' => (bool) ($navigation?->settings['show_store_name'] ?? true),
            'show_search' => (bool) ($navigation?->settings['show_search'] ?? true),
            'items' => $items,
        ];
    }

    private function homepageSections(?Storefront $storefront, WebsiteInfo $legacy, int $tenantId, bool $forRevision = false): array
    {
        $media = collect($storefront?->media ?? [])->keyBy('id');
        $sections = collect($storefront?->homepageSections ?? [])
            ->map(function ($section) use ($media, $tenantId, $forRevision, $legacy) {
                $configuration = $section->configuration ?? [];

                // Always normalize media-derived keys so consumers (draft preview,
                // public storefront) never substitute legacy WebsiteInfo imagery
                // when a section simply has no media attached.
                $mediaIds = collect(array_merge(
                    $configuration['media_ids'] ?? [],
                    array_filter([$configuration['media_id'] ?? null]),
                ))->filter()->unique()->values();
                $mediaItems = $mediaIds
                    ->map(fn ($mediaId) => $media->get($mediaId))
                    ->filter(fn ($item) => $item && ImageService::exists($item->path))
                    ->values();
                $configuration['images'] = $mediaItems
                    ->map(fn ($item) => $item->url)
                    ->all();
                $configuration['image_alts'] = $mediaItems->map(fn ($item) => $item->alt_text)->all();

                return [
                'id' => $section->id,
                'type' => $section->type,
                'variant' => $section->variant,
                'enabled' => (bool) $section->enabled,
                'desktop_visible' => (bool) $section->desktop_visible,
                'mobile_visible' => (bool) $section->mobile_visible,
                'position' => (int) $section->position,
                'configuration' => $configuration,
                'data' => $section->enabled ? $this->sectionData($section->type, $configuration, $media, $tenantId, $forRevision, $legacy) : [],
                ];
            })->values()->all();

        if ($sections) {
            return $sections;
        }

        return [
            [
                'id' => null,
                'type' => 'hero',
                'variant' => !empty($legacy->hero_images_urls) ? 'image' : 'text-only',
                'enabled' => true,
                'desktop_visible' => true,
                'mobile_visible' => true,
                'position' => 0,
                'configuration' => [
                    'title' => $legacy->hero_title ?: $legacy->site_name,
                    'subtitle' => $legacy->hero_subtitle ?: $legacy->site_description,
                    'button_text' => $legacy->hero_button_text,
                    'button_link' => $legacy->hero_button_link,
                    'images' => $legacy->hero_images_urls,
                ],
            ],
        ];
    }

    private function sectionData(string $type, array $configuration, $media, int $tenantId, bool $forRevision = false, ?WebsiteInfo $legacy = null): array
    {
        return match ($type) {
            'promotion' => $this->promotionData($configuration, $tenantId, $forRevision),
            'featured_categories' => $this->categoryData($configuration, $tenantId),
            'featured_products', 'product_showcase' => $this->productData($configuration, $tenantId),
            'store_highlights' => ['items' => array_values(array_filter($configuration['items'] ?? [], fn ($item) => !empty($item['title'])))] ,
            'brand_story', 'cta' => $this->contentSectionData($type, $configuration, $media, $legacy),
            default => [],
        };
    }

    private function promotionData(array $configuration, int $tenantId, bool $forRevision = false): array
    {
        $query = PromotionBanner::query()->withoutGlobalScopes()
            ->where('promotion_banners.tenant_id', $tenantId)
            ->with('storefrontMedia')
            ->orderBy('position')->orderByDesc('id');
        if (!$forRevision) {
            $query->where('promotion_banners.is_active', true)
                ->where(function ($q) {
                    $q->whereNull('promotion_banners.starts_at')->orWhere('promotion_banners.starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('promotion_banners.ends_at')->orWhere('promotion_banners.ends_at', '>=', now());
                });
        }
        $promotions = $query->limit((int) ($configuration['limit'] ?? 6))->get();

        return [
            'promotions' => $promotions->filter(fn ($promotion) => (int) $promotion->tenant_id === $tenantId && $promotion->title && ($forRevision || $promotion->isCurrentlyVisible()))->map(fn ($promotion) => [
                'id' => $promotion->id,
                'title' => $promotion->title,
                'description' => $promotion->description,
                'image_url' => $this->validPromotionImage($promotion),
                'media_id' => $promotion->storefront_media_id,
                'cta_label' => $promotion->cta_label ?: 'Shop Now',
                'link' => $promotion->link ?: '/products',
                'is_active' => (bool) $promotion->is_active,
                'starts_at' => $promotion->starts_at?->toISOString(),
                'ends_at' => $promotion->ends_at?->toISOString(),
                'desktop_visible' => $promotion->desktop_visible,
                'mobile_visible' => $promotion->mobile_visible,
            ])->values()->all(),
        ];
    }

    private function validPromotionImage(PromotionBanner $promotion): ?string
    {
        if ($promotion->storefrontMedia && ImageService::exists($promotion->storefrontMedia->path)) {
            return $promotion->storefrontMedia->url;
        }
        if ($promotion->image && ImageService::exists($promotion->image)) {
            return ImageService::url($promotion->image);
        }
        return null;
    }

    private function categoryData(array $configuration, int $tenantId): array
    {
        $ids = array_values(array_filter(array_map('intval', $configuration['category_ids'] ?? [])));
        if (!$ids) return ['categories' => []];
        $categories = Category::withoutTenantScope()->where('tenant_id', $tenantId)->whereIn('id', $ids)
            ->withCount(['products as products_count' => fn ($query) => $query->withoutGlobalScopes()->where('products.tenant_id', $tenantId)])
            ->get()->sortBy(fn ($category) => array_search($category->id, $ids, true))->values();
        return ['categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'products_count' => $category->products_count])->all()];
    }

    private function productData(array $configuration, int $tenantId): array
    {
        $ids = array_values(array_filter(array_map('intval', $configuration['product_ids'] ?? [])));
        if (!$ids) return ['products' => []];
        $limit = min(max((int) ($configuration['limit'] ?? 8), 1), 24);
        $products = Product::withoutTenantScope()->where('tenant_id', $tenantId)->active()->whereIn('id', $ids)
            ->with([
                'category' => fn ($query) => $query->withoutGlobalScopes()->where('categories.tenant_id', $tenantId),
                'brand' => fn ($query) => $query->withoutGlobalScopes()->where('brands.tenant_id', $tenantId),
                'variants', 'comboItems.comboProduct', 'comboItems.linkedVariant',
            ])->get()
            ->sortBy(fn ($product) => array_search($product->id, $ids, true))->values()->take($limit);
        return ['products' => $products->values()->all()];
    }

    private function contentSectionData(string $type, array $configuration, $media, ?WebsiteInfo $legacy = null): array
    {
        // Explicitly configured values always win; legacy WebsiteInfo content is
        // only a fallback for sections that have never been configured.
        $data = [
            'title' => ($type === 'brand_story' ? (($configuration['title'] ?? null) ?: ($legacy?->about_title ?? null)) : ($configuration['title'] ?? null)),
            'description' => ($type === 'brand_story' ? (($configuration['description'] ?? null) ?: ($legacy?->about_description ?? null)) : ($configuration['description'] ?? null)),
            'button_text' => $configuration['button_text'] ?? null,
            'button_link' => $configuration['button_link'] ?? null,
            'image_url' => null,
        ];
        $mediaId = $configuration['media_id'] ?? null;
        if ($mediaId && $media->get($mediaId) && ImageService::exists($media->get($mediaId)->path)) {
            $data['image_url'] = $media->get($mediaId)->url;
        }
        if ($type === 'brand_story' && empty($data['description'])) return [];
        if ($type === 'cta' && empty($data['title'])) return [];
        return $data;
    }

    private function filterRevisionConfiguration(array $configuration, ?string $storeName = null): array
    {
        if ($storeName) {
            $previousName = $configuration['identity']['name'] ?? null;
            $configuration['identity']['name'] = $storeName;
            $configuration['identity']['store_name'] = $storeName;
            $configuration['identity']['site_title'] = $configuration['identity']['site_title'] ?? ($previousName ?: $storeName);
        }
        foreach ($configuration['homepage']['sections'] ?? [] as &$section) {
            if (array_key_exists('enabled', $section)) {
                $section['enabled'] = (bool) $section['enabled'];
            }
            if (array_key_exists('desktop_visible', $section)) {
                $section['desktop_visible'] = (bool) $section['desktop_visible'];
            }
            if (array_key_exists('mobile_visible', $section)) {
                $section['mobile_visible'] = (bool) $section['mobile_visible'];
            }

            if (($section['type'] ?? null) !== 'promotion') continue;

            $section['data']['promotions'] = array_values(array_filter(
                $section['data']['promotions'] ?? [],
                function (array $promotion): bool {
                    if (($promotion['is_active'] ?? true) !== true) return false;
                    if (!empty($promotion['starts_at']) && Carbon::parse($promotion['starts_at'])->isFuture()) return false;
                    if (!empty($promotion['ends_at']) && Carbon::parse($promotion['ends_at'])->isPast()) return false;
                    return !empty($promotion['title']);
                },
            ));
        }
        unset($section);

        return $configuration;
    }

    private function emptyContract(): array
    {
        return [
            'id' => null,
            'status' => 'inactive',
            'identity' => ['name' => 'My Store', 'store_name' => 'My Store', 'site_title' => 'My Store', 'tagline' => null, 'description' => null, 'logo_url' => null, 'favicon_url' => null],
            'theme' => ['id' => null, 'slug' => 'commerce-default', 'name' => 'Commerce Default', 'version' => '1.0.0', 'configuration' => []],
            'design' => $this->defaultTokens(),
            'navigation' => ['show_store_name' => true, 'show_search' => true, 'items' => []],
            'homepage' => ['sections' => []],
            'media' => ['logo' => null, 'favicon' => null, 'og_image' => null, 'hero' => []],
            'content' => ['labels' => self::DEFAULT_LABELS],
            'behavior' => ['allow_registration' => true, 'enable_reviews' => true, 'enable_wishlist' => true, 'enable_compare' => true],
            'shop' => [],
            'checkout' => ['guest_checkout_enabled' => true, 'cod_enabled' => true],
            'seo' => [],
        ];
    }

    private function defaultTokens(?string $primary = null): array
    {
        return [
            'color' => [
                'primary' => $primary ?: '#3B82F6',
                'secondary' => '#1D4ED8',
                'accent' => '#F59E0B',
                'surface' => '#FFFFFF',
                'background' => '#F9FAFB',
                'surface_muted' => '#F1F5F9',
                'text' => '#111827',
                'muted' => '#6B7280',
                'text_muted' => '#6B7280',
                'border' => '#E5E7EB',
                'success' => '#16A34A',
                'warning' => '#D97706',
                'danger' => '#DC2626',
            ],
            'typography' => ['font_family' => 'Figtree', 'heading_weight' => '700', 'body_weight' => '400', 'heading_scale' => '1.15', 'body_size' => '1rem', 'small_size' => '0.875rem', 'line_height' => '1.5'],
            'layout' => ['page_width' => '80rem', 'section_spacing' => '2rem', 'content_spacing' => '1rem', 'grid_gap' => '1rem'],
            'spacing' => ['section' => '2rem', 'card' => '1rem', 'control' => '0.625rem'],
            'radius' => ['button' => '0.5rem', 'card' => '0.75rem', 'input' => '0.5rem'],
            'borders' => ['width' => '1px', 'style' => 'solid'],
            'elevation' => ['card' => '0 1px 3px 0 rgb(15 23 42 / 0.10)', 'dropdown' => '0 10px 25px -5px rgb(15 23 42 / 0.15)', 'modal' => '0 20px 40px -10px rgb(15 23 42 / 0.25)'],
            'shadows' => ['card' => '0 1px 3px 0 rgb(0 0 0 / 0.1)'],
            'buttons' => ['primary_style' => 'solid'],
            'cards' => ['style' => 'bordered'],
            'inputs' => ['style' => 'outlined'],
            'product_cards' => ['variant' => 'standard'],
            'variants' => ['hero' => 'split', 'categories' => 'grid', 'products' => 'grid', 'brand_story' => 'split', 'cta' => 'centered'],
        ];
    }
}
