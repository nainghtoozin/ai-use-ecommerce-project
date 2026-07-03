<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'name',
        'display_name',
        'slug',
        'type',
        'account_name',
        'account_number',
        'bank_name',
        'branch',
        'instructions',
        'currency',
        'qr_image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeBankTransfers($query)
    {
        return $query->where('type', 'bank_transfer');
    }

    public static function allowsNullTenantFallback(): bool
    {
        return true;
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
