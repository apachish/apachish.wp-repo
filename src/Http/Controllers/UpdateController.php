<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Contracts\EntitlementChecker;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpPackageVersion;
use Apachish\WpRepo\Models\WpSite;
use Apachish\WpRepo\Models\WpSitePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class UpdateController
{
    public function check(Request $request, EntitlementChecker $entitlement)
    {
        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'max:100'],
            'type' => ['nullable', 'in:plugin,theme'],
            'version' => ['nullable', 'string', 'max:64'],
            'wp' => ['nullable', 'string', 'max:20'],
            'php' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var WpSite $site */
        $site = $request->attributes->get('wp_site');

        $package = WpPackage::withoutGlobalScopes()
            ->where('slug', $data['slug'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $site->project_id))
            ->orderByRaw('project_id is null') // project-specific package wins over a shared one
            ->first();

        if (! $package || (isset($data['type']) && $data['type'] !== $package->type)) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if ($package->is_paid && ! $entitlement->allows($site, $package)) {
            return response()->json(['error' => 'license_required'], 403);
        }

        $site->forceFill(array_filter(['wp_version' => $data['wp'] ?? null, 'php_version' => $data['php'] ?? null]))->saveQuietly();

        $record = WpSitePackage::firstOrNew(['wp_site_id' => $site->id, 'wp_package_id' => $package->id]);
        if (isset($data['version'])) {
            $record->installed_version = $data['version'];
        }
        $record->save();
        $record->refresh(); // load DB defaults (auto_update, status) for a freshly created row

        /** @var WpPackageVersion|null $target */
        $target = $record->target_version
            ? $package->versions()->where('version', $record->target_version)->first()
            : $package->latestStable();

        if (! $target) {
            return response()->json(['error' => 'no_release'], 404);
        }

        $installed = $record->installed_version ?: ($data['version'] ?? '0');
        $available = $record->auto_update || $record->target_version
            ? version_compare($target->version, $installed, '>')
            : false;

        return response()->json([
            'slug' => $package->slug,
            'type' => $package->type,
            'name' => $package->name,
            'version' => $target->version,
            'update_available' => $available,
            'download_url' => $available
                ? URL::temporarySignedRoute('wp-repo.download', now()->addMinutes((int) config('wp-repo.download_ttl', 15)),
                    ['version' => $target->id, 'site' => $site->id])
                : null,
            'checksum' => $target->checksum,
            'requires' => $target->requires_wp,
            'tested' => $target->tested_wp,
            'requires_php' => $target->requires_php,
            'last_updated' => optional($target->released_at)->toDateTimeString(),
            'sections' => ['changelog' => nl2br(e((string) $target->changelog))],
        ]);
    }
}
