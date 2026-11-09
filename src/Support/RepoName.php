<?php

namespace Apachish\WpRepo\Support;

final class RepoName
{
    /**
     * Accepts what people actually paste (clone URLs, web URLs, trailing slashes, ".git")
     * and returns the bare "owner/repo" (or "group/subgroup/project" for GitLab).
     */
    public static function normalize(?string $value): string
    {
        $repo = trim((string) $value);

        $repo = preg_replace('#^(?:https?://[^/]+/|ssh://git@[^/]+/|git@[^:]+:)#i', '', $repo);
        $repo = rtrim($repo, '/');
        $repo = preg_replace('#\.git$#i', '', $repo);

        return $repo;
    }
}
