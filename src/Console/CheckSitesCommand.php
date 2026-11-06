<?php

namespace Apachish\WpRepo\Console;

use Apachish\WpRepo\Models\WpSite;
use Illuminate\Console\Command;

class CheckSitesCommand extends Command
{
    protected $signature = 'wp-repo:check-sites {--hours= : Silence threshold in hours (default: config wp-repo.stale_site_hours)}';

    protected $description = 'List WordPress sites that have not asked for updates recently';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?: config('wp-repo.stale_site_hours', 48));

        $stale = WpSite::withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('last_check_at')->orWhere('last_check_at', '<', now()->subHours($hours)))
            ->orderBy('last_check_at')
            ->get(['id', 'domain', 'last_check_at']);

        if ($stale->isEmpty()) {
            $this->info('All sites checked in recently.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Domain', 'Last check'], $stale->map(fn ($s) => [$s->id, $s->domain, $s->last_check_at?->diffForHumans() ?? 'never']));

        return self::SUCCESS;
    }
}
