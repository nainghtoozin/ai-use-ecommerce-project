<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity, HasRoles;

    const ROLE_CUSTOMER = 'customer';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERADMIN = 'superadmin';

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'status',
        'is_owner',
        'profile_image',
        'notification_preferences',
        'plan_id',
        'plan_started_at',
        'plan_expires_at',
        'plan_status',
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

    public function scopeOwners($query)
    {
        return $query->where('is_owner', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
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
        if ($this->plan) {
            return $this->plan;
        }

        return Plan::defaultPlan();
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
     * Check if user has an active paid subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->plan_status === 'active'
            && $this->plan_id !== null
            && (!$this->plan_expires_at || $this->plan_expires_at->isFuture());
    }

    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistedProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists');
    }

    public function telegramIntegration()
    {
        return $this->hasOne(TelegramIntegration::class);
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
