<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;

class Tenant extends Model
{
    protected $appends = [
        'logo_url',
    ];

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'store_url',
        'email',
        'logo',
        'status',
        'settings',
        'activated_at',
        'locked_at',
        'subscription_plan_id',
        'expires_at',
        'used_storage_bytes',
    ];

    protected $casts = [
        'settings' => 'array',
        'activated_at' => 'datetime',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_storage_bytes' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Set default settings if not provided
            if (empty($tenant->settings)) {
                $tenant->settings = [
                    'language' => 'en',
                    'theme' => 'light',
                    'timezone' => 'Asia/Yangon',
                    'currency' => 'MMK',
                    'notifications' => true,
                    'admin_menu_visibility' => \App\Services\MenuVisibilityService::getDefaults(),
                ];
            } elseif (empty($tenant->settings['admin_menu_visibility'] ?? null)) {
                $settings = $tenant->settings;
                $settings['admin_menu_visibility'] = \App\Services\MenuVisibilityService::getDefaults();
                $tenant->settings = $settings;
            }

            // Generate store_url if not set
            if (empty($tenant->store_url) && !empty($tenant->slug)) {
                $tenant->store_url = '/store/' . $tenant->slug;
            }

            // Set activated_at if status is active and not already set
            if ($tenant->status === 'active' && empty($tenant->activated_at)) {
                $tenant->activated_at = now();
            }
        });

        static::saved(function ($tenant) {
            // Sync activated_at when status changes to active
            if ($tenant->status === 'active' && empty($tenant->activated_at)) {
                $tenant->updateQuietly(['activated_at' => now()]);
            }

            // Sync locked_at when status changes to suspended/expired
            if (in_array($tenant->status, ['suspended', 'expired', 'locked']) && empty($tenant->locked_at)) {
                $tenant->updateQuietly(['locked_at' => now()]);
            }
        });
    }

    public function getStoreSlugAttribute(): string
    {
        return $this->slug;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return ImageService::url($this->logo);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function storefront()
    {
        return $this->hasOne(Storefront::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(Plan::class, 'subscription_plan_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['trialing', 'active'])
            ->latestOfMany();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function lock(): void
    {
        if ($this->locked_at) {
            return;
        }

        $this->update(['locked_at' => now()]);
    }

    public function unlock(): void
    {
        if (!$this->locked_at) {
            return;
        }

        $this->update(['locked_at' => null]);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function subscriptionExpired(): bool
    {
        $subscription = $this->subscription;
        return $subscription && $subscription->hasExpired();
    }

    public static function getDefault(): ?self
    {
        return Cache::rememberForever('tenant_default', function () {
            return self::where('slug', 'default')->first();
        });
    }

    public static function getCurrent(): ?self
    {
        if (App::has('current.tenant')) {
            return App::make('current.tenant');
        }

        return null;
    }

    public static function clearDefaultCache(): void
    {
        Cache::forget('tenant_default');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->whereHas('subscription', function ($q) {
            $q->whereRaw('id = (SELECT id FROM subscriptions s2 WHERE s2.tenant_id = subscriptions.tenant_id ORDER BY id DESC LIMIT 1)')
              ->where(function ($q) {
                  $q->where('status', 'expired')
                    ->orWhere(function ($q) {
                        $q->whereNotNull('expires_at')
                          ->where('expires_at', '<', now())
                          ->whereNotIn('status', ['canceled', 'suspended']);
                    });
              });
        });
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function memberships()
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function activeMemberships()
    {
        return $this->memberships()->where('status', 'active');
    }

    public function ownerMembership()
    {
        return $this->hasOne(TenantMembership::class)->where('is_owner', true);
    }

    public function adminMemberships()
    {
        return $this->memberships()->whereHas('role', fn($q) => $q->where('name', 'admin'));
    }

    public function notifyAdmins($notification): void
    {
        $admins = $this->users()->whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->get();

        Notification::send($admins, $notification);
    }
}
