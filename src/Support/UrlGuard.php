<?php

namespace Apachish\WpRepo\Support;

use RuntimeException;

/**
 * Tenants may point a connection at their own GitLab/Gitea. Without a guard that would let a tenant make
 * this server call internal addresses (SSRF), so custom API URLs must be https and resolve to public IPs.
 */
final class UrlGuard
{
    public static function assertSafe(string $url): void
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('API URL must be an https URL.');
        }

        if (config('wp-repo.allow_private_hosts', false)) {
            return;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if (! $ips) {
            throw new RuntimeException('API host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('API host resolves to a private or reserved address.');
            }
        }
    }
}
