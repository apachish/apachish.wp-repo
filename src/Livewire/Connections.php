<?php

namespace Apachish\WpRepo\Livewire;

use Apachish\WpRepo\Models\WpGitConnection;
use Apachish\WpRepo\Support\UrlGuard;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use RuntimeException;

class Connections extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** Shown exactly once after creation / regeneration. */
    public ?string $newSecret = null;

    public ?string $newSecretUrl = null;

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
        $this->form = ['name' => '', 'provider' => 'github', 'api_url' => '', 'token' => ''];
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.provider' => ['required', 'in:github,gitlab,gitea'],
            'form.api_url' => [$this->form['provider'] === 'gitea' ? 'required' : 'nullable', 'url:https', 'max:255'],
            // On edit the token may stay blank (= keep the stored one).
            'form.token' => [$this->editingId ? 'nullable' : 'required', 'string', 'max:500'],
        ];
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
        $connection = WpGitConnection::findOrFail($id);
        $this->editingId = $connection->id;
        // The token is never sent back to the browser.
        $this->form = ['name' => $connection->name, 'provider' => $connection->provider, 'api_url' => (string) $connection->api_url, 'token' => ''];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-wp-repo');
        $this->form['token'] = trim((string) ($this->form['token'] ?? ''));
        $data = $this->validate()['form'];

        if (! empty($data['api_url'])) {
            try {
                UrlGuard::assertSafe($data['api_url']);
            } catch (RuntimeException $e) {
                $this->addError('form.api_url', $e->getMessage());

                return;
            }
        }

        $fields = ['name' => $data['name'], 'provider' => $data['provider'], 'api_url' => $data['api_url'] ?: null];

        if ($this->editingId) {
            $connection = WpGitConnection::findOrFail($this->editingId);
            $connection->fill($fields);
            if (! empty($data['token'])) {
                $connection->token = $data['token'];
            }
            $connection->save();
            $connection->packages()->update(['git_provider' => $connection->provider]);
        } else {
            $connection = new WpGitConnection($fields);
            $connection->token = $data['token'];
            $connection->webhook_secret = $secret = WpGitConnection::generateSecret();
            $connection->save();

            $this->newSecret = $secret;
            $this->newSecretUrl = $connection->webhookUrl();
        }

        $this->showForm = false;
        $this->resetForm();
        $this->message = __('wp-repo::messages.saved');
    }

    public function regenerateSecret(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $connection = WpGitConnection::findOrFail($id);
        $connection->webhook_secret = $this->newSecret = WpGitConnection::generateSecret();
        $connection->save();
        $this->newSecretUrl = $connection->webhookUrl();
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-wp-repo');
        $connection = WpGitConnection::findOrFail($id);

        if ($connection->packages()->exists()) {
            $this->message = __('wp-repo::messages.conn_in_use');

            return;
        }

        $connection->delete();
        $this->message = __('wp-repo::messages.deleted');
    }

    public function render()
    {
        return view('wp-repo::livewire.connections', [
            'connections' => WpGitConnection::withCount('packages')->orderBy('name')->get(),
        ])->layout(config('wp-repo.panel.layout'));
    }
}
