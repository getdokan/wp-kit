<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationStatus;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class MigrationManagerTest extends TestCase {

	/**
	 * @var MigrationRegistry|Mockery\MockInterface
	 */
	private $registry;

	/**
	 * @var MigrationManager
	 */
	private MigrationManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = Mockery::mock( MigrationRegistry::class );
		$this->manager  = new MigrationManager( $this->registry, 'testplugin' );
	}

	public function test_constructor_sets_prefix(): void {
		$this->assertSame( 'testplugin', $this->manager->get_prefix() );
	}

	public function test_get_registry_returns_injected_registry(): void {
		$this->assertSame( $this->registry, $this->manager->get_registry() );
	}

	public function test_is_upgrade_required_delegates_to_registry(): void {
		$this->registry
			->shouldReceive( 'is_upgrade_required' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->manager->is_upgrade_required() );
	}

	public function test_has_ongoing_process_checks_option(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_is_upgrading_db', false )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->manager->has_ongoing_process() );
	}

	public function test_has_ongoing_process_returns_true_when_upgrading(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_is_upgrading_db', false )
			->once()
			->andReturn( [ '1.1.0' => [ 'V_1_1_0' ] ] );

		$this->assertTrue( $this->manager->has_ongoing_process() );
	}

	public function test_get_upgrades_caches_pending_in_option(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_is_upgrading_db', null )
			->once()
			->andReturn( null );

		$this->registry
			->shouldReceive( 'get_pending_migrations' )
			->once()
			->andReturn( [
				'1.1.0' => 'V_1_1_0',
				'2.0.0' => 'V_2_0_0',
			] );

		Functions\expect( 'update_option' )
			->once();

		$upgrades = $this->manager->get_upgrades();

		$this->assertArrayHasKey( '1.1.0', $upgrades );
		$this->assertArrayHasKey( '2.0.0', $upgrades );
	}

	public function test_get_upgrades_returns_cached_option_if_exists(): void {
		$cached = [
			'1.1.0' => [ 'V_1_1_0' ],
		];

		Functions\expect( 'get_option' )
			->with( 'testplugin_is_upgrading_db', null )
			->once()
			->andReturn( $cached );

		$this->registry->shouldNotReceive( 'get_pending_migrations' );

		$upgrades = $this->manager->get_upgrades();

		$this->assertSame( $cached, $upgrades );
	}

	public function test_do_upgrade_runs_all_pending_and_fires_action(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_is_upgrading_db', null )
			->once()
			->andReturn( null );

		$this->registry
			->shouldReceive( 'get_pending_migrations' )
			->once()
			->andReturn( [] );

		Functions\expect( 'update_option' )->zeroOrMoreTimes();
		Functions\expect( 'delete_option' )
			->with( 'testplugin_is_upgrading_db' )
			->once();

		Functions\expect( 'do_action' )
			->with( 'testplugin_upgrade_finished' )
			->once();

		$this->manager->do_upgrade();
	}

	public function test_get_log_option_key(): void {
		$this->assertSame( 'testplugin_migration_log', $this->manager->get_log_option_key() );
	}

	public function test_get_status_returns_migration_status_instance(): void {
		$status = $this->manager->get_status();

		$this->assertInstanceOf( MigrationStatus::class, $status );
	}

	public function test_do_upgrade_logs_migration_start_and_complete(): void {
		$migration_class = Mockery::mock( 'alias:TestMigration_V_1_0_0' );
		$migration_class->shouldReceive( 'run' )->with( null )->once();
		$migration_class->shouldReceive( 'update_db_version' )->once();

		// Stateful mock: track migration log across get/update_option calls.
		$stored_log     = [];
		$logged_entries = [];

		Functions\expect( 'get_option' )
			->andReturnUsing( function ( $key, $default = false ) use ( &$stored_log ) {
				if ( $key === 'testplugin_is_upgrading_db' && $default === null ) {
					return [ '1.0.0' => [ 'TestMigration_V_1_0_0' ] ];
				}
				if ( $key === 'testplugin_migration_log' ) {
					return $stored_log;
				}
				return $default;
			} );

		Functions\expect( 'update_option' )
			->andReturnUsing( function ( $key, $value ) use ( &$stored_log, &$logged_entries ) {
				if ( $key === 'testplugin_migration_log' ) {
					$stored_log       = $value;
					$logged_entries[] = $value;
				}
				return true;
			} );

		Functions\expect( 'delete_option' )
			->with( 'testplugin_is_upgrading_db' )
			->once();

		Functions\expect( 'do_action' )
			->with( 'testplugin_upgrade_finished' )
			->once();

		$this->manager->do_upgrade();

		$this->assertCount( 2, $logged_entries );

		// First call: log_migration_start.
		$this->assertArrayHasKey( '1.0.0', $logged_entries[0] );
		$this->assertSame( 'running', $logged_entries[0]['1.0.0']['status'] );
		$this->assertSame( 'TestMigration_V_1_0_0', $logged_entries[0]['1.0.0']['class'] );
		$this->assertNotNull( $logged_entries[0]['1.0.0']['started_at'] );
		$this->assertNull( $logged_entries[0]['1.0.0']['completed_at'] );

		// Second call: log_migration_complete.
		$this->assertSame( 'completed', $logged_entries[1]['1.0.0']['status'] );
		$this->assertNotNull( $logged_entries[1]['1.0.0']['completed_at'] );
		$this->assertNull( $logged_entries[1]['1.0.0']['error'] );
	}

	public function test_do_upgrade_logs_migration_failure(): void {
		$exception       = new \RuntimeException( 'Table creation failed' );
		$migration_class = Mockery::mock( 'alias:FailingMigration_V_2_0_0' );
		$migration_class->shouldReceive( 'run' )->with( null )->once()->andThrow( $exception );

		// Stateful mock for migration log.
		$stored_log     = [];
		$logged_entries = [];

		Functions\expect( 'get_option' )
			->andReturnUsing( function ( $key, $default = false ) use ( &$stored_log ) {
				if ( $key === 'testplugin_is_upgrading_db' && $default === null ) {
					return [ '2.0.0' => [ 'FailingMigration_V_2_0_0' ] ];
				}
				if ( $key === 'testplugin_migration_log' ) {
					return $stored_log;
				}
				return $default;
			} );

		Functions\expect( 'update_option' )
			->andReturnUsing( function ( $key, $value ) use ( &$stored_log, &$logged_entries ) {
				if ( $key === 'testplugin_migration_log' ) {
					$stored_log       = $value;
					$logged_entries[] = $value;
				}
				return true;
			} );

		try {
			$this->manager->do_upgrade();
			$this->fail( 'Expected RuntimeException was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Table creation failed', $e->getMessage() );
		}

		$this->assertCount( 2, $logged_entries );

		// First call: log_migration_start.
		$this->assertSame( 'running', $logged_entries[0]['2.0.0']['status'] );

		// Second call: log_migration_failed.
		$this->assertSame( 'failed', $logged_entries[1]['2.0.0']['status'] );
		$this->assertSame( 'Table creation failed', $logged_entries[1]['2.0.0']['error'] );
	}
}
