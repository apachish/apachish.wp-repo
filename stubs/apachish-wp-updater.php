<?php
/**
 * Plugin Name: Apachish Package Updater
 * Description: Connects this site to the Apachish update server so private plugins/themes update from the normal WordPress dashboard.
 * Version: 1.0.0
 * Requires PHP: 7.4
 *
 * Install as a must-use plugin: wp-content/mu-plugins/apachish-wp-updater.php
 *
 * Required constants (wp-config.php):
 *   define('APACHISH_SITE_KEY', 'wpr_...');                       // key issued by the panel / POST sites/register
 *   define('APACHISH_PACKAGES', ['my-plugin' => 'plugin', 'my-theme' => 'theme']);
 * Optional:
 *   define('APACHISH_API', 'https://apachish.ir/api/wp-updates');
 *   define('APACHISH_SITE_DOMAIN', 'example.com');                // defaults to the host of home_url()
 */

defined('ABSPATH') || exit;

if (!defined('APACHISH_API')) {
    define('APACHISH_API', 'https://apachish.ir/api/wp-updates');
}

final class Apachish_Updater
{
    const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    public static function boot()
    {
        if (!defined('APACHISH_SITE_KEY') || !defined('APACHISH_PACKAGES') || !is_array(APACHISH_PACKAGES)) {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'filter_plugins']);
        add_filter('pre_set_site_transient_update_themes', [__CLASS__, 'filter_themes']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        add_filter('upgrader_pre_download', [__CLASS__, 'verify_download'], 10, 4);
    }

    private static function domain()
    {
        if (defined('APACHISH_SITE_DOMAIN')) {
            return strtolower(APACHISH_SITE_DOMAIN);
        }
        return strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    /** Ask the server about one package (cached). Returns array|null. */
    private static function fetch($slug, $type, $installed, $force = false)
    {
        $cache_key = 'apachish_upd_' . md5($type . $slug . $installed);
        $cached = $force ? false : get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        global $wp_version;
        $url = add_query_arg([
            'slug'    => $slug,
            'type'    => $type,
            'version' => $installed,
            'wp'      => $wp_version,
            'php'     => PHP_VERSION,
        ], trailingslashit(APACHISH_API) . 'check');

        $res = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept'        => 'application/json',
                'X-Site-Key'    => APACHISH_SITE_KEY,
                'X-Site-Domain' => self::domain(),
            ],
        ]);

        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            // Back off briefly so a down server does not slow every admin page load.
            set_transient($cache_key, ['_error' => true], 15 * MINUTE_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($data) || empty($data['version'])) {
            return null;
        }

        set_transient($cache_key, $data, self::CACHE_TTL);
        return $data;
    }

    /**
     * The signed URL from the server lives only a few minutes, but WordPress keeps the "package" URL in its
     * update transient for hours. So WordPress gets this stable pseudo-URL, and the real signed URL is
     * requested fresh in verify_download() right when the update is actually installed.
     */
    private static function package_url($slug, $type, $installed)
    {
        return add_query_arg([
            'apachish_pkg'  => $slug,
            'apachish_type' => $type,
            'apachish_from' => $installed,
        ], trailingslashit(APACHISH_API) . 'package');
    }

    private static function usable($data)
    {
        return is_array($data) && empty($data['_error']);
    }

    public static function filter_plugins($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        foreach (APACHISH_PACKAGES as $slug => $type) {
            if ($type !== 'plugin') {
                continue;
            }
            foreach ($transient->checked as $file => $installed) {
                if (strtok($file, '/') !== $slug) {
                    continue;
                }
                $data = self::fetch($slug, 'plugin', $installed);
                if (self::usable($data) && !empty($data['update_available']) && !empty($data['download_url'])) {
                    $transient->response[$file] = (object) [
                        'id'           => 'apachish/' . $slug,
                        'slug'         => $slug,
                        'plugin'       => $file,
                        'new_version'  => $data['version'],
                        'url'          => '',
                        'package'      => self::package_url($slug, 'plugin', $installed),
                        'tested'       => isset($data['tested']) ? $data['tested'] : '',
                        'requires_php' => isset($data['requires_php']) ? $data['requires_php'] : '',
                    ];
                    unset($transient->no_update[$file]);
                }
            }
        }
        return $transient;
    }

    public static function filter_themes($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        foreach (APACHISH_PACKAGES as $slug => $type) {
            if ($type !== 'theme' || !isset($transient->checked[$slug])) {
                continue;
            }
            $data = self::fetch($slug, 'theme', $transient->checked[$slug]);
            if (self::usable($data) && !empty($data['update_available']) && !empty($data['download_url'])) {
                $transient->response[$slug] = [
                    'theme'        => $slug,
                    'new_version'  => $data['version'],
                    'url'          => '',
                    'package'      => self::package_url($slug, 'theme', $transient->checked[$slug]),
                    'requires'     => isset($data['requires']) ? $data['requires'] : '',
                    'requires_php' => isset($data['requires_php']) ? $data['requires_php'] : '',
                ];
            }
        }
        return $transient;
    }

    /** Fills the "View details" modal with the changelog. */
    public static function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug)
            || !isset(APACHISH_PACKAGES[$args->slug]) || APACHISH_PACKAGES[$args->slug] !== 'plugin') {
            return $result;
        }

        $installed = '0';
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (get_plugins() as $file => $meta) {
            if (strtok($file, '/') === $args->slug) {
                $installed = $meta['Version'];
            }
        }

        $data = self::fetch($args->slug, 'plugin', $installed);
        if (!self::usable($data)) {
            return $result;
        }

        return (object) [
            'name'          => $data['name'],
            'slug'          => $data['slug'],
            'version'       => $data['version'],
            'requires'      => isset($data['requires']) ? $data['requires'] : '',
            'tested'        => isset($data['tested']) ? $data['tested'] : '',
            'requires_php'  => isset($data['requires_php']) ? $data['requires_php'] : '',
            'last_updated'  => isset($data['last_updated']) ? $data['last_updated'] : '',
            'sections'      => isset($data['sections']) ? $data['sections'] : [],
            'download_link' => self::package_url($args->slug, 'plugin', $installed),
        ];
    }

    /**
     * Resolve the pseudo-URL into a fresh signed URL, download it ourselves and verify the sha256
     * before WordPress unpacks anything.
     */
    public static function verify_download($reply, $package, $upgrader, $hook_extra)
    {
        if (!is_string($package) || strpos($package, APACHISH_API) !== 0) {
            return $reply;
        }

        $query = (string) wp_parse_url($package, PHP_URL_QUERY);
        parse_str($query, $q);
        $slug = isset($q['apachish_pkg']) ? sanitize_key($q['apachish_pkg']) : '';
        $type = isset($q['apachish_type']) && $q['apachish_type'] === 'theme' ? 'theme' : 'plugin';

        if ($slug === '' || !isset(APACHISH_PACKAGES[$slug])) {
            return new WP_Error('apachish_package', 'Unknown package.');
        }

        $from = isset($q['apachish_from']) ? (string) $q['apachish_from'] : '0';
        $data = self::fetch($slug, $type, $from, true);

        if (!self::usable($data) || empty($data['download_url'])) {
            return new WP_Error('apachish_no_update', 'The update server did not offer a download for this package.');
        }

        $tmp = download_url($data['download_url'], 300);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        if (!empty($data['checksum']) && !hash_equals($data['checksum'], hash_file('sha256', $tmp))) {
            @unlink($tmp);
            return new WP_Error('apachish_checksum', 'Checksum verification failed for the downloaded package.');
        }

        return $tmp;
    }
}

Apachish_Updater::boot();
