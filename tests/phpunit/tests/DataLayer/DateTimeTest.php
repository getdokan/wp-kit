<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\DataLayer\DateTime;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class DateTimeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_timezone_string' )->justReturn( 'UTC' );
	}

	public function test_from_db_string_creates_datetime_from_valid_string(): void {
		$dt = DateTime::from_db_string( '2024-01-15 10:30:00' );

		$this->assertInstanceOf( DateTime::class, $dt );
		$this->assertSame( '2024-01-15', $dt->format( 'Y-m-d' ) );
		$this->assertSame( '10:30:00', $dt->format( 'H:i:s' ) );
	}

	public function test_from_db_string_returns_null_for_empty_string(): void {
		$this->assertNull( DateTime::from_db_string( '' ) );
	}

	public function test_from_db_string_returns_null_for_zero_date(): void {
		$this->assertNull( DateTime::from_db_string( '0000-00-00 00:00:00' ) );
	}

	public function test_from_db_string_returns_null_for_invalid_date(): void {
		$this->assertNull( DateTime::from_db_string( 'not-a-date' ) );
	}

	public function test_from_timestamp_creates_datetime(): void {
		$timestamp = 1705312200; // 2024-01-15 10:30:00 UTC

		$dt = DateTime::from_timestamp( $timestamp );

		$this->assertInstanceOf( DateTime::class, $dt );
		$this->assertSame( $timestamp, $dt->getTimestamp() );
	}

	public function test_to_db_string_formats_for_database(): void {
		$dt = DateTime::from_db_string( '2024-06-15 14:30:00' );

		$this->assertSame( '2024-06-15 14:30:00', $dt->to_db_string() );
	}

	public function test_to_db_string_with_custom_format(): void {
		$dt = DateTime::from_db_string( '2024-06-15 14:30:00' );

		$this->assertSame( '2024-06-15', $dt->to_db_string( 'Y-m-d' ) );
	}

	public function test_utc_offset_getter_setter(): void {
		$dt = DateTime::from_db_string( '2024-01-15 10:00:00' );

		$this->assertSame( 0, $dt->get_utc_offset() );

		$dt->set_utc_offset( 3600 );

		$this->assertSame( 3600, $dt->get_utc_offset() );
	}

	public function test_datetime_is_immutable(): void {
		$dt = DateTime::from_db_string( '2024-01-15 10:00:00' );

		$this->assertInstanceOf( \DateTimeImmutable::class, $dt );
	}
}
