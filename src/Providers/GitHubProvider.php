<?php

namespace Apachish\WpRepo\Providers;

use Illuminate\Http\Request;

class GitHubProvider extends AbstractGitProvider
{
    public function name(): string
    {
        return 'github';
    }

    protected function headers(): array
    {
        return array_filter([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Authorization' => ($this->config['token'] ?? null) ? 'Bearer '.$this->config['token'] : null,
        ]);
    }

    public function getRelease(string $repo, ?string $tag = null): array
    {
        $path = $tag ? '/repos/'.$repo.'/releases/tags/'.rawurlencode($tag) : '/repos/'.$repo.'/releases/latest';

        return $this->normalise($this->api()->get($this->baseUrl().$path)->throw()->json(), 'body', 'published_at');
    }

    protected function zipUrl(string $repo, string $ref): string
    {
        return $this->baseUrl().'/repos/'.$repo.'/zipball/'.rawurlencode($ref);
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->hmacValid($request->getContent(), $request->header('X-Hub-Signature-256'), 'sha256=');
    }

    public function parseWebhook(Request $request): ?array
    {
        $event = $request->header('X-GitHub-Event');

        if ($event === 'release' && $request->input('action') === 'published' && ! $request->boolean('release.draft')) {
            return ['repo' => $request->input('repository.full_name'), 'tag' => $request->input('release.tag_name')];
        }

        // Pushing a tag (refs/tags/v1.2.3) also triggers a sync.
        if ($event === 'push' && str_starts_with((string) $request->input('ref'), 'refs/tags/') && ! $request->boolean('deleted')) {
            return ['repo' => $request->input('repository.full_name'), 'tag' => substr($request->input('ref'), 10)];
        }

        return null;
    }
}
