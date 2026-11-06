<?php

namespace Apachish\WpRepo\Models;

use Apachish\WpRepo\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WpSite extends Model
{
    use BelongsToProject;

    protected $table = 'wp_sites';

    protected $guarded = [];

    protected $hidden = ['api_key_hash'];

    protected $casts = ['last_check_at' => 'datetime'];

    public function sitePackages(): HasMany
    {
        return $this->hasMany(WpSitePackage::class);
    }

    /** Generates a new key, stores only its hash, returns the plain key (show it once!). */
    public function issueKey(): string
    {
        $plain = 'wpr_'.Str::random(48);
        $this->api_key_hash = hash('sha256', $plain);

        return $plain;
    }

    public function keyMatches(string $plain): bool
    {
        return hash_equals((string) $this->api_key_hash, hash('sha256', $plain));
    }
}
