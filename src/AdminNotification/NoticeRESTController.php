<?php

namespace WeDevs\WPKit\AdminNotification;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for admin notices.
 */
class NoticeRESTController {

	/**
	 * Notice manager instance.
	 *
	 * @var NoticeManager
	 */
	protected NoticeManager $manager;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected string $namespace;

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected string $base = 'notices';

	/**
	 * @param NoticeManager $manager   Notice manager.
	 * @param string        $namespace REST API namespace (e.g., 'dokan/v1').
	 */
	public function __construct( NoticeManager $manager, string $namespace ) {
		$this->manager   = $manager;
		$this->namespace = $namespace;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/admin',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_admin_notices' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'scope' => [
							'type'              => 'string',
							'enum'              => [ 'local', 'global' ],
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/dismiss',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'dismiss_notice' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'key' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Get admin notices.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_admin_notices( WP_REST_Request $request ): WP_REST_Response {
		$scope   = $request->get_param( 'scope' ) ?: '';
		$notices = $this->manager->get_notices( $scope );

		return rest_ensure_response( $notices );
	}

	/**
	 * Dismiss a notice by key.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function dismiss_notice( WP_REST_Request $request ): WP_REST_Response {
		$key    = $request->get_param( 'key' );
		$prefix = $this->manager->get_prefix();

		$dismissed = get_option( $prefix . '_dismissed_notices', [] );

		if ( ! in_array( $key, $dismissed, true ) ) {
			$dismissed[] = $key;
			update_option( $prefix . '_dismissed_notices', $dismissed );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Check if the current user has permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
