<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class BillingPaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'display_name',
        'type',
        'account_name',
        'account_number',
        'bank_name',
        'branch',
        'qr_image',
        'instructions',
        'currency',
        'sort_order',
        'is_default',
        'is_active',
        'supports_manual_payment',
        'supports_gateway',
        'gateway_code',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'supports_manual_payment' => 'boolean',
        'supports_gateway' => 'boolean',
        'metadata' => 'json',
    ];

    protected $appends = ['qr_image_url'];

    public function getQrImageUrlAttribute(): ?string
    {
        if (empty($this->qr_image)) {
            return null;
        }

        if (str_starts_with($this->qr_image, 'http://') || str_starts_with($this->qr_image, 'https://')) {
            return $this->qr_image;
        }

        return asset('storage/' . $this->qr_image);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
