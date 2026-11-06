<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Models\WpPackageVersion;
use Apachish\WpRepo\Models\WpUpdateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadController
{
    public function __invoke(Request $request, WpPackageVersion $version)
    {
        $package = $version->package;
        abort_unless($package && $package->is_active, 404);

        $disk = Storage::disk(config('wp-repo.disk'));
        abort_unless($disk->exists($version->file_path), 404);

        WpUpdateLog::record(
            $request->integer('site') ?: null, $package->id, 'pull', 'downloaded', null, $version->version
        );

        return $disk->download($version->file_path, "{$package->slug}.zip", ['X-Checksum-Sha256' => $version->checksum]);
    }
}
