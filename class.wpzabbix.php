<?php

class WpZabbix
{


    const METHODS = 'GET, POST';


    public function __construct()
    {

    }

    /**
     * Initializes WordPress hooks
     */
    public function init()
    {

        add_action('rest_api_init', [$this, 'add_endpoints']);

    }


    /**
     * Add the endpoints to the API
     */
    public function add_endpoints()
    {

        $health_controller = new WP_ZABBIX_SiteHealth_Controller();
        $health_controller->register_routes();

        $updates_controller = new WP_ZABBIX_Updates_Controller();
        $updates_controller->register_routes();


    }


    public static function permission_check()
    {

        $secret_key = defined('WPZABBIX_KEY') ? WPZABBIX_KEY : false;

        if (!$secret_key) {
            return new WP_Error(
                'wpzabbix_bad_config',
                __('wp-zabbix is not configurated properly, please contact the admin', 'wp-zabbix'),
                [
                    'status' => 500,
                ]
            );
        }

        if (!isset($_REQUEST['wpzabbix-key'])) {
            return new WP_Error(
                'wpzabbix_bad_parameter',
                __('no key set', 'wp-zabbix'),
                [
                    'status' => 500,
                ]
            );
        }

        return ($_REQUEST['wpzabbix-key'] === $secret_key);
    }


}