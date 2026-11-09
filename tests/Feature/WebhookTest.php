<?php

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpGitConnection;
use Apachish\WpRepo\Models\WpPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function connection(string $secret = 'topsecret', string $token = 'ghp_realtoken'): WpGitConnection
{
    $c = new WpGitConnection(['name' => 'Acme', 'provider' => 'github']);
    $c->token = $token;
    $c->webhook_secret = $secret;
    $c->save();

    return $c;
}

function package(WpGitConnection $c, string $slug = 'my-plugin'): WpPackage
{
    return WpPackage::create(['slug' => $slug, 'type' => 'plugin', 'name' => $slug, 'repo' => 'acme/my-plugin',
        'git_provider' => 'github', 'wp_git_connection_id' => $c->id]);
}

function githubBody(): string
{
    return json_encode(['action' => 'published', 'release' => ['tag_name' => 'v1.2.0', 'draft' => false], 'repository' => ['full_name' => 'acme/my-plugin']]);
}

function ghHeaders(string $body, string $secret): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => 'release',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ];
}

it('queues a sync when the signature matches that connection\'s secret', function () {
    Queue::fake();
    $c = connection();
    package($c);
    $body = githubBody();

    $this->call('POST', "/api/wp-updates/webhook/c/{$c->uuid}", [], [], [], ghHeaders($body, 'topsecret'), $body)
        ->assertStatus(202)->assertJson(['status' => 'queued', 'packages' => 1]);

    Queue::assertPushed(SyncPackageVersion::class, fn ($job) => $job->tag === 'v1.2.0');
});

it('rejects a wrong secret and an unknown connection', function () {
    Queue::fake();
    $c = connection();
    package($c);
    $body = githubBody();

    $this->call('POST', "/api/wp-updates/webhook/c/{$c->uuid}", [], [], [], ghHeaders($body, 'wrong'), $body)->assertForbidden();
    $this->call('POST', '/api/wp-updates/webhook/c/'.fake()->uuid(), [], [], [], ghHeaders($body, 'topsecret'), $body)->assertNotFound();

    Queue::assertNothingPushed();
});

it('never syncs packages that belong to another connection', function () {
    Queue::fake();
    $mine = connection('secret-a');
    $other = connection('secret-b');
    $theirs = package($other, 'their-plugin'); // same repo name, different connection
    $body = githubBody();

    $this->call('POST', "/api/wp-updates/webhook/c/{$mine->uuid}", [], [], [], ghHeaders($body, 'secret-a'), $body)
        ->assertStatus(202)->assertJson(['packages' => 0]);

    Queue::assertNothingPushed();
});

it('stores credentials encrypted and never serialises them', function () {
    $c = connection('topsecret', 'ghp_realtoken');

    $raw = DB::table('wp_git_connections')->first();
    expect($raw->token)->not->toBe('ghp_realtoken')->and($raw->token)->not->toContain('ghp_')
        ->and($raw->webhook_secret)->not->toBe('topsecret');

    expect($c->fresh()->token)->toBe('ghp_realtoken');
    expect($c->fresh()->toArray())->not->toHaveKeys(['token', 'webhook_secret']);
    expect($c->fresh()->toJson())->not->toContain('ghp_realtoken');
});
