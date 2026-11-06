<?php

namespace Apachish\WpRepo\Providers;

use Illuminate\Http\Request;

/** Gitea and Forgejo share the same API and webhook format. */
class GiteaProvider extends AbstractGitProvider
{
    public function name(): string
    {
        return 'gitea';
    }

    protected function headers(): array
    {
        return array_filter(['Authorization' => ($this->config['token'] ?? null) ? 'token '.$this->config['token'] : null]);
    }

    public function getRelease(string $repo, ?string $tag = null): array
    {
        $path = $tag ? "/repos/{$repo}/releases/tags/".rawurlencode($tag) : "/repos/{$repo}/releases/latest";

        return $this->normalise($this->api()->get($this->baseUrl().$path)->throw()->json(), 'body', 'published_at');
    }

    protected function zipUrl(string $repo, string $ref): string
    {
        return $this->baseUrl()."/repos/{$repo}/archive/".rawurlencode($ref).'.zip';
    }

    public function verifyWebhook(Request $request): bool
    {
        $sig = $request->header('X-Gitea-Signature') ?: $request->header('X-Forgejo-Signature');

        return $this->hmacValid($request->getContent(), $sig);
    }

    public function parseWebhook(Request $request): ?array
    {
        $event = $request->header('X-Gitea-Event') ?: $request->header('X-Forgejo-Event');

        if ($event === 'release' && $request->input('action') === 'published') {
            return ['repo' => $request->input('repository.full_name'), 'tag' => $request->input('release.tag_name')];
        }

        if ($event === 'push' && str_starts_with((string) $request->input('ref'), 'refs/tags/')) {
            return ['repo' => $request->input('repository.full_name'), 'tag' => substr($request->input('ref'), 10)];
        }

        return null;
    }
}
