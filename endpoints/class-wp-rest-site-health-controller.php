<?php
/**
 * REST API: WP_ZABBIX_SiteHealth_Controller class
 *
 * @package wp-zabbix
 */

/**
 *
 *
 */
class WP_ZABBIX_SiteHealth_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 */
	public function __construct() {
		$this->namespace = 'wpzabbix/v1';
		$this->rest_base = 'sitehealth';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 4.7.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WpZabbix::METHODS,
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                    'args'                => [],
                ],
                'schema' => [$this, 'get_item_schema'],
            ]
        );

	}

	/**
	 * Checks if a given request has access to read and manage settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, otherwise false.
	 */
	public function get_items_permissions_check( $request ) {
		return WpZabbix::permission_check();
	}

	/**
	 * Retrieves the settings.
	 *
	 * @since 4.7.0
	 *
     * @param WP_REST_Request $request Full details about the request.
     * @return array
     */
	public function get_items( $request ) {
		$response = [];
		
	if(!function_exists('got_url_rewrite')) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

        if ( !class_exists( 'WP_Debug_Data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }
        require_once ABSPATH . 'wp-admin/includes/update.php';

        WP_Debug_Data::check_for_updates();

        try {
            $response = WP_Debug_Data::debug_data();
        } catch (\ImagickException $ex) {

        }
		return $response;
	}



}
