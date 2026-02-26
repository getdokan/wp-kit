<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationRegistry;
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

		Functions\expect( 'update_option' )->once();
		Functions\expect( 'delete_option' )
			->with( 'testplugin_is_upgrading_db' )
			->once();

		Functions\expect( 'do_action' )
			->with( 'testplugin_upgrade_finished' )
			->once();

		$this->manager->do_upgrade();
	}
}
