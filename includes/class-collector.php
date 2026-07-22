<?php

namespace WPZabbix;

class Collector
{
    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function all(): array
    {
        return [
            'wordpress'   => $this->wordpress(),
            'php'         => $this->phpConfig(),
            'database'    => $this->database(),
            'plugins'     => $this->plugins(),
            'themes'      => $this->themes(),
            'content'     => $this->content(),
            'users'       => $this->users(),
            'sitehealth'  => $this->siteHealth(),
            'security'    => $this->security(),
            'performance' => $this->performance(),
            'cron'        => $this->cronJobs(),
            'system'      => $this->system(),
            'ssl'         => $this->ssl(),
        ];
    }

    // ─── WordPress Core ─────────────────────────────────────

    private function wordpress(): array
    {
        global $wp_version;
        require_once ABSPATH . 'wp-admin/includes/update.php';

        $coreUpdates = get_core_updates();
        $latest = '';
        $hasUpdate = 0;

        foreach ($coreUpdates as $update) {
            if ($update->response === 'upgrade') {
                $hasUpdate = 1;
                $latest = $update->current ?? $latest;
            }
        }

        return [
            'version'              => $wp_version,
            'version_id'           => (int) str_replace('.', '', str_pad(str_replace('.', '', $wp_version), 5, '0', STR_PAD_RIGHT)),
            'core_updates'         => $hasUpdate,
            'core_update_version'  => $latest,
            'locale'               => get_locale(),
            'multisite'            => is_multisite() ? 1 : 0,
            'blog_count'           => is_multisite() ? (int) get_blog_count() : 1,
            'home_url'             => home_url(),
            'site_url'             => site_url(),
            'timezone'             => wp_timezone_string(),
            'abspath'              => ABSPATH,
            'content_dir'          => WP_CONTENT_DIR,
        ];
    }

    // ─── PHP Configuration ──────────────────────────────────

    private function phpConfig(): array
    {
        return [
            'version'               => PHP_VERSION,
            'version_id'            => PHP_VERSION_ID,
            'memory_limit_bytes'    => self::parseBytes(ini_get('memory_limit')),
            'max_execution_time'    => (int) ini_get('max_execution_time'),
            'max_input_vars'        => (int) ini_get('max_input_vars'),
            'max_input_time'        => (int) ini_get('max_input_time'),
            'post_max_size'         => self::parseBytes(ini_get('post_max_size')),
            'upload_max_filesize'   => self::parseBytes(ini_get('upload_max_filesize')),
            'max_file_uploads'      => (int) ini_get('max_file_uploads'),
            'opcache_enabled'       => function_exists('opcache_get_status') && opcache_get_status() !== false ? 1 : 0,
            'curl_version'          => (function_exists('curl_version') ? curl_version()['version'] : ''),
            'sapi'                  => PHP_SAPI,
        ];
    }

    // ─── Database ───────────────────────────────────────────

