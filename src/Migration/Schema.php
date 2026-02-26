<?php

namespace WeDevs\WPKit\Migration;

/**
 * Static helpers for database schema operations.
 */
class Schema {

	/**
	 * Create or update a table using dbDelta.
	 *
	 * @param string $sql The CREATE TABLE SQL statement.
	 *
	 * @return array Results from dbDelta.
	 */
	public static function create_table( string $sql ): array {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		if ( false === strpos( $sql, 'CHARSET' ) && false === strpos( $sql, 'COLLATE' ) ) {
			$sql = rtrim( $sql, ';' ) . " {$charset_collate};";
		}

		return dbDelta( $sql );
	}

	/**
	 * Drop a table.
	 *
	 * @param string $table_name Full table name (with prefix).
	 */
	public static function drop_table( string $table_name ): void {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table_name Full table name (with prefix).
	 *
	 * @return bool
	 */
	public static function table_exists( string $table_name ): bool {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $result === $table_name;
	}

	/**
	 * Get a prefixed table name.
	 *
	 * @param string $name Table name without prefix.
	 *
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table_name  Full table name.
	 * @param string $column_name Column name.
	 *
	 * @return bool
	 */
	public static function column_exists( string $table_name, string $column_name ): bool {
		global $wpdb;

		$columns = $wpdb->get_col( "DESCRIBE {$table_name}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return in_array( $column_name, $columns, true );
	}
}
