<?php
/**
 * Standalone SQL clause builder.
 *
 * @package WeDevs\WPKit\DataLayer\DataStore
 */

namespace WeDevs\WPKit\DataLayer\DataStore;

/**
 * Standalone SQL clause builder.
 *
 * Replaces WooCommerce's Automattic\WooCommerce\Admin\API\Reports\SqlQuery
 * with a self-contained implementation.
 */
class SqlQuery {

	/**
	 * SQL clauses storage.
	 *
	 * @var array
	 */
	protected array $sql_clauses = [
		'select'    => [],
		'from'      => [],
		'join'      => [],
		'left_join' => [],
		'where'     => [],
		'group_by'  => [],
		'having'    => [],
		'order_by'  => [],
		'limit'     => [],
	];

	/**
	 * Context identifier for filter hooks.
	 *
	 * @var string
	 */
	protected string $context;

	/**
	 * Filter prefix for SQL clause hooks.
	 *
	 * @var string
	 */
	protected string $filter_prefix = '';

	/**
	 * Constructor.
	 *
	 * @param string $context Context identifier for hook filtering.
	 */
	public function __construct( string $context = '' ) {
		$this->context = $context;
	}

	/**
	 * Set the filter prefix for SQL clause hooks.
	 *
	 * @param string $prefix Filter prefix (e.g., 'dokan').
	 */
	public function set_filter_prefix( string $prefix ): void {
		$this->filter_prefix = $prefix;
	}

	/**
	 * Add a SQL clause.
	 *
	 * @param string $type   Clause type (select, from, join, where, etc.).
	 * @param string $clause The SQL clause string.
	 */
	public function add_sql_clause( string $type, string $clause ): void {
		if ( isset( $this->sql_clauses[ $type ] ) ) {
			$this->sql_clauses[ $type ][] = $clause;
		}
	}

	/**
	 * Get a specific SQL clause as a string.
	 *
	 * @param string $type Clause type.
	 *
	 * @return string
	 */
	protected function get_sql_clause( string $type ): string {
		if ( ! isset( $this->sql_clauses[ $type ] ) ) {
			return '';
		}

		$clause = implode( ' ', $this->sql_clauses[ $type ] );

		if ( $this->context && $this->filter_prefix ) {
			$clause = apply_filters( "{$this->filter_prefix}_sql_clauses_{$type}_{$this->context}", $clause, $this );
		}

		return $clause;
	}

	/**
	 * Clear specific clause type(s).
	 *
	 * @param string|array $types Clause type(s) to clear.
	 */
	protected function clear_sql_clause( $types ): void {
		foreach ( (array) $types as $type ) {
			if ( isset( $this->sql_clauses[ $type ] ) ) {
				$this->sql_clauses[ $type ] = [];
			}
		}
	}

	/**
	 * Clear all SQL clauses.
	 */
	public function clear_all_clauses(): void {
		foreach ( $this->sql_clauses as $type => $clauses ) {
			$this->sql_clauses[ $type ] = [];
		}
	}

	/**
	 * Build the complete SQL query statement.
	 *
	 * @return string
	 */
	public function get_query_statement(): string {
		$select    = $this->get_sql_clause( 'select' );
		$from      = $this->get_sql_clause( 'from' );
		$join      = $this->get_sql_clause( 'join' );
		$left_join = $this->get_sql_clause( 'left_join' );
		$where     = $this->get_sql_clause( 'where' );
		$group_by  = $this->get_sql_clause( 'group_by' );
		$having    = $this->get_sql_clause( 'having' );
		$order_by  = $this->get_sql_clause( 'order_by' );
		$limit     = $this->get_sql_clause( 'limit' );

		$query = "SELECT {$select} FROM {$from}";

		if ( $join ) {
			$query .= " {$join}";
		}

		if ( $left_join ) {
			$query .= " LEFT JOIN {$left_join}";
		}

		$query .= " WHERE 1=1 {$where}";

		if ( $group_by ) {
			$query .= " GROUP BY {$group_by}";
		}

		if ( $having ) {
			$query .= " HAVING {$having}";
		}

		if ( $order_by ) {
			$query .= " ORDER BY {$order_by}";
		}

		if ( $limit ) {
			$query .= " {$limit}";
		}

		return $query;
	}
}
