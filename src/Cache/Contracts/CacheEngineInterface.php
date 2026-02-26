<?php

namespace WeDevs\WPKit\Cache\Contracts;

/**
 * Interface for cache engines used by ObjectCache.
 *
 * Inspired by WooCommerce's CacheEngine interface.
 */
interface CacheEngineInterface {

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 *
	 * @return mixed|null The cached value, or null if not found.
	 */
	public function get( string $key, string $group = '' );

	/**
	 * Retrieve multiple cached values.
	 *
	 * @param string[] $keys  The cache keys.
	 * @param string   $group The cache group.
	 *
	 * @return array Associative array of key => value, null for cache misses.
	 */
	public function get_many( array $keys, string $group = '' ): array;

	/**
	 * Store a value in cache.
	 *
	 * @param string $key        The cache key.
	 * @param mixed  $value      The value to cache.
	 * @param string $group      The cache group.
	 * @param int    $expiration Expiration in seconds.
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, $value, string $group = '', int $expiration = 0 ): bool;

	/**
	 * Store multiple values in cache.
	 *
	 * @param array  $items      Associative array of key => value.
	 * @param string $group      The cache group.
	 * @param int    $expiration Expiration in seconds.
	 *
	 * @return array Associative array of key => bool success.
	 */
	public function set_many( array $items, string $group = '', int $expiration = 0 ): array;

	/**
	 * Delete a cached value.
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 *
	 * @return bool True on success.
	 */
	public function delete( string $key, string $group = '' ): bool;

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key   The cache key.
	 * @param string $group The cache group.
	 *
	 * @return bool True if cached.
	 */
	public function exists( string $key, string $group = '' ): bool;

	/**
	 * Invalidate all cached values in a group.
	 *
	 * @param string $group The cache group to flush.
	 *
	 * @return bool True on success.
	 */
	public function flush_group( string $group = '' ): bool;
}
