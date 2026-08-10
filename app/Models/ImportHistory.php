<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportHistory extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'user_id', 'file_name', 'file_type', 'import_type',
        'status', 'import_mode', 'total_rows', 'total_products', 'total_variants',
        'products_created', 'products_skipped', 'variants_created',
        'warning_count', 'error_count', 'errors', 'warnings',
        'error_report_path', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'warnings' => 'array',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_VALIDATING = 'validating';
    const STATUS_IMPORTING = 'importing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_COMPLETED_WITH_WARNINGS = 'completed_with_warnings';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByFileType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_VALIDATING => 'Validating',
            self::STATUS_IMPORTING => 'Importing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_COMPLETED_WITH_WARNINGS => 'Completed with Warnings',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_COMPLETED_WITH_WARNINGS => 'amber',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_IMPORTING, self::STATUS_VALIDATING => 'blue',
            default => 'gray',
        };
    }

    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration_ms) return '-';
        if ($this->duration_ms < 1000) return $this->duration_ms . 'ms';
        return round($this->duration_ms / 1000, 1) . 's';
    }

    public function hasErrorReport(): bool
    {
        return !empty($this->error_report_path) && $this->error_count > 0;
    }
}
