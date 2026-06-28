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
        'trial_enabled',
        'trial_days',
        'allow_trial_renewal',
        'max_trial_renewals',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'registration_enabled' => 'boolean',
        'trial_enabled' => 'boolean',
        'trial_days' => 'integer',
        'allow_trial_renewal' => 'boolean',
        'max_trial_renewals' => 'integer',
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
