<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id',
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
        'is_demo',
        'logo_path',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['qr_image_url', 'logo_url'];

    public function getQrImageUrlAttribute(): ?string
    {
        if (empty($this->qr_image)) {
            return null;
        }

        return ImageService::url($this->qr_image);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        return str_starts_with($this->logo_path, '/')
            ? $this->logo_path
            : ImageService::url($this->logo_path);
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
