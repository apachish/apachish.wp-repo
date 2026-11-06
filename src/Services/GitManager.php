<?php

namespace Apachish\WpRepo\Services;

use Apachish\WpRepo\Contracts\GitProvider;
use Apachish\WpRepo\Providers\GiteaProvider;
use Apachish\WpRepo\Providers\GitHubProvider;
use Apachish\WpRepo\Providers\GitLabProvider;
use InvalidArgumentException;

class GitManager
{
    public function provider(?string $name = null): GitProvider
    {
        $name ??= config('wp-repo.provider', 'github');
        $key = $name === 'forgejo' ? 'gitea' : $name;
        $config = config("wp-repo.providers.{$key}", []);

        return match ($key) {
            'github' => new GitHubProvider($config),
            'gitlab' => new GitLabProvider($config),
            'gitea' => new GiteaProvider($config),
            default => throw new InvalidArgumentException("Unknown git provider [{$name}]"),
        };
    }
}
