<?php

namespace Apachish\WpRepo\Tests;

use Apachish\WpRepo\WpRepoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WpRepoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('wp-repo.tenancy.enabled', false);
        $app['config']->set('wp-repo.panel.enabled', false);
        $app['config']->set('wp-repo.tmp_path', sys_get_temp_dir().'/wp-repo-tests');
        $app['config']->set('wp-repo.providers.github.webhook_secret', 'topsecret');
        $app['config']->set('wp-repo.disk', 'local');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
