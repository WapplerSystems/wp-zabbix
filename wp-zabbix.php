<?php
/**
 * Plugin Name:       WordPress Zabbix Monitoring Client
 * Plugin URI:        https://wappler.systems/wordpress/plugins/wp-zabbix/
 * Description:       This is a REST API client for the Zabbix Monitoring system.
 * Version:           0.0.2
 * Requires at least: 4.4
 * Requires PHP:      7.0
 * Author:            Sven Wappler
 * Author URI:        https://wappler.systems/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-zabbix
 * Domain Path:       /languages
 */


define( 'WPZABBIX_VERSION', '0.0.1' );
define( 'WPZABBIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );


require_once WPZABBIX_PLUGIN_DIR . 'class.wpzabbix.php';
require_once WPZABBIX_PLUGIN_DIR . 'endpoints' . DIRECTORY_SEPARATOR . 'class-wp-rest-site-health-controller.php';
require_once WPZABBIX_PLUGIN_DIR . 'endpoints' . DIRECTORY_SEPARATOR . 'class-wp-rest-updates-controller.php';


function run_zabbix_client()
{
    $plugin = new WpZabbix();
    $plugin->init();

}
run_zabbix_client();