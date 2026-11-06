<?php

namespace Apachish\WpRepo\Contracts;

use Illuminate\Http\Request;

interface GitProvider
{
    public function name(): string;

    /**
     * Normalised release: ['tag' => string, 'name' => ?string, 'body' => ?string, 'published_at' => ?string].
     * $tag === null means "latest release".
     */
    public function getRelease(string $repo, ?string $tag = null): array;

    /** Download the source archive (zip) of $ref to $destination (a file path). */
    public function downloadZip(string $repo, string $ref, string $destination): void;

    public function verifyWebhook(Request $request): bool;

    /** @return array{repo: string, tag: string}|null  null = event we do not care about */
    public function parseWebhook(Request $request): ?array;
}
