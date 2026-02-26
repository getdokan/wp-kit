<?php

namespace WeDevs\WPKit\Tests\Cache;

use WeDevs\WPKit\Cache\WPCacheEngine;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class WPCacheEngineTest extends TestCase {

	/**
	 * @var WPCacheEngine
	 */
	private WPCacheEngine $engine;

	protected function setUp(): void {
		parent::setUp();
		$this->engine = new WPCacheEngine( 'testplugin' );
	}

	public function test_get_cache_key_prefix_returns_constructor_value(): void {
		$this->assertSame( 'testplugin', $this->engine->get_cache_key_prefix() );
	}

	public function test_get_returns_cached_value(): void {
		// Mock the namespace prefix lookup.
		Functions\expect( 'wp_cache_get' )
			->once()
			->with( 'testplugin_mygroup_prefix', 'mygroup' )
			->andReturn( '0.12345' );

		// Mock the actual value lookup.
		Functions\expect( 'wp_cache_get' )
			->once()
			->andReturn( 'cached_data' );

		$result = $this->engine->get( 'my_key', 'mygroup' );

		$this->assertSame( 'cached_data', $result );
	}

	public function test_get_returns_null_on_cache_miss(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( false );

		Functions\expect( 'wp_cache_set' )
			->andReturn( true );

		$result = $this->engine->get( 'missing_key', 'group' );

		$this->assertNull( $result );
	}

	public function test_set_stores_value_in_cache(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( '0.12345' );

		Functions\expect( 'wp_cache_set' )
			->once()
			->andReturn( true );

		$result = $this->engine->set( 'key', 'value', 'group', 3600 );

		$this->assertTrue( $result );
	}

	public function test_delete_removes_cached_value(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( '0.12345' );

		Functions\expect( 'wp_cache_delete' )
			->once()
			->andReturn( true );

		$result = $this->engine->delete( 'key', 'group' );

		$this->assertTrue( $result );
	}

	public function test_exists_checks_cache_key_presence(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( '0.12345' );

		// Second call for the actual exists check.
		Functions\expect( 'wp_cache_get' )
			->andReturn( 'some_value' );

		$result = $this->engine->exists( 'key', 'group' );

		$this->assertTrue( $result );
	}

	public function test_flush_group_invalidates_cache_group(): void {
		Functions\expect( 'wp_cache_set' )
			->once()
			->andReturn( true );

		$result = $this->engine->flush_group( 'group' );

		$this->assertTrue( $result );
	}

	public function test_get_many_returns_values_for_multiple_keys(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( '0.12345' );

		Functions\expect( 'wp_cache_get_multiple' )
			->once()
			->andReturn( [
				'testplugin_cache_0.12345_key1' => 'val1',
				'testplugin_cache_0.12345_key2' => false,
			] );

		$result = $this->engine->get_many( [ 'key1', 'key2' ], 'group' );

		$this->assertSame( 'val1', $result['key1'] );
		$this->assertNull( $result['key2'] );
	}

	public function test_set_many_stores_multiple_values(): void {
		Functions\expect( 'wp_cache_get' )
			->andReturn( '0.12345' );

		Functions\expect( 'wp_cache_set_multiple' )
			->once()
			->andReturn( [ 'key1' => true, 'key2' => true ] );

		$result = $this->engine->set_many(
			[ 'key1' => 'val1', 'key2' => 'val2' ],
			'group',
			3600
		);

		$this->assertIsArray( $result );
	}
}
