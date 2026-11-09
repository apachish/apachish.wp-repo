<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Git providers
    |--------------------------------------------------------------------------
    | NO credentials live here. Every project stores its own Git connection (token + webhook secret,
    | encrypted) in the database via the panel. These are only the default API endpoints for the hosted
    | services; self-hosted GitLab/Gitea URLs are entered per connection.
    */
    'providers' => [
        'github' => ['api' => 'https://api.github.com'],
        'gitlab' => ['api' => 'https://gitlab.com/api/v4'],
        'gitea' => ['api' => null],
    ],

    // Allow connection API URLs that resolve to private/internal IPs (self-hosted Git on your own network).
    // Leave false in multi-tenant setups, otherwise tenants can make the server call internal addresses.
    'allow_private_hosts' => false,

    /*
    | Storage for built zips (use a private disk, never a public one).
    */
    'disk' => env('WP_REPO_DISK', 'local'),
    'tmp_path' => storage_path('app/wp-repo/tmp'),
    'max_unzipped_bytes' => 300 * 1024 * 1024,

    // Minutes a signed download link stays valid.
    'download_ttl' => 15,

    // How many versions `wp-repo:prune` keeps per package.
    'keep_versions' => 5,

    // Header token for POST /sites/register (leave empty to disable the endpoint).
    'registration_token' => env('WP_REPO_REGISTRATION_TOKEN'),

    // Files/dirs removed from every built package (fnmatch, same idea as .distignore).
    // A .distignore file inside the repo is honoured as well.
    'distignore' => [
        '.git', '.github', '.gitlab', '.gitignore', '.gitattributes', '.distignore',
        '.idea', '.vscode', 'node_modules', 'tests', 'phpunit.xml*', '.env', '.env.*', '*.log', '.DS_Store',
    ],

    /*
    | Entitlement: class implementing Apachish\WpRepo\Contracts\EntitlementChecker.
    | Used for packages flagged is_paid. Bind it to your subscription system.
    */
    'entitlement' => Apachish\WpRepo\Support\AllowAllEntitlement::class,

    /*
    | Multi-tenancy. When enabled, models are scoped by `column`.
    | `resolver`: invokable class returning the current project id (or null = no scope).
    | Default falls back to app('current_project')->id when bound.
    */
    'tenancy' => [
        'enabled' => true,
        'column' => 'project_id',
        'resolver' => null,
    ],

    /*
    | Direct push (deploy to WordPress installs on this server).
    | `allowed_roots` is REQUIRED for push: wp_path must live under one of these directories.
    */
    'wp' => [
        'allowed_roots' => [], // e.g. ['/var/www/sites']
        'use_cli' => false,    // use wp-cli when available instead of the built-in swap
        'cli' => 'wp',
        'cli_allow_root' => false,
        'chown' => null,       // e.g. 'www-data:www-data' (requires privileges)
        'backup_path' => storage_path('app/wp-repo/backups'),
        'keep_backups' => 3,
    ],

    'api' => [
        'prefix' => 'api/wp-updates',
        'middleware' => ['throttle:60,1'],
    ],

    'panel' => [
        'enabled' => true,
        'prefix' => 'panel/{api_key}/wp-repo',
        'middleware' => [ 'web','auth:web,project', 'can:manage-wp-repo',\App\Http\Middleware\SetProject::class],

        'layout' => 'layouts::panel',
    ],
    'load_migrations' => true, // set false if you publish the migration
    'schedule' => true,        // register prune + check-sites in the scheduler
    'stale_site_hours' => 48,
    'guards' => ['web', 'project'],
];
