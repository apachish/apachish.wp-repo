<?php

namespace Apachish\WpRepo\Services;

use RuntimeException;
use ZipArchive;

/**
 * Turns a raw git archive into a WordPress-ready zip:
 *  - safe extraction (Zip Slip / symlink / size guards)
 *  - strips the "owner-repo-hash/" wrapper folder and re-roots everything under "{slug}/"
 *  - removes dev files (config distignore + the repo's own .distignore)
 *  - reads WordPress headers (Version, Requires at least, Tested up to, Requires PHP)
 */
class PackageBuilder
{
    /** @return array{version:?string,requires_wp:?string,tested_wp:?string,requires_php:?string,changelog:?string} */
    public function build(string $sourceZip, string $slug, string $type, string $outputZip): array
    {
        $work = $this->makeTempDir();

        try {
            $this->safeExtract($sourceZip, $work);
            $root = $this->detectRoot($work);
            $this->prune($root, $root, $this->ignorePatterns($root));

            $info = $this->readHeaders($root, $type);
            $this->zipDirectory($root, $slug, $outputZip);

            return $info;
        } finally {
            $this->deleteDir($work);
        }
    }

    public function safeExtract(string $zipPath, string $dest): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open zip archive.');
        }

        $limit = (int) config('wp-repo.max_unzipped_bytes', 300 * 1024 * 1024);
        $total = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', $stat['name']);

                if ($name === '' || str_starts_with($name, '/') || str_contains($name, "\0")
                    || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[A-Za-z]:#', $name)) {
                    throw new RuntimeException("Unsafe zip entry: {$name}");
                }

                // Skip symlinks entirely.
                $zip->getExternalAttributesIndex($i, $opsys, $attr);
                if ($opsys === ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0170000) === 0120000)) {
                    continue;
                }

                $target = rtrim($dest, '/').'/'.$name;

                if (str_ends_with($name, '/')) {
                    if (! is_dir($target)) {
                        mkdir($target, 0755, true);
                    }
                    continue;
                }

                $total += (int) $stat['size'];
                if ($total > $limit) {
                    throw new RuntimeException('Archive exceeds the maximum unzipped size.');
                }

                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0755, true);
                }

                $in = $zip->getStream($stat['name']);
                $out = fopen($target, 'wb');

                if (! $in || ! $out) {
                    throw new RuntimeException("Cannot extract {$name}");
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            }
        } finally {
            $zip->close();
        }
    }

    public function zipDirectory(string $dir, string $rootName, string $outputZip): void
    {
        if (file_exists($outputZip)) {
            unlink($outputZip);
        }

        $zip = new ZipArchive;

        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create zip archive.');
        }

        $zip->addEmptyDir($rootName);
        $dir = rtrim($dir, '/');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                continue;
            }

            $rel = $rootName.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));

            if ($file->isDir()) {
                $zip->addEmptyDir($rel);
            } elseif ($file->getSize() < 2 * 1024 * 1024) {
                // addFromString avoids hitting the open-file-descriptor limit on big vendor/ trees.
                $zip->addFromString($rel, (string) file_get_contents($file->getPathname()));
            } else {
                $zip->addFile($file->getPathname(), $rel);
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('Failed to write zip archive.');
        }
    }

    public function makeTempDir(): string
    {
        $base = config('wp-repo.tmp_path');

        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        $dir = $base.'/'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);

        return $dir;
    }

    public function deleteDir(string $dir): void
    {
        if (is_link($dir) || is_file($dir)) {
            @unlink($dir);

            return;
        }

        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->deleteDir($dir.'/'.$item);
            }
        }

        @rmdir($dir);
    }

    protected function detectRoot(string $work): string
    {
        $items = array_values(array_diff(scandir($work), ['.', '..']));

        if (count($items) === 1 && is_dir($work.'/'.$items[0])) {
            return $work.'/'.$items[0];
        }

        return $work;
    }

    protected function ignorePatterns(string $root): array
    {
        $patterns = (array) config('wp-repo.distignore', []);

        if (is_file($root.'/.distignore')) {
            $patterns = array_merge($patterns, file($root.'/.distignore', FILE_IGNORE_NEW_LINES));
        }

        return array_values(array_filter(array_map('trim', $patterns), fn ($p) => $p !== '' && $p[0] !== '#'));
    }

    protected function prune(string $dir, string $root, array $patterns): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir.'/'.$item;
            $rel = ltrim(substr($path, strlen($root)), '/');

            if ($this->matches($rel, $item, $patterns)) {
                $this->deleteDir($path);
            } elseif (is_dir($path) && ! is_link($path)) {
                $this->prune($path, $root, $patterns);
            }
        }
    }

    protected function matches(string $rel, string $base, array $patterns): bool
    {
        foreach ($patterns as $p) {
            $p = trim($p, '/');

            if (fnmatch($p, $rel) || fnmatch($p, $base)) {
                return true;
            }
        }

        return false;
    }

    protected function readHeaders(string $root, string $type): array
    {
        $main = null;

        if ($type === 'theme') {
            if (is_file($root.'/style.css') && $this->header($this->head($root.'/style.css'), 'Theme Name')) {
                $main = $root.'/style.css';
            }
        } else {
            foreach (glob($root.'/*.php') ?: [] as $file) {
                if ($this->header($this->head($file), 'Plugin Name')) {
                    $main = $file;
                    break;
                }
            }
        }

        if (! $main) {
            throw new RuntimeException("Main {$type} file with a valid WordPress header not found in the repository root.");
        }

        $head = $this->head($main);
        $readme = is_file($root.'/readme.txt') ? $this->head($root.'/readme.txt', 16384) : '';

        return [
            'version' => $this->header($head, 'Version') ?: $this->header($readme, 'Stable tag'),
            'requires_wp' => $this->header($head, 'Requires at least') ?: $this->header($readme, 'Requires at least'),
            'tested_wp' => $this->header($head, 'Tested up to') ?: $this->header($readme, 'Tested up to'),
            'requires_php' => $this->header($head, 'Requires PHP') ?: $this->header($readme, 'Requires PHP'),
            'changelog' => $this->readmeChangelog($root.'/readme.txt'),
        ];
    }

    protected function head(string $file, int $bytes = 8192): string
    {
        return str_replace("\r", '', (string) file_get_contents($file, false, null, 0, $bytes));
    }

    protected function header(string $content, string $name): ?string
    {
        if (preg_match('/^[ \t\/*#@=]*'.preg_quote($name, '/').'\s*:(.*)$/mi', $content, $m)) {
            $value = trim(preg_replace('/\s*(?:\*\/|\?>).*$/', '', $m[1]));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    protected function readmeChangelog(string $readme): ?string
    {
        if (! is_file($readme)) {
            return null;
        }

        $text = str_replace("\r", '', (string) file_get_contents($readme));

        if (! preg_match('/^==\s*Changelog\s*==\s*\n(.*?)(?=^==[^=]|\z)/ims', $text, $m)) {
            return null;
        }

        return mb_substr(trim($m[1]), 0, 4000) ?: null;
    }
}
