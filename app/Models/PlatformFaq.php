<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformFaq extends Model
{
    use HasFactory;

    protected $fillable = [
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

    public const CACHE_KEY = 'platform_faqs_active';
    public const CACHE_TTL = 3600; // 1 hour

    public const CATEGORIES = [
        'general' => 'General',
        'getting_started' => 'Getting Started',
        'billing' => 'Billing & Payments',
        'store_setup' => 'Store Setup',
        'features' => 'Features',
        'security' => 'Security & Data',
        'support' => 'Support',
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

    public static function getActiveCached()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::active()->ordered()->get();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
