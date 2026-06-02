<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'name',
        'type',
        'account_name',
        'account_number',
        'qr_image',
        'bank_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
