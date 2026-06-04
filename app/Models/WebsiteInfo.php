<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class WebsiteInfo extends Model
{
    use TenantAware;

    protected $fillable = [
        'site_name',
        'site_tagline',
        'site_description',
        'site_keywords',
        'theme_color',
        'default_language',
        'timezone',
        'currency_code',
        'currency_symbol',
        'date_format',
        'logo',
        'favicon',
        'footer_logo',
        'contact_email',
        'support_email',
        'phone',
        'whatsapp_number',
        'address',
        'country',
        'google_maps_embed_url',
        'about_title',
        'about_description',
        'mission_title',
        'mission_description',
        'vision_title',
        'vision_description',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'linkedin_url',
        'youtube_url',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'robots_meta',
        'og_image',
        'hero_title',
        'hero_subtitle',
        'hero_button_text',
        'hero_button_link',
        'hero_image',
        'hero_images',
        'footer_description',
        'footer_copyright',
        'footer_settings',
        'contact_info',
        'address_info',
        'maintenance_mode',
        'maintenance_message',
        'allow_registration',
        'enable_reviews',
        'enable_wishlist',
        'enable_compare',
        'guest_checkout_enabled',
        'cod_enabled',
        'free_shipping_threshold',
        'default_shipping_fee',
        'is_active',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'allow_registration' => 'boolean',
        'enable_reviews' => 'boolean',
        'enable_wishlist' => 'boolean',
        'enable_compare' => 'boolean',
        'guest_checkout_enabled' => 'boolean',
        'cod_enabled' => 'boolean',
        'is_active' => 'boolean',
        'free_shipping_threshold' => 'decimal:2',
        'default_shipping_fee' => 'decimal:2',
        'hero_images' => 'array',
        'contact_info' => 'array',
        'address_info' => 'array',
        'footer_settings' => 'array',
    ];

    public static function getSettings(): self
    {
        $tenant = Tenant::getCurrent();
        $cacheKey = 'website_settings_' . ($tenant ? $tenant->id : 'default');

        return Cache::rememberForever($cacheKey, function () {
            $settings = self::first();

            if ($settings) {
                return $settings;
            }

            return self::create([
                'site_name' => tenant()?->name ?? 'My E-Commerce Store',
                'theme_color' => '#3B82F6',
                'default_language' => 'en',
                'timezone' => 'Asia/Yangon',
                'currency_code' => 'MMK',
                'currency_symbol' => 'K',
                'date_format' => 'Y-m-d',
                'allow_registration' => true,
                'maintenance_mode' => false,
            ]);
        });
    }

    public static function clearCache(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? Tenant::getCurrent();
        $cacheKey = 'website_settings_' . ($tenant ? $tenant->id : 'default');
        Cache::forget($cacheKey);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;
        if (str_starts_with($this->logo, 'http')) return $this->logo;
        return asset('storage/' . $this->logo);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        if (!$this->favicon) return null;
        if (str_starts_with($this->favicon, 'http')) return $this->favicon;
        return asset('storage/' . $this->favicon);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        if (!$this->og_image) return null;
        if (str_starts_with($this->og_image, 'http')) return $this->og_image;
        return asset('storage/' . $this->og_image);
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        if (!$this->hero_image) return null;
        if (str_starts_with($this->hero_image, 'http')) return $this->hero_image;
        return asset('storage/' . $this->hero_image);
    }

    public function getHeroImagesUrlsAttribute(): array
    {
        if (empty($this->hero_images) || !is_array($this->hero_images)) {
            return [];
        }

        return array_map(function ($path) {
            if (empty($path)) return null;
            $path = self::normalizeImagePath($path);
            if (str_starts_with($path, '/storage/')) return asset($path);
            return asset('storage/' . $path);
        }, $this->hero_images);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model) {
            $imageFields = ['logo', 'favicon', 'og_image', 'hero_image', 'footer_logo', 'about_image'];
            foreach ($imageFields as $field) {
                if (isset($model->attributes[$field]) && is_string($model->attributes[$field])) {
                    $model->attributes[$field] = self::normalizeImagePath($model->attributes[$field]);
                }
            }

            if (isset($model->attributes['hero_images'])) {
                $paths = is_array($model->hero_images) ? $model->hero_images : [];
                $cleaned = array_map(fn($p) => self::normalizeImagePath($p), $paths);
                $model->hero_images = $cleaned;
            }
        });
    }

    public static function normalizeImagePath(string $path): string
    {
        if (empty($path)) {
            return $path;
        }

        if (preg_match('#^https?://[^/]+/storage/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($path, '/storage/')) {
            return substr($path, 9);
        }

        return $path;
    }

    public function getFooterLogoUrlAttribute(): ?string
    {
        if (!$this->footer_logo) return null;
        if (str_starts_with($this->footer_logo, 'http')) return $this->footer_logo;
        return asset('storage/' . $this->footer_logo);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['logo_url'] = $this->logo_url;
        $data['favicon_url'] = $this->favicon_url;
        $data['og_image_url'] = $this->og_image_url;
        $data['hero_image_url'] = $this->hero_image_url;
        $data['hero_images_urls'] = $this->hero_images_urls;
        $data['footer_logo_url'] = $this->footer_logo_url;
        return $data;
    }
}