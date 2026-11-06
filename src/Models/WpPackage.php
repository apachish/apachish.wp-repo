<?php

namespace Apachish\WpRepo\Models;

use Apachish\WpRepo\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpPackage extends Model
{
    use BelongsToProject;

    protected $table = 'wp_packages';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_paid' => 'boolean'];

    protected static function sharesGlobalRows(): bool
    {
        return true;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WpPackageVersion::class);
    }

    public function sitePackages(): HasMany
    {
        return $this->hasMany(WpSitePackage::class);
    }

    /** Highest stable version by version_compare (not by insertion order). */
    public function latestStable(): ?WpPackageVersion
    {
        return $this->versions()->where('is_stable', true)->get()
            ->sort(fn ($a, $b) => version_compare($b->version, $a->version))
            ->first();
    }
}