    private function database(): array
    {
        $version = $this->wpdb->get_var('SELECT VERSION()');
        $size = $this->getDatabaseSize();

        return [
            'version'    => $version,
            'size_bytes' => $size,
            'table_count'=> (int) $this->wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"),
            'prefix'     => $this->wpdb->prefix,
            'engine'     => $this->wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$this->wpdb->posts}'"),
            'charset'    => defined('DB_CHARSET') ? DB_CHARSET : $this->wpdb->charset,
            'collation'  => defined('DB_COLLATE') && DB_COLLATE ? DB_COLLATE : $this->wpdb->collate,
        ];
    }

    private function getDatabaseSize(): int
    {
        $cached = wp_cache_get('wpzabbix_db_size', 'wpzabbix');
        if ($cached !== false) return (int) $cached;

        $query = sprintf(
            "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '%s'",
            esc_sql(DB_NAME)
        );
        $size = (int) $this->wpdb->get_var($query);
        wp_cache_set('wpzabbix_db_size', $size, 'wpzabbix', 300);
        return $size;
    }

    // ─── Plugins ────────────────────────────────────────────

    private function plugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('get_plugin_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $updates = get_plugin_updates();

        $total = count($allPlugins);
        $active = count($activePlugins);
        $inactive = $total - $active;
        $muPlugins = count(get_mu_plugins());
        $dropins = count(get_dropins());
        $updatesAvail = count($updates);

        $activeUpdates = 0;
        $activeUpdateNames = [];

        foreach ($updates as $key => $plugin) {
            if (is_plugin_active($key)) {
                $activeUpdates++;
                $activeUpdateNames[] = $plugin->Name;
            }
        }

        return [
            'total'              => $total,
            'active'             => $active,
            'inactive'           => $inactive,
            'mu_plugins'         => $muPlugins,
            'dropins'            => $dropins,
            'updates_available'  => $updatesAvail,
            'active_updates'     => $activeUpdates,
            'inactive_updates'   => $updatesAvail - $activeUpdates,
            'active_list'        => implode(', ', $activeUpdateNames),
        ];
    }

    // ─── Themes ─────────────────────────────────────────────

    private function themes(): array
    {
        if (!function_exists('wp_get_themes')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        if (!function_exists('get_theme_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        $allThemes = wp_get_themes();
        $currentTheme = wp_get_theme();
        $updates = get_theme_updates();

        $isBlock = $currentTheme->is_block_theme() ? 1 : 0;
        $activeUpdate = isset($updates[$currentTheme->get_stylesheet()]) ? 1 : 0;

        return [
            'total'              => count($allThemes),
            'active'             => $currentTheme->get('Name'),
            'active_version'     => $currentTheme->get('Version'),
            'active_is_block'    => $isBlock,
            'active_update'      => $activeUpdate,
            'updates_available'  => count($updates),
            'inactive_updates'   => count($updates) - $activeUpdate,
        ];
    }

    // ─── Content ────────────────────────────────────────────

    private function content(): array
    {
        $counts = wp_count_posts();
        $pageCounts = wp_count_posts('page');

        $comments = (array) $this->wpdb->get_row("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN comment_approved = '1' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN comment_approved = '0' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN comment_approved = 'spam' THEN 1 ELSE 0 END) AS spam
            FROM {$this->wpdb->comments}
        ");

        $cats = wp_count_terms('category', ['hide_empty' => false]);
        $tags = wp_count_terms('post_tag', ['hide_empty' => false]);
        $mediaCount = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->posts} WHERE post_type = 'attachment'");

        $publicCpts = count(get_post_types(['public' => true, '_builtin' => false], 'names'));

        return [
            'posts_published'    => (int) ($counts->publish ?? 0),
            'posts_total'        => (int) array_sum((array) $counts),
            'posts_draft'        => (int) ($counts->draft ?? 0),
            'posts_trash'        => (int) ($counts->trash ?? 0),
            'pages_published'    => (int) ($pageCounts->publish ?? 0),
            'pages_total'        => (int) array_sum((array) $pageCounts),
            'custom_post_types'  => $publicCpts,
            'comments_total'     => (int) ($comments['total'] ?? 0),
            'comments_approved'  => (int) ($comments['approved'] ?? 0),
            'comments_pending'   => (int) ($comments['pending'] ?? 0),
            'comments_spam'      => (int) ($comments['spam'] ?? 0),
            'categories'         => is_wp_error($cats) ? 0 : (int) $cats,
            'tags'               => is_wp_error($tags) ? 0 : (int) $tags,
            'media_count'        => $mediaCount,
        ];
    }

    // ─── Users ──────────────────────────────────────────────

    private function users(): array
    {
        $result = ['total' => 0];
        $roles = wp_roles();
        $coreRoles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

        foreach ($roles->roles as $roleSlug => $role) {
            $count = count(get_users(['role' => $roleSlug, 'fields' => 'ID']));
            $result[in_array($roleSlug, $coreRoles) ? $roleSlug : 'custom_' . $roleSlug] = $count;
            $result['total'] += $count;
        }

        $customCount = 0;
        foreach ($roles->roles as $slug => $r) {
            if (!in_array($slug, $coreRoles)) {
                $customCount++;
            }
        }
        $result['custom_roles'] = $customCount;

        return $result;
    }

    // ─── Site Health ────────────────────────────────────────

    private function siteHealth(): array
    {
        $good = $recommended = $critical = 0;
        $criticalLabels = [];

        // Use WP_Debug_Data for reliable cross-version access to test results
        if (class_exists('WP_Debug_Data')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
            $debugData = \WP_Debug_Data::debug_data();

            foreach ($debugData as $sectionKey => $section) {
                $fields = $section['fields'] ?? [];
                foreach ($fields as $fieldKey => $field) {
                    $value = $field['value'] ?? '';
                    $debug = $field['debug'] ?? '';

                    // Check for site health status indicators
                    if (str_contains($fieldKey, 'site_status_')) {
                        if (str_contains($debug, 'good') || str_contains((string) $value, 'good')) {
                            $good++;
                        } elseif (str_contains($debug, 'recommended') || str_contains($debug, 'should be')) {
                            $recommended++;
                        } elseif (str_contains($debug, 'critical') || str_contains($debug, 'error') || str_contains($debug, 'not')) {
                            $critical++;
                            $criticalLabels[] = $field['label'] ?? '';
                        }
                    }
                }
            }
        }

        // Also check the dedicated site-health endpoint data if available
        $getSiteHealth = get_option('health-check-site-status-result', []);
        if (!empty($getSiteHealth)) {
            $good = (int) ($getSiteHealth['good'] ?? $good);
            $recommended = (int) ($getSiteHealth['recommended'] ?? $recommended);
            $critical = (int) ($getSiteHealth['critical'] ?? $critical);
        }

        $total = $good + $recommended + $critical;

        return [
            'good'            => $good,
            'recommended'     => $recommended,
            'critical'        => $critical,
            'total'           => $total,
            'percentage'      => $total > 0 ? round(($good / max(1, $total)) * 100, 1) : 100.0,
            'critical_labels' => implode(', ', $criticalLabels),
        ];
    }

    // ─── Security ───────────────────────────────────────────

    private function security(): array
    {
        return [
            'debug_mode'             => (defined('WP_DEBUG') && WP_DEBUG) ? 1 : 0,
            'debug_display'          => (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? 1 : 0,
            'debug_log'              => (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 1 : 0,
            'script_debug'           => (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 1 : 0,
            'file_editing_disabled'  => (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? 1 : 0,
            'file_mods_disabled'     => (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) ? 1 : 0,
            'user_registration'      => (int) get_option('users_can_register', 0),
            'default_role'           => get_option('default_role', 'subscriber'),
            'https_admin'            => (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN) ? 1 : 0,
            'https_login'            => (defined('FORCE_SSL_LOGIN') && FORCE_SSL_LOGIN) ? 1 : 0,
            'xmlrpc_enabled'         => apply_filters('xmlrpc_enabled', true) ? 1 : 0,
            'rest_anonymous'         => 1,
            'application_passwords'  => function_exists('wp_is_application_passwords_available') && wp_is_application_passwords_available() ? 1 : 0,
        ];
    }

    // ─── Performance ────────────────────────────────────────

    private function performance(): array
    {
        $autoloaded = $this->wpdb->get_row("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(LENGTH(option_value)), 0) AS total_size
            FROM {$this->wpdb->options}
            WHERE autoload IN ('yes', 'on', 'auto')
        ");

        $transients = $this->wpdb->get_row("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN option_name LIKE '%\\_transient\\_timeout\\_%' THEN 1 ELSE 0 END) AS timeouts
            FROM {$this->wpdb->options}
            WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'
        ");

        $hasObjectCache = wp_using_ext_object_cache() ? 1 : 0;

        return [
            'autoloaded_options_size'    => (int) ($autoloaded->total_size ?? 0),
            'autoloaded_options_count'   => (int) ($autoloaded->cnt ?? 0),
            'transients_count'           => (int) ceil(((int) ($transients->total ?? 0)) / 2),
            'transients_expired'         => 0,
            'transients_timeout'         => (int) (($transients->timeouts ?? 0) / 2),
            'object_cache'               => $hasObjectCache,
            'page_cache'                 => self::detectPageCache(),
            'wp_memory_limit_bytes'      => self::parseBytes(defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M'),
            'wp_max_memory_limit_bytes'  => self::parseBytes(defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '256M'),
        ];
    }

    // ─── Cron ───────────────────────────────────────────────

    private function cronJobs(): array
    {
        $crons = get_option('cron', []);
        $total = 0;
        $dueNow = 0;
        $nextTs = PHP_INT_MAX;

        foreach ($crons as $timestamp => $hooks) {
            if (!is_numeric($timestamp)) continue;
            $ts = (int) $timestamp;
            foreach ($hooks as $hook => $events) {
                if (!is_array($events)) continue;
                $count = count($events);
                $total += $count;
                if ($ts <= time()) {
                    $dueNow += $count;
                }
                if ($ts < $nextTs) {
                    $nextTs = $ts;
                }
            }
        }

        return [
            'total_jobs'             => $total,
            'due_now'                => $dueNow,
            'next_event_seconds'     => $nextTs === PHP_INT_MAX ? -1 : max(0, $nextTs - time()),
            'wp_cron_disabled'       => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 1 : 0,
            'last_run_seconds_ago'   => (int) (time() - get_option('health-check-cron-last-run', time())),
        ];
    }

    // ─── System ─────────────────────────────────────────────

    private function system(): array
    {
        $webServer = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
        $diskFree = disk_free_space(WP_CONTENT_DIR);
        $diskTotal = disk_total_space(WP_CONTENT_DIR);
        $uploadsSize = $this->getDirSize(wp_upload_dir()['basedir']);

        return [
            'web_server'               => $webServer,
            'php_sapi'                 => PHP_SAPI,
            'server_os'                => php_uname('s') . ' ' . php_uname('r'),
            'server_arch'              => php_uname('m'),
            'disk_free_bytes'          => $diskFree !== false ? $diskFree : 0,
            'disk_total_bytes'         => $diskTotal !== false ? $diskTotal : 0,
            'uploads_size_bytes'       => $uploadsSize,
            'wp_content_size_bytes'    => $this->getDirSize(WP_CONTENT_DIR),
        ];
    }

    // ─── SSL ────────────────────────────────────────────────

    private function ssl(): array
    {
        // Use SITEURL constant (from wp-config.php) not home_url() (from DB)
        // to prevent SSRF if the home_url option is tampered with.
        $siteUrl = defined('WP_SITEURL') && WP_SITEURL ? WP_SITEURL : site_url();
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!$host) return ['expiry_days' => -1, 'issuer' => '', 'subject' => ''];

        // Only check HTTPS if the site uses it (prevents connections to arbitrary ports)
        $scheme = parse_url($siteUrl, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            return ['expiry_days' => -1, 'issuer' => '', 'subject' => ''];
        }

        $cached = get_transient('wpzabbix_ssl_cert');
        if ($cached !== false) return $cached;

        $stream = @stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ]]);
        $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $stream);

        if (!$client) {
            $result = ['expiry_days' => -1, 'issuer' => '', 'subject' => ''];
            set_transient('wpzabbix_ssl_cert', $result, 3600); // cache failures too
            return $result;
        }

        $cert = stream_context_get_params($client);
        $certData = openssl_x509_parse($cert['options']['ssl']['peer_certificate'] ?? '');

        if (!$certData) {
            $result = ['expiry_days' => -1, 'issuer' => '', 'subject' => ''];
            set_transient('wpzabbix_ssl_cert', $result, 3600);
            return $result;
        }

        $expiry = $certData['validTo_time_t'] ?? 0;

        $result = [
            'expiry_days' => $expiry > 0 ? (int) ceil(($expiry - time()) / 86400) : -1,
            'issuer'      => $certData['issuer']['O'] ?? '',
            'subject'     => $certData['subject']['CN'] ?? '',
        ];

        set_transient('wpzabbix_ssl_cert', $result, 3600);

        return $result;
    }

    // ─── Helpers ────────────────────────────────────────────

    private static function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $val,
        };
    }

    private function getDirSize(string $dir): int
    {
        if (!is_dir($dir)) return 0;

        $cacheKey = 'wpzabbix_dirsize_' . md5($dir);
        $cached = wp_cache_get($cacheKey, 'wpzabbix');
        if ($cached !== false) return (int) $cached;

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        wp_cache_set($cacheKey, $size, 'wpzabbix', 300);
        return $size;
    }

    private static function detectPageCache(): int
    {
        $indicators = [
            'WP_CACHE',           // WP Super Cache, W3 Total Cache
            'WP_ROCKET_VERSION',  // WP Rocket
            'LSCWP_V',            // LiteSpeed Cache
            'W3TC',               // W3 Total Cache
            'WPFC_WP_CONTENT_DIR',// WP Fastest Cache
            'SG_CACHEPRESS',      // SiteGround Optimizer
        ];
        foreach ($indicators as $constant) {
            if (defined($constant) && constant($constant)) {
                return 1;
            }
        }
        return 0;
    }
}
