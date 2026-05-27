<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Plan model.
 *
 * Represents a subscription plan in the SaaS system.
 * Each plan has a set of features that control access to product types
 * and other platform capabilities.
 *
 * @see PlanFeature
 * @see User
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'price', 'currency', 'interval',
        'description', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'currency' => 'USD',
        'interval' => 'monthly',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /* ── Relationships ── */

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /* ── Helper methods ── */

    /**
     * Check if this plan has a specific feature enabled.
     */
    public function hasFeature(string $featureKey): bool
    {
        return $this->features()
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Get all enabled feature keys for this plan.
     */
    public function getEnabledFeatures(): array
    {
        return $this->features()
            ->where('is_enabled', true)
            ->pluck('feature_key')
            ->toArray();
    }

    /**
     * Get the free plan.
     */
    public static function free(): ?self
    {
        return static::where('price', 0)->where('is_active', true)->first();
    }

    /**
     * Get the default plan for new users.
     */
    public static function defaultPlan(): ?self
    {
        return static::default()->first() ?? static::free();
    }
}
