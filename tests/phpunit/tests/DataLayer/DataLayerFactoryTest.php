<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\Cache\WPCacheEngine;
use WeDevs\WPKit\DataLayer\DataLayerFactory;
use WeDevs\WPKit\DataLayer\DataStore\BaseDataStore;
use WeDevs\WPKit\DataLayer\Model\BaseModel;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Concrete model for factory testing.
 */
class FactoryTestModel extends BaseModel {
	protected string $object_type  = 'factory_item';
	protected string $hook_prefix  = 'test_';
	protected string $cache_group  = 'test_factory_items';
	protected array $data          = [ 'name' => '' ];
}

/**
 * Concrete data store for factory testing.
 */
class FactoryTestStore extends BaseDataStore {

	public function get_table_name(): string {
		return 'test_items';
	}

	protected function get_fields_with_format(): array {
		return [ 'name' => '%s' ];
	}
}

class DataLayerFactoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		DataLayerFactory::reset();

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
	}

	protected function tearDown(): void {
		DataLayerFactory::reset();
		parent::tearDown();
	}

	public function test_init_sets_prefix(): void {
		DataLayerFactory::init( 'myplugin' );

		$this->assertSame( 'myplugin', DataLayerFactory::get_prefix() );
	}

	public function test_register_store_and_make_store(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );

		$store = DataLayerFactory::make_store( FactoryTestModel::class );

		$this->assertInstanceOf( FactoryTestStore::class, $store );
	}

	public function test_make_store_returns_null_for_unregistered(): void {
		DataLayerFactory::init( 'myplugin' );

		$store = DataLayerFactory::make_store( 'NonExistentModel' );

		$this->assertNull( $store );
	}

	public function test_make_store_returns_same_instance_on_repeated_calls(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );

		$store1 = DataLayerFactory::make_store( FactoryTestModel::class );
		$store2 = DataLayerFactory::make_store( FactoryTestModel::class );

		$this->assertSame( $store1, $store2 );
	}

	public function test_make_model_creates_instance(): void {
		DataLayerFactory::init( 'myplugin' );

		Functions\when( 'absint' )->alias( function ( $val ) {
			return abs( (int) $val );
		} );

		$model = DataLayerFactory::make_model( FactoryTestModel::class, 42 );

		$this->assertInstanceOf( FactoryTestModel::class, $model );
		$this->assertSame( 42, $model->get_id() );
	}

	public function test_get_cache_engine_returns_wp_cache_engine(): void {
		DataLayerFactory::init( 'myplugin' );

		$engine = DataLayerFactory::get_cache_engine();

		$this->assertInstanceOf( WPCacheEngine::class, $engine );
		$this->assertSame( 'myplugin', $engine->get_cache_key_prefix() );
	}

	public function test_get_cache_engine_returns_same_instance(): void {
		DataLayerFactory::init( 'myplugin' );

		$engine1 = DataLayerFactory::get_cache_engine();
		$engine2 = DataLayerFactory::get_cache_engine();

		$this->assertSame( $engine1, $engine2 );
	}

	public function test_reset_clears_all_state(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );
		DataLayerFactory::make_store( FactoryTestModel::class );
		DataLayerFactory::get_cache_engine();

		DataLayerFactory::reset();

		$this->assertSame( '', DataLayerFactory::get_prefix() );
		$this->assertNull( DataLayerFactory::make_store( FactoryTestModel::class ) );
	}

	public function test_make_store_auto_configures_hook_prefix(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );

		$store = DataLayerFactory::make_store( FactoryTestModel::class );

		// The hook prefix should be set to 'myplugin_test_items_'.
		$reflection = new \ReflectionProperty( $store, 'hook_prefix' );
		$reflection->setAccessible( true );

		$this->assertSame( 'myplugin_test_items_', $reflection->getValue( $store ) );
	}

	public function test_make_store_auto_configures_filter_prefix(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );

		$store = DataLayerFactory::make_store( FactoryTestModel::class );

		$reflection = new \ReflectionProperty( $store, 'filter_prefix' );
		$reflection->setAccessible( true );

		$this->assertSame( 'myplugin', $reflection->getValue( $store ) );
	}

	public function test_make_store_auto_configures_cache_when_model_has_cache_group(): void {
		DataLayerFactory::init( 'myplugin' );
		DataLayerFactory::register_store( FactoryTestModel::class, FactoryTestStore::class );

		$store = DataLayerFactory::make_store( FactoryTestModel::class );
		$cache = $store->get_cache();

		$this->assertNotNull( $cache );
		$this->assertSame( 'test_factory_items', $cache->get_object_type() );
	}
}
