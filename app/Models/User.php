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

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
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
            'notification_preferences' => 'array',
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
            if (empty($user->role)) {
                $user->role = 'customer';
            }
            if (empty($user->status)) {
                $user->status = self::STATUS_ACTIVE;
            }
        });
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
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
