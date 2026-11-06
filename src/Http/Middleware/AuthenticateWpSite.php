<?php

namespace Apachish\WpRepo\Http\Middleware;

use Apachish\WpRepo\Models\WpSite;
use Closure;
use Illuminate\Http\Request;

/** Authenticates a WordPress site by X-Site-Domain + X-Site-Key. */
class AuthenticateWpSite
{
    public function handle(Request $request, Closure $next)
    {
        $domain = strtolower(trim((string) $request->header('X-Site-Domain')));
        $key = (string) $request->header('X-Site-Key');

        $site = $domain !== '' && $key !== ''
            ? WpSite::withoutGlobalScopes()->where('domain', $domain)->first()
            : null;

        if (! $site || ! $site->keyMatches($key)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $site->forceFill(['last_check_at' => now()])->saveQuietly();
        $request->attributes->set('wp_site', $site);

        return $next($request);
    }
}
