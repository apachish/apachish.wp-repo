<?php

namespace Apachish\WpRepo\Models\Concerns;

use Apachish\WpRepo\Support\ProjectResolver;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToProject
{
    /** Models may override to also expose rows with a NULL project_id (shared/global rows). */
    protected static function sharesGlobalRows(): bool
    {
        return false;
    }

    public static function bootBelongsToProject(): void
    {
        static::addGlobalScope('wp_repo_project', function (Builder $query) {
            if (! config('wp-repo.tenancy.enabled')) {
                return;
            }

            $id = ProjectResolver::current();
            if ($id === null) {
                return;
            }

            $column = $query->getModel()->getTable().'.'.config('wp-repo.tenancy.column', 'project_id');

            $query->where(function ($q) use ($column, $id) {
                $q->where($column, $id);
                if (static::sharesGlobalRows()) {
                    $q->orWhereNull($column);
                }
            });
        });

        static::creating(function ($model) {
            $column = config('wp-repo.tenancy.column', 'project_id');

            if (config('wp-repo.tenancy.enabled') && $model->{$column} === null) {
                $model->{$column} = ProjectResolver::current();
            }
        });
    }
}
