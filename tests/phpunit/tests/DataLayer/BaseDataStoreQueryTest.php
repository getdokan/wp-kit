<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\Cache\ObjectCache;
use WeDevs\WPKit\Cache\Contracts\CacheEngineInterface;
use WeDevs\WPKit\DataLayer\DataStore\BaseDataStore;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Concrete data store for testing query().
 */
class TestTaskStore extends BaseDataStore {

	public function get_table_name(): string {
		return 'wpkit_tasks';
	}

	protected function get_fields_with_format(): array {
		return [
			'title'       => '%s',
			'description' => '%s',
			'status'      => '%s',
			'priority'    => '%d',
			'assigned_to' => '%d',
			'due_date'    => '%s',
			'created_at'  => '%s',
		];
	}

	protected function get_searchable_fields(): array {
		return [ 'title', 'description' ];
	}
}

class BaseDataStoreQueryTest extends TestCase {

	private TestTaskStore $store;
	private object $wpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

		Functions\when( 'wp_json_encode' )->alias( function ( $data ) {
			return json_encode( $data );
		} );

		$this->store = new TestTaskStore();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	// ----------------------------------------------------------
	// Basic query
	// ----------------------------------------------------------

	public function test_query_returns_results_with_pagination_structure(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'Task 1', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'title' => 'Task 2', 'status' => 'completed' ],
		];

		// COUNT query.
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '5' );

		// Results query.
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $rows );

		// prepare for LIMIT.
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query();

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'current_page', $result );
		$this->assertArrayHasKey( 'total_pages', $result );

		$this->assertSame( $rows, $result['items'] );
		$this->assertSame( 5, $result['total'] );
		$this->assertSame( 20, $result['per_page'] );
		$this->assertSame( 1, $result['current_page'] );
		$this->assertSame( 1, $result['total_pages'] );
	}

	// ----------------------------------------------------------
	// Return types
	// ----------------------------------------------------------

	public function test_query_return_count(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '42' );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				return func_get_args()[0];
			} );

		$result = $this->store->query( [ 'return' => 'count' ] );

		$this->assertSame( 42, $result['items'] );
		$this->assertSame( 42, $result['total'] );
	}

	public function test_query_return_ids(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '3' );

		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( [ '1', '2', '3' ] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				return func_get_args()[0];
			} );

		$result = $this->store->query( [ 'return' => 'ids' ] );

		$this->assertSame( [ 1, 2, 3 ], $result['items'] );
	}

	// ----------------------------------------------------------
	// Field filters
	// ----------------------------------------------------------

	public function test_query_with_exact_match_filter(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query( [ 'status' => 'pending' ] );

		// Verify the SQL was built (we check the method ran without error).
		$this->assertIsArray( $result );
	}

	public function test_query_with_in_filter(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				if ( count( $args ) > 2 ) {
					return vsprintf( $args[0], array_slice( $args, 1 ) );
				}
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query( [ 'status__in' => [ 'pending', 'completed' ] ] );

		$this->assertIsArray( $result );
	}

	public function test_query_with_comparison_operators(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query( [
			'priority__gte' => 5,
			'priority__lte' => 20,
		] );

		$this->assertIsArray( $result );
	}

	public function test_query_ignores_unknown_fields(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				return func_get_args()[0];
			} );

		// 'nonexistent' is not a valid field — should be ignored.
		$result = $this->store->query( [ 'nonexistent' => 'value' ] );

		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['total'] );
	}

	// ----------------------------------------------------------
	// Search
	// ----------------------------------------------------------

	public function test_query_with_search(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [
			(object) [ 'id' => 1, 'title' => 'Fix bug' ],
		] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$this->wpdb->shouldReceive( 'esc_like' )
			->with( 'bug' )
			->andReturn( 'bug' );

		$result = $this->store->query( [ 'search' => 'bug' ] );

		$this->assertCount( 1, $result['items'] );
	}

	// ----------------------------------------------------------
	// Date query
	// ----------------------------------------------------------

	public function test_query_with_date_query(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query( [
			'date_query' => [
				'column' => 'due_date',
				'after'  => '2025-01-01',
				'before' => '2025-12-31',
			],
		] );

		$this->assertIsArray( $result );
	}

	public function test_query_date_query_ignores_invalid_column(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				return func_get_args()[0];
			} );

		// 'nonexistent_column' is not a valid field.
		$result = $this->store->query( [
			'date_query' => [
				'column' => 'nonexistent_column',
				'after'  => '2025-01-01',
			],
		] );

		$this->assertIsArray( $result );
	}

	// ----------------------------------------------------------
	// Pagination
	// ----------------------------------------------------------

	public function test_query_pagination_calculates_total_pages(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '55' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				$args = func_get_args();
				return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $args[0] ), array_slice( $args, 1 ) );
			} );

		$result = $this->store->query( [ 'per_page' => 10, 'page' => 3 ] );

		$this->assertSame( 55, $result['total'] );
		$this->assertSame( 10, $result['per_page'] );
		$this->assertSame( 3, $result['current_page'] );
		$this->assertSame( 6, $result['total_pages'] );
	}

	public function test_query_unlimited_per_page(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '100' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( array_fill( 0, 100, (object) [] ) );

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () {
				return func_get_args()[0];
			} );

		$result = $this->store->query( [ 'per_page' => -1 ] );

		$this->assertSame( -1, $result['per_page'] );
		$this->assertCount( 100, $result['items'] );
	}

	// ----------------------------------------------------------
	// Caching
	// ----------------------------------------------------------

	public function test_query_uses_cache_on_hit(): void {
		$cached_result = [
			'items'        => [ (object) [ 'id' => 1 ] ],
			'total'        => 1,
			'per_page'     => 20,
			'current_page' => 1,
			'total_pages'  => 1,
		];

		$engine = Mockery::mock( CacheEngineInterface::class );
		$cache  = new ObjectCache( $engine, 'test_cache' );

		// Cache returns a hit.
		$engine->shouldReceive( 'get' )
			->once()
			->andReturn( $cached_result );

		$this->store->set_cache( $cache );

		// wpdb should NOT be called.
		$this->wpdb->shouldNotReceive( 'get_var' );
		$this->wpdb->shouldNotReceive( 'get_results' );

		$result = $this->store->query();

		$this->assertSame( $cached_result, $result );
	}

	public function test_query_stores_result_in_cache_on_miss(): void {
		$engine = Mockery::mock( CacheEngineInterface::class );
		$cache  = new ObjectCache( $engine, 'test_cache' );

		// Cache miss.
		$engine->shouldReceive( 'get' )
			->once()
			->andReturn( null );

		// Should store result.
		$engine->shouldReceive( 'set' )
			->once()
			->andReturn( true );

		$this->store->set_cache( $cache );

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$this->store->query();
	}

	public function test_query_skips_cache_when_no_cache_is_true(): void {
		$engine = Mockery::mock( CacheEngineInterface::class );
		$cache  = new ObjectCache( $engine, 'test_cache' );

		// Cache should NOT be read.
		$engine->shouldNotReceive( 'get' );
		$engine->shouldNotReceive( 'set' );

		$this->store->set_cache( $cache );

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$this->store->query( [ 'no_cache' => true ] );
	}

	// ----------------------------------------------------------
	// Ordering
	// ----------------------------------------------------------

	public function test_query_validates_orderby_field(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		// 'invalid_field' should fallback to 'id'.
		$result = $this->store->query( [ 'orderby' => 'invalid_field' ] );

		$this->assertIsArray( $result );
	}

	public function test_query_sanitizes_order_direction(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		// 'INVALID' should fallback to 'DESC'.
		$result = $this->store->query( [ 'order' => 'INVALID' ] );

		$this->assertIsArray( $result );
	}

	// ----------------------------------------------------------
	// Searchable fields
	// ----------------------------------------------------------

	public function test_get_searchable_fields_returns_configured_fields(): void {
		$reflection = new \ReflectionMethod( $this->store, 'get_searchable_fields' );
		$reflection->setAccessible( true );

		$fields = $reflection->invoke( $this->store );

		$this->assertSame( [ 'title', 'description' ], $fields );
	}

	public function test_search_is_skipped_when_no_searchable_fields(): void {
		// Create a store with no searchable fields.
		$store = new class extends BaseDataStore {
			public function get_table_name(): string {
				return 'wpkit_items';
			}

			protected function get_fields_with_format(): array {
				return [ 'name' => '%s' ];
			}
		};

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		// No esc_like should be called since no searchable fields.
		$this->wpdb->shouldNotReceive( 'esc_like' );

		$result = $store->query( [ 'search' => 'test' ] );

		$this->assertIsArray( $result );
	}

	// ----------------------------------------------------------
	// count_total = false
	// ----------------------------------------------------------

	public function test_query_skips_count_when_count_total_is_false(): void {
		$this->wpdb->shouldNotReceive( 'get_var' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [ (object) [ 'id' => 1 ] ] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$result = $this->store->query( [ 'count_total' => false ] );

		$this->assertSame( 1, $result['total'] ); // Falls back to count( $items ).
	}
}
