<?php
/**
 * REST API controller for migration status and upgrades.
 *
 * @package WeDevs\WPKit\Migration
 */

namespace WeDevs\WPKit\Migration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for migration status and upgrades.
 */
class MigrationRESTController {

	/**
	 * Migration manager instance.
	 *
	 * @var MigrationManager
	 */
	protected MigrationManager $manager;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected string $namespace;

	/**
	 * Constructor.
	 *
	 * @param MigrationManager $manager   Migration manager.
	 * @param string           $namespace REST API namespace (e.g., 'myplugin/v1').
	 */
	public function __construct( MigrationManager $manager, string $namespace ) {
		$this->manager   = $manager;
		$this->namespace = $namespace;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/migration/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/migration/upgrade',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'do_upgrade' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get migration status.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$status = $this->manager->get_status();

		return new WP_REST_Response(
			[
				'summary'             => $status->get_summary(),
				'log'                 => $status->get_log(),
				'is_running'          => $status->is_running(),
				'is_upgrade_required' => $this->manager->is_upgrade_required(),
				'db_version'          => $this->manager->get_registry()->get_db_installed_version(),
				'plugin_version'      => $this->manager->get_registry()->get_plugin_version(),
			],
			200
		);
	}

	/**
	 * Trigger admin upgrade.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function do_upgrade( WP_REST_Request $request ): WP_REST_Response {
		if ( $this->manager->has_ongoing_process() ) {
			return new WP_REST_Response( [ 'message' => 'Upgrade already in progress.' ], 400 );
		}

		if ( ! $this->manager->is_upgrade_required() ) {
			return new WP_REST_Response( [ 'message' => 'No upgrade required.' ], 400 );
		}

		$this->manager->do_upgrade();

		return new WP_REST_Response( [ 'success' => true ], 201 );
	}

	/**
	 * Check if the current user has permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'update_plugins' );
	}
}
