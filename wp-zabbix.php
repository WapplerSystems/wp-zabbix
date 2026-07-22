<?php
/**
 * Plugin Name:  WP-Zabbix Monitoring Client
 * Plugin URI:   https://github.com/WapplerSystems/wp-zabbix
 * Description:  REST API client for Zabbix monitoring. Exposes 80+ WordPress health, performance, and security metrics via a single authenticated endpoint.
 * Version:      1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:       Wappler Systems
 * Author URI:   https://wappler.systems
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  wp-zabbix
 */

if (!defined('ABSPATH')) exit;

define('WPZABBIX_VERSION', '1.0.0');
define('WPZABBIX_DIR', plugin_dir_path(__FILE__));

require_once WPZABBIX_DIR . 'includes/class-plugin.php';
require_once WPZABBIX_DIR . 'includes/class-collector.php';
require_once WPZABBIX_DIR . 'includes/class-api.php';

WPZabbix\Plugin::boot();
