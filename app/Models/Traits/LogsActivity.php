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

        ActivityLog::create([
            'log_name' => $logName,
            'description' => $description,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'causer_type' => auth()->user() ? get_class(auth()->user()) : null,
            'causer_id' => auth()->id(),
            'properties' => $properties,
            'event' => $event,
            'batch_uuid' => (string) Str::uuid(),
        ]);
    }
}
