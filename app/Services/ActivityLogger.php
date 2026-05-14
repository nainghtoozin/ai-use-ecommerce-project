<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogger
{
    public static function log(
        string $description,
        string $event,
        mixed $subject = null,
        array $properties = [],
        ?string $logName = null
    ): ActivityLog {
        return ActivityLog::create([
            'log_name' => $logName ?? $event,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => auth()->user() ? get_class(auth()->user()) : null,
            'causer_id' => auth()->id(),
            'properties' => $properties,
            'event' => $event,
            'batch_uuid' => (string) Str::uuid(),
        ]);
    }
}
