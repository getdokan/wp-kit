<?php
/**
 * Read-only migration status service.
 *
 * @package WeDevs\WPKit\Migration
 */

namespace WeDevs\WPKit\Migration;

/**
 * Read-only migration status service.
 *
 * Provides a convenient API for querying migration execution history,
 * current state, and summary statistics.
 *
 * Usage:
 *   $status = $manager->get_status();
 *   $status->get_log();             // Full migration log
 *   $status->get_status( '1.2.0' ); // Single migration entry
 *   $status->is_running();          // Any migration currently running?
 *   $status->get_summary();         // { total, completed, pending, failed, running }
 */
class MigrationStatus {

	/**
	 * Migration manager.
	 *
	 * @var MigrationManager
	 */
	protected MigrationManager $manager;

	/**
	 * Constructor.
	 *
	 * @param MigrationManager $manager Migration manager instance.
	 */
	public function __construct( MigrationManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Get the full migration execution log.
	 *
	 * @return array<string, array> Version-keyed array of log entries.
	 */
	public function get_log(): array {
		return get_option( $this->get_option_key(), [] );
	}

	/**
	 * Get the status of a specific migration version.
	 *
	 * @param string $version Migration version (e.g., '1.2.0').
	 *
	 * @return array|null Log entry or null if not found.
	 */
	public function get_status( string $version ): ?array {
		$log = $this->get_log();

		return $log[ $version ] ?? null;
	}

	/**
	 * Check if any migration is currently running.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		$log = $this->get_log();

		foreach ( $log as $entry ) {
			if ( 'running' === $entry['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a summary of migration status counts.
	 *
	 * @return array{total: int, completed: int, failed: int, running: int, pending: int}
	 */
	public function get_summary(): array {
		$log     = $this->get_log();
		$pending = $this->manager->get_registry()->get_pending_migrations();

		$summary = [
			'total'     => 0,
			'completed' => 0,
			'failed'    => 0,
			'running'   => 0,
			'pending'   => count( $pending ),
		];

		foreach ( $log as $entry ) {
			++$summary['total'];

			if ( isset( $summary[ $entry['status'] ] ) ) {
				++$summary[ $entry['status'] ];
			}
		}

		// Include pending migrations in total.
		$summary['total'] += $summary['pending'];

		return $summary;
	}

	/**
	 * Clear the migration log.
	 */
	public function clear_log(): void {
		delete_option( $this->get_option_key() );
	}

	/**
	 * Get the option key for the migration log.
	 *
	 * @return string
	 */
	public function get_option_key(): string {
		return $this->manager->get_log_option_key();
	}
}
