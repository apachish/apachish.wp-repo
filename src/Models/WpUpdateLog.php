<?php

namespace Apachish\WpRepo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpUpdateLog extends Model
{
    public $timestamps = false;

    protected $table = 'wp_update_logs';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public static function record(?int $siteId, ?int $packageId, string $method, string $status, ?string $from = null, ?string $to = null, ?string $message = null): self
    {
        return static::create([
            'wp_site_id' => $siteId,
            'wp_package_id' => $packageId,
            'method' => $method,
            'status' => $status,
            'from_version' => $from,
            'to_version' => $to,
            'message' => $message ? mb_substr($message, 0, 2000) : null,
            'created_at' => now(),
        ]);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WpSite::class, 'wp_site_id')->withoutGlobalScopes();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(WpPackage::class, 'wp_package_id')->withoutGlobalScopes();
    }
}
