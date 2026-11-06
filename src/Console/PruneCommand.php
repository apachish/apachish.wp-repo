<?php

namespace Apachish\WpRepo\Console;

use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSitePackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneCommand extends Command
{
    protected $signature = 'wp-repo:prune {--keep= : Versions to keep per package (default: config wp-repo.keep_versions)}';

    protected $description = 'Delete old package versions (and their zip files) from storage';

    public function handle(): int
    {
        $keep = max(1, (int) ($this->option('keep') ?: config('wp-repo.keep_versions', 5)));
        $disk = Storage::disk(config('wp-repo.disk'));
        $removed = 0;

        foreach (WpPackage::withoutGlobalScopes()->get() as $package) {
            // Never delete a version that is pinned or currently installed somewhere.
            $inUse = WpSitePackage::where('wp_package_id', $package->id)
                ->get(['installed_version', 'target_version'])
                ->flatMap(fn ($r) => [$r->installed_version, $r->target_version])
                ->filter()->unique()->all();

            $sorted = $package->versions()->get()->sort(fn ($a, $b) => version_compare($b->version, $a->version))->values();

            foreach ($sorted->slice($keep) as $version) {
                if (in_array($version->version, $inUse, true)) {
                    continue;
                }

                $disk->delete($version->file_path);
                $version->delete();
                $removed++;
            }
        }

        $this->info("Removed {$removed} old version(s).");

        return self::SUCCESS;
    }
}
