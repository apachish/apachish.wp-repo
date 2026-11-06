<?php

namespace Apachish\WpRepo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpSitePackage extends Model
{
    protected $table = 'wp_site_packages';

    protected $guarded = [];

    protected $casts = ['auto_update' => 'boolean', 'last_updated_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WpSite::class, 'wp_site_id')->withoutGlobalScopes();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(WpPackage::class, 'wp_package_id')->withoutGlobalScopes();
    }
}
