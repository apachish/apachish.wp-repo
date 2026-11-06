<?php

namespace Apachish\WpRepo\Jobs;

use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpUpdateLog;
use Apachish\WpRepo\Services\GitManager;
use Apachish\WpRepo\Services\PackageBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Downloads a release from the private repo, builds a WordPress-ready zip and registers the version. */
class SyncPackageVersion implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public int $packageId, public ?string $tag = null)
    {
    }

    public function uniqueId(): string
    {
        return $this->packageId.':'.($this->tag ?? 'latest');
    }

    public function handle(GitManager $git, PackageBuilder $builder): void
    {
        $package = WpPackage::withoutGlobalScopes()->findOrFail($this->packageId);
        $provider = $git->provider($package->git_provider);

        $tmp = config('wp-repo.tmp_path');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }

        $src = $tmp.'/'.Str::uuid().'-src.zip';
        $out = $tmp.'/'.Str::uuid().'-out.zip';

        try {
            $release = $provider->getRelease($package->repo, $this->tag);
            $tag = $release['tag'];

            $provider->downloadZip($package->repo, $tag, $src);
            $info = $builder->build($src, $package->slug, $package->type, $out);

            // WordPress compares the version in the file header, so that is the source of truth.
            $version = $info['version'] ?: ltrim($tag, 'vV');
            if ($info['version'] && ltrim($tag, 'vV') !== $info['version']) {
                Log::warning("wp-repo: tag [{$tag}] differs from header version [{$info['version']}] for {$package->slug}");
            }

            $path = "wp-packages/{$package->slug}/{$package->slug}-{$version}.zip";
            $stream = fopen($out, 'rb');
            Storage::disk(config('wp-repo.disk'))->put($path, $stream);
            is_resource($stream) && fclose($stream);

            $package->versions()->updateOrCreate(['version' => $version], [
                'file_path' => $path,
                'checksum' => hash_file('sha256', $out),
                'changelog' => $info['changelog'] ?: $release['body'],
                'requires_wp' => $info['requires_wp'],
                'tested_wp' => $info['tested_wp'],
                'requires_php' => $info['requires_php'],
                'released_at' => $release['published_at'] ? \Illuminate\Support\Carbon::parse($release['published_at']) : now(),
                'is_stable' => ! preg_match('/(alpha|beta|rc|dev)/i', $version),
            ]);

            WpUpdateLog::record(null, $package->id, 'sync', 'ok', null, $version, "Synced {$package->repo}@{$tag}");
        } catch (Throwable $e) {
            WpUpdateLog::record(null, $package->id, 'sync', 'failed', null, $this->tag, $e->getMessage());

            throw $e;
        } finally {
            @unlink($src);
            @unlink($out);
        }
    }
}
