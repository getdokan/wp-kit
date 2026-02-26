<?php
/**
 * Interface for migration classes.
 *
 * @package WeDevs\WPKit\Migration\Contracts
 */

namespace WeDevs\WPKit\Migration\Contracts;

/**
 * Interface for migration classes.
 */
interface MigrationInterface {

	/**
	 * Run the migration.
	 *
	 * @param string|null $required_version Optional required DB version for conditional execution.
	 */
	public static function run( ?string $required_version = null ): void;

	/**
	 * Update the stored DB version after this migration completes.
	 */
	public static function update_db_version(): void;
}
