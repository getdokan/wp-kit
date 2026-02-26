<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationStatus;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class MigrationStatusTest extends TestCase {

	/**
	 * @var MigrationManager
	 */
	private MigrationManager $manager;

	/**
	 * @var MigrationRegistry|Mockery\MockInterface
	 */
	private $registry;

	/**
	 * @var MigrationStatus
	 */
	private MigrationStatus $status;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = Mockery::mock( MigrationRegistry::class );
		$this->manager  = new MigrationManager( $this->registry, 'testplugin' );
		$this->status   = new MigrationStatus( $this->manager );
	}

	public function test_get_option_key_returns_prefixed_key(): void {
		$this->assertSame( 'testplugin_migration_log', $this->status->get_option_key() );
	}

	public function test_get_log_returns_empty_array_when_no_log(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [] );

		$this->assertSame( [], $this->status->get_log() );
	}

	public function test_get_log_returns_stored_log(): void {
		$log = [
			'1.0.0' => [
				'version'      => '1.0.0',
				'class'        => 'V_1_0_0',
				'status'       => 'completed',
				'started_at'   => 1708900000,
				'completed_at' => 1708900002,
				'error'        => null,
			],
		];

		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( $log );

		$this->assertSame( $log, $this->status->get_log() );
	}

	public function test_get_status_returns_entry_for_version(): void {
		$entry = [
			'version'      => '1.0.0',
			'class'        => 'V_1_0_0',
			'status'       => 'completed',
			'started_at'   => 1708900000,
			'completed_at' => 1708900002,
			'error'        => null,
		];

		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [ '1.0.0' => $entry ] );

		$this->assertSame( $entry, $this->status->get_status( '1.0.0' ) );
	}

	public function test_get_status_returns_null_for_unknown_version(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [] );

		$this->assertNull( $this->status->get_status( '9.9.9' ) );
	}

	public function test_is_running_returns_true_when_migration_running(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [
				'1.0.0' => [ 'status' => 'completed' ],
				'1.1.0' => [ 'status' => 'running' ],
			] );

		$this->assertTrue( $this->status->is_running() );
	}

	public function test_is_running_returns_false_when_none_running(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [
				'1.0.0' => [ 'status' => 'completed' ],
				'1.1.0' => [ 'status' => 'completed' ],
			] );

		$this->assertFalse( $this->status->is_running() );
	}

	public function test_is_running_returns_false_when_log_empty(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [] );

		$this->assertFalse( $this->status->is_running() );
	}

	public function test_get_summary_counts_statuses(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [
				'1.0.0' => [ 'status' => 'completed' ],
				'1.1.0' => [ 'status' => 'completed' ],
				'1.2.0' => [ 'status' => 'failed' ],
				'1.3.0' => [ 'status' => 'running' ],
			] );

		$this->registry
			->shouldReceive( 'get_pending_migrations' )
			->once()
			->andReturn( [
				'1.4.0' => 'V_1_4_0',
				'1.5.0' => 'V_1_5_0',
			] );

		$summary = $this->status->get_summary();

		$this->assertSame( 6, $summary['total'] );
		$this->assertSame( 2, $summary['completed'] );
		$this->assertSame( 1, $summary['failed'] );
		$this->assertSame( 1, $summary['running'] );
		$this->assertSame( 2, $summary['pending'] );
	}

	public function test_get_summary_with_empty_log_and_no_pending(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_migration_log', [] )
			->once()
			->andReturn( [] );

		$this->registry
			->shouldReceive( 'get_pending_migrations' )
			->once()
			->andReturn( [] );

		$summary = $this->status->get_summary();

		$this->assertSame( 0, $summary['total'] );
		$this->assertSame( 0, $summary['completed'] );
		$this->assertSame( 0, $summary['failed'] );
		$this->assertSame( 0, $summary['running'] );
		$this->assertSame( 0, $summary['pending'] );
	}

	public function test_clear_log_deletes_option(): void {
		Functions\expect( 'delete_option' )
			->with( 'testplugin_migration_log' )
			->once();

		$this->status->clear_log();
	}
}
