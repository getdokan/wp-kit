<?php

namespace WeDevs\WPKit\Migration;

/**
 * Wires the migration system into WordPress hooks.
 *
 * Registers filter hooks for upgrade detection, AJAX handler for
 * admin-triggered upgrades, and post-upgrade cleanup.
 */
class MigrationHooks {

	/**
	 * Migration manager.
	 *
	 * @var MigrationManager
	 */
	protected MigrationManager $manager;

	/**
	 * Plugin-specific prefix.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * @param MigrationManager $manager Migration manager.
	 * @param string           $prefix  Plugin-specific prefix (e.g., 'dokan').
	 */
	public function __construct( MigrationManager $manager, string $prefix ) {
		$this->manager = $manager;
		$this->prefix  = $prefix;
	}

	/**
	 * Register all necessary WordPress hooks.
	 */
	public function register(): void {
		add_filter( "{$this->prefix}_upgrade_is_upgrade_required", [ $this, 'is_upgrade_required' ], 1 );
		add_filter( "{$this->prefix}_upgrade_upgrades", [ $this, 'get_upgrades' ], 1 );

		add_action( "wp_ajax_{$this->prefix}_do_upgrade", [ $this, 'ajax_do_upgrade' ] );

		add_action( "{$this->prefix}_upgrade_finished", [ $this, 'on_upgrade_finished' ] );
		add_action( "{$this->prefix}_upgrade_is_not_required", [ $this, 'on_upgrade_not_required' ] );

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register REST API routes for migration status.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			"{$this->prefix}/v1",
			'/migration/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'update_plugins' );
				},
			]
		);
	}

	/**
	 * REST callback: get migration status.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_status(): \WP_REST_Response {
		$status = $this->manager->get_status();

		return new \WP_REST_Response(
			[
				'summary'            => $status->get_summary(),
				'log'                => $status->get_log(),
				'is_running'         => $status->is_running(),
				'is_upgrade_required' => $this->manager->is_upgrade_required(),
				'db_version'         => $this->manager->get_registry()->get_db_installed_version(),
				'plugin_version'     => $this->manager->get_registry()->get_plugin_version(),
			],
			200
		);
	}

	/**
	 * Filter callback: check if upgrade is required.
	 *
	 * @param bool $required Current required state.
	 *
	 * @return bool
	 */
	public function is_upgrade_required( bool $required = false ): bool {
		return $this->manager->is_upgrade_required();
	}

	/**
	 * Filter callback: get pending upgrades.
	 *
	 * @param array $upgrades Current upgrades.
	 *
	 * @return array
	 */
	public function get_upgrades( array $upgrades = [] ): array {
		if ( ! $this->manager->is_upgrade_required() ) {
			return $upgrades;
		}

		$pending = $this->manager->get_registry()->get_pending_migrations();

		foreach ( $pending as $version => $class ) {
			$upgrades[ $version ][] = $class;
		}

		return $upgrades;
	}

	/**
	 * AJAX handler for admin-triggered upgrades.
	 */
	public function ajax_do_upgrade(): void {
		check_ajax_referer( $this->prefix . '_admin' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		if ( $this->manager->has_ongoing_process() ) {
			wp_send_json_error( [ 'message' => 'Upgrade already in progress.' ], 400 );
		}

		if ( ! $this->manager->is_upgrade_required() ) {
			wp_send_json_error( [ 'message' => 'No upgrade required.' ], 400 );
		}

		$this->manager->do_upgrade();

		wp_send_json_success( [ 'success' => true ], 201 );
	}

	/**
	 * Hook callback: after upgrade completes.
	 */
	public function on_upgrade_finished(): void {
		$this->manager->get_registry()->update_db_version_to_current();
	}

	/**
	 * Hook callback: when no upgrade is required.
	 */
	public function on_upgrade_not_required(): void {
		$this->manager->get_registry()->update_db_version_to_current();
	}
}
