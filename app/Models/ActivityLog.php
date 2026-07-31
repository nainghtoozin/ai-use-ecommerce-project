<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use TenantAware;
    protected $table = 'activity_logs';

    const CATEGORY_AUTH = 'authentication';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_ORDERS = 'orders';
    const CATEGORY_INVENTORY = 'inventory';
    const CATEGORY_PRODUCTS = 'products';
    const CATEGORY_USERS = 'users';
    const CATEGORY_WEBSITE = 'website';
    const CATEGORY_SETTINGS = 'settings';
    const CATEGORY_BILLING = 'billing';
    const CATEGORY_NOTIFICATIONS = 'notifications';
    const CATEGORY_SYSTEM = 'system';

    const SEVERITY_INFO = 'info';
    const SEVERITY_SUCCESS = 'success';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'log_name',
        'category',
        'severity',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'impersonator_id',
        'impersonated_user_id',
        'properties',
        'event',
        'batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function impersonator(): MorphTo
    {
        return $this->morphTo();
    }

    public function impersonatedUser(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeInLog($query, string $logName)
    {
        return $query->where('log_name', $logName);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByCauser($query, string $causerType, int $causerId)
    {
        return $query->where('causer_type', $causerType)->where('causer_id', $causerId);
    }

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_AUTH => 'Authentication',
            self::CATEGORY_SECURITY => 'Security',
            self::CATEGORY_ORDERS => 'Orders',
            self::CATEGORY_INVENTORY => 'Inventory',
            self::CATEGORY_PRODUCTS => 'Products',
            self::CATEGORY_USERS => 'Users',
            self::CATEGORY_WEBSITE => 'Website',
            self::CATEGORY_SETTINGS => 'Settings',
            self::CATEGORY_BILLING => 'Billing',
            self::CATEGORY_NOTIFICATIONS => 'Notifications',
            self::CATEGORY_SYSTEM => 'System',
        ];
    }

    public static function getSeverities(): array
    {
        return [
            self::SEVERITY_INFO => 'Info',
            self::SEVERITY_SUCCESS => 'Success',
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_ERROR => 'Error',
            self::SEVERITY_CRITICAL => 'Critical',
        ];
    }
}
