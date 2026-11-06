<?php

namespace Apachish\WpRepo\Livewire;

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSitePackage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class PackageDetail extends Component
{
    public WpPackage $package;

    public ?string $message = null;

    public function mount(WpPackage $package): void
    {
        Gate::authorize('manage-wp-repo');
        $this->package = $package;
    }

    public function sync(): void
    {
        Gate::authorize('manage-wp-repo');
        SyncPackageVersion::dispatch($this->package->id);
        $this->message = __('wp-repo::messages.sync_queued');
    }

    public function setStable(int $versionId, bool $stable): void
    {
        Gate::authorize('manage-wp-repo');
        $this->package->versions()->whereKey($versionId)->update(['is_stable' => $stable]);
    }

    public function deleteVersion(int $versionId): void
    {
        Gate::authorize('manage-wp-repo');
        $version = $this->package->versions()->findOrFail($versionId);

        $inUse = WpSitePackage::where('wp_package_id', $this->package->id)
            ->where(fn ($q) => $q->where('installed_version', $version->version)->orWhere('target_version', $version->version))
            ->exists();

        if ($inUse) {
            $this->message = __('wp-repo::messages.in_use');

            return;
        }

        Storage::disk(config('wp-repo.disk'))->delete($version->file_path);
        $version->delete();
        $this->message = __('wp-repo::messages.deleted');
    }

    public function render()
    {
        $versions = $this->package->versions()->get()
            ->sort(fn ($a, $b) => version_compare($b->version, $a->version))->values();

        return view('wp-repo::livewire.package-detail', compact('versions'))
            ->layout(config('wp-repo.panel.layout'));
    }
}
