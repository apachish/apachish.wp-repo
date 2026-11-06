<?php

use Apachish\WpRepo\Contracts\EntitlementChecker;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSite;

function makeSite(): array
{
    $site = new WpSite(['domain' => 'example.com']);
    $key = $site->issueKey();
    $site->save();

    return [$site, ['X-Site-Domain' => 'example.com', 'X-Site-Key' => $key]];
}

function makePackage(array $attrs = []): WpPackage
{
    $package = WpPackage::create($attrs + ['slug' => 'my-plugin', 'type' => 'plugin', 'name' => 'My Plugin', 'repo' => 'acme/my-plugin']);
    $package->versions()->create(['version' => '1.2.0', 'file_path' => 'x/1.2.0.zip', 'checksum' => str_repeat('a', 64), 'is_stable' => true]);
    $package->versions()->create(['version' => '1.10.0', 'file_path' => 'x/1.10.0.zip', 'checksum' => str_repeat('b', 64), 'is_stable' => true]);
    $package->versions()->create(['version' => '2.0.0-beta', 'file_path' => 'x/2.0.0-beta.zip', 'checksum' => str_repeat('c', 64), 'is_stable' => false]);

    return $package;
}

it('rejects unknown sites and bad keys', function () {
    [, $headers] = makeSite();
    makePackage();

    $this->getJson('/api/wp-updates/check?slug=my-plugin')->assertUnauthorized();
    $this->getJson('/api/wp-updates/check?slug=my-plugin', array_merge($headers, ['X-Site-Key' => 'nope']))->assertUnauthorized();
});

it('offers the highest stable version (semver-aware) with a signed url', function () {
    [, $headers] = makeSite();
    makePackage();

    $this->getJson('/api/wp-updates/check?slug=my-plugin&version=1.2.0', $headers)
        ->assertOk()
        ->assertJsonPath('version', '1.10.0')
        ->assertJsonPath('update_available', true)
        ->assertJsonPath('checksum', str_repeat('b', 64))
        ->assertJson(fn ($json) => $json->where('download_url', fn ($u) => str_contains($u, 'signature=') && str_contains($u, 'expires='))->etc());
});

it('reports no update when already current', function () {
    [, $headers] = makeSite();
    makePackage();

    $this->getJson('/api/wp-updates/check?slug=my-plugin&version=1.10.0', $headers)
        ->assertOk()
        ->assertJsonPath('update_available', false)
        ->assertJsonPath('download_url', null);
});

it('returns 404 for unknown or inactive packages', function () {
    [, $headers] = makeSite();
    makePackage(['is_active' => false]);

    $this->getJson('/api/wp-updates/check?slug=my-plugin', $headers)->assertNotFound();
    $this->getJson('/api/wp-updates/check?slug=nope', $headers)->assertNotFound();
});

it('blocks paid packages when the entitlement checker says no', function () {
    [, $headers] = makeSite();
    makePackage(['is_paid' => true]);

    app()->bind(EntitlementChecker::class, fn () => new class implements EntitlementChecker {
        public function allows(WpSite $site, WpPackage $package): bool
        {
            return false;
        }
    });

    $this->getJson('/api/wp-updates/check?slug=my-plugin', $headers)
        ->assertForbidden()
        ->assertJsonPath('error', 'license_required');
});

it('honours a pinned target version and auto_update=false', function () {
    [$site, $headers] = makeSite();
    $package = makePackage();

    $site->sitePackages()->create(['wp_package_id' => $package->id, 'target_version' => '1.2.0', 'installed_version' => '1.0.0']);

    $this->getJson('/api/wp-updates/check?slug=my-plugin&version=1.0.0', $headers)
        ->assertOk()->assertJsonPath('version', '1.2.0');

    $site->sitePackages()->update(['target_version' => null, 'auto_update' => false]);

    $this->getJson('/api/wp-updates/check?slug=my-plugin&version=1.0.0', $headers)
        ->assertOk()->assertJsonPath('update_available', false);
});
