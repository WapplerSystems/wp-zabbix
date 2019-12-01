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
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                    'args'                => $this->get_collection_params(),
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
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
	public function get_items( $request ) {
		$response = [];

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


	/**
	 * Prepares a value for output based off a schema array.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed $value  Value to prepare.
	 * @param array $schema Schema to match.
	 * @return mixed The prepared value.
	 */
	protected function prepare_value( $value, $schema ) {
		/*
		 * If the value is not valid by the schema, set the value to null.
		 * Null values are specifically non-destructive, so this will not cause
		 * overwriting the current invalid value to null.
		 */
		if ( is_wp_error( rest_validate_value_from_schema( $value, $schema ) ) ) {
			return null;
		}
		return rest_sanitize_value_from_schema( $value, $schema );
	}

	/**
	 * Updates settings for the settings object.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or error object on failure.
	 */
	public function update_item( $request ) {
		$options = $this->get_registered_options();

		$params = $request->get_params();

		foreach ( $options as $name => $args ) {
			if ( ! array_key_exists( $name, $params ) ) {
				continue;
			}

			/**
			 * Filters whether to preempt a setting value update.
			 *
			 * Allows hijacking the setting update logic and overriding the built-in behavior by
			 * returning true.
			 *
			 * @since 4.7.0
			 *
			 * @param bool   $result Whether to override the default behavior for updating the
			 *                       value of a setting.
			 * @param string $name   Setting name (as shown in REST API responses).
			 * @param mixed  $value  Updated setting value.
			 * @param array  $args   Arguments passed to register_setting() for this setting.
			 */
			$updated = apply_filters( 'rest_pre_update_setting', false, $name, $request[ $name ], $args );

			if ( $updated ) {
				continue;
			}

			/*
			 * A null value for an option would have the same effect as
			 * deleting the option from the database, and relying on the
			 * default value.
			 */
			if ( is_null( $request[ $name ] ) ) {
				/*
				 * A null value is returned in the response for any option
				 * that has a non-scalar value.
				 *
				 * To protect clients from accidentally including the null
				 * values from a response object in a request, we do not allow
				 * options with values that don't pass validation to be updated to null.
				 * Without this added protection a client could mistakenly
				 * delete all options that have invalid values from the
				 * database.
				 */
				if ( is_wp_error( rest_validate_value_from_schema( get_option( $args['option_name'], false ), $args['schema'] ) ) ) {
					return new WP_Error(
						'rest_invalid_stored_value',
						/* translators: %s: Property name. */
						sprintf( __( 'The %s property has an invalid stored value, and cannot be updated to null.' ), $name ),
						array( 'status' => 500 )
					);
				}

				delete_option( $args['option_name'] );
			} else {
				update_option( $args['option_name'], $request[ $name ] );
			}
		}

		return $this->get_item( $request );
	}

	/**
	 * Retrieves all of the registered options for the Settings API.
	 *
	 * @since 4.7.0
	 *
	 * @return array Array of registered options.
	 */
	protected function get_registered_options() {
		$rest_options = array();

		foreach ( get_registered_settings() as $name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$rest_args = array();

			if ( is_array( $args['show_in_rest'] ) ) {
				$rest_args = $args['show_in_rest'];
			}

			$defaults = array(
				'name'   => ! empty( $rest_args['name'] ) ? $rest_args['name'] : $name,
				'schema' => array(),
			);

			$rest_args = array_merge( $defaults, $rest_args );

			$default_schema = array(
				'type'        => empty( $args['type'] ) ? null : $args['type'],
				'description' => empty( $args['description'] ) ? '' : $args['description'],
				'default'     => isset( $args['default'] ) ? $args['default'] : null,
			);

			$rest_args['schema']      = array_merge( $default_schema, $rest_args['schema'] );
			$rest_args['option_name'] = $name;

			// Skip over settings that don't have a defined type in the schema.
			if ( empty( $rest_args['schema']['type'] ) ) {
				continue;
			}

			/*
			 * Whitelist the supported types for settings, as we don't want invalid types
			 * to be updated with arbitrary values that we can't do decent sanitizing for.
			 */
			if ( ! in_array( $rest_args['schema']['type'], array( 'number', 'integer', 'string', 'boolean', 'array', 'object' ), true ) ) {
				continue;
			}

			$rest_args['schema'] = $this->set_additional_properties_to_false( $rest_args['schema'] );

			$rest_options[ $rest_args['name'] ] = $rest_args;
		}

		return $rest_options;
	}

	/**
	 * Retrieves the site setting schema, conforming to JSON Schema.
	 *
	 * @since 4.7.0
	 *
	 * @return array Item schema data.
	 */
    public function get_item_schema() {
        if ( $this->schema ) {
            return $this->add_additional_fields_schema( $this->schema );
        }

        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'theme',
            'type'       => 'object',
        );

        $this->schema = $schema;
        return $this->add_additional_fields_schema( $this->schema );
    }

	/**
	 * Custom sanitize callback used for all options to allow the use of 'null'.
	 *
	 * By default, the schema of settings will throw an error if a value is set to
	 * `null` as it's not a valid value for something like "type => string". We
	 * provide a wrapper sanitizer to whitelist the use of `null`.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed           $value   The value for the setting.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return mixed|WP_Error
	 */
	public function sanitize_callback( $value, $request, $param ) {
		if ( is_null( $value ) ) {
			return $value;
		}
		return rest_parse_request_arg( $value, $request, $param );
	}


}
