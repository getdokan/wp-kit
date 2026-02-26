<?php

namespace WeDevs\WPKit\AdminNotification;

/**
 * Handles AJAX notice dismissal.
 */
class DismissalHandler {

	/**
	 * Prefix for option keys and AJAX actions.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * @param string $prefix Plugin-specific prefix (e.g., 'dokan').
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Register AJAX hooks for dismissal.
	 */
	public function register(): void {
		add_action( "wp_ajax_{$this->prefix}_dismiss_notice", [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Handle the AJAX dismissal request.
	 */
	public function handle_dismiss(): void {
		check_ajax_referer( $this->prefix . '_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
			return;
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( ! $key ) {
			wp_send_json_error( [ 'message' => 'Missing notice key.' ], 400 );
			return;
		}

		$dismissed = get_option( $this->prefix . '_dismissed_notices', [] );

		if ( ! in_array( $key, $dismissed, true ) ) {
			$dismissed[] = $key;
			update_option( $this->prefix . '_dismissed_notices', $dismissed );
		}

		wp_send_json_success();
	}
}
