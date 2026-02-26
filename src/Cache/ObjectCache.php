<?php
/**
 * Typed object cache with TTL support.
 *
 * @package WeDevs\WPKit\Cache
 */

namespace WeDevs\WPKit\Cache;

use WeDevs\WPKit\Cache\Contracts\CacheEngineInterface;

/**
 * Typed object cache with TTL support.
 *
 * Provides a high-level caching API for specific object types (models, data stores).
 * Objects are identified by integer or string IDs. Cache keys are automatically
 * namespaced by object type and prefixed for group invalidation.
 */
class ObjectCache {

	/**
	 * Use default expiration value.
	 */
	const DEFAULT_EXPIRATION = -1;

	/**
	 * Maximum expiration time in seconds.
	 */
	const MAX_EXPIRATION = MONTH_IN_SECONDS;

	/**
	 * Default expiration in seconds.
	 *
	 * @var int
	 */
	protected int $default_expiration = HOUR_IN_SECONDS;

	/**
	 * The cache engine instance.
	 *
	 * @var CacheEngineInterface
	 */
	protected CacheEngineInterface $engine;

	/**
	 * The object type identifier used as cache group.
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Constructor.
	 *
	 * @param CacheEngineInterface $engine      The cache engine to use.
	 * @param string               $object_type Object type identifier (used as cache group).
	 * @param int                  $expiration  Default expiration in seconds.
	 */
	public function __construct( CacheEngineInterface $engine, string $object_type, int $expiration = HOUR_IN_SECONDS ) {
		$this->engine             = $engine;
		$this->object_type        = $object_type;
		$this->default_expiration = $expiration;
	}

	/**
	 * Get the object type / cache group.
	 *
	 * @return string
	 */
	public function get_object_type(): string {
		return $this->object_type;
	}

	/**
	 * Get a cached object by ID.
	 *
	 * If not cached and a callback is provided, the callback is called to fetch the object,
	 * which is then cached and returned.
	 *
	 * @param int|string    $id       Object ID.
	 * @param callable|null $callback Optional callback to fetch on cache miss. Receives $id as argument.
	 * @param int           $expiration Expiration in seconds, or DEFAULT_EXPIRATION.
	 *
	 * @return mixed|null The cached object, or null if not found.
	 */
	public function get( $id, ?callable $callback = null, int $expiration = self::DEFAULT_EXPIRATION ) {
		$data = $this->engine->get( (string) $id, $this->object_type );

		if ( null !== $data ) {
			return $data;
		}

		if ( $callback ) {
			$object = $callback( $id );

			if ( null !== $object ) {
				$this->set( $object, $id, $expiration );
				return $object;
			}
		}

		return null;
	}

	/**
	 * Get multiple cached objects by IDs.
	 *
	 * @param array $ids Array of object IDs.
	 *
	 * @return array Associative array of id => object, null for misses.
	 */
	public function get_many( array $ids ): array {
		$string_ids = array_map( 'strval', $ids );

		return $this->engine->get_many( $string_ids, $this->object_type );
	}

	/**
	 * Cache an object.
	 *
	 * @param mixed      $object     The object to cache.
	 * @param int|string $id         Object ID.
	 * @param int        $expiration Expiration in seconds, or DEFAULT_EXPIRATION.
	 *
	 * @return bool True on success.
	 */
	public function set( $object, $id, int $expiration = self::DEFAULT_EXPIRATION ): bool {
		$ttl = self::DEFAULT_EXPIRATION === $expiration ? $this->default_expiration : $expiration;

		if ( $ttl > self::MAX_EXPIRATION ) {
			$ttl = self::MAX_EXPIRATION;
		}

		return $this->engine->set( (string) $id, $object, $this->object_type, $ttl );
	}

	/**
	 * Cache multiple objects.
	 *
	 * @param array $objects    Associative array of id => object.
	 * @param int   $expiration Expiration in seconds, or DEFAULT_EXPIRATION.
	 *
	 * @return array Associative array of id => bool success.
	 */
	public function set_many( array $objects, int $expiration = self::DEFAULT_EXPIRATION ): array {
		$ttl = self::DEFAULT_EXPIRATION === $expiration ? $this->default_expiration : $expiration;

		if ( $ttl > self::MAX_EXPIRATION ) {
			$ttl = self::MAX_EXPIRATION;
		}

		$string_keyed = [];
		foreach ( $objects as $id => $object ) {
			$string_keyed[ (string) $id ] = $object;
		}

		return $this->engine->set_many( $string_keyed, $this->object_type, $ttl );
	}

	/**
	 * Remove a cached object.
	 *
	 * @param int|string $id Object ID.
	 *
	 * @return bool True on success.
	 */
	public function remove( $id ): bool {
		return $this->engine->delete( (string) $id, $this->object_type );
	}

	/**
	 * Flush all cached objects of this type.
	 *
	 * @return bool True on success.
	 */
	public function flush(): bool {
		return $this->engine->flush_group( $this->object_type );
	}

	/**
	 * Check if an object is cached.
	 *
	 * @param int|string $id Object ID.
	 *
	 * @return bool True if cached.
	 */
	public function is_cached( $id ): bool {
		return $this->engine->exists( (string) $id, $this->object_type );
	}

	/**
	 * Get the cache engine instance.
	 *
	 * @return CacheEngineInterface
	 */
	public function get_engine(): CacheEngineInterface {
		return $this->engine;
	}
}
