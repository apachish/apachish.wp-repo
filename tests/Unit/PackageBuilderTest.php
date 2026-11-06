<?php

use Apachish\WpRepo\Services\PackageBuilder;

function makeZip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'wpr').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return $path;
}

it('re-roots under the slug, strips dev files and reads headers', function () {
    $src = makeZip([
        'acme-my-plugin-abc123/my-plugin.php' => "<?php\n/**\n * Plugin Name: My Plugin\n * Version: 1.4.0\n * Requires at least: 6.0\n * Requires PHP: 8.1\n */\n",
        'acme-my-plugin-abc123/readme.txt' => "=== My Plugin ===\nTested up to: 6.7\n\n== Changelog ==\n\n= 1.4.0 =\n* Fixed things\n",
        'acme-my-plugin-abc123/.github/workflows/ci.yml' => 'x',
        'acme-my-plugin-abc123/node_modules/foo/index.js' => 'x',
        'acme-my-plugin-abc123/.distignore' => "secret-dir\n",
        'acme-my-plugin-abc123/secret-dir/a.txt' => 'x',
        'acme-my-plugin-abc123/includes/class.php' => '<?php',
    ]);
    $out = tempnam(sys_get_temp_dir(), 'wpr').'.zip';

    $info = (new PackageBuilder)->build($src, 'my-plugin', 'plugin', $out);

    expect($info['version'])->toBe('1.4.0')
        ->and($info['requires_wp'])->toBe('6.0')
        ->and($info['requires_php'])->toBe('8.1')
        ->and($info['tested_wp'])->toBe('6.7')
        ->and($info['changelog'])->toContain('Fixed things');

    $zip = new ZipArchive;
    $zip->open($out);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));

    expect($names)->toContain('my-plugin/my-plugin.php', 'my-plugin/includes/class.php')
        ->and($names->contains(fn ($n) => str_contains($n, '.github') || str_contains($n, 'node_modules') || str_contains($n, 'secret-dir')))->toBeFalse();
});

it('refuses zip-slip entries', function () {
    $src = makeZip(['repo/ok.php' => '<?php', '../evil.php' => 'pwned']);
    $out = tempnam(sys_get_temp_dir(), 'wpr').'.zip';

    expect(fn () => (new PackageBuilder)->build($src, 'my-plugin', 'plugin', $out))
        ->toThrow(RuntimeException::class, 'Unsafe zip entry');

    expect(file_exists(sys_get_temp_dir().'/evil.php'))->toBeFalse();
});

it('fails when no WordPress header is found', function () {
    $src = makeZip(['repo/readme.md' => 'hello']);
    $out = tempnam(sys_get_temp_dir(), 'wpr').'.zip';

    expect(fn () => (new PackageBuilder)->build($src, 'my-plugin', 'plugin', $out))
        ->toThrow(RuntimeException::class, 'Main plugin file');
});

it('reads theme headers from style.css', function () {
    $src = makeZip(['repo/style.css' => "/*\nTheme Name: Mine\nVersion: 2.1.0\n*/", 'repo/functions.php' => '<?php']);
    $out = tempnam(sys_get_temp_dir(), 'wpr').'.zip';

    expect((new PackageBuilder)->build($src, 'mine', 'theme', $out)['version'])->toBe('2.1.0');
});
