<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;
use WeDevs\WPKit\DataLayer\DataLayerFactory;
use WeDevs\WPKit\DataLayer\Model\BaseModel;
use WeDevs\WPKit\DataLayer\DataStore\BaseDataStore;
use WeDevs\WPKit\DataLayer\QueryResult;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test model for query tests.
 */
class QueryTask extends BaseModel {

	protected string $object_type = 'task';
	protected string $hook_prefix = 'test_';

	protected array $data = [
		'title'  => '',
		'status' => 'pending',
	];

	public function get_title( string $context = 'view' ): string {
		return $this->get_prop( 'title', $context );
	}

	public function set_title( string $title ): void {
		$this->set_prop( 'title', $title );
	}

	public function get_status( string $context = 'view' ): string {
		return $this->get_prop( 'status', $context );
	}

	public function set_status( string $status ): void {
		$this->set_prop( 'status', $status );
	}
}

/**
 * Test data store for query tests.
 */
class QueryTaskStore extends BaseDataStore {

	public function get_table_name(): string {
		return 'wpkit_tasks';
	}

	protected function get_fields_with_format(): array {
		return [
			'title'  => '%s',
			'status' => '%s',
		];
	}
}

class BaseModelQueryTest extends TestCase {

	private object $wpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );

		Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

		Functions\when( 'wp_json_encode' )->alias( function ( $data ) {
			return json_encode( $data );
		} );

		// Register store.
		DataLayerFactory::reset();
		DataLayerFactory::init( 'test' );
		DataLayerFactory::register_store( QueryTask::class, QueryTaskStore::class );
	}

	protected function tearDown(): void {
		DataLayerFactory::reset();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_query_returns_query_result_instance(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$result = QueryTask::query();

		$this->assertInstanceOf( QueryResult::class, $result );
	}

	public function test_query_hydrates_models(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'Task One', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'title' => 'Task Two', 'status' => 'completed' ],
		];

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$result = QueryTask::query();

		$this->assertCount( 2, $result );
		$this->assertSame( 2, $result->total() );

		$first = $result->first();
		$this->assertInstanceOf( QueryTask::class, $first );
		$this->assertSame( 1, $first->get_id() );
		$this->assertSame( 'Task One', $first->get_title( 'edit' ) );
		$this->assertTrue( $first->get_object_read() );
		$this->assertNotNull( $first->get_data_store() );
	}

	public function test_query_models_are_saveable(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'Original', 'status' => 'pending' ],
		];

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '1' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$result = QueryTask::query();
		$task   = $result->first();

		// Model has data_store, so set_prop tracks changes.
		$task->set_title( 'Updated' );
		$this->assertTrue( $task->is_dirty( 'title' ) );
		$this->assertSame( 'Updated', $task->get_title( 'edit' ) );
	}

	public function test_query_with_pagination(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '55' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( [
			(object) [ 'id' => 11, 'title' => 'Task', 'status' => 'pending' ],
		] );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$result = QueryTask::query( [ 'per_page' => 10, 'page' => 2 ] );

		$this->assertSame( 55, $result->total() );
		$this->assertSame( 10, $result->per_page() );
		$this->assertSame( 2, $result->current_page() );
		$this->assertSame( 6, $result->total_pages() );
		$this->assertTrue( $result->has_more() );
	}

	public function test_query_iterable(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'A', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'title' => 'B', 'status' => 'pending' ],
		];

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$titles = [];
		foreach ( QueryTask::query() as $task ) {
			$titles[] = $task->get_title( 'edit' );
		}

		$this->assertSame( [ 'A', 'B' ], $titles );
	}

	public function test_all_returns_array_of_models(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'A', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'title' => 'B', 'status' => 'done' ],
		];

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$tasks = QueryTask::all( [ 'status' => 'pending' ] );

		$this->assertIsArray( $tasks );
		$this->assertCount( 2, $tasks );
		$this->assertInstanceOf( QueryTask::class, $tasks[0] );
	}

	public function test_count_returns_integer(): void {
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '42' );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$count = QueryTask::count( [ 'status' => 'pending' ] );

		$this->assertSame( 42, $count );
	}

	public function test_query_returns_empty_result_without_store(): void {
		DataLayerFactory::reset();
		DataLayerFactory::init( 'test' );
		// No store registered.

		$result = QueryTask::query();

		$this->assertInstanceOf( QueryResult::class, $result );
		$this->assertTrue( $result->is_empty() );
		$this->assertSame( 0, $result->total() );
	}

	public function test_pluck_on_query_result(): void {
		$rows = [
			(object) [ 'id' => 1, 'title' => 'Alpha', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'title' => 'Beta', 'status' => 'done' ],
		];

		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '2' );
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( function () {
			return func_get_args()[0];
		} );

		$titles = QueryTask::query()->pluck( 'get_title' );

		$this->assertSame( [ 'Alpha', 'Beta' ], $titles );
	}
}
