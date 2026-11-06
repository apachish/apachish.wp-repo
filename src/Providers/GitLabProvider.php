<?php

namespace Apachish\WpRepo\Providers;

use Illuminate\Http\Request;

class GitLabProvider extends AbstractGitProvider
{
    public function name(): string
    {
        return 'gitlab';
    }

    protected function headers(): array
    {
        return array_filter(['PRIVATE-TOKEN' => $this->config['token'] ?? null]);
    }

    public function getRelease(string $repo, ?string $tag = null): array
    {
        $project = rawurlencode($repo);

        if ($tag) {
            $r = $this->api()->get($this->baseUrl()."/projects/{$project}/releases/".rawurlencode($tag))->throw()->json();
        } else {
            $r = $this->api()->get($this->baseUrl()."/projects/{$project}/releases", ['per_page' => 1])->throw()->json()[0]
                ?? throw new \RuntimeException("No releases found for {$repo}");
        }

        return $this->normalise($r, 'description', 'released_at');
    }

    protected function zipUrl(string $repo, string $ref): string
    {
        return $this->baseUrl().'/projects/'.rawurlencode($repo).'/repository/archive.zip?sha='.rawurlencode($ref);
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;
        $given = $request->header('X-Gitlab-Token');

        return $secret && $given && hash_equals($secret, $given);
    }

    public function parseWebhook(Request $request): ?array
    {
        $repo = $request->input('project.path_with_namespace');
        $kind = $request->input('object_kind');

        if ($kind === 'release' && $request->input('action') === 'create') {
            return ['repo' => $repo, 'tag' => $request->input('tag')];
        }

        if ($kind === 'tag_push' && str_starts_with((string) $request->input('ref'), 'refs/tags/')
            && ! preg_match('/^0+$/', (string) $request->input('after'))) {
            return ['repo' => $repo, 'tag' => substr($request->input('ref'), 10)];
        }

        return null;
    }
}
