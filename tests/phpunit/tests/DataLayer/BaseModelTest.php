<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;
use WeDevs\WPKit\DataLayer\Model\BaseModel;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery;

/**
 * Concrete model implementation for testing.
 */
class TestOrder extends BaseModel {

	protected string $object_type = 'order';
	protected string $hook_prefix = 'test_';
	protected string $cache_group = 'test_orders';

	protected array $data = [
		'customer_id' => 0,
		'total'       => 0.00,
		'status'      => 'pending',
		'note'        => '',
	];

	protected array $casts = [
		'customer_id' => 'int',
		'total'       => 'float',
	];

	public function get_customer_id( string $context = 'view' ): int {
		return $this->get_prop( 'customer_id', $context );
	}

	public function set_customer_id( int $id ): void {
		$this->set_prop( 'customer_id', $id );
	}

	public function get_total( string $context = 'view' ): float {
		return $this->get_prop( 'total', $context );
	}

	public function set_total( float $total ): void {
		$this->set_prop( 'total', $total );
	}

	public function get_status( string $context = 'view' ): string {
		return $this->get_prop( 'status', $context );
	}

	public function set_status( string $status ): void {
		$this->set_prop( 'status', $status );
	}

	public function get_note( string $context = 'view' ): string {
		return $this->get_prop( 'note', $context );
	}

	public function set_note( string $note ): void {
		$this->set_prop( 'note', $note );
	}
}

class BaseModelTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );
	}

	public function test_constructor_sets_default_id_to_zero(): void {
		$model = new TestOrder();

		$this->assertSame( 0, $model->get_id() );
	}

	public function test_constructor_sets_id_when_provided(): void {
		$model = new TestOrder( 42 );

		$this->assertSame( 42, $model->get_id() );
	}

	public function test_set_id_stores_absolute_integer(): void {
		$model = new TestOrder();
		$model->set_id( -5 );

		$this->assertSame( 5, $model->get_id() );
	}

	public function test_get_object_type(): void {
		$model = new TestOrder();

		$this->assertSame( 'order', $model->get_object_type() );
	}

	public function test_get_cache_group(): void {
		$model = new TestOrder();

		$this->assertSame( 'test_orders', $model->get_cache_group() );
	}

	public function test_get_data_returns_all_properties(): void {
		$model = new TestOrder();

		$data = $model->get_data();

		$this->assertArrayHasKey( 'customer_id', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'note', $data );
	}

	public function test_set_prop_before_object_read_writes_to_data(): void {
		$model = new TestOrder();
		$model->set_customer_id( 5 );

		$this->assertSame( 5, $model->get_customer_id( 'edit' ) );
		$this->assertEmpty( $model->get_changes() );
	}

	public function test_set_prop_after_object_read_writes_to_changes(): void {
		$model = new TestOrder();
		$model->set_object_read( true );
		$model->set_status( 'completed' );

		$changes = $model->get_changes();

		$this->assertArrayHasKey( 'status', $changes );
		$this->assertSame( 'completed', $changes['status'] );
	}

	public function test_set_props_calls_individual_setters(): void {
		$model = new TestOrder();
		$model->set_props( [
			'customer_id' => 10,
			'total'       => 99.99,
			'status'      => 'shipped',
		] );

		$this->assertSame( 10, $model->get_customer_id( 'edit' ) );
		$this->assertSame( 99.99, $model->get_total( 'edit' ) );
		$this->assertSame( 'shipped', $model->get_status( 'edit' ) );
	}

	public function test_set_props_skips_null_values(): void {
		$model = new TestOrder();
		$model->set_customer_id( 5 );
		$model->set_props( [ 'customer_id' => null ] );

		$this->assertSame( 5, $model->get_customer_id( 'edit' ) );
	}

	public function test_is_dirty_detects_changes(): void {
		$model = new TestOrder();
		$model->set_object_read( true );

		$this->assertFalse( $model->is_dirty() );
		$this->assertFalse( $model->is_dirty( 'status' ) );

		$model->set_status( 'completed' );

		$this->assertTrue( $model->is_dirty() );
		$this->assertTrue( $model->is_dirty( 'status' ) );
		$this->assertFalse( $model->is_dirty( 'total' ) );
	}

	public function test_apply_changes_merges_changes_into_data(): void {
		$model = new TestOrder();
		$model->set_object_read( true );
		$model->set_status( 'completed' );
		$model->set_total( 150.00 );

		$model->apply_changes();

		$this->assertEmpty( $model->get_changes() );
		$this->assertSame( 'completed', $model->get_status( 'edit' ) );
		$this->assertSame( 150.0, $model->get_total( 'edit' ) );
	}

	public function test_set_defaults_resets_data(): void {
		$model = new TestOrder();
		$model->set_customer_id( 42 );
		$model->set_status( 'shipped' );

		$model->set_defaults();

		$this->assertSame( 0, $model->get_customer_id( 'edit' ) );
		$this->assertSame( 'pending', $model->get_status( 'edit' ) );
		$this->assertFalse( $model->get_object_read() );
	}

	public function test_to_array_includes_id_and_data(): void {
		$model = new TestOrder( 42 );
		$model->set_customer_id( 10 );
		$model->set_total( 50.00 );

		$array = $model->to_array();

		$this->assertSame( 42, $array['id'] );
		$this->assertSame( 10, $array['customer_id'] );
		$this->assertSame( 50.0, $array['total'] );
	}

	public function test_type_casting_on_view_context(): void {
		$model = new TestOrder();
		$model->set_customer_id( 5 );

		// 'view' context applies type casting.
		$this->assertIsInt( $model->get_customer_id( 'view' ) );
		$this->assertIsFloat( $model->get_total( 'view' ) );
	}

	public function test_get_prop_returns_null_for_undefined_property(): void {
		$model = new TestOrder();
		// Access via reflection to test undefined prop.
		$reflection = new \ReflectionMethod( $model, 'get_prop' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $model, 'nonexistent', 'edit' );

		$this->assertNull( $result );
	}

	public function test_set_prop_ignores_undefined_property(): void {
		$model = new TestOrder();

		$reflection = new \ReflectionMethod( $model, 'set_prop' );
		$reflection->setAccessible( true );
		$reflection->invoke( $model, 'nonexistent', 'value' );

		$data = $model->get_data();
		$this->assertArrayNotHasKey( 'nonexistent', $data );
	}

	public function test_object_read_state(): void {
		$model = new TestOrder();

		$this->assertFalse( $model->get_object_read() );

		$model->set_object_read( true );
		$this->assertTrue( $model->get_object_read() );

		$model->set_object_read( false );
		$this->assertFalse( $model->get_object_read() );
	}

	public function test_save_fires_before_and_after_hooks(): void {
		$store = Mockery::mock( DataStoreInterface::class );
		$store->shouldReceive( 'create' )->once();

		$model = new TestOrder();

		// Inject data store via reflection.
		$ref = new \ReflectionProperty( $model, 'data_store' );
		$ref->setAccessible( true );
		$ref->setValue( $model, $store );

		Actions\expectDone( 'test_before_order_save' )
			->once()
			->with( $model, $store );

		Actions\expectDone( 'test_after_order_save' )
			->once()
			->with( $model, $store );

		$model->save();
	}

	public function test_save_calls_create_for_new_model(): void {
		$store = Mockery::mock( DataStoreInterface::class );
		$store->shouldReceive( 'create' )->once();

		$model = new TestOrder();

		$ref = new \ReflectionProperty( $model, 'data_store' );
		$ref->setAccessible( true );
		$ref->setValue( $model, $store );

		$model->save();
	}

	public function test_save_calls_update_for_existing_model(): void {
		$store = Mockery::mock( DataStoreInterface::class );
		$store->shouldReceive( 'update' )->once();

		$model = new TestOrder( 42 );

		$ref = new \ReflectionProperty( $model, 'data_store' );
		$ref->setAccessible( true );
		$ref->setValue( $model, $store );

		$model->save();
	}

	public function test_save_returns_id_when_no_data_store(): void {
		$model = new TestOrder( 42 );

		$this->assertSame( 42, $model->save() );
	}

	public function test_delete_calls_data_store_delete(): void {
		$store = Mockery::mock( DataStoreInterface::class );
		$store->shouldReceive( 'delete' )->once();

		Filters\expectApplied( 'test_pre_delete_order' )
			->once()
			->andReturn( null );

		$model = new TestOrder( 42 );

		$ref = new \ReflectionProperty( $model, 'data_store' );
		$ref->setAccessible( true );
		$ref->setValue( $model, $store );

		$result = $model->delete();

		$this->assertTrue( $result );
		$this->assertSame( 0, $model->get_id() );
	}

	public function test_delete_short_circuits_when_filter_returns_non_null(): void {
		Filters\expectApplied( 'test_pre_delete_order' )
			->once()
			->andReturn( false );

		$model = new TestOrder( 42 );

		$store = Mockery::mock( DataStoreInterface::class );
		$store->shouldNotReceive( 'delete' );

		$ref = new \ReflectionProperty( $model, 'data_store' );
		$ref->setAccessible( true );
		$ref->setValue( $model, $store );

		$result = $model->delete();

		$this->assertFalse( $result );
	}

	public function test_get_data_includes_pending_changes(): void {
		$model = new TestOrder();
		$model->set_object_read( true );
		$model->set_status( 'shipped' );

		$data = $model->get_data();

		$this->assertSame( 'shipped', $data['status'] );
	}
}
