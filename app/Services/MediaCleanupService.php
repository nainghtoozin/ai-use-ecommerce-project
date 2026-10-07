<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromotionBanner;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\WebsiteFaq;
use App\Models\WebsiteInfo;
use Illuminate\Support\Facades\Schema;

class MediaCleanupService
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function usageFor(StorefrontMedia $media, int $tenantId): ?string
    {
        if (in_array($media->key, ['logo', 'favicon'], true)) {
            return $media->key;
        }

        $heroUses = StorefrontHomepageSection::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('type', 'hero')
            ->get()
            ->contains(fn ($section) => in_array($media->id, array_map('intval', $section->configuration['media_ids'] ?? []), true));
        if ($heroUses) {
            return 'the homepage hero';
        }

        if (PromotionBanner::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where('storefront_media_id', $media->id)->exists()) {
            return 'a promotion';
        }

        $info = WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenantId)->first();
        if ($info) {
            $identityPaths = [$info->logo, $info->favicon, $info->og_image, $info->footer_logo, $info->about_image];
            if (in_array($media->path, $identityPaths, true)) {
                return 'store identity or SEO';
            }
            foreach ((array) ($info->hero_images ?? []) as $heroPath) {
                if ($heroPath && $heroPath === $media->path) {
                    return 'store identity or SEO';
                }
            }
        }

        $quotedPath = '"%' . addcslashes($media->path, '%_\\"') . '%"';
        $srcDouble = '%src="' . addcslashes($media->path, '%_\\"') . '"%';
        $srcSingle = "%src='" . addcslashes($media->path, '%_\\"') . "'%";

        if (Product::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where('photo1', $media->path)
                ->orWhere('photo2', $media->path)
                ->orWhere('seo_image', $media->path)
                ->orWhere('gallery_images', 'like', $quotedPath))
            ->exists()) {
            return 'a product';
        }

        if (ProductVariant::withoutTenantScope()
            ->whereIn('product_id', function ($q) use ($tenantId) {
                $q->select('id')->from('products')->where('tenant_id', $tenantId);
            })
            ->where('image', $media->path)
            ->exists()) {
            return 'a product variant';
        }

        if (PromotionBanner::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where('image', $media->path)
            ->exists()) {
            return 'a promotion';
        }

        if (WebsiteFaq::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where('answer_en', 'like', $srcDouble)
                ->orWhere('answer_en', 'like', $srcSingle)
                ->orWhere('answer_my', 'like', $srcDouble)
                ->orWhere('answer_my', 'like', $srcSingle))
            ->exists()) {
            return 'an FAQ answer';
        }

        if (Product::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where('description', 'like', $srcDouble)
                ->orWhere('description', 'like', $srcSingle)
                ->orWhere('short_description', 'like', $srcDouble)
                ->orWhere('short_description', 'like', $srcSingle))
            ->exists()) {
            return 'a product description';
        }

        return null;
    }

    public function isOrphan(StorefrontMedia $media, int $tenantId): bool
    {
        if ((int) $media->tenant_id !== $tenantId) {
            return false;
        }

        if ($this->usageFor($media, $tenantId) !== null) {
            return false;
        }

        return !$this->appearsInFreeText($media->path, $tenantId);
    }

    public function appearsInFreeText(string $path, int $tenantId): bool
    {
        if ($path === '') {
            return false;
        }

        $like = '%' . addcslashes($path, '%_\\"') . '%';

        if (Product::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where('description', 'like', $like)
                ->orWhere('short_description', 'like', $like))
            ->exists()) {
            return true;
        }

        if (WebsiteFaq::withoutTenantScope()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q
                ->where('answer_en', 'like', $like)
                ->orWhere('answer_my', 'like', $like))
            ->exists()) {
            return true;
        }

        if (Schema::hasTable('website_infos')) {
            $textColumns = array_values(array_filter([
                'site_description', 'about_description', 'mission_description',
                'vision_description', 'footer_description', 'privacy_policy',
                'terms_conditions', 'shipping_policy', 'return_policy', 'refund_policy',
            ], fn ($column) => Schema::hasColumn('website_infos', $column)));

            if ($textColumns !== [] && WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenantId)
                ->where(function ($q) use ($textColumns, $like) {
                    foreach ($textColumns as $index => $column) {
                        if ($index === 0) {
                            $q->where($column, 'like', $like);
                        } else {
                            $q->orWhere($column, 'like', $like);
                        }
                    }
                })->exists()) {
                return true;
            }
        }

        return false;
    }

    public function orphanCandidates(int $tenantId, int $limit = 500): array
    {
        return StorefrontMedia::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->filter(fn ($media) => $this->isOrphan($media, $tenantId))
            ->values()
            ->all();
    }

    public function deleteOrphan(StorefrontMedia $media, int $tenantId): bool
    {
        if (!$this->isOrphan($media, $tenantId)) {
            return false;
        }

        $this->imageService->delete($media->path);
        $media->delete();

        return true;
    }
}
