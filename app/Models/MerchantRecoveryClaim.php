<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable, cross-tenant idempotency record for merchant archive recovery.
 *
 * One row per archive identifier. The unique index on archive_id is the
 * authoritative duplicate guard: it survives cache lock expiry, request
 * retries and application restarts. The row is deliberately not tenant scoped
 * because uniqueness must hold platform wide.
 */
class MerchantRecoveryClaim extends Model
{
    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'merchant_recovery_claims';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_CLAIMED
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Upload file names that an in-flight recovery is still reading. Cleanup
     * must never delete these even when they are older than the upload TTL.
     *
     * @return list<string>
     */
    public static function activeUploadNames(int $tenantId): array
    {
        return static::query()
            ->where('source_tenant_id', $tenantId)
            ->where('status', self::STATUS_CLAIMED)
            ->whereNotNull('archive_name')
            ->where('expires_at', '>', now())
            ->pluck('archive_name')
            ->all();
    }
}
