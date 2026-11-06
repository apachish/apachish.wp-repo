<?php

namespace Apachish\WpRepo\Support;

final class ProjectResolver
{
    public static function current(): int|string|null
    {
        $resolver = config('wp-repo.tenancy.resolver');

        if ($resolver) {
            return app($resolver)();
        }

        if (app()->bound('current_project')) {
            $project = app('current_project');

            return is_object($project) ? ($project->id ?? null) : $project;
        }

        return null;
    }
}
