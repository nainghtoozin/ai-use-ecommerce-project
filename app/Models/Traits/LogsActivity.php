<?php

namespace App\Models\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('created', "Created " . class_basename($model));
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (!empty($changes)) {
                $description = "Updated " . class_basename($model);
                $model->logActivity('updated', $description, [
                    'old' => $model->getOriginal(),
                    'attributes' => $changes,
                ]);
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted', "Deleted " . class_basename($model), [
                'attributes' => $model->getOriginal(),
            ]);
        });
    }

    public function activities()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function logActivity(string $event, string $description, array $properties = []): void
    {
        $logName = $this->activityLogName ?? strtolower(class_basename($this));

        $impersonatorId = session('impersonator_id');
        $isImpersonating = $impersonatorId && auth()->check() && $impersonatorId !== auth()->id();

        $category = $this->resolveActivityCategory($event, $logName);
        $severity = $this->resolveActivitySeverity($event);

        ActivityLog::create([
            'log_name' => $logName,
            'category' => $category,
            'severity' => $severity,
            'description' => $description,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'causer_type' => auth()->user() ? get_class(auth()->user()) : null,
            'causer_id' => $isImpersonating ? $impersonatorId : auth()->id(),
            'impersonator_id' => $isImpersonating ? $impersonatorId : null,
            'impersonated_user_id' => $isImpersonating ? auth()->id() : null,
            'properties' => $properties,
            'event' => $event,
            'batch_uuid' => (string) Str::uuid(),
        ]);
    }

    protected function resolveActivityCategory(string $event, string $logName): string
    {
        $categoryMap = [
            'product' => ActivityLog::CATEGORY_PRODUCTS,
            'category' => ActivityLog::CATEGORY_PRODUCTS,
            'brand' => ActivityLog::CATEGORY_PRODUCTS,
            'order' => ActivityLog::CATEGORY_ORDERS,
            'payment_method' => ActivityLog::CATEGORY_SETTINGS,
            'user' => ActivityLog::CATEGORY_USERS,
            'role' => ActivityLog::CATEGORY_SECURITY,
            'permission' => ActivityLog::CATEGORY_SECURITY,
            'subscription' => ActivityLog::CATEGORY_BILLING,
            'invoice' => ActivityLog::CATEGORY_BILLING,
        ];

        return $categoryMap[$logName] ?? ActivityLog::CATEGORY_SYSTEM;
    }

    protected function resolveActivitySeverity(string $event): string
    {
        return match ($event) {
            'created' => ActivityLog::SEVERITY_SUCCESS,
            'deleted' => ActivityLog::SEVERITY_WARNING,
            default => ActivityLog::SEVERITY_INFO,
        };
    }
}
