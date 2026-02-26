<?php
/**
 * Wires the migration system into WordPress hooks.
 *
 * @package WeDevs\WPKit\Migration
 */

namespace WeDevs\WPKit\Migration;

/**
 * Wires the migration system into WordPress hooks.
 *
 * Registers filter hooks for upgrade detection and post-upgrade cleanup.
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
	 * Constructor.
	 *
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

		add_action( "{$this->prefix}_upgrade_finished", [ $this, 'on_upgrade_finished' ] );
		add_action( "{$this->prefix}_upgrade_is_not_required", [ $this, 'on_upgrade_not_required' ] );
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
