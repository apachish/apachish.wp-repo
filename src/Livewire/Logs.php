<?php

namespace Apachish\WpRepo\Livewire;

use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpUpdateLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Logs extends Component
{
    use WithPagination;

    public string $status = '';

    public string $method = '';

    public ?int $siteId = null;

    public function mount(): void
    {
        Gate::authorize('manage-wp-repo');
    }

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // With tenancy on, only show logs of the current project's sites (and global sync logs).
        $visibleSites = WpSite::pluck('id');

        $logs = WpUpdateLog::with(['site', 'package'])
            ->where(fn ($q) => $q->whereNull('wp_site_id')->orWhereIn('wp_site_id', $visibleSites))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->method, fn ($q) => $q->where('method', $this->method))
            ->when($this->siteId, fn ($q) => $q->where('wp_site_id', $this->siteId))
            ->latest('id')
            ->paginate(25);

        return view('wp-repo::livewire.logs', [
            'logs' => $logs,
            'sites' => WpSite::orderBy('domain')->get(['id', 'domain']),
        ])->layout(config('wp-repo.panel.layout'));
    }
}
