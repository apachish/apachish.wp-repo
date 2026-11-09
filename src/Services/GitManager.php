<?php

namespace Apachish\WpRepo\Services;

use Apachish\WpRepo\Contracts\GitProvider;
use Apachish\WpRepo\Models\WpGitConnection;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Providers\GiteaProvider;
use Apachish\WpRepo\Providers\GitHubProvider;
use Apachish\WpRepo\Providers\GitLabProvider;
use InvalidArgumentException;
use RuntimeException;

class GitManager
{
    public function forConnection(WpGitConnection $connection): GitProvider
    {
        $config = $connection->providerConfig();

        return match ($connection->provider) {
            'github' => new GitHubProvider($config),
            'gitlab' => new GitLabProvider($config),
            'gitea', 'forgejo' => new GiteaProvider($config),
            default => throw new InvalidArgumentException("Unknown git provider [{$connection->provider}]"),
        };
    }

    public function forPackage(WpPackage $package): GitProvider
    {
        $connection = $package->connection;

        if (! $connection) {
            throw new RuntimeException("Package [{$package->slug}] has no Git connection.");
        }

        return $this->forConnection($connection);
    }
}
