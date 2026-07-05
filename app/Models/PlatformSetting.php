<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $appends = [
        'site_logo_url',
        'favicon_url',
    ];

    protected $fillable = [
        'site_name',
        'site_logo',
        'favicon',
        'support_email',
        'maintenance_mode',
        'registration_enabled',
        'trial_enabled',
        'trial_days',
        'allow_trial_renewal',
        'max_trial_renewals',
        'platform_currency_code',
        'platform_currency_symbol',
        'platform_currency_position',
        'platform_decimal_places',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'registration_enabled' => 'boolean',
        'trial_enabled' => 'boolean',
        'trial_days' => 'integer',
        'allow_trial_renewal' => 'boolean',
        'max_trial_renewals' => 'integer',
        'platform_decimal_places' => 'integer',
    ];

    public function getSiteLogoUrlAttribute(): ?string
    {
        return ImageService::url($this->site_logo);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return ImageService::url($this->favicon);
    }

    public static function current(): self
    {
        return Cache::rememberForever('platform_settings', function () {
            return static::first() ?? static::create();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('platform_settings');
    }
}
