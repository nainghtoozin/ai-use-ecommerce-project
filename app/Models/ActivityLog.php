<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use TenantAware;
    protected $table = 'activity_logs';

    protected $fillable = [
        'log_name',
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

    public function scopeByCauser($query, string $causerType, int $causerId)
    {
        return $query->where('causer_type', $causerType)->where('causer_id', $causerId);
    }
}
