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
        'support_page_url',
        'support_phone',
        'support_center_enabled',
        'telegram_url',
        'telegram_username',
        'viber_url',
        'whatsapp_url',
        'facebook_url',
        'messenger_url',
        'support_title',
        'support_description',
        'live_chat_enabled',
        'business_hours',
        'support_schedule',
        'platform_timezone',
        'maintenance_title',
        'maintenance_message',
        'maintenance_return_time',
        'support_button_text',
        'maintenance_mode',
        'registration_enabled',
        'trial_enabled',
        'trial_days',
        'allow_trial_renewal',
        'max_trial_renewals',
        'billing_renewal_reminder_days',
        'audit_retention_days',
        'notification_retention_days',
        'platform_currency_code',
        'platform_currency_symbol',
        'platform_currency_position',
        'platform_decimal_places',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'maintenance_return_time' => 'datetime',
        'support_center_enabled' => 'boolean',
        'live_chat_enabled' => 'boolean',
        'registration_enabled' => 'boolean',
        'trial_enabled' => 'boolean',
        'trial_days' => 'integer',
        'allow_trial_renewal' => 'boolean',
        'max_trial_renewals' => 'integer',
        'billing_renewal_reminder_days' => 'integer',
        'audit_retention_days' => 'integer',
        'notification_retention_days' => 'integer',
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
            $setting = static::first() ?? static::create();
            $setting->refresh();
            return $setting;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('platform_settings');
    }
}
