<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = ['key', 'value'];

    public static function get($key, $default = null, $tenantId = null)
    {
        $query = static::where('key', $key);

        if ($tenantId !== null) {
            $query = $query->withoutTenantScope()->where('tenant_id', $tenantId);
        }

        $setting = $query->first();
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value)
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
