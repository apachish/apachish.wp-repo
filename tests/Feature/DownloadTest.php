<?php

use Apachish\WpRepo\Models\WpPackage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    $this->package = WpPackage::create(['slug' => 'my-plugin', 'type' => 'plugin', 'name' => 'My Plugin', 'repo' => 'acme/my-plugin']);
    Storage::disk('local')->put('wp-packages/my-plugin/my-plugin-1.0.0.zip', 'zipbytes');
    $this->version = $this->package->versions()->create([
        'version' => '1.0.0', 'file_path' => 'wp-packages/my-plugin/my-plugin-1.0.0.zip', 'checksum' => hash('sha256', 'zipbytes'),
    ]);
});

it('serves the file through a valid signed url and logs it', function () {
    $url = URL::temporarySignedRoute('wp-repo.download', now()->addMinutes(5), ['version' => $this->version->id]);

    $this->get($url)->assertOk()->assertHeader('X-Checksum-Sha256', $this->version->checksum);
    $this->assertDatabaseHas('wp_update_logs', ['method' => 'pull', 'status' => 'downloaded', 'to_version' => '1.0.0']);
});

it('rejects expired and unsigned urls', function () {
    $expired = URL::temporarySignedRoute('wp-repo.download', now()->subMinute(), ['version' => $this->version->id]);

    $this->get($expired)->assertForbidden();
    $this->get('/api/wp-updates/download/'.$this->version->id)->assertForbidden();
});
