<?php

namespace WPZabbix;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class API
{
    public static function register(): void
    {
        register_rest_route('wpzabbix/v1', '/status', [
            'methods'             => [WP_REST_Server::CREATABLE, WP_REST_Server::READABLE],
            'callback'            => [self::class, 'status'],
            'permission_callback' => [self::class, 'auth'],
        ]);
    }

    /**
     * Authentication: POST-only key with timing-safe comparison,
     * optional IP whitelist, and rate limiting. Fails closed.
     */
    public static function auth(WP_REST_Request $request): bool
    {
        if (!defined('WPZABBIX_KEY') || WPZABBIX_KEY === '') {
            return false;
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Rate limiting: max 10 failed attempts per IP per 10 minutes
        $failKey = 'wpzabbix_fail_' . md5($clientIp);
        $failCount = (int) get_transient($failKey);
        if ($failCount >= 10) {
            return false;
        }

        $key = (string) ($request->get_param('wpzabbix-key') ?? '');

        if ($key === '' || !hash_equals(WPZABBIX_KEY, $key)) {
            set_transient($failKey, $failCount + 1, 600);
            return false;
        }

        // Clear fail counter on success
        delete_transient($failKey);

        // Optional IP whitelist
        if (defined('WPZABBIX_ALLOWED_IPS')) {
            $allowed = array_map('trim', explode(',', WPZABBIX_ALLOWED_IPS));
            $matched = false;
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

        // Allow filtering for partial responses (e.g. ?category=plugins,security)
        $category = $request->get_param('category');
        if ($category && isset($data[$category])) {
            $data = $data[$category];
        }

        return new WP_REST_Response($data);
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
