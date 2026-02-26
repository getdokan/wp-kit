<?php
/**
 * Base migration class.
 *
 * @package WeDevs\WPKit\Migration
 */

namespace WeDevs\WPKit\Migration;

use ReflectionClass;
use WeDevs\WPKit\Migration\Contracts\MigrationInterface;

/**
 * Base migration class.
 *
 * Uses reflection to auto-discover and execute all public static methods
 * in child classes. Version is extracted from class name (e.g., V_4_1_0 => 4.1.0).
 *
 * Host plugins should create an intermediate abstract class that overrides
 * $db_version_key:
 *
 *   abstract class DokanMigration extends BaseMigration {
 *       protected static string $db_version_key = 'dokan_lite_db_version';
 *   }
 */
abstract class BaseMigration implements MigrationInterface {

	/**
	 * The option key used to store the DB version.
	 * Host plugins MUST override this (e.g., 'dokan_lite_db_version').
	 *
	 * @var string
	 */
	protected static string $db_version_key = '';

	/**
	 * Methods to exclude from auto-execution.
	 *
	 * @var array
	 */
	private static array $excluded_methods = [
		'run',
		'update_db_version',
		'get_db_version_key',
	];

	/**
	 * Run all public static methods in the migration class.
	 *
	 * @param string|null $required_version Minimum DB version required to run.
	 */
	public static function run( ?string $required_version = null ): void {
		if ( $required_version ) {
			$current_db_version = get_option( static::$db_version_key, '0.0.0' );

			if ( version_compare( $current_db_version, $required_version, '<' ) ) {
				return;
			}
		}

		$methods = get_class_methods( static::class );

		foreach ( $methods as $method ) {
			if ( ! in_array( $method, self::$excluded_methods, true ) ) {
				call_user_func( [ static::class, $method ] );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function update_db_version(): void {
		$reflect    = new ReflectionClass( static::class );
		$short_name = $reflect->getShortName();
		$version    = str_replace( [ 'V_', '_' ], [ '', '.' ], $short_name );

		update_option( static::get_db_version_key(), $version );
	}

	/**
	 * Get the DB version option key.
	 *
	 * @return string
	 */
	public static function get_db_version_key(): string {
		return static::$db_version_key;
	}
}
