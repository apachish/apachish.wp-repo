<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Jobs\SyncPackageVersion;
use Apachish\WpRepo\Models\WpGitConnection;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Services\GitManager;
use Illuminate\Http\Request;

/** POST /webhook/c/{uuid}: the uuid identifies the project's Git connection (and therefore its secret). */
class WebhookController
{
    public function __invoke(Request $request, GitManager $git, string $uuid)
    {
        $connection = WpGitConnection::withoutGlobalScopes()->where('uuid', $uuid)->first();
        abort_unless($connection, 404);

        $driver = $git->forConnection($connection);
        abort_unless($driver->verifyWebhook($request), 403, 'Invalid signature.');

        $event = $driver->parseWebhook($request);

        if (! $event || empty($event['repo']) || empty($event['tag'])) {
            return response()->json(['status' => 'ignored'], 202);
        }

        // Only this connection's own packages can be triggered by this secret.
        $packages = WpPackage::withoutGlobalScopes()
            ->where('wp_git_connection_id', $connection->id)
            ->where('repo', $event['repo'])
            ->where('is_active', true)
            ->get();

        foreach ($packages as $package) {
            SyncPackageVersion::dispatch($package->id, $event['tag']);
        }

        return response()->json(['status' => 'queued', 'packages' => $packages->count()], 202);
    }
}
