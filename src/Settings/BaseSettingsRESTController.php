<?php

namespace WeDevs\WPKit\Settings;

/**
 * Abstract REST controller for plugin settings.
 *
 * Provides generic schema-driven settings management compatible
 * with the @wedevs/plugin-ui <Settings> component.
 *
 * Subclasses must implement get_validation_messages, get_permission_error_message() and get_settings_schema() and pass
 * $namespace, $rest_base, and $option_prefix to the constructor.
 *
 * Endpoints:
 *  GET  /{namespace}/{rest_base} — returns schema + nested values
 *  POST /{namespace}/{rest_base} — saves nested values to wp_options
 */
abstract class BaseSettingsRESTController extends \WP_REST_Controller {

	/**
	 * WordPress options key prefix (e.g. "wpkit_tasks").
	 *
	 * @var string
	 */
	protected $option_prefix;

	/**
	 * Constructor.
	 *
	 * @param string $namespace     REST API namespace (e.g. "myplugin/v1").
	 * @param string $rest_base     REST route base (e.g. "settings").
	 * @param string $option_prefix WordPress options key prefix.
	 */
	public function __construct( string $namespace, string $rest_base, string $option_prefix ) {
		$this->namespace     = $namespace;
		$this->rest_base     = $rest_base;
		$this->option_prefix = $option_prefix;
	}

	/**
	 * Return the settings schema array.
	 *
	 * @return array[] Flat array of SettingsElement objects.
	 */
	abstract protected function get_settings_schema(): array;

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission check for GET — manage_options required.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$capability = apply_filters( "{$this->option_prefix}_settings_capability", 'manage_options', 'read', $request );

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'rest_forbidden',
				$this->get_permission_error_message( 'read' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Permission check for POST — manage_options required.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return true|\WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$capability = apply_filters( "{$this->option_prefix}_settings_capability", 'manage_options', 'write', $request );

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'rest_forbidden',
				$this->get_permission_error_message( 'write' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Get the permission error message for a given context.
	 *
	 * IMPORTANT: Subclasses MUST override this method to return properly
	 * translated strings using the plugin's own text domain. The base
	 * implementation returns untranslated English strings as fallback only.
	 *
	 * Example:
	 *
	 *     protected function get_permission_error_message( string $context ): string {
	 *         if ( 'write' === $context ) {
	 *             return __( 'You do not have permission to update settings.', 'your-text-domain' );
	 *         }
	 *         return __( 'You do not have permission to view settings.', 'your-text-domain' );
	 *     }
	 *
	 * @param string $context 'read' or 'write'.
	 *
	 * @return string
	 */
	protected function get_permission_error_message( string $context ): string {
		if ( 'write' === $context ) {
			return 'You do not have permission to update settings.';
		}

		return 'You do not have permission to view settings.';
	}

	/**
	 * GET handler — return schema and current nested values.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$schema = $this->get_settings_schema();

		/**
		 * Filter the settings schema before loading values.
		 *
		 * @param array            $schema  Settings schema elements.
		 * @param \WP_REST_Request $request Request object.
		 */
		$schema = apply_filters( "{$this->option_prefix}_settings_schema", $schema, $request );

		$values = $this->load_values( $schema );

		// Set default prop on each field from stored nested values.
		foreach ( $schema as &$element ) {
			if ( 'field' !== $element['type'] ) {
				continue;
			}

			$path  = $this->get_field_path( $element );
			$value = $this->get_nested_value( $values, $path );

			if ( null !== $value ) {
				$element['default'] = $value;
			}
		}
		unset( $element );

		$response_data = [
			'schema' => $schema,
			'values' => $values,
		];

		/**
		 * Filter the GET settings response data.
		 *
		 * @param array            $response_data Response containing schema and values.
		 * @param \WP_REST_Request $request       Request object.
		 */
		$response_data = apply_filters( "{$this->option_prefix}_settings_get_response", $response_data, $request );

		return new \WP_REST_Response( $response_data );
	}

	/**
	 * POST handler — save nested values.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function create_item( $request ) {
		$scope_id = sanitize_key( $request->get_param( 'scopeId' ) ?? '' );
		$values   = $request->get_param( 'values' );

		if ( ! is_array( $values ) || empty( $scope_id ) ) {
			return new \WP_REST_Response(
				[ 'errors' => [ 'values' => 'Invalid values format.' ] ],
				400
			);
		}

		$schema   = $this->get_settings_schema();
		$page_ids = $this->get_page_ids( $schema );

		if ( ! in_array( $scope_id, $page_ids, true ) ) {
			return new \WP_REST_Response(
				[ 'errors' => [ 'scopeId' => 'Invalid scope ID.' ] ],
				400
			);
		}

		$fields    = $this->get_fields_for_page( $schema, $scope_id );
		$errors    = [];
		$sanitized = [];

		foreach ( $fields as $field ) {
			$path  = $this->get_field_path( $field );
			$value = $this->get_nested_value( $values, $path );

			if ( null === $value ) {
				continue;
			}

			$error = $this->validate_field( $field, $value );

			if ( $error ) {
				$errors[ $field['id'] ] = $error;
				continue;
			}

			$clean = $this->sanitize_field( $field, $value );

			/**
			 * Filter a single field's sanitized value.
			 *
			 * @param mixed  $clean Sanitized value.
			 * @param array  $field Field definition.
			 * @param mixed  $value Original raw value.
			 */
			$clean = apply_filters( "{$this->option_prefix}_settings_sanitize_field", $clean, $field, $value );

			if ( null === $clean ) {
				continue;
			}

			$this->set_nested_value( $sanitized, $path, $clean );
		}

