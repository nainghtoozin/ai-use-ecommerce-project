<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class Storefront extends Model
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'theme_id', 'status', 'draft_revision_id', 'published_revision_id'];

    protected $casts = [
        'draft_revision_id' => 'integer',
        'published_revision_id' => 'integer',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function themeConfig()
    {
        return $this->hasOne(StorefrontThemeConfig::class);
    }

    public function designTokens()
    {
        return $this->hasOne(StorefrontDesignToken::class);
    }

    public function homepageSections()
    {
        return $this->hasMany(StorefrontHomepageSection::class)->orderBy('position');
    }

    public function media()
    {
        return $this->hasMany(StorefrontMedia::class);
    }

    public function content()
    {
        return $this->hasOne(StorefrontContent::class);
    }

    public function navigation()
    {
        return $this->hasOne(StorefrontNavigation::class);
    }

    public function draftRevision()
    {
        return $this->belongsTo(StorefrontRevision::class, 'draft_revision_id');
    }

    public function publishedRevision()
    {
        return $this->belongsTo(StorefrontRevision::class, 'published_revision_id');
    }

    public function revisions()
    {
        return $this->hasMany(StorefrontRevision::class)->latest('revision_number');
    }
}
