<?php

namespace Apachish\WpRepo\Services;

use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpPackageVersion;
use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpSitePackage;
use Apachish\WpRepo\Models\WpUpdateLog;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/** Pushes a built package straight into a WordPress install living on this server. */
class SiteDeployer
{
    public function __construct(protected PackageBuilder $builder)
    {
    }

    public function deploy(WpSite $site, WpPackage $package, WpPackageVersion $version): void
    {
        $record = WpSitePackage::firstOrNew(['wp_site_id' => $site->id, 'wp_package_id' => $package->id]);
        $from = $record->installed_version;

        $tmp = $this->builder->makeTempDir();
        $backup = null;

        try {
            $contentDir = $this->contentDir($site);
            $dir = $contentDir.'/'.($package->type === 'theme' ? 'themes' : 'plugins');
            $target = $dir.'/'.$package->slug;

            if (is_link($target)) {
                throw new RuntimeException('Target is a symlink; refusing to deploy.');
            }

            $zip = $tmp.'/package.zip';
            $in = Storage::disk(config('wp-repo.disk'))->readStream($version->file_path);
            $out = fopen($zip, 'wb');

            if (! $in || ! $out) {
                throw new RuntimeException('Package file is missing from storage.');
            }

            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            if (! hash_equals($version->checksum, hash_file('sha256', $zip))) {
                throw new RuntimeException('Checksum mismatch; stored package is corrupted.');
            }

            if (is_dir($target)) {
                $backup = $this->backup($site, $package, $target, $from);
            }

            if ($this->useCli()) {
                try {
                    $this->installWithCli($site, $package, $zip);
                } catch (Throwable $e) {
                    if ($backup) {
                        $this->restore($backup, $dir, $target, $package->slug);
                    }
                    throw $e;
                }
            } else {
                $this->swap($zip, $dir, $target, $package->slug);
            }

            $this->fixOwner($target);
        } catch (Throwable $e) {
            $record->fill(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            WpUpdateLog::record($site->id, $package->id, 'push', 'failed', $from, $version->version, $e->getMessage());

            throw $e;
        } finally {
            $this->builder->deleteDir($tmp);
        }

        $record->fill([
            'installed_version' => $version->version,
            'last_updated_at' => now(),
            'status' => 'ok',
            'last_error' => null,
        ])->save();

        WpUpdateLog::record($site->id, $package->id, 'push', 'ok', $from, $version->version, $backup ? 'Backup: '.basename($backup) : null);
    }

    protected function contentDir(WpSite $site): string
    {
        $roots = (array) config('wp-repo.wp.allowed_roots', []);

        if (! $roots) {
            throw new RuntimeException('Direct push is disabled: set wp-repo.wp.allowed_roots.');
        }

        $real = $site->wp_path ? realpath($site->wp_path) : false;

        if (! $real || ! is_dir($real.'/wp-content')) {
            throw new RuntimeException('wp_path is invalid or is not a WordPress install.');
        }

        foreach ($roots as $root) {
            $rootReal = realpath($root);

            if ($rootReal && str_starts_with($real.'/', rtrim($rootReal, '/').'/')) {
                return $real.'/wp-content';
            }
        }

        throw new RuntimeException('wp_path is outside the allowed roots.');
    }

    protected function swap(string $zip, string $dir, string $target, string $slug): void
    {
        $stamp = time();
        $new = $dir.'/.'.$slug.'.new-'.$stamp;
        $old = $dir.'/.'.$slug.'.old-'.$stamp;

        mkdir($new, 0755, true);

        try {
            $this->builder->safeExtract($zip, $new);

            if (! is_dir($new.'/'.$slug)) {
                throw new RuntimeException("Zip does not contain the [{$slug}] folder.");
            }

            $hadOld = is_dir($target);

            if ($hadOld && ! rename($target, $old)) {
                throw new RuntimeException('Cannot move the current version aside.');
            }

            if (! rename($new.'/'.$slug, $target)) {
                if ($hadOld) {
                    rename($old, $target); // rollback
                }
                throw new RuntimeException('Cannot move the new version into place.');
            }

            $this->builder->deleteDir($old);
        } finally {
            $this->builder->deleteDir($new);
        }
    }

    protected function useCli(): bool
    {
        return config('wp-repo.wp.use_cli') && (new ExecutableFinder)->find(config('wp-repo.wp.cli', 'wp')) !== null;
    }

    protected function installWithCli(WpSite $site, WpPackage $package, string $zip): void
    {
        $cmd = [config('wp-repo.wp.cli', 'wp'), $package->type === 'theme' ? 'theme' : 'plugin', 'install', $zip,
            '--force', '--path='.$site->wp_path, '--skip-plugins', '--skip-themes'];

        if (config('wp-repo.wp.cli_allow_root')) {
            $cmd[] = '--allow-root';
        }

        $result = Process::timeout(300)->run($cmd);

        if (! $result->successful()) {
            throw new RuntimeException('wp-cli failed: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    protected function backup(WpSite $site, WpPackage $package, string $target, ?string $installed): string
    {
        $dir = rtrim(config('wp-repo.wp.backup_path'), '/').'/site-'.$site->id;

        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $file = $dir.'/'.$package->slug.'-'.($installed ?: 'unknown').'-'.date('Ymd-His').'.zip';
        $this->builder->zipDirectory($target, $package->slug, $file);

        // Keep only the newest N backups per package.
        $all = glob($dir.'/'.$package->slug.'-*.zip') ?: [];
        rsort($all);
        foreach (array_slice($all, (int) config('wp-repo.wp.keep_backups', 3)) as $stale) {
            @unlink($stale);
        }

        return $file;
    }

    protected function restore(string $backupZip, string $dir, string $target, string $slug): void
    {
        $stage = $dir.'/.'.$slug.'.restore-'.time();
        mkdir($stage, 0755, true);

        try {
            $this->builder->safeExtract($backupZip, $stage);

            if (is_dir($stage.'/'.$slug)) {
                $this->builder->deleteDir($target);
                rename($stage.'/'.$slug, $target);
            }
        } finally {
            $this->builder->deleteDir($stage);
        }
    }

    protected function fixOwner(string $target): void
    {
        if ($owner = config('wp-repo.wp.chown')) {
            Process::run(['chown', '-R', $owner, $target]);
        }
    }
}
