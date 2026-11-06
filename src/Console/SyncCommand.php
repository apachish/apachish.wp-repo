<?php

namespace Apachish\WpRepo\Console;

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpPackage;
use Illuminate\Console\Command;
use Throwable;

class SyncCommand extends Command
{
    protected $signature = 'wp-repo:sync {slug? : Package slug (omit for all active packages)} {--tag= : Specific tag (default: latest release)} {--queue : Dispatch to the queue instead of running now}';

    protected $description = 'Pull the latest release of WordPress packages from their Git repositories';

    public function handle(): int
    {
        $packages = WpPackage::withoutGlobalScopes()->where('is_active', true)
            ->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        if ($packages->isEmpty()) {
            $this->warn('No matching active packages.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($packages as $package) {
            try {
                if ($this->option('queue')) {
                    SyncPackageVersion::dispatch($package->id, $this->option('tag'));
                    $this->line("queued   {$package->slug}");
                } else {
                    SyncPackageVersion::dispatchSync($package->id, $this->option('tag'));
                    $this->info("synced   {$package->slug}");
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error("failed   {$package->slug}: ".$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
