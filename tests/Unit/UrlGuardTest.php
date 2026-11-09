<?php

use Apachish\WpRepo\Support\UrlGuard;

it('rejects non-https and internal API hosts', function (string $url) {
    expect(fn () => UrlGuard::assertSafe($url))->toThrow(RuntimeException::class);
})->with([
    'http' => 'http://git.example.com/api/v1',
    'loopback' => 'https://127.0.0.1/api/v1',
    'private' => 'https://10.0.0.5/api/v1',
    'link-local metadata' => 'https://169.254.169.254/latest',
    'private 192' => 'https://192.168.1.10/api',
]);

it('accepts a public https host and honours allow_private_hosts', function () {
    UrlGuard::assertSafe('https://8.8.8.8/api/v4');
    expect(true)->toBeTrue();

    config(['wp-repo.allow_private_hosts' => true]);
    UrlGuard::assertSafe('https://10.0.0.5/api/v1');
    expect(true)->toBeTrue();
});
