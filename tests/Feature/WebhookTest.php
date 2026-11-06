<?php

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpPackage;
use Illuminate\Support\Facades\Queue;

function githubPayload(): string
{
    return json_encode([
        'action' => 'published',
        'release' => ['tag_name' => 'v1.2.0', 'draft' => false],
        'repository' => ['full_name' => 'acme/my-plugin'],
    ]);
}

function signedHeaders(string $body, string $secret = 'topsecret'): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => 'release',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ];
}

beforeEach(function () {
    WpPackage::create(['slug' => 'my-plugin', 'type' => 'plugin', 'name' => 'My Plugin', 'repo' => 'acme/my-plugin', 'git_provider' => 'github']);
});

it('queues a sync for a correctly signed release webhook', function () {
    Queue::fake();
    $body = githubPayload();

    $this->call('POST', '/api/wp-updates/webhook/github', [], [], [], signedHeaders($body), $body)
        ->assertStatus(202)
        ->assertJson(['status' => 'queued', 'packages' => 1]);

    Queue::assertPushed(SyncPackageVersion::class, fn ($job) => $job->tag === 'v1.2.0');
});

it('rejects an invalid signature', function () {
    Queue::fake();
    $body = githubPayload();

    $this->call('POST', '/api/wp-updates/webhook/github', [], [], [], signedHeaders($body, 'wrong'), $body)
        ->assertStatus(403);

    Queue::assertNothingPushed();
});

it('404s on an unknown provider', function () {
    $this->postJson('/api/wp-updates/webhook/bitbucket')->assertNotFound();
});
