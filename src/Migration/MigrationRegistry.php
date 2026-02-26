<?php
/**
 * Migration registry that tracks version → class mappings.
 *
 * @package WeDevs\WPKit\Migration
 */

namespace WeDevs\WPKit\Migration;

/**
 * Migration registry that tracks version → class mappings.
 *
 * Determines which migrations are pending based on the stored DB version.
 */
class MigrationRegistry {

	/**
	 * Registered migrations: version => class name or array.
	 *
	 * @var array<string, string|array>
	 */
	protected array $migrations = [];

	/**
	 * Option key for stored DB version.
	 *
	 * @var string
	 */
	protected string $db_version_key;

	/**
	 * Current plugin version to update to after all migrations.
	 *
	 * @var string
	 */
	protected string $plugin_version;

	/**
	 * Constructor.
	 *
	 * @param string $db_version_key Option key for DB version.
	 * @param string $plugin_version Current plugin version.
	 */
	public function __construct( string $db_version_key, string $plugin_version ) {
		$this->db_version_key = $db_version_key;
		$this->plugin_version = $plugin_version;
	}

	/**
	 * Register a single migration.
	 *
	 * @param string       $version         Version string (e.g., '4.1.0').
	 * @param string|array $migration_class Class name, or ['upgrader' => class, 'require' => version].
	 *
	 * @return self
	 */
	public function register( string $version, $migration_class ): self {
		$this->migrations[ $version ] = $migration_class;

		return $this;
	}

	/**
	 * Bulk register migrations.
	 *
	 * @param array<string, string|array> $migrations Version => class map.
	 *
	 * @return self
	 */
	public function register_many( array $migrations ): self {
		foreach ( $migrations as $version => $class ) {
			$this->register( $version, $class );
		}

		return $this;
	}

	/**
	 * Get the currently installed DB version.
	 *
	 * @return string
	 */
	public function get_db_installed_version(): string {
		return get_option( $this->db_version_key, '0.0.0' );
	}

	/**
	 * Check if any upgrade is required.
	 *
	 * @return bool
	 */
	public function is_upgrade_required(): bool {
		$installed = $this->get_db_installed_version();
		$versions  = array_keys( $this->migrations );

		if ( empty( $versions ) ) {
			return false;
		}

		$latest = end( $versions );

		return version_compare( $installed, $latest, '<' );
	}

	/**
	 * Get only the migrations that need to run.
	 *
	 * @return array<string, string|array> Sorted by version.
	 */
	public function get_pending_migrations(): array {
		$installed = $this->get_db_installed_version();
		$pending   = [];

		foreach ( $this->migrations as $version => $class ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				$pending[ $version ] = $class;
			}
		}

		uksort( $pending, 'version_compare' );

		return $pending;
	}

	/**
	 * Update the stored DB version to the current plugin version.
	 */
	public function update_db_version_to_current(): void {
		$installed = $this->get_db_installed_version();

		if ( version_compare( $installed, $this->plugin_version, '<' ) ) {
			update_option( $this->db_version_key, $this->plugin_version );
		}
	}

	/**
	 * Get the DB version option key.
	 *
	 * @return string
	 */
	public function get_db_version_key(): string {
		return $this->db_version_key;
	}

	/**
	 * Get the current plugin version.
	 *
	 * @return string
	 */
	public function get_plugin_version(): string {
		return $this->plugin_version;
	}
}
