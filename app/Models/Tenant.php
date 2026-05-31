<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'email',
        'logo',
        'status',
        'settings',
        'subscription_plan_id',
        'expires_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'expires_at' => 'datetime',
    ];

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

    public function categories()
    {
        return $this->hasMany(Category::class);
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

        return self::getDefault();
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
            $q->where('status', 'expired')
              ->orWhere(function ($q) {
                  $q->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->whereNotIn('status', ['canceled']);
              });
        });
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }
}
