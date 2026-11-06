<?php

namespace Apachish\WpRepo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpPackageVersion extends Model
{
    protected $table = 'wp_package_versions';

    protected $guarded = [];

    protected $casts = ['released_at' => 'datetime', 'is_stable' => 'boolean'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(WpPackage::class, 'wp_package_id')->withoutGlobalScopes();
    }
}
