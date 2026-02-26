<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\DataLayer\DataStore\SqlQuery;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class SqlQueryTest extends TestCase {

	/**
	 * @var SqlQuery
	 */
	private SqlQuery $query;

	protected function setUp(): void {
		parent::setUp();
		$this->query = new SqlQuery( 'test_context' );
	}

	public function test_build_basic_select_query(): void {
		$this->query->add_sql_clause( 'select', 'id, name' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'SELECT id, name', $sql );
		$this->assertStringContainsString( 'FROM wp_orders', $sql );
		$this->assertStringContainsString( 'WHERE 1=1', $sql );
	}

	public function test_build_query_with_where_clause(): void {
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'where', "AND status = 'active'" );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( "WHERE 1=1 AND status = 'active'", $sql );
	}

	public function test_build_query_with_join(): void {
		$this->query->add_sql_clause( 'select', 'o.*, c.name' );
		$this->query->add_sql_clause( 'from', 'wp_orders o' );
		$this->query->add_sql_clause( 'join', 'INNER JOIN wp_customers c ON o.customer_id = c.id' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'INNER JOIN wp_customers c ON o.customer_id = c.id', $sql );
	}

	public function test_build_query_with_left_join(): void {
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'left_join', 'wp_meta ON orders.id = wp_meta.order_id' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'LEFT JOIN wp_meta ON orders.id = wp_meta.order_id', $sql );
	}

	public function test_build_query_with_group_by_and_having(): void {
		$this->query->add_sql_clause( 'select', 'customer_id, SUM(total) as sum_total' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'group_by', 'customer_id' );
		$this->query->add_sql_clause( 'having', 'sum_total > 100' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'GROUP BY customer_id', $sql );
		$this->assertStringContainsString( 'HAVING sum_total > 100', $sql );
	}

	public function test_build_query_with_order_by_and_limit(): void {
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'order_by', 'created_at DESC' );
		$this->query->add_sql_clause( 'limit', 'LIMIT 10' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'ORDER BY created_at DESC', $sql );
		$this->assertStringContainsString( 'LIMIT 10', $sql );
	}

	public function test_clear_all_clauses_resets_query(): void {
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'where', "AND id = 1" );

		$this->query->clear_all_clauses();

		$sql = $this->query->get_query_statement();

		$this->assertSame( 'SELECT  FROM  WHERE 1=1 ', $sql );
	}

	public function test_multiple_clauses_of_same_type_are_concatenated(): void {
		$this->query->add_sql_clause( 'select', 'id' );
		$this->query->add_sql_clause( 'select', ', name' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'where', 'AND status = 1' );
		$this->query->add_sql_clause( 'where', 'AND total > 50' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'SELECT id , name', $sql );
		$this->assertStringContainsString( 'AND status = 1 AND total > 50', $sql );
	}

	public function test_invalid_clause_type_is_ignored(): void {
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );
		$this->query->add_sql_clause( 'nonexistent', 'should be ignored' );

		$sql = $this->query->get_query_statement();

		$this->assertStringNotContainsString( 'should be ignored', $sql );
	}

	public function test_filter_prefix_applies_filter_to_clauses(): void {
		$this->query->set_filter_prefix( 'myplugin' );

		Functions\when( 'apply_filters' )->alias( function () {
			$args = func_get_args();
			// Modify only the select clause; pass others through.
			if ( $args[0] === 'myplugin_sql_clauses_select_test_context' ) {
				return 'id, name, total';
			}
			return $args[1];
		} );

		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );

		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'SELECT id, name, total', $sql );
	}

	public function test_no_filter_applied_without_prefix(): void {
		// No set_filter_prefix() called.
		$this->query->add_sql_clause( 'select', '*' );
		$this->query->add_sql_clause( 'from', 'wp_orders' );

		// apply_filters should not be called.
		$sql = $this->query->get_query_statement();

		$this->assertStringContainsString( 'SELECT *', $sql );
	}

	public function test_no_filter_applied_without_context(): void {
		$query = new SqlQuery( '' ); // Empty context.
		$query->set_filter_prefix( 'myplugin' );

		$query->add_sql_clause( 'select', '*' );
		$query->add_sql_clause( 'from', 'wp_orders' );

		// apply_filters should not be called since context is empty.
		$sql = $query->get_query_statement();

		$this->assertStringContainsString( 'SELECT *', $sql );
	}
}
