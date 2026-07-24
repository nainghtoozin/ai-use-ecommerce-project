<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use TenantAware;

    protected $fillable = [];

    protected function casts(): array
    {
        return [];
    }
}
