<?php

namespace WeDevs\WPKit\DataLayer\Model;

use WeDevs\WPKit\DataLayer\Contracts\ModelInterface;
use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;
use WeDevs\WPKit\DataLayer\DataLayerFactory;
use WeDevs\WPKit\DataLayer\DateTime;
use WeDevs\WPKit\DataLayer\QueryResult;

/**
 * Base model class for WPKit data layer.
 *
 * Provides property management, change tracking, type casting, and CRUD
 * operations without requiring WooCommerce's WC_Data.
 */
abstract class BaseModel implements ModelInterface {

	/**
	 * Object ID.
	 *
	 * @var int
	 */
	protected int $id = 0;

	/**
	 * Object type identifier (e.g., 'vendor_balance').
	 *
	 * @var string
	 */
	protected string $object_type = 'data';

	/**
	 * Core data with defaults. Subclasses define this.
	 *
	 * @var array
	 */
	protected array $data = [];

	/**
	 * Pending changes not yet committed.
	 *
	 * @var array
	 */
	protected array $changes = [];

	/**
	 * Snapshot of $data at construct time for reset.
	 *
	 * @var array
	 */
	protected array $default_data = [];

	/**
	 * Whether the object has been read from the database.
	 *
	 * @var bool
	 */
	protected bool $object_read = false;

	/**
	 * Data store instance.
	 *
	 * @var DataStoreInterface|null
	 */
	protected ?DataStoreInterface $data_store = null;

	/**
	 * Cache group for invalidation.
	 *
	 * @var string
	 */
	protected string $cache_group = '';

	/**
	 * Type casting map. Keys are prop names, values are types.
	 * Supported: 'int', 'float', 'string', 'bool', 'date', 'array'.
	 *
	 * @var array
	 */
	protected array $casts = [];

	/**
	 * Hook prefix (e.g., 'dokan_'). Must be set by consumer subclasses.
	 *
	 * @var string
	 */
	protected string $hook_prefix = '';

