<?php

namespace WPZabbix;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Automatic_Upgrader_Skin;
use Core_Upgrader;
use Plugin_Upgrader;

class API
{
    public static function register(): void
    {
        register_rest_route('wpzabbix/v1', '/status', [
            'methods'             => [WP_REST_Server::CREATABLE, WP_REST_Server::READABLE],
            'callback'            => [self::class, 'status'],
            'permission_callback' => [self::class, 'auth'],
        ]);

        register_rest_route('wpzabbix/v1', '/update-core', [
            'methods'             => [WP_REST_Server::CREATABLE],
            'callback'            => [self::class, 'updateCore'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpzabbix/v1', '/update-plugins', [
            'methods'             => [WP_REST_Server::CREATABLE],
            'callback'            => [self::class, 'updatePlugins'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Authentication: timing-safe comparison with POST/input fallback.
     */
    public static function auth(WP_REST_Request $request): bool
    {
        if (!defined('WPZABBIX_KEY') || WPZABBIX_KEY === '') {
            return false;
        }

        $key = (string) (
            $request->get_param('wpzabbix-key') 
            ?? $_POST['wpzabbix-key'] 
            ?? ''
        );

        if ($key === '') {
            $raw = file_get_contents('php://input');
            parse_str($raw, $parsed);
            $key = (string) ($parsed['wpzabbix-key'] ?? '');
        }

        if ($key === '' || !hash_equals(WPZABBIX_KEY, $key)) {
            return false;
        }

        // Optional IP whitelist
        if (defined('WPZABBIX_ALLOWED_IPS')) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $allowed  = array_map('trim', explode(',', WPZABBIX_ALLOWED_IPS));
            $matched  = false;
            foreach ($allowed as $ip) {
                if ($clientIp === $ip || (str_contains($ip, '/') && self::ipInCidr($clientIp, $ip))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    public static function status(WP_REST_Request $request): WP_REST_Response
    {
        $collector = new Collector();
        $data = $collector->all();

        $category = $request->get_param('category');
        if ($category && isset($data[$category])) {
            $data = $data[$category];
        }

        return new WP_REST_Response($data);
    }

    public static function updateCore(WP_REST_Request $request): WP_REST_Response
    {
        // 1. Verify key directly inside callback to avoid REST timing quirks
        if (!self::auth($request)) {
            return new WP_REST_Response([
                'code'    => 'rest_forbidden',
                'message' => 'Unauthorized or invalid key.',
            ], 401);
        }

        // 2. Load WordPress core upgrade dependencies
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        wp_version_check();
        $updates = get_site_transient('update_core');

        if (empty($updates->updates) || $updates->updates[0]->response !== 'upgrade') {
            return new WP_REST_Response([
                'status'  => 'already_current',
                'message' => 'WordPress core is already up to date.'
            ], 200);
        }

        // 3. Perform the silent background upgrade
        $skin     = new Automatic_Upgrader_Skin();
        $upgrader = new Core_Upgrader($skin);
        $result   = $upgrader->upgrade($updates->updates[0]);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'status'  => 'error',
                'message' => $result->get_error_message()
            ], 500);
        }

        return new WP_REST_Response([
            'status'     => 'success',
            'updated_to' => $updates->updates[0]->current
        ], 200);
    }

    public static function updatePlugins(WP_REST_Request $request): WP_REST_Response
    {
        // 1. Verify key directly inside callback
        if (!self::auth($request)) {
            return new WP_REST_Response([
                'code'    => 'rest_forbidden',
                'message' => 'Unauthorized or invalid key.',
            ], 401);
        }

        // 2. Load WordPress plugin upgrade dependencies
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        // 3. Refresh available plugin update transients
        wp_update_plugins();
        $updates = get_site_transient('update_plugins');

        if (empty($updates->response)) {
            return new WP_REST_Response([
                'status'  => 'already_current',
                'message' => 'All plugins are already up to date.'
            ], 200);
        }

        $pluginsToUpdate = array_keys($updates->response);

        // 4. Execute silent bulk upgrade
        $skin     = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $results  = $upgrader->bulk_upgrade($pluginsToUpdate);

        $updated = [];
        $failed  = [];

        foreach ($pluginsToUpdate as $pluginFile) {
            if (isset($results[$pluginFile]) && $results[$pluginFile] !== false && !is_wp_error($results[$pluginFile])) {
                $updated[] = $pluginFile;
            } else {
                $failed[] = $pluginFile;
            }
        }

        return new WP_REST_Response([
            'status'        => empty($failed) ? 'success' : 'partial_success',
            'updated_count' => count($updated),
            'updated'       => $updated,
            'failed_count'  => count($failed),
            'failed'        => $failed,
        ], 200);
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask       = -1 << (32 - (int) $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
