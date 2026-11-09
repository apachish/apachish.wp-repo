<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Models\WpPackageVersion;
use Apachish\WpRepo\Models\WpUpdateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadController
{
    public function __invoke(Request $request, WpPackageVersion $version)
    {
        $package = $version->package;

        // Distinct reasons (all 404 for the client) so a missing file is not confused with an inactive package.
        if (! $package || ! $package->is_active) {
            return response()->json(['error' => 'package_inactive'], 404);
        }

        $disk = Storage::disk(config('wp-repo.disk'));

        if (! $disk->exists($version->file_path)) {
            Log::error('wp-repo: package file is missing on the storage disk', [
                'disk' => config('wp-repo.disk'),
                'path' => $version->file_path,
            ]);

            return response()->json(['error' => 'file_missing'], 404);
        }

        WpUpdateLog::record(
            $request->integer('site') ?: null, $package->id, 'pull', 'downloaded', null, $version->version
        );

        return $disk->download($version->file_path, "{$package->slug}.zip", ['X-Checksum-Sha256' => $version->checksum]);
    }
}
