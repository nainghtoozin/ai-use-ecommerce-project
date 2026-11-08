<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantEmailAttachment extends Model
{
    protected $fillable = [
        'campaign_id',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MerchantEmailCampaign::class, 'campaign_id');
    }
}
