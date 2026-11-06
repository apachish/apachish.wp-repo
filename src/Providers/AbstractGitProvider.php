<?php

namespace Apachish\WpRepo\Providers;

use Apachish\WpRepo\Contracts\GitProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractGitProvider implements GitProvider
{
    public function __construct(protected array $config = [])
    {
    }

    /** Auth + vendor headers. Never log these. */
    abstract protected function headers(): array;

    protected function api(): PendingRequest
    {
        return Http::withHeaders($this->headers())->acceptJson()->timeout(60);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['api'] ?? ''), '/');
    }

    public function downloadZip(string $repo, string $ref, string $destination): void
    {
        Http::withHeaders($this->headers())
            ->timeout(300)
            ->withOptions(['sink' => $destination])
            ->get($this->zipUrl($repo, $ref))
            ->throw();
    }

    abstract protected function zipUrl(string $repo, string $ref): string;

    protected function hmacValid(string $payload, ?string $given, string $prefix = ''): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        if (! $secret || ! $given) {
            return false;
        }

        return hash_equals($prefix.hash_hmac('sha256', $payload, $secret), $given);
    }

    protected function normalise(array $r, string $bodyKey, string $dateKey): array
    {
        return [
            'tag' => $r['tag_name'],
            'name' => $r['name'] ?? null,
            'body' => $r[$bodyKey] ?? null,
            'published_at' => $r[$dateKey] ?? null,
        ];
    }
}
