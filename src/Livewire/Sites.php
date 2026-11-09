<?php

namespace Apachish\WpRepo\Livewire;

use Apachish\WpRepo\Jobs\DeployToSite;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpSitePackage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Sites extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $domain = '';

    public string $wp_path = '';

    /** Plain API key, shown exactly once right after creation/regeneration. */
    public ?string $newKey = null;

    public ?int $assignSite = null;

    public ?int $assignPackage = null;

    /** @var array<int,string> pinned version input per wp_site_packages.id */
    public array $pins = [];

    public ?string $message = null;

    public $project;

    public function mount(): void
    {
        Gate::authorize('manage-wp-repo');
        $this->project = current_project();

    }

    public function createSite(): void
    {
        Gate::authorize('manage-wp-repo');

        $this->domain = strtolower(trim($this->domain));

        $this->validate([
            'domain' => ['required', 'regex:/^(?=.{4,253}$)([a-z0-9\p{L}]([a-z0-9\p{L}-]{0,61}[a-z0-9\p{L}])?\.)+[a-z\p{L}]{2,}$/iu',
                Rule::unique('wp_sites', 'domain')],
            'wp_path' => ['nullable', 'string', 'max:500'],
        ]);

        $site = new WpSite(['domain' => $this->domain, 'wp_path' => $this->wp_path ?: null]);
        $this->newKey = $site->issueKey();
        $site->save();

        $this->reset(['domain', 'wp_path', 'showForm']);
    }

    public function regenerateKey(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $site = WpSite::findOrFail($id);
        $this->newKey = $site->issueKey();
        $site->save();
    }

    public function deleteSite(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        WpSite::findOrFail($id)->delete();
        $this->message = __('wp-repo::messages.deleted');
    }

    public function assign(): void
    {
        Gate::authorize('manage-wp-repo');

        $this->validate([
            'assignSite' => ['required', 'integer'],
            'assignPackage' => ['required', 'integer'],
        ]);

        $site = WpSite::findOrFail($this->assignSite);
        $package = WpPackage::findOrFail($this->assignPackage);

        WpSitePackage::firstOrCreate(['wp_site_id' => $site->id, 'wp_package_id' => $package->id]);
        $this->message = __('wp-repo::messages.saved');
    }

    public function toggleAuto(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $sp = $this->ownedSitePackage($id);
        $sp->update(['auto_update' => ! $sp->auto_update]);
    }

    public function pin(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $sp = $this->ownedSitePackage($id);
        $wanted = trim($this->pins[$id] ?? '');

        if ($wanted !== '' && ! $sp->package->versions()->where('version', $wanted)->exists()) {
            $this->addError("pins.{$id}", __('wp-repo::messages.none'));

            return;
        }

        $sp->update(['target_version' => $wanted ?: null]);
        $this->resetErrorBag("pins.{$id}");
        $this->message = __('wp-repo::messages.saved');
    }

    public function updateNow(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $sp = $this->ownedSitePackage($id);
        DeployToSite::dispatch($sp->wp_site_id, $sp->wp_package_id);
        $sp->update(['status' => 'pending']);
        $this->message = __('wp-repo::messages.update_queued');
    }

    public function updateAll(int $siteId): void
    {
        Gate::authorize('manage-wp-repo');
        $site = WpSite::findOrFail($siteId);

        foreach ($site->sitePackages as $sp) {
            DeployToSite::dispatch($site->id, $sp->wp_package_id);
        }

        $this->message = __('wp-repo::messages.update_queued');
    }

    public function removeAssignment(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $this->ownedSitePackage($id)->delete();
    }

    /** Resolve a pivot row only if its site is visible to the current tenant. */
    protected function ownedSitePackage(int $id): WpSitePackage
    {
        $sp = WpSitePackage::findOrFail($id);
        WpSite::findOrFail($sp->wp_site_id); // tenant scope check

        return $sp;
    }

    public function render()
    {
        return view('wp-repo::livewire.sites', [
            'sites' => WpSite::with('sitePackages.package')->orderBy('domain')->paginate(10),
            'allSites' => WpSite::orderBy('domain')->get(['id', 'domain']),
            'allPackages' => WpPackage::orderBy('name')->get(['id', 'name', 'slug']),
        ])->layout(config('wp-repo.panel.layout'));
    }
}
