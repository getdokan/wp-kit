<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class MigrationRegistryTest extends TestCase {

	/**
	 * @var MigrationRegistry
	 */
	private MigrationRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new MigrationRegistry( 'test_db_version', '2.0.0' );
	}

	public function test_constructor_stores_version_key_and_plugin_version(): void {
		$this->assertSame( 'test_db_version', $this->registry->get_db_version_key() );
		$this->assertSame( '2.0.0', $this->registry->get_plugin_version() );
	}

	public function test_register_adds_migration(): void {
		$this->registry->register( '1.0.0', 'Migration_V1' );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '0.0.0' );

		$pending = $this->registry->get_pending_migrations();

		$this->assertArrayHasKey( '1.0.0', $pending );
		$this->assertSame( 'Migration_V1', $pending['1.0.0'] );
	}

	public function test_register_returns_self_for_chaining(): void {
		$result = $this->registry->register( '1.0.0', 'Migration_V1' );

		$this->assertSame( $this->registry, $result );
	}

	public function test_register_many_adds_multiple_migrations(): void {
		$this->registry->register_many( [
			'1.0.0' => 'V_1_0_0',
			'1.1.0' => 'V_1_1_0',
			'2.0.0' => 'V_2_0_0',
		] );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '0.0.0' );

		$pending = $this->registry->get_pending_migrations();

		$this->assertCount( 3, $pending );
	}

	public function test_get_db_installed_version_reads_from_option(): void {
		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->once()
			->andReturn( '1.5.0' );

		$version = $this->registry->get_db_installed_version();

		$this->assertSame( '1.5.0', $version );
	}

	public function test_get_db_installed_version_defaults_to_zero(): void {
		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->once()
			->andReturn( '0.0.0' );

		$version = $this->registry->get_db_installed_version();

		$this->assertSame( '0.0.0', $version );
	}

	public function test_is_upgrade_required_returns_true_when_behind(): void {
		$this->registry->register_many( [
			'1.0.0' => 'V_1_0_0',
			'1.1.0' => 'V_1_1_0',
		] );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '1.0.0' );

		$this->assertTrue( $this->registry->is_upgrade_required() );
	}

	public function test_is_upgrade_required_returns_false_when_current(): void {
		$this->registry->register_many( [
			'1.0.0' => 'V_1_0_0',
			'1.1.0' => 'V_1_1_0',
		] );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '1.1.0' );

		$this->assertFalse( $this->registry->is_upgrade_required() );
	}

	public function test_is_upgrade_required_returns_false_when_no_migrations(): void {
		Functions\expect( 'get_option' )
			->andReturn( '0.0.0' );

		$this->assertFalse( $this->registry->is_upgrade_required() );
	}

	public function test_get_pending_migrations_returns_only_newer_versions(): void {
		$this->registry->register_many( [
			'1.0.0' => 'V_1_0_0',
			'1.1.0' => 'V_1_1_0',
			'1.2.0' => 'V_1_2_0',
			'2.0.0' => 'V_2_0_0',
		] );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '1.1.0' );

		$pending = $this->registry->get_pending_migrations();

		$this->assertCount( 2, $pending );
		$this->assertArrayHasKey( '1.2.0', $pending );
		$this->assertArrayHasKey( '2.0.0', $pending );
		$this->assertArrayNotHasKey( '1.0.0', $pending );
		$this->assertArrayNotHasKey( '1.1.0', $pending );
	}

	public function test_get_pending_migrations_are_sorted_by_version(): void {
		$this->registry->register( '2.0.0', 'V_2_0_0' );
		$this->registry->register( '1.5.0', 'V_1_5_0' );
		$this->registry->register( '1.2.0', 'V_1_2_0' );

		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->andReturn( '0.0.0' );

		$pending = $this->registry->get_pending_migrations();
		$keys    = array_keys( $pending );

		$this->assertSame( [ '1.2.0', '1.5.0', '2.0.0' ], $keys );
	}

	public function test_update_db_version_to_current_writes_plugin_version(): void {
		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->once()
			->andReturn( '1.0.0' );

		Functions\expect( 'update_option' )
			->with( 'test_db_version', '2.0.0' )
			->once();

		$this->registry->update_db_version_to_current();
	}

	public function test_update_db_version_skips_when_already_current(): void {
		Functions\expect( 'get_option' )
			->with( 'test_db_version', '0.0.0' )
			->once()
			->andReturn( '2.0.0' );

		Functions\expect( 'update_option' )->never();

		$this->registry->update_db_version_to_current();
	}
}
