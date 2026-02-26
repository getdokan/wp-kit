<?php
/**
 * Standalone DateTime class for WPKit models.
 *
 * @package WeDevs\WPKit\DataLayer
 */

namespace WeDevs\WPKit\DataLayer;

use DateTimeZone;

/**
 * Standalone DateTime class for WPKit models.
 *
 * Replaces WC_DateTime dependency. Uses DateTimeImmutable to avoid
 * accidental mutations.
 */
class DateTime extends \DateTimeImmutable {

	/**
	 * UTC offset for display purposes.
	 *
	 * @var int
	 */
	protected int $utc_offset = 0;

	/**
	 * Set UTC offset.
	 *
	 * @param int $offset Offset in seconds.
	 */
	public function set_utc_offset( int $offset ): void {
		$this->utc_offset = $offset;
	}

	/**
	 * Get UTC offset.
	 *
	 * @return int
	 */
	public function get_utc_offset(): int {
		return $this->utc_offset;
	}

	/**
	 * Format date for database storage.
	 *
	 * @param string $format Date format string.
	 *
	 * @return string
	 */
	public function to_db_string( string $format = 'Y-m-d H:i:s' ): string {
		return $this->format( $format );
	}

	/**
	 * Create from a database date string.
	 *
	 * @param string $date_string Date string from database.
	 *
	 * @return static|null Null if empty or zero date.
	 */
	public static function from_db_string( string $date_string ): ?self {
		if ( empty( $date_string ) || '0000-00-00 00:00:00' === $date_string ) {
			return null;
		}

		try {
			return new static( $date_string, new DateTimeZone( wp_timezone_string() ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Create from a Unix timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return static
	 */
	public static function from_timestamp( int $timestamp ): self {
		$dt = new static( "@{$timestamp}", new DateTimeZone( 'UTC' ) );

		return $dt->setTimezone( new DateTimeZone( wp_timezone_string() ) );
	}
}
