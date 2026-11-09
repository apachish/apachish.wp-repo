<?php

namespace Apachish\WpRepo\Livewire;

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpGitConnection;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Support\RepoName;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Packages extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** slug/type define the folder name inside WordPress, so they lock once versions or sites exist. */
    public bool $locked = false;

    public array $form = [];

    public ?string $message = null;

    public $project;


    public function mount(): void
    {
        Gate::authorize('manage-wp-repo');
        $this->resetForm();
        $this->project = current_project();

    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->locked = false;
        $this->form = [
            'slug' => '', 'type' => 'plugin', 'name' => '', 'repo' => '',
            'wp_git_connection_id' => '', 'is_active' => true, 'is_paid' => false,
        ];
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'form.slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'max:100', Rule::unique('wp_packages', 'slug')->ignore($this->editingId)],
            'form.type' => ['required', 'in:plugin,theme'],
            'form.name' => ['required', 'string', 'max:150'],
            'form.repo' => ['required', 'regex:/^[\w.\-]+(\/[\w.\-]+)+$/', 'max:200'],
            'form.wp_git_connection_id' => ['required', 'integer'],
            'form.is_active' => ['boolean'],
            'form.is_paid' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage-wp-repo');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $package = WpPackage::findOrFail($id);
        $this->editingId = $package->id;
        $this->locked = $package->versions()->exists() || $package->sitePackages()->exists();
        $this->form = $package->only(['slug', 'type', 'name', 'repo', 'wp_git_connection_id', 'is_active', 'is_paid']);
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-wp-repo');
        $this->form['repo'] = RepoName::normalize($this->form['repo'] ?? '');
        $data = $this->validate()['form'];

        // Scoped lookup: a tenant can only pick one of its own connections.
        $connection = WpGitConnection::findOrFail((int) $data['wp_git_connection_id']);
        $data['wp_git_connection_id'] = $connection->id;
        $data['git_provider'] = $connection->provider;

        if ($this->editingId) {
            $package = WpPackage::findOrFail($this->editingId);
            $package->update(collect($data)->except($this->locked ? ['slug', 'type'] : [])->all());
        } else {
            WpPackage::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->message = __('wp-repo::messages.saved');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $package = WpPackage::findOrFail($id);
        $package->update(['is_active' => ! $package->is_active]);
    }

    public function sync(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        SyncPackageVersion::dispatch(WpPackage::findOrFail($id)->id);
        $this->message = __('wp-repo::messages.sync_queued');
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $package = WpPackage::findOrFail($id);
        $disk = Storage::disk(config('wp-repo.disk'));

        foreach ($package->versions as $version) {
            $disk->delete($version->file_path);
        }

        $package->delete();
        $this->message = __('wp-repo::messages.deleted');
    }

    public function render()
    {
        $packages = WpPackage::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('slug', 'like', '%'.$this->search.'%')
                ->orWhere('repo', 'like', '%'.$this->search.'%')))
            ->with('connection:id,name')
            ->latest()
            ->paginate(15);

        $connections = WpGitConnection::orderBy('name')->get(['id', 'name', 'provider']);

        return view('wp-repo::livewire.packages', compact('packages', 'connections'))
            ->layout(config('wp-repo.panel.layout'));
    }
}