		/**
		 * Filter validation errors before returning.
		 *
		 * @param array  $errors   Validation errors keyed by field ID.
		 * @param array  $values   Submitted values.
		 * @param string $scope_id Page/scope ID being saved.
		 */
		$errors = apply_filters( "{$this->option_prefix}_settings_validation_errors", $errors, $values, $scope_id );

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [ 'errors' => $errors ], 400 );
		}

		/**
		 * Filter sanitized values before saving to the database.
		 *
		 * @param array  $sanitized Sanitized values to be saved.
		 * @param array  $values    Original submitted values.
		 * @param string $scope_id  Page/scope ID being saved.
		 */
		$sanitized = apply_filters( "{$this->option_prefix}_settings_before_save", $sanitized, $values, $scope_id );

		$option_key      = $this->option_prefix . '_' . $scope_id;
		$existing_values = get_option( $option_key, [] );

		if ( ! is_array( $existing_values ) ) {
			$existing_values = [];
		}

		$merged = $this->array_merge_deep( $existing_values, $sanitized );
		update_option( $option_key, $merged );

		/**
		 * Fires after settings have been saved.
		 *
		 * @param array  $merged    Final merged values saved to the database.
		 * @param array  $sanitized Sanitized values that were applied.
		 * @param string $scope_id  Page/scope ID that was saved.
		 */
		do_action( "{$this->option_prefix}_settings_after_save", $merged, $sanitized, $scope_id );

		return new \WP_REST_Response(
			[
				'success' => true,
				'values'  => $merged,
			]
		);
	}

	/**
	 * Validate a field value.
	 *
	 * Override in subclass for plugin-specific validation.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Submitted value.
	 *
	 * @return string|null Error message or null if valid.
	 */
	protected function validate_field( array $field, $value ): ?string {
		$variant  = $field['variant'] ?? 'text';
		$messages = $this->get_validation_messages();
		$error    = null;

		switch ( $variant ) {
			case 'number':
				if ( ! is_numeric( $value ) ) {
					$error = $messages['number'];
				}
				break;

			case 'switch':
				if ( ! in_array( $value, [ 'on', 'off' ], true ) ) {
					$error = $messages['switch'];
				}
				break;

			case 'select':
			case 'radio_capsule':
			case 'customize_radio':
				$options = array_column( $field['options'] ?? [], 'value' );
				if ( ! empty( $options ) && ! in_array( $value, $options, true ) ) {
					$error = $messages['invalid_option'];
				}
				break;

			case 'multicheck':
				if ( ! is_array( $value ) ) {
					$error = $messages['must_be_array'];
					break;
				}
				$options = array_column( $field['options'] ?? [], 'value' );
				if ( ! empty( $options ) ) {
					$invalid = array_diff( $value, $options );
					if ( ! empty( $invalid ) ) {
						$error = $messages['invalid_options'];
					}
				}
				break;

			case 'color_picker':
				if ( ! is_string( $value ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
					$error = $messages['color_picker'];
				}
				break;

			case 'combine_input':
				if ( ! is_array( $value ) ) {
					$error = $messages['must_be_object'];
				}
				break;
		}

		/**
		 * Filter the validation error for a single field.
		 *
		 * @param string|null $error Validation error message, or null if valid.
		 * @param array       $field Field definition.
		 * @param mixed       $value Submitted value.
		 */
		return apply_filters( "{$this->option_prefix}_settings_validate_field", $error, $field, $value );
	}

	/**
	 * Get validation error messages.
	 *
	 * IMPORTANT: Subclasses MUST override this method to return properly
	 * translated strings using the plugin's own text domain. The base
	 * implementation returns untranslated English strings as fallback only.
	 *
	 * Example:
	 *
	 *     protected function get_validation_messages(): array {
	 *         return [
	 *             'number'          => __( 'Must be a numeric value.', 'your-text-domain' ),
	 *             'switch'          => __( 'Must be "on" or "off".', 'your-text-domain' ),
	 *             'invalid_option'  => __( 'Invalid option selected.', 'your-text-domain' ),
	 *             'must_be_array'   => __( 'Must be an array.', 'your-text-domain' ),
	 *             'invalid_options' => __( 'Contains invalid options.', 'your-text-domain' ),
	 *             'color_picker'    => __( 'Must be a valid hex color (e.g. #ff0000).', 'your-text-domain' ),
	 *             'must_be_object'  => __( 'Must be an object.', 'your-text-domain' ),
	 *         ];
	 *     }
	 *
	 * @return array<string, string> Keyed error messages.
	 */
	protected function get_validation_messages(): array {
		$messages = [
			'number'          => 'Must be a numeric value.',
			'switch'          => 'Must be "on" or "off".',
			'invalid_option'  => 'Invalid option selected.',
			'must_be_array'   => 'Must be an array.',
			'invalid_options' => 'Contains invalid options.',
			'color_picker'    => 'Must be a valid hex color (e.g. #ff0000).',
			'must_be_object'  => 'Must be an object.',
		];

		/**
		 * Filter validation error messages.
		 *
		 * @param array<string, string> $messages Keyed error messages.
		 */
		return apply_filters( "{$this->option_prefix}_settings_validation_messages", $messages );
	}

	/**
	 * Sanitize a field value based on variant.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 *
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_field( array $field, $value ) {
		$variant = $field['variant'] ?? 'text';

		switch ( $variant ) {
			case 'number':
				return is_float( $value + 0 ) ? floatval( $value ) : intval( $value );

			case 'switch':
				return in_array( $value, [ 'on', 'off' ], true ) ? $value : 'off';

			case 'select':
			case 'radio_capsule':
			case 'customize_radio':
				return sanitize_text_field( $value );

			case 'multicheck':
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_map( 'sanitize_text_field', $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'color_picker':
				$hex = sanitize_hex_color( $value );
				return $hex ? $hex : '';

			case 'combine_input':
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_map( 'sanitize_text_field', $value );

			case 'html':
			case 'base_field_label':
				return null;

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Load current nested values from wp_options, with defaults filled in.
	 *
	 * @param array $schema Schema array.
	 *
	 * @return array Nested values.
	 */
	protected function load_values( array $schema ): array {
		$page_ids = $this->get_page_ids( $schema );
		$fields   = $this->get_fields( $schema );
		$values   = [];

		foreach ( $page_ids as $page_id ) {
			$option_key    = $this->option_prefix . '_' . $page_id;
			$stored_values = get_option( $option_key, [] );

			if ( is_array( $stored_values ) ) {
				$values = $this->array_merge_deep( $values, $stored_values );
			}
		}

		foreach ( $fields as $field ) {
			$path    = $this->get_field_path( $field );
			$current = $this->get_nested_value( $values, $path );

			if ( null === $current ) {
				$this->set_nested_value( $values, $path, $field['default'] ?? '' );
			}
		}

		/**
		 * Filter loaded settings values (with defaults applied).
		 *
		 * @param array $values Nested settings values.
		 * @param array $schema Settings schema.
		 */
		return apply_filters( "{$this->option_prefix}_settings_loaded_values", $values, $schema );
	}

	/**
	 * Build the nested path array for a field element.
	 *
	 * @param array $element Field element.
	 *
	 * @return string[]
	 */
	protected function get_field_path( array $element ): array {
		$parts       = [];
		$parent_keys = [ 'subpage_id', 'tab_id', 'section_id', 'subsection_id', 'field_group_id' ];

		foreach ( $parent_keys as $pk ) {
			if ( ! empty( $element[ $pk ] ) ) {
				$parts[] = $element[ $pk ];
			}
		}

		$parts[] = $element['id'];

		return $parts;
	}

	/**
	 * Get a value from a nested array by path.
	 *
	 * @param array    $data Nested array.
	 * @param string[] $path Path parts.
	 *
	 * @return mixed|null
	 */
	protected function get_nested_value( array $data, array $path ) {
		$cursor = $data;

		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return null;
			}
			$cursor = $cursor[ $key ];
		}

		return $cursor;
	}

	/**
	 * Set a value in a nested array by path.
	 *
	 * @param array    $data  Nested array (by reference).
	 * @param string[] $path  Path parts.
	 * @param mixed    $value Value to set.
	 */
	protected function set_nested_value( array &$data, array $path, $value ): void {
		$cursor = &$data;

		for ( $i = 0, $len = count( $path ); $i < $len - 1; $i++ ) {
			if ( ! isset( $cursor[ $path[ $i ] ] ) || ! is_array( $cursor[ $path[ $i ] ] ) ) {
				$cursor[ $path[ $i ] ] = [];
			}
			$cursor = &$cursor[ $path[ $i ] ];
		}

		$cursor[ end( $path ) ] = $value;
	}

	/**
	 * Deep merge two nested arrays (second wins on scalar conflicts).
	 *
	 * @param array $base    Base array.
	 * @param array $overlay Overlay array.
	 *
	 * @return array
	 */
	protected function array_merge_deep( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = $this->array_merge_deep( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Get all page IDs from schema.
	 *
	 * @param array $schema Schema array.
	 *
	 * @return string[]
	 */
	protected function get_page_ids( array $schema ): array {
		$ids = [];

		foreach ( $schema as $element ) {
			if ( 'page' === $element['type'] ) {
				$ids[] = $element['id'];
			}
		}

		return $ids;
	}

	/**
	 * Get all field elements from schema.
	 *
	 * @param array $schema Schema array.
	 *
	 * @return array[]
	 */
	protected function get_fields( array $schema ): array {
		$fields = [];

		foreach ( $schema as $element ) {
			if ( 'field' === $element['type'] ) {
				$fields[] = $element;
			}
		}

		return $fields;
	}

	/**
	 * Get field elements for a specific page.
	 *
	 * @param array  $schema  Schema array.
	 * @param string $page_id Page ID.
	 *
	 * @return array[]
	 */
	protected function get_fields_for_page( array $schema, string $page_id ): array {
		$fields = [];

		foreach ( $schema as $element ) {
			if ( 'field' === $element['type']
				&& isset( $element['page_id'] )
				&& $element['page_id'] === $page_id
			) {
				$fields[] = $element;
			}
		}

		return $fields;
	}
}
