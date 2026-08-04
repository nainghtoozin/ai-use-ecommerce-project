<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class WebsiteFaq extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
        'category',
        'question_en',
        'question_my',
        'answer_en',
        'answer_my',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const CATEGORIES = [
        'general' => 'General',
        'getting_started' => 'Getting Started',
        'billing' => 'Billing & Payments',
        'store_setup' => 'Store Setup',
        'features' => 'Features',
        'security' => 'Security & Data',
        'support' => 'Support',
        'shipping' => 'Shipping & Delivery',
        'returns' => 'Returns & Refunds',
    ];

    public function getQuestionAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"question_{$locale}"} ?? $this->question_en ?? '';
    }

    public function getAnswerAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"answer_{$locale}"} ?? $this->answer_en ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function getActiveCachedForTenant(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "faq_tenant_{$tenantId}";

        return Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            return self::where('tenant_id', $tenantId)
                ->active()
                ->ordered()
                ->get();
        });
    }

    public static function clearCacheForTenant(int $tenantId): void
    {
        Cache::forget("faq_tenant_{$tenantId}");
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            if ($model->tenant_id) {
                self::clearCacheForTenant($model->tenant_id);
            }
        });

        static::deleted(function ($model) {
            if ($model->tenant_id) {
                self::clearCacheForTenant($model->tenant_id);
            }
        });
    }
}
