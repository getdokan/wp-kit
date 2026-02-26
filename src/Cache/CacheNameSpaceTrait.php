<?php

namespace WeDevs\WPKit\Cache;

/**
 * Implements namespacing algorithm for wp_cache group invalidation.
 *
 * Uses a microtime-based prefix stored in cache itself. To invalidate a group,
 * the prefix is regenerated, making all old keys inaccessible.
 *
 * Works correctly with distributed caches (Redis, Memcached) where
 * wp_cache_flush_group() may not exist.
 *
 * Classes using this trait MUST define a `get_cache_key_prefix()` method
 * that returns the consumer-provided prefix (e.g., 'dokan', 'myplugin').
 *
 * @see https://github.com/memcached/memcached/wiki/ProgrammingTricks#namespacing
 */
trait CacheNameSpaceTrait {

	/**
	 * Get the cache prefix for a group.
	 *
	 * @param string $group Cache group.
	 *
	 * @return string The prefix string.
	 */
	public function get_cache_prefix( string $group ): string {
		$key_prefix = $this->get_cache_key_prefix();
		$prefix     = wp_cache_get( $key_prefix . '_' . $group . '_prefix', $group );

		if ( false === $prefix ) {
			$prefix = microtime();
			wp_cache_set( $key_prefix . '_' . $group . '_prefix', $prefix, $group );
		}

		return $key_prefix . '_cache_' . $prefix . '_';
	}

	/**
	 * Invalidate all cached values in a group by regenerating the prefix.
	 *
	 * @param string $group Cache group to invalidate.
	 *
	 * @return bool True on success.
	 */
	public function invalidate_cache_group( string $group ): bool {
		$key_prefix = $this->get_cache_key_prefix();

		return false !== wp_cache_set( $key_prefix . '_' . $group . '_prefix', microtime(), $group );
	}

	/**
	 * Get a prefixed cache key.
	 *
	 * @param string $key   The original key.
	 * @param string $group The cache group.
	 *
	 * @return string The prefixed key.
	 */
	public function get_prefixed_key( string $key, string $group ): string {
		return $this->get_cache_prefix( $group ) . $key;
	}
}
