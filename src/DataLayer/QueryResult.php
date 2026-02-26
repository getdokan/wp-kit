<?php

namespace WeDevs\WPKit\DataLayer;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Query result object with pagination metadata.
 *
 * Wraps query results with convenient accessors. Iterable and countable,
 * so you can foreach over it or count() it directly.
 *
 * Usage:
 *   $result = Task::query( [ 'status' => 'pending', 'per_page' => 10 ] );
 *
 *   foreach ( $result as $task ) {
 *       echo $task->get_title();
 *   }
 *
 *   $result->total();        // 55 (total matching rows)
 *   $result->total_pages();  // 6
 *   $result->current_page(); // 1
 *   $result->per_page();     // 10
 *   $result->has_more();     // true
 *   $result->items();        // Task[] array
 *   count( $result );        // 10 (items on current page)
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class QueryResult implements IteratorAggregate, Countable {

	/**
	 * @var T[]
	 */
	private array $items;

	private int $total;
	private int $per_page;
	private int $current_page;
	private int $total_pages;

	/**
	 * @param T[] $items        Items on current page.
	 * @param int $total        Total matching rows.
	 * @param int $per_page     Items per page.
	 * @param int $current_page Current page number.
	 * @param int $total_pages  Total number of pages.
	 */
	public function __construct(
		array $items,
		int $total,
		int $per_page,
		int $current_page,
		int $total_pages
	) {
		$this->items        = $items;
		$this->total        = $total;
		$this->per_page     = $per_page;
		$this->current_page = $current_page;
		$this->total_pages  = $total_pages;
	}

	/**
	 * Create from a BaseDataStore::query() result array.
	 *
	 * @param array $result Query result array.
	 *
	 * @return static
	 */
	public static function from_array( array $result ): self {
		return new static(
			is_array( $result['items'] ) ? $result['items'] : [],
			(int) $result['total'],
			(int) $result['per_page'],
			(int) $result['current_page'],
			(int) $result['total_pages']
		);
	}

	/**
	 * Get items on the current page.
	 *
	 * @return T[]
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Total matching rows (before pagination).
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Items per page.
	 *
	 * @return int
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * Current page number.
	 *
	 * @return int
	 */
	public function current_page(): int {
		return $this->current_page;
	}

	/**
	 * Total number of pages.
	 *
	 * @return int
	 */
	public function total_pages(): int {
		return $this->total_pages;
	}

	/**
	 * Whether there are more pages after the current one.
	 *
	 * @return bool
	 */
	public function has_more(): bool {
		return $this->current_page < $this->total_pages;
	}

	/**
	 * Whether the result set is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}

	/**
	 * Get the first item, or null if empty.
	 *
	 * @return T|null
	 */
	public function first() {
		return $this->items[0] ?? null;
	}

	/**
	 * Get the last item, or null if empty.
	 *
	 * @return T|null
	 */
	public function last() {
		return ! empty( $this->items ) ? end( $this->items ) : null;
	}

	/**
	 * Pluck a property from all items.
	 *
	 * @param string $method Getter method name (e.g. 'get_title').
	 *
	 * @return array
	 */
	public function pluck( string $method ): array {
		return array_map( function ( $item ) use ( $method ) {
			return $item->{$method}();
		}, $this->items );
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'items'        => $this->items,
			'total'        => $this->total,
			'per_page'     => $this->per_page,
			'current_page' => $this->current_page,
			'total_pages'  => $this->total_pages,
		];
	}

	/**
	 * Count items on the current page (Countable interface).
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Get iterator (IteratorAggregate interface).
	 *
	 * @return Traversable<int, T>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->items );
	}
}
