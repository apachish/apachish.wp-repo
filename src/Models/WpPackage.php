<?php

namespace Apachish\WpRepo\Models;

use Apachish\WpRepo\Models\Concerns\BelongsToProject;
use Apachish\WpRepo\Support\RepoName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $project = current_project();

            if ($project) {
                $model->project_id = $project->id;
            }
        });

        static::updating(function ($model) {
            $project = current_project();

            if ($project) {
                $model->project_id = $project->id;
            }
        });
    }

    protected function repo(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : RepoName::normalize($value),
            set: fn ($value) => RepoName::normalize($value),
        );
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WpGitConnection::class, 'wp_git_connection_id')->withoutGlobalScopes();
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
