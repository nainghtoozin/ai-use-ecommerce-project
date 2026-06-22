<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = [
        'site_name',
        'site_logo',
        'favicon',
        'support_email',
        'maintenance_mode',
        'registration_enabled',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'registration_enabled' => 'boolean',
    ];

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
