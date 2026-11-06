<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Services\GitManager;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookController
{
    public function __invoke(Request $request, GitManager $git, string $provider)
    {
        try {
            $driver = $git->provider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless($driver->verifyWebhook($request), 403, 'Invalid signature.');

        $event = $driver->parseWebhook($request);

        if (! $event || empty($event['repo']) || empty($event['tag'])) {
            return response()->json(['status' => 'ignored'], 202);
        }

        $packages = WpPackage::withoutGlobalScopes()
            ->where('repo', $event['repo'])
            ->where('git_provider', $driver->name())
            ->where('is_active', true)
            ->get();

        foreach ($packages as $package) {
            SyncPackageVersion::dispatch($package->id, $event['tag']);
        }

        return response()->json(['status' => 'queued', 'packages' => $packages->count()], 202);
    }
}