	/**
	 * Constructor.
	 *
	 * @param int $id Optional object ID.
	 */
	public function __construct( int $id = 0 ) {
		$this->default_data = $this->data;

		if ( $id > 0 ) {
			$this->set_id( $id );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_id( int $id ): void {
		$this->id = absint( $id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_object_read(): bool {
		return $this->object_read;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_object_read( bool $read = true ): void {
		$this->object_read = $read;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_data_store(): ?DataStoreInterface {
		return $this->data_store;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_object_type(): string {
		return $this->object_type;
	}

	/**
	 * Get the cache group.
	 *
	 * @return string
	 */
	public function get_cache_group(): string {
		return $this->cache_group;
	}

	/**
	 * Get a property value.
	 *
	 * @param string $prop    Property name.
	 * @param string $context 'view' or 'edit'.
	 *
	 * @return mixed
	 */
	protected function get_prop( string $prop, string $context = 'view' ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = array_key_exists( $prop, $this->changes )
				? $this->changes[ $prop ]
				: $this->data[ $prop ];

			if ( 'view' === $context ) {
				$value = apply_filters(
					$this->get_hook_prefix() . $prop,
					$value,
					$this
				);
			}
		}

		if ( 'view' === $context && isset( $this->casts[ $prop ] ) ) {
			$value = $this->cast_value( $value, $this->casts[ $prop ] );
		}

		return $value;
	}

	/**
	 * Set a property value.
	 *
	 * @param string $prop  Property name.
	 * @param mixed  $value Property value.
	 */
	protected function set_prop( string $prop, $value ): void {
		if ( ! array_key_exists( $prop, $this->data ) ) {
			return;
		}

		if ( $this->object_read ) {
			if ( $value !== $this->data[ $prop ] || array_key_exists( $prop, $this->changes ) ) {
				$this->changes[ $prop ] = $value;
			}
		} else {
			$this->data[ $prop ] = $value;
		}
	}

	/**
	 * Set multiple properties.
	 *
	 * @param array $props Associative array of prop => value.
	 */
	public function set_props( array $props ): void {
		foreach ( $props as $prop => $value ) {
			if ( is_null( $value ) ) {
				continue;
			}

			$setter = "set_{$prop}";

			if ( is_callable( [ $this, $setter ] ) ) {
				$this->{$setter}( $value );
			}
		}
	}

	/**
	 * Set a date property from various formats.
	 *
	 * @param string $prop  Property name.
	 * @param mixed  $value Date value (string, timestamp, DateTimeInterface, or null).
	 */
	protected function set_date_prop( string $prop, $value ): void {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			$this->set_prop( $prop, null );
			return;
		}

		if ( $value instanceof DateTime || $value instanceof \DateTimeInterface ) {
			$this->set_prop( $prop, $value );
		} elseif ( is_numeric( $value ) ) {
			$this->set_prop( $prop, DateTime::from_timestamp( (int) $value ) );
		} elseif ( is_string( $value ) ) {
			$this->set_prop( $prop, DateTime::from_db_string( $value ) );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_changes(): array {
		return $this->changes;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_data(): array {
		return array_merge( $this->data, $this->changes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_changes(): void {
		$this->data    = array_replace( $this->data, $this->changes );
		$this->changes = [];
	}

	/**
	 * Reset data to defaults.
	 */
	public function set_defaults(): void {
		$this->data    = $this->default_data;
		$this->changes = [];
		$this->set_object_read( false );
	}

	/**
	 * Check if the model or a specific property has been modified.
	 *
	 * @param string $prop Optional property name.
	 *
	 * @return bool
	 */
	public function is_dirty( string $prop = '' ): bool {
		if ( $prop ) {
			return array_key_exists( $prop, $this->changes );
		}

		return ! empty( $this->changes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function save(): int {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		do_action( $this->hook_prefix . 'before_' . $this->object_type . '_save', $this, $this->data_store );

		if ( $this->get_id() ) {
			$this->data_store->update( $this );
		} else {
			$this->data_store->create( $this );
		}

		$this->clear_cache_group();

		do_action( $this->hook_prefix . 'after_' . $this->object_type . '_save', $this, $this->data_store );

		return $this->get_id();
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( bool $force_delete = false ): bool {
		$check = apply_filters( $this->hook_prefix . 'pre_delete_' . $this->object_type, null, $this, $force_delete );

		if ( null !== $check ) {
			return (bool) $check;
		}

		if ( $this->data_store ) {
			$this->data_store->delete( $this, [ 'force_delete' => $force_delete ] );
			$this->set_id( 0 );
			$this->clear_cache_group();

			return true;
		}

		return false;
	}

	/**
	 * Delete records matching criteria.
	 *
	 * @param array $data Conditions for deletion.
	 *
	 * @return bool
	 */
	public static function delete_by( array $data ): bool {
		$object  = new static();
		$deleted = $object->data_store->delete_by( $data );

		if ( $deleted ) {
			$object->clear_cache_group();
		}

		return (bool) $deleted;
	}

	/**
	 * Find a model by ID. Auto-resolves data store and reads from DB/cache.
	 *
	 * Usage: $task = Task::find( 1 );
	 *
	 * @param int $id Object ID.
	 *
	 * @return static|null The populated model, or null if not found.
	 */
	public static function find( int $id ): ?self {
		$store = DataLayerFactory::make_store( static::class );

		if ( ! $store ) {
			return null;
		}

		$model = DataLayerFactory::make_model( static::class, $id );
		$model->data_store = $store;

		try {
			$store->read( $model );
		} catch ( \Exception $e ) {
			return null;
		}

		return $model;
	}

	/**
	 * Query records and return hydrated model instances in a QueryResult.
	 *
	 * Usage:
	 *   $result = Task::query( [ 'status' => 'pending', 'per_page' => 10 ] );
	 *
	 *   foreach ( $result as $task ) {
	 *       echo $task->get_title();
	 *   }
	 *
	 *   $result->total();        // 55
	 *   $result->total_pages();  // 6
	 *   $result->current_page(); // 1
	 *   $result->has_more();     // true
	 *
	 * @param array $args Query arguments (see BaseDataStore::query()).
	 *
	 * @return QueryResult<static>
	 */
	public static function query( array $args = [] ): QueryResult {
		$store = DataLayerFactory::make_store( static::class );

		if ( ! $store ) {
			return new QueryResult( [], 0, $args['per_page'] ?? 20, $args['page'] ?? 1, 0 );
		}

		$raw = $store->query( $args );

		// Hydrate raw rows into model instances.
		$items = [];

		if ( is_array( $raw['items'] ) ) {
			$id_field = $store->get_id_field_name();

			foreach ( $raw['items'] as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}

				$model = new static();
				$model->data_store = $store;
				$model->set_id( (int) ( $row->{$id_field} ?? 0 ) );
				$model->set_props( (array) $row );
				$model->set_object_read( true );

				$items[] = $model;
			}
		}

		return new QueryResult(
			$items,
			(int) $raw['total'],
			(int) $raw['per_page'],
			(int) $raw['current_page'],
			(int) $raw['total_pages']
		);
	}

	/**
	 * Get all records matching criteria (no pagination).
	 *
	 * Usage: $tasks = Task::all( [ 'status' => 'pending', 'orderby' => 'priority' ] );
	 *
	 * @param array $args Query arguments (per_page defaults to -1).
	 *
	 * @return static[]
	 */
	public static function all( array $args = [] ): array {
		$args['per_page'] = $args['per_page'] ?? -1;

		return static::query( $args )->items();
	}

	/**
	 * Count records matching criteria.
	 *
	 * Usage: $count = Task::count( [ 'status' => 'pending' ] );
	 *
	 * @param array $args Query arguments.
	 *
	 * @return int
	 */
	public static function count( array $args = [] ): int {
		$args['return'] = 'count';

		return static::query( $args )->total();
	}

	/**
	 * Get the hook prefix for filters.
	 *
	 * @return string
	 */
	protected function get_hook_prefix(): string {
		return $this->hook_prefix . $this->object_type . '_get_';
	}

	/**
	 * Clear the cache group for this model.
	 */
	public function clear_cache_group(): void {
		if ( ! $this->cache_group ) {
			return;
		}

		if ( $this->data_store && method_exists( $this->data_store, 'get_cache' ) ) {
			$cache = $this->data_store->get_cache();

			if ( $cache ) {
				$cache->flush();
				return;
			}
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $this->cache_group );
		}
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data       = $this->get_data();
		$data['id'] = $this->get_id();

		return $data;
	}

	/**
	 * Cast a value to the specified type.
	 *
	 * @param mixed  $value The value to cast.
	 * @param string $type  The target type.
	 *
	 * @return mixed
	 */
	protected function cast_value( $value, string $type ) {
		switch ( $type ) {
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
			case 'string':
				return (string) $value;
			case 'bool':
				return (bool) $value;
			case 'array':
				return (array) $value;
			case 'date':
				if ( $value instanceof \DateTimeInterface ) {
					return $value;
				}

				return DateTime::from_db_string( (string) $value );
			default:
				return $value;
		}
	}
}
