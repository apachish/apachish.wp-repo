<?php

namespace Apachish\WpRepo\Console;

use Apachish\WpRepo\Jobs\DeployToSite;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpSitePackage;
use Illuminate\Console\Command;

class DeployCommand extends Command
{
    // NOTE: "--version" is reserved by Symfony/Artisan, so the pin option is "--ver".
    protected $signature = 'wp-repo:deploy {slug : Package slug}
        {--site=* : Site domain or id (repeatable)}
        {--all : Every site that has this package}
        {--ver= : Deploy this exact version instead of the latest stable}
        {--sync : Run now instead of queueing}';

    protected $description = 'Push a package directly into WordPress installs on this server';

    public function handle(): int
    {
        $package = WpPackage::withoutGlobalScopes()->where('slug', $this->argument('slug'))->first();

        if (! $package) {
            $this->error('Package not found.');

            return self::FAILURE;
        }

        $versionId = null;
        if ($ver = $this->option('ver')) {
            $versionId = $package->versions()->where('version', $ver)->value('id');

            if (! $versionId) {
                $this->error("Version {$ver} does not exist.");

                return self::FAILURE;
            }
        }

        if ($this->option('all')) {
            $sites = WpSite::withoutGlobalScopes()
                ->whereIn('id', WpSitePackage::where('wp_package_id', $package->id)->pluck('wp_site_id'))->get();
        } elseif ($this->option('site')) {
            $sites = WpSite::withoutGlobalScopes()->where(function ($q) {
                foreach ($this->option('site') as $s) {
                    $q->orWhere('domain', strtolower($s))->orWhere('id', ctype_digit($s) ? (int) $s : 0);
                }
            })->get();
        } else {
            $this->error('Pass --site=<domain|id> or --all.');

            return self::FAILURE;
        }

        if ($sites->isEmpty()) {
            $this->warn('No matching sites.');

            return self::FAILURE;
        }

        foreach ($sites as $site) {
            WpSitePackage::firstOrCreate(['wp_site_id' => $site->id, 'wp_package_id' => $package->id]);

            $this->option('sync')
                ? DeployToSite::dispatchSync($site->id, $package->id, $versionId)
                : DeployToSite::dispatch($site->id, $package->id, $versionId);

            $this->line(($this->option('sync') ? 'deployed ' : 'queued   ').$site->domain);
        }

        return self::SUCCESS;
    }
}
