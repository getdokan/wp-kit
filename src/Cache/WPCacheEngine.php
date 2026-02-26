<?php
/**
 * WordPress wp_cache implementation of CacheEngineInterface.
 *
 * @package WeDevs\WPKit\Cache
 */

namespace WeDevs\WPKit\Cache;

use WeDevs\WPKit\Cache\Contracts\CacheEngineInterface;

/**
 * WordPress wp_cache_* implementation of CacheEngineInterface.
 *
 * Uses CacheNameSpaceTrait for group-level invalidation that works
 * with distributed caches (Redis, Memcached).
 *
 * The consumer plugin must provide a prefix (e.g., 'dokan', 'myplugin')
 * to namespace all cache keys.
 */
class WPCacheEngine implements CacheEngineInterface {

	use CacheNameSpaceTrait;

	/**
	 * Consumer-provided cache key prefix.
	 *
	 * @var string
	 */
	protected string $cache_key_prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Consumer-provided prefix (e.g., 'dokan').
	 */
	public function __construct( string $prefix ) {
		$this->cache_key_prefix = $prefix;
	}

	/**
	 * Get the consumer-provided cache key prefix.
	 *
	 * Required by CacheNameSpaceTrait.
	 *
	 * @return string
	 */
	public function get_cache_key_prefix(): string {
		return $this->cache_key_prefix;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 */
	public function get( string $key, string $group = '' ) {
		$prefixed_key = $this->get_prefixed_key( $key, $group );
		$value        = wp_cache_get( $prefixed_key, $group );

		return false === $value ? null : $value;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string[] $keys  The cache keys.
	 * @param string   $group The cache group.
	 */
	public function get_many( array $keys, string $group = '' ): array {
		$prefix  = $this->get_cache_prefix( $group );
		$key_map = array_combine(
			$keys,
			array_map(
				function ( $key ) use ( $prefix ) {
					return $prefix . $key;
				},
				$keys
			)
		);

		$cached_values = wp_cache_get_multiple( array_values( $key_map ), $group );
		$result        = [];

		foreach ( $key_map as $key => $prefixed_key ) {
			if ( isset( $cached_values[ $prefixed_key ] ) && false !== $cached_values[ $prefixed_key ] ) {
				$result[ $key ] = $cached_values[ $prefixed_key ];
			} else {
				$result[ $key ] = null;
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $key        The cache key.
	 * @param mixed  $value      The value to cache.
	 * @param string $group      The cache group.
	 * @param int    $expiration Expiration in seconds.
	 */
	public function set( string $key, $value, string $group = '', int $expiration = 0 ): bool {
		$prefixed_key = $this->get_prefixed_key( $key, $group );

		return false !== wp_cache_set( $prefixed_key, $value, $group, $expiration );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array  $items      Associative array of key => value.
	 * @param string $group      The cache group.
	 * @param int    $expiration Expiration in seconds.
	 */
	public function set_many( array $items, string $group = '', int $expiration = 0 ): array {
		$prefix         = $this->get_cache_prefix( $group );
		$prefixed_items = [];

		foreach ( $items as $key => $value ) {
			$prefixed_items[ $prefix . $key ] = $value;
		}

		return wp_cache_set_multiple( $prefixed_items, $group, $expiration );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 */
	public function delete( string $key, string $group = '' ): bool {
		$prefixed_key = $this->get_prefixed_key( $key, $group );

		return false !== wp_cache_delete( $prefixed_key, $group );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 */
	public function exists( string $key, string $group = '' ): bool {
		$prefixed_key = $this->get_prefixed_key( $key, $group );

		return false !== wp_cache_get( $prefixed_key, $group );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $group The cache group to flush.
	 */
	public function flush_group( string $group = '' ): bool {
		return $this->invalidate_cache_group( $group );
	}
}
