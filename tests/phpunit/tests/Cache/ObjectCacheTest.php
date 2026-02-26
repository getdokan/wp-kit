<?php

namespace WeDevs\WPKit\Tests\Cache;

use WeDevs\WPKit\Cache\Contracts\CacheEngineInterface;
use WeDevs\WPKit\Cache\ObjectCache;
use WeDevs\WPKit\Tests\TestCase;
use Mockery;

class ObjectCacheTest extends TestCase {

	/**
	 * @var CacheEngineInterface|Mockery\MockInterface
	 */
	private $engine;

	/**
	 * @var ObjectCache
	 */
	private ObjectCache $cache;

	protected function setUp(): void {
		parent::setUp();

		$this->engine = Mockery::mock( CacheEngineInterface::class );
		$this->cache  = new ObjectCache( $this->engine, 'test_orders', 3600 );
	}

	public function test_get_object_type_returns_configured_type(): void {
		$this->assertSame( 'test_orders', $this->cache->get_object_type() );
	}

	public function test_get_engine_returns_injected_engine(): void {
		$this->assertSame( $this->engine, $this->cache->get_engine() );
	}

	public function test_get_returns_cached_value_on_hit(): void {
		$this->engine
			->shouldReceive( 'get' )
			->with( '42', 'test_orders' )
			->once()
			->andReturn( [ 'id' => 42, 'total' => 99.99 ] );

		$result = $this->cache->get( 42 );

		$this->assertSame( [ 'id' => 42, 'total' => 99.99 ], $result );
	}

	public function test_get_returns_null_on_miss_without_callback(): void {
		$this->engine
			->shouldReceive( 'get' )
			->with( '42', 'test_orders' )
			->once()
			->andReturn( null );

		$result = $this->cache->get( 42 );

		$this->assertNull( $result );
	}

	public function test_get_calls_callback_on_miss_and_caches_result(): void {
		$this->engine
			->shouldReceive( 'get' )
			->with( '42', 'test_orders' )
			->once()
			->andReturn( null );

		$this->engine
			->shouldReceive( 'set' )
			->with( '42', [ 'from_callback' => true ], 'test_orders', 3600 )
			->once()
			->andReturn( true );

		$callback_called = false;

		$result = $this->cache->get( 42, function ( $id ) use ( &$callback_called ) {
			$callback_called = true;
			$this->assertSame( 42, $id );
			return [ 'from_callback' => true ];
		} );

		$this->assertTrue( $callback_called );
		$this->assertSame( [ 'from_callback' => true ], $result );
	}

	public function test_get_callback_returning_null_does_not_cache(): void {
		$this->engine
			->shouldReceive( 'get' )
			->andReturn( null );

		$this->engine
			->shouldNotReceive( 'set' );

		$result = $this->cache->get( 42, function () {
			return null;
		} );

		$this->assertNull( $result );
	}

	public function test_set_stores_object_with_default_expiration(): void {
		$this->engine
			->shouldReceive( 'set' )
			->with( '42', [ 'data' => 'test' ], 'test_orders', 3600 )
			->once()
			->andReturn( true );

		$result = $this->cache->set( [ 'data' => 'test' ], 42 );

		$this->assertTrue( $result );
	}

	public function test_set_with_custom_expiration(): void {
		$this->engine
			->shouldReceive( 'set' )
			->with( '42', 'data', 'test_orders', 7200 )
			->once()
			->andReturn( true );

		$result = $this->cache->set( 'data', 42, 7200 );

		$this->assertTrue( $result );
	}

	public function test_set_caps_expiration_at_max(): void {
		$max = MONTH_IN_SECONDS;

		$this->engine
			->shouldReceive( 'set' )
			->with( '1', 'data', 'test_orders', $max )
			->once()
			->andReturn( true );

		$this->cache->set( 'data', 1, $max + 99999 );
	}

	public function test_remove_deletes_cached_object(): void {
		$this->engine
			->shouldReceive( 'delete' )
			->with( '42', 'test_orders' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->cache->remove( 42 ) );
	}

	public function test_flush_invalidates_entire_group(): void {
		$this->engine
			->shouldReceive( 'flush_group' )
			->with( 'test_orders' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->cache->flush() );
	}

	public function test_is_cached_returns_true_for_existing_key(): void {
		$this->engine
			->shouldReceive( 'exists' )
			->with( '42', 'test_orders' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->cache->is_cached( 42 ) );
	}

	public function test_is_cached_returns_false_for_missing_key(): void {
		$this->engine
			->shouldReceive( 'exists' )
			->with( '999', 'test_orders' )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->cache->is_cached( 999 ) );
	}

	public function test_get_many_delegates_to_engine(): void {
		$this->engine
			->shouldReceive( 'get_many' )
			->with( [ '1', '2', '3' ], 'test_orders' )
			->once()
			->andReturn( [ '1' => 'a', '2' => null, '3' => 'c' ] );

		$result = $this->cache->get_many( [ 1, 2, 3 ] );

		$this->assertSame( 'a', $result['1'] );
		$this->assertNull( $result['2'] );
		$this->assertSame( 'c', $result['3'] );
	}

	public function test_set_many_delegates_to_engine(): void {
		$this->engine
			->shouldReceive( 'set_many' )
			->once()
			->andReturn( [ '1' => true, '2' => true ] );

		$result = $this->cache->set_many( [ 1 => 'a', 2 => 'b' ] );

		$this->assertSame( [ '1' => true, '2' => true ], $result );
	}
}
