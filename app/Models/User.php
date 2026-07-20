<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Contracts\HasSubscription;
use App\Models\Traits\LogsActivity;
use App\Models\Traits\SyncsIdentity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, HasSubscription
{
    use \Illuminate\Auth\MustVerifyEmail, HasFactory, Notifiable, LogsActivity, HasRoles, SyncsIdentity;

    protected function getCounterpartClass(): string
    {
        return Account::class;
    }

    protected $appends = [
        'profile_image_url',
        'role_name',
    ];

    const ROLE_CUSTOMER = 'customer';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERADMIN = 'superadmin';

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'status',
        'remember_token',
        'tenant_id',
        'is_owner',
        'is_admin',
        'allow_cod',
        'profile_image',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'allow_cod' => 'boolean',
            'notification_preferences' => 'array',
            'plan_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN) || $this->hasRole(self::ROLE_SUPERADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPERADMIN);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    public function delete()
    {
        if ($this->hasRole(self::ROLE_SUPERADMIN) && self::role(self::ROLE_SUPERADMIN)->count() <= 1) {
            throw new \RuntimeException('Cannot delete the last remaining superadmin.');
        }
        return parent::delete();
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->status)) {
                $user->status = self::STATUS_ACTIVE;
            }

            if (empty($user->tenant_id)) {
                $tenant = \App\Models\Tenant::getCurrent();

                if ($tenant) {
                    $user->tenant_id = $tenant->id;
                }
            }
        });
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->email;
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function getRoleLabel(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }

        if ($this->isOwner()) {
            return 'Owner';
        }

        $roleName = $this->getRoleNames()->first();
        if (!$roleName) {
            return '';
        }

        return match ($roleName) {
            'admin' => 'Admin',
            'customer' => 'Customer',
            'staff' => 'Staff',
            default => str($roleName)->title(),
        };
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('users.tenant_id', $tenantId);
    }

    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        if ($this->isOwner()) {
            return \Spatie\Permission\Models\Permission::all();
        }

        return parent::getAllPermissions();
    }

    public function scopeOwners($query)
    {
        return $query->where('is_owner', true);
    }

    public function orders()
    {
        return $this->morphMany(Order::class, 'user');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the plan this user is subscribed to, or the default plan.
     */
    public function getActivePlan(): ?Plan
    {
        if ($this->isSuperAdmin()) {
            return null;
        }
        $subscription = $this->tenant?->subscription;
        return $subscription?->plan ?? Plan::free();
    }

    /**
     * Check if the user has a specific feature enabled through their plan.
     */
    public function hasFeature(string $featureKey): bool
    {
        $plan = $this->getActivePlan();

        if (!$plan) {
            return false;
        }

        return $plan->hasFeature($featureKey);
    }

    /**
     * Check if user's tenant has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->tenant?->hasActiveSubscription() ?? false;
    }

    public function wishlistItems()
    {
        return $this->morphMany(Wishlist::class, 'user');
    }

    public function wishlistedProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists');
    }

    public function telegramIntegration()
    {
        return $this->morphOne(TelegramIntegration::class, 'user');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->tenant) {
            $slug = $this->tenant->slug;
            ResetPasswordNotification::$createUrlCallback = function ($notifiable, $token) use ($slug) {
                return url("/store/{$slug}/reset-password/{$token}");
            };
        }

        $this->notify(new ResetPasswordNotification($token));

        ResetPasswordNotification::$createUrlCallback = null;
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return ImageService::url($this->profile_image);
    }

    public function getDefaultNotificationPreferences(): array
    {
        $prefs = [
            'order_placed' => true,
            'order_status_changed' => true,
            'payment_verified' => true,
            'payment_rejected' => true,
            'new_message' => true,
            'notification_sound' => false,
        ];

        if ($this->isAdmin()) {
            $prefs['new_order'] = true;
            $prefs['payment_proof_uploaded'] = true;
            $prefs['low_stock'] = true;
            $prefs['order_cancelled'] = true;
        }

        return $prefs;
    }

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];

        if (empty($prefs)) {
            return true;
        }

        return $prefs[$type] ?? true;
    }

    public function getAllowedNotificationTypes(): array
    {
        $types = [
            'order_placed',
            'order_status_changed',
            'payment_verified',
            'payment_rejected',
            'new_message',
            'notification_sound',
        ];

        if ($this->isAdmin()) {
            $types = array_merge($types, [
                'new_order',
                'payment_proof_uploaded',
                'low_stock',
                'order_cancelled',
            ]);
        }

        return $types;
    }

    public function getNotificationPreferencesAttribute($value): array
    {
        $prefs = $value ? json_decode($value, true) : [];

        $defaults = $this->getDefaultNotificationPreferences();

        foreach ($defaults as $key => $default) {
            if (!isset($prefs[$key])) {
                $prefs[$key] = $default;
            }
        }

        return $prefs;
    }
}
