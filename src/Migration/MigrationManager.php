<?php

namespace WeDevs\WPKit\Migration;

/**
 * Orchestrates migration execution.
 *
 * Discovers pending migrations from the registry, runs them in version order,
 * and tracks the upgrade process via WordPress options.
 */
class MigrationManager {

	/**
	 * Migration registry.
	 *
	 * @var MigrationRegistry
	 */
	protected MigrationRegistry $registry;

	/**
	 * Option key for tracking ongoing upgrades.
	 *
	 * @var string
	 */
	protected string $upgrading_option_key;

	/**
	 * Hook prefix.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * @param MigrationRegistry $registry Migration registry.
	 * @param string            $prefix   Plugin-specific prefix (e.g., 'dokan').
	 */
	public function __construct( MigrationRegistry $registry, string $prefix ) {
		$this->registry             = $registry;
		$this->prefix               = $prefix;
		$this->upgrading_option_key = $prefix . '_is_upgrading_db';
	}

	/**
	 * Check if an upgrade is required.
	 *
	 * @return bool
	 */
	public function is_upgrade_required(): bool {
		return $this->registry->is_upgrade_required();
	}

	/**
	 * Check if there's an ongoing upgrade process.
	 *
	 * @return bool
	 */
	public function has_ongoing_process(): bool {
		return (bool) get_option( $this->upgrading_option_key, false );
	}

	/**
	 * Get the pending upgrades (caches in option for process tracking).
	 *
	 * @return array
	 */
	public function get_upgrades(): array {
		$upgrades = get_option( $this->upgrading_option_key, null );

		if ( ! empty( $upgrades ) && is_array( $upgrades ) ) {
			return $upgrades;
		}

		$pending  = $this->registry->get_pending_migrations();
		$upgrades = [];

		foreach ( $pending as $version => $class ) {
			$upgrades[ $version ][] = $class;
		}

		uksort( $upgrades, 'version_compare' );

		update_option( $this->upgrading_option_key, $upgrades, false );

		return $upgrades;
	}

	/**
	 * Run all pending migrations in version order.
	 */
	public function do_upgrade(): void {
		$upgrades = $this->get_upgrades();

		foreach ( $upgrades as $version => $upgraders ) {
			foreach ( (array) $upgraders as $upgrader ) {
				$required_version = null;

				if ( is_array( $upgrader ) ) {
					$required_version = $upgrader['require'] ?? null;
					$upgrader         = $upgrader['upgrader'];
				}

				call_user_func( [ $upgrader, 'run' ], $required_version );
				call_user_func( [ $upgrader, 'update_db_version' ] );
			}
		}

		delete_option( $this->upgrading_option_key );

		do_action( $this->prefix . '_upgrade_finished' );
	}

	/**
	 * Get the migration registry.
	 *
	 * @return MigrationRegistry
	 */
	public function get_registry(): MigrationRegistry {
		return $this->registry;
	}

	/**
	 * Get the prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->prefix;
	}
}
