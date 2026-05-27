<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * PlanFeature model.
 *
 * Represents a single feature toggle within a subscription plan.
 * Features are identified by a unique key (e.g., 'variable_products', 'combo_products')
 * and control access to platform capabilities.
 *
 * @see Plan
 */
class PlanFeature extends Model
{
    use HasFactory;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id', 'feature_key', 'is_enabled', 'display_label', 'description',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    protected $attributes = [
        'is_enabled' => true,
    ];

    /* ── Relationships ── */

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
