<?php

namespace Apachish\WpRepo\Http\Controllers;

use Apachish\WpRepo\Models\WpSite;
use Illuminate\Http\Request;

class SiteRegisterController
{
    public function __invoke(Request $request)
    {
        $token = config('wp-repo.registration_token');
        abort_unless($token, 404);
        abort_unless(hash_equals($token, (string) $request->header('X-Registration-Token')), 403);

        $data = $request->validate([
            'domain' => ['required', 'regex:/^(?=.{4,253}$)([a-z0-9\p{L}]([a-z0-9\p{L}-]{0,61}[a-z0-9\p{L}])?\.)+[a-z\p{L}]{2,}$/iu'],
            'wp_path' => ['nullable', 'string', 'max:500'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $domain = strtolower($data['domain']);

        if (WpSite::withoutGlobalScopes()->where('domain', $domain)->exists()) {
            return response()->json(['error' => 'already_registered'], 409);
        }

        $site = new WpSite(['domain' => $domain, 'wp_path' => $data['wp_path'] ?? null]);
        $site->project_id = $data['project_id'] ?? null;
        $plain = $site->issueKey();
        $site->saveQuietly(); // explicit project_id given here; skip scope-based auto-fill

        return response()->json(['domain' => $site->domain, 'api_key' => $plain], 201);
    }
}
