<?php

namespace Apachish\WpRepo\Jobs;

use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpPackageVersion;
use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpSitePackage;
use Apachish\WpRepo\Models\WpUpdateLog;
use Apachish\WpRepo\Services\SiteDeployer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class DeployToSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $siteId, public int $packageId, public ?int $versionId = null)
    {
    }

    public function handle(SiteDeployer $deployer): void
    {
        $site = WpSite::withoutGlobalScopes()->findOrFail($this->siteId);
        $package = WpPackage::withoutGlobalScopes()->findOrFail($this->packageId);

        $version = $this->versionId
            ? WpPackageVersion::findOrFail($this->versionId)
            : $this->resolveVersion($site, $package);

        if (! $version) {
            WpUpdateLog::record($site->id, $package->id, 'push', 'failed', null, null, 'No stable version available.');

            return;
        }

        try {
            $deployer->deploy($site, $package, $version);
        } catch (Throwable $e) {
            // SiteDeployer already logged the failure and updated the status.
            $this->fail($e);
        }
    }

    protected function resolveVersion(WpSite $site, WpPackage $package): ?WpPackageVersion
    {
        $pin = WpSitePackage::where('wp_site_id', $site->id)->where('wp_package_id', $package->id)->value('target_version');

        return $pin
            ? $package->versions()->where('version', $pin)->first()
            : $package->latestStable();
    }
}
