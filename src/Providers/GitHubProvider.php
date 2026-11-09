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
        $path = $tag ? "/repos/{$repo}/releases/tags/".rawurlencode($tag) : "/repos/{$repo}/releases/latest";
        $res = $this->api()->get($this->baseUrl().$path);

        // No published Release (drafts/pre-releases do not count, and a bare tag is not a Release):
        // fall back to plain git tags so "git tag v1.0.0 && git push --tags" is enough.
        if ($res->status() === 404) {
            return $this->fromTags($repo, $tag);
        }

        return $this->normalise($res->throw()->json(), 'body', 'published_at');
    }

    protected function fromTags(string $repo, ?string $tag): array
    {
        $res = $this->api()->get($this->baseUrl()."/repos/{$repo}/tags", ['per_page' => 100]);

        // GitHub answers 404 (not 403) for a private repo the token cannot see.
        if ($res->status() === 404) {
            throw new \RuntimeException($this->explainNotFound($repo));
        }

        $names = array_column($res->throw()->json(), 'name');

        if ($tag) {
            if (! in_array($tag, $names, true)) {
                throw new \RuntimeException("Tag [{$tag}] was not found in [{$repo}].");
            }

            return ['tag' => $tag, 'name' => $tag, 'body' => null, 'published_at' => null];
        }

        if (! $names) {
            throw new \RuntimeException("[{$repo}] has no published Release and no tags. Create a Release (or push a tag such as v1.0.0).");
        }

        usort($names, fn ($a, $b) => version_compare(ltrim($b, 'vV'), ltrim($a, 'vV')));

        return ['tag' => $names[0], 'name' => $names[0], 'body' => null, 'published_at' => null];
    }

    protected function zipUrl(string $repo, string $ref): string
    {
        return $this->baseUrl().'/repos/'.$repo.'/zipball/'.rawurlencode($ref);
    }

    /** Turns GitHub's ambiguous 404 into something actionable by asking who the token is. */
    protected function explainNotFound(string $repo): string
    {
        if (empty($this->config['token'])) {
            return "GitHub returned 404 for [{$repo}] and NO token was sent: the Git connection has an empty token.";
        }

        $me = $this->api()->get($this->baseUrl().'/user');

        if ($me->status() === 401) {
            return "GitHub rejected the token (401 Bad credentials): it is wrong, expired or revoked. Create a new one and save it in the Git connection.";
        }

        if ($me->successful()) {
            $login = $me->json('login');

            return "The token is valid and belongs to [{$login}], but GitHub does not show it the repository [{$repo}]. "
                .'Open the token settings: Resource owner must be the repo owner, and [Repository access] must include this repository (Permissions: Contents = Read-only). '
                .'For an organization repo the org owner may need to approve the token. Also check the owner/repo spelling.';
        }

        return "GitHub returned 404 for [{$repo}]: wrong owner/repo name, or the token has no access to it.";
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
