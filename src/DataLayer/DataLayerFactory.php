<?php
/**
 * Static factory for creating models and data stores.
 *
 * @package WeDevs\WPKit\DataLayer
 */

namespace WeDevs\WPKit\DataLayer;

use WeDevs\WPKit\Cache\ObjectCache;
use WeDevs\WPKit\Cache\WPCacheEngine;
use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;

/**
 * Static factory for creating models and data stores without a DI container.
 *
 * Consumer plugins must call `init()` with their prefix before using other methods.
 */
class DataLayerFactory {

	/**
	 * Consumer-provided prefix for cache keys and hooks.
	 *
	 * @var string
	 */
	private static string $prefix = '';

	/**
	 * Model class => DataStore class mapping.
	 *
	 * @var array<string, string>
	 */
	private static array $store_map = [];

	/**
	 * Cached data store instances.
	 *
	 * @var array<string, DataStoreInterface>
	 */
	private static array $store_instances = [];

	/**
	 * Shared cache engine instance.
	 *
	 * @var WPCacheEngine|null
	 */
	private static ?WPCacheEngine $cache_engine = null;

	/**
	 * Initialize the factory with the consumer plugin's prefix.
	 *
	 * Must be called before using any other methods.
	 *
	 * @param string $prefix Consumer-provided prefix (e.g., 'dokan').
	 */
	public static function init( string $prefix ): void {
		static::$prefix = $prefix;
	}

	/**
	 * Register a data store for a model class.
	 *
	 * @param string $model_class The model class name.
	 * @param string $store_class The data store class name.
	 */
	public static function register_store( string $model_class, string $store_class ): void {
		static::$store_map[ $model_class ] = $store_class;
	}

	/**
	 * Create or retrieve a data store instance for a model.
	 *
	 * Automatically sets up ObjectCache if the model has a cache_group property.
	 *
	 * @param string $model_class The model class name.
	 *
	 * @return DataStoreInterface|null
	 */
	public static function make_store( string $model_class ): ?DataStoreInterface {
		if ( isset( static::$store_instances[ $model_class ] ) ) {
			return static::$store_instances[ $model_class ];
		}

		$store_class = static::$store_map[ $model_class ] ?? null;

		if ( ! $store_class || ! class_exists( $store_class ) ) {
			return null;
		}

		$store = new $store_class();

		// Auto-setup hook and filter prefixes.
		if ( method_exists( $store, 'set_hook_prefix' ) && static::$prefix ) {
			$table_name = method_exists( $store, 'get_table_name' ) ? $store->get_table_name() : '';
			$store->set_hook_prefix( static::$prefix . '_' . $table_name . '_' );
		}

		if ( method_exists( $store, 'set_filter_prefix' ) && static::$prefix ) {
			$store->set_filter_prefix( static::$prefix );
		}

		// Auto-setup ObjectCache if model has a cache_group.
		if ( method_exists( $store, 'set_cache' ) && class_exists( $model_class ) ) {
			$cache_group = static::get_model_cache_group( $model_class );

			if ( $cache_group ) {
				$engine = static::get_cache_engine();
				$cache  = new ObjectCache( $engine, $cache_group );
				$store->set_cache( $cache );
			}
		}

		static::$store_instances[ $model_class ] = $store;

		return $store;
	}

	/**
	 * Create a model instance.
	 *
	 * @param string $model_class The model class name.
	 * @param int    $id          Optional object ID.
	 *
	 * @return object
	 */
	public static function make_model( string $model_class, int $id = 0 ): object {
		return new $model_class( $id );
	}

	/**
	 * Get the shared cache engine instance.
	 *
	 * @return WPCacheEngine
	 */
	public static function get_cache_engine(): WPCacheEngine {
		if ( null === static::$cache_engine ) {
			static::$cache_engine = new WPCacheEngine( static::$prefix );
		}

		return static::$cache_engine;
	}

	/**
	 * Get the configured prefix.
	 *
	 * @return string
	 */
	public static function get_prefix(): string {
		return static::$prefix;
	}

	/**
	 * Get the cache group from a model class.
	 *
	 * @param string $model_class The model class name.
	 *
	 * @return string Empty string if no cache group defined.
	 */
	private static function get_model_cache_group( string $model_class ): string {
		try {
			$reflection = new \ReflectionClass( $model_class );
			$property   = $reflection->getProperty( 'cache_group' );
			$property->setAccessible( true );

			$instance = $reflection->newInstanceWithoutConstructor();

			return $property->getValue( $instance ) ? $property->getValue( $instance ) : '';
		} catch ( \ReflectionException $e ) {
			return '';
		}
	}

	/**
	 * Reset all registered stores (useful for testing).
	 */
	public static function reset(): void {
		static::$prefix          = '';
		static::$store_map       = [];
		static::$store_instances = [];
		static::$cache_engine    = null;
	}
}
