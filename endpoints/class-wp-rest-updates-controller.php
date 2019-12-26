<?php
/**
 * REST API: WP_ZABBIX_Updates_Controller class
 *
 * @package wp-zabbix
 */

/**
 *
 *
 */
class WP_ZABBIX_Updates_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 */
	public function __construct() {
		$this->namespace = 'wpzabbix/v1';
		$this->rest_base = 'updates';
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

        require_once( ABSPATH . 'wp-admin/includes/update.php' );
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $plugins = get_plugin_updates();

        $active = 0;
        $inactive = 0;

        foreach ($plugins as $key => $plugin) {
            if (is_plugin_active($key)) {
                $active++;
            } else {
                $inactive++;
            }
        }

        $response['plugins'] = [
            'total' => count($plugins),
            'active' => $active,
            'inactive' => $inactive,
        ];


        require_once( ABSPATH . 'wp-admin/includes/theme.php' );
        $themes = get_theme_updates();

        $currentTheme = wp_get_theme();
        $active = 0;
        $inactive = 0;

        /** @var WP_Theme $theme */
        foreach ($themes as $theme) {
            if ($theme->get('Name') === $currentTheme->get('Name')) {
                $active++;
            } else {
                $inactive++;
            }
        }

        $response['themes'] = [
            'total' => count($themes),
            'active' => $active,
            'inactive' => $inactive,
        ];

		return $response;
	}


}
