<?php
/**
 * Base data store with SQL query building and integrated caching.
 *
 * @package WeDevs\WPKit\DataLayer\DataStore
 */

namespace WeDevs\WPKit\DataLayer\DataStore;

use Exception;
use WeDevs\WPKit\Cache\ObjectCache;
use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;
use WeDevs\WPKit\DataLayer\Contracts\ModelInterface;

/**
 * Base data store with SQL query building and integrated caching.
 *
 * Shallow-copies the pattern from Dokan's BaseDataStore but uses WPKit's
 * own SqlQuery instead of WooCommerce's SqlQuery.
 */
abstract class BaseDataStore extends SqlQuery implements DataStoreInterface {

	/**
	 * Columns to select.
	 *
	 * @var array
	 */
	protected array $selected_columns = [ '*' ];

	/**
	 * Optional object cache instance.
	 *
	 * @var ObjectCache|null
	 */
	protected ?ObjectCache $cache = null;

	/**
	 * Hook prefix for actions and filters. Must be set by consumer.
	 *
	 * @var string
	 */
	protected string $hook_prefix = '';

	/**
	 * Get the fields with format as an array where key is the db field name and value is the format.
	 *
	 * @return array E.g., ['vendor_id' => '%d', 'status' => '%s', 'amount' => '%f'].
	 */
	abstract protected function get_fields_with_format(): array;

	/**
	 * Get the table name (without prefix).
	 *
	 * @return string
	 */
	abstract public function get_table_name(): string;

	/**
	 * Set the object cache instance.
	 *
	 * @param ObjectCache $cache Cache instance.
	 */
	public function set_cache( ObjectCache $cache ): void {
		$this->cache = $cache;
	}

	/**
	 * Get the object cache instance.
	 *
	 * @return ObjectCache|null
	 */
	public function get_cache(): ?ObjectCache {
		return $this->cache;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ModelInterface $model The model to create.
	 */
	public function create( ModelInterface &$model ) {
		$data = $this->map_model_to_db_data( $model );

		$inserted_id = $this->insert( $data );

		if ( $inserted_id ) {
			$model->set_id( $inserted_id );
			$model->apply_changes();
		}

		if ( $this->cache && $inserted_id ) {
			$this->cache->remove( $inserted_id );
		}

		do_action( $this->get_hook_prefix() . 'created', $inserted_id, $data );

		return $inserted_id;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ModelInterface $model The model to populate.
	 *
	 * @throws Exception If the entity has no ID or is not found.
	 */
	public function read( ModelInterface &$model ) {
		global $wpdb;

		if ( ! $model->get_id() ) {
			throw new Exception( 'Invalid entity: no ID set.' );
		}

		// Check cache first.
		if ( $this->cache ) {
			$cached = $this->cache->get( $model->get_id() );

			if ( null !== $cached ) {
				$model->set_defaults();
				$model->set_props( is_array( $cached ) ? $cached : (array) $cached );
				$model->set_object_read( true );

				return $cached;
			}
		}

		$model->set_defaults();

		$id_field_name = $this->get_id_field_name();
		$format        = $this->get_id_field_format();

		$this->clear_all_clauses();
		$this->add_sql_clause( 'select', $this->get_selected_columns() );
		$this->add_sql_clause( 'from', $this->get_table_name_with_prefix() );
		$this->add_sql_clause(
			'where',
			$wpdb->prepare(
				" AND {$id_field_name} = {$format}",
				$model->get_id()
			)
		);

		$raw_item = $wpdb->get_row( $this->get_query_statement() );

		if ( ! $raw_item ) {
			throw new Exception( 'Entity not found.' );
		}

		$mapped_data = $this->map_db_raw_to_model_data( $raw_item );

		$model->set_props( $mapped_data );
		$model->set_object_read( true );

		// Cache the result.
		if ( $this->cache ) {
			$this->cache->set( $mapped_data, $model->get_id() );
		}

		return $raw_item;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ModelInterface $model The model to update.
	 */
	public function update( ModelInterface &$model ) {
		global $wpdb;

		$data   = $this->map_model_to_db_data( $model );
		$format = $this->get_fields_format();

		$result = $wpdb->update(
			$this->get_table_name_with_prefix(),
			$data,
			[
				$this->get_id_field_name() => $model->get_id(),
			],
			$format
		);

		$model->apply_changes();

		if ( $this->cache ) {
			$this->cache->remove( $model->get_id() );
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param ModelInterface $model The model to delete.
	 * @param array          $args  Additional arguments.
	 */
	public function delete( ModelInterface &$model, array $args = [] ) {
		$model_id = $model->get_id();
		$this->delete_by_id( $model_id );

		$model->set_id( 0 );
	}

	/**
	 * Delete a record by ID.
	 *
	 * @param int $id Record ID.
	 *
	 * @return int Number of affected rows.
	 */
	public function delete_by_id( int $id ): int {
		$result = $this->delete_by( [ $this->get_id_field_name() => $id ] );

		do_action( $this->get_hook_prefix() . 'deleted', $id, $result );

		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array $data Associative array of column => value conditions.
	 *
	 * @throws Exception If the delete query fails.
	 */
	public function delete_by( array $data ): int {
		global $wpdb;

		$table_name   = $this->get_table_name_with_prefix();
		$where_clause = $this->prepare_where_clause( $data );

		$result = $wpdb->query(
			"DELETE FROM {$table_name} WHERE {$where_clause}"
		);

		if ( false === $result ) {
			throw new Exception( 'Failed to delete.' );
		}

		if ( $this->cache ) {
			$this->cache->flush();
		}

		return (int) $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array $where          Conditions for matching records.
	 * @param array $data_to_update Data to update.
	 *
	 * @throws Exception If the update query fails.
	 */
	public function update_by( array $where, array $data_to_update ): int {
		global $wpdb;

		$fields_format                               = $this->get_fields_with_format();
		$fields_format[ $this->get_id_field_name() ] = $this->get_id_field_format();

		$data_format = [];
		foreach ( $data_to_update as $key => $value ) {
			$data_format[] = $fields_format[ $key ];
		}

		$where_format = [];
		foreach ( $where as $key => $value ) {
			$where_format[] = $fields_format[ $key ];
		}

		$result = $wpdb->update(
			$this->get_table_name_with_prefix(),
			$data_to_update,
			$where,
			$data_format,
			$where_format
		);

		if ( false === $result ) {
			throw new Exception( 'Failed to update.' );
		}

		if ( $this->cache ) {
			$this->cache->flush();
		}

		return (int) $result;
	}

	// -------------------------------------------------------
	// Query: WP_Query-like listing with search, pagination, caching
	// -------------------------------------------------------

	/**
	 * Query records with WP_Query-style arguments.
	 *
	 * Supported args:
	 * - per_page (int, default 20, -1 for all)
	 * - page (int, default 1)
	 * - offset (int, overrides page-based offset when > 0)
	 * - orderby (string, validated against field list)
	 * - order (string, 'ASC' or 'DESC')
	 * - search (string, LIKE search on searchable fields)
	 * - fields (string, columns to select, default '*')
	 * - count_total (bool, default true)
	 * - return (string, 'results'|'count'|'ids')
	 * - no_cache (bool, default false)
	 * - {field_name} (mixed, exact match)
	 * - {field_name}__in (array, IN clause)
	 * - {field_name}__not_in (array, NOT IN clause)
	 * - {field_name}__gt (mixed, greater than)
	 * - {field_name}__gte (mixed, greater than or equal)
	 * - {field_name}__lt (mixed, less than)
	 * - {field_name}__lte (mixed, less than or equal)
	 * - date_query (array, with 'column', 'after', 'before')
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array {
	 *     items: object[]|int[]|int,
	 *     total: int,
	 *     per_page: int,
	 *     current_page: int,
	 *     total_pages: int,
	 * }
	 */
	public function query( array $args = [] ): array {
		global $wpdb;

		$args = wp_parse_args( $args, $this->get_default_query_args() );
		$args = apply_filters( $this->get_hook_prefix() . 'query_args', $args );

		// Check cache.
		$cache_key = '';
		if ( ! $args['no_cache'] && $this->cache ) {
			$cache_key = $this->get_query_cache_key( $args );
			$cached    = $this->cache->get( 'query_' . $cache_key );

			if ( null !== $cached ) {
				return $cached;
			}
		}

		$table = $this->get_table_name_with_prefix();

		// Build WHERE clauses.
		$this->clear_all_clauses();
		$this->add_sql_clause( 'from', $table );
		$this->build_query_where( $args );

		if ( ! empty( $args['search'] ) ) {
			$this->build_query_search( $args['search'] );
		}

		if ( ! empty( $args['date_query'] ) ) {
			$this->build_query_date( $args['date_query'] );
		}

		// Handle 'count' return type — just return the count.
		if ( 'count' === $args['return'] ) {
			$this->add_sql_clause( 'select', 'COUNT(*)' );
			$count = (int) $wpdb->get_var( $this->get_query_statement() );

			$result = [
				'items'        => $count,
				'total'        => $count,
				'per_page'     => $args['per_page'],
				'current_page' => $args['page'],
				'total_pages'  => 1,
			];

			if ( $cache_key && $this->cache ) {
				$this->cache->set( $result, 'query_' . $cache_key );
			}

			return apply_filters( $this->get_hook_prefix() . 'query_results', $result, $args );
		}

		// Count total before pagination.
		$total = 0;
		if ( $args['count_total'] ) {
			$this->add_sql_clause( 'select', 'COUNT(*)' );
			$total = (int) $wpdb->get_var( $this->get_query_statement() );

			// Reset select for actual query.
			$this->clear_sql_clause( 'select' );
		}

		// Select columns.
		if ( 'ids' === $args['return'] ) {
			$this->add_sql_clause( 'select', $this->get_id_field_name() );
		} else {
			$this->add_sql_clause( 'select', $args['fields'] );
		}

		// Order.
		$allowed_fields = array_merge( $this->get_fields(), [ $this->get_id_field_name() ] );
		$orderby        = in_array( $args['orderby'], $allowed_fields, true ) ? $args['orderby'] : $this->get_id_field_name();
		$order          = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$this->add_sql_clause( 'order_by', "{$orderby} {$order}" );

		// Pagination.
		if ( $args['per_page'] > 0 ) {
			$offset = $args['offset'] > 0
				? $args['offset']
				: ( $args['page'] - 1 ) * $args['per_page'];

			$this->add_sql_clause( 'limit', $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], $offset ) );
		}

		// Execute.
		if ( 'ids' === $args['return'] ) {
			$items = $wpdb->get_col( $this->get_query_statement() );
			$items = array_map( 'intval', $items );
		} else {
			$items = $wpdb->get_results( $this->get_query_statement() );
		}

		$per_page    = $args['per_page'] > 0 ? $args['per_page'] : max( count( $items ), 1 );
		$total_pages = $args['count_total'] ? (int) ceil( $total / $per_page ) : 1;

		$result = [
			'items'        => $items,
			'total'        => $args['count_total'] ? $total : count( $items ),
			'per_page'     => $args['per_page'],
			'current_page' => $args['page'],
			'total_pages'  => $total_pages,
		];

		// Cache result.
		if ( $cache_key && $this->cache ) {
			$this->cache->set( $result, 'query_' . $cache_key );
		}

		return apply_filters( $this->get_hook_prefix() . 'query_results', $result, $args );
	}

	/**
	 * Get default query arguments.
	 *
	 * @return array
	 */
	protected function get_default_query_args(): array {
		return [
			'per_page'    => 20,
			'page'        => 1,
			'offset'      => 0,
			'orderby'     => $this->get_id_field_name(),
			'order'       => 'DESC',
			'search'      => '',
			'fields'      => '*',
			'count_total' => true,
			'return'      => 'results',
			'no_cache'    => false,
			'date_query'  => [],
		];
	}

	/**
	 * Get fields that support LIKE search.
	 *
	 * Override in subclasses to enable search support.
	 *
	 * @return array Field names, e.g. ['title', 'description'].
	 */
	protected function get_searchable_fields(): array {
		return [];
	}

	/**
	 * Build WHERE clauses from query args (exact match and comparison operators).
	 *
	 * @param array $args Query arguments.
	 */
	protected function build_query_where( array $args ): void {
		global $wpdb;

		$fields                                     = $this->get_fields();
		$field_format                               = $this->get_fields_with_format();
		$field_format[ $this->get_id_field_name() ] = $this->get_id_field_format();
		$reserved                                   = array_keys( $this->get_default_query_args() );

		foreach ( $args as $key => $value ) {
			if ( in_array( $key, $reserved, true ) || null === $value ) {
				continue;
			}

			// Handle comparison operators: field__in, field__not_in, field__gt, etc.
			$parts    = explode( '__', $key, 2 );
			$field    = $parts[0];
			$operator = $parts[1] ?? '';

			if ( ! in_array( $field, $fields, true ) && $field !== $this->get_id_field_name() ) {
				continue;
			}

			$format = $field_format[ $field ] ?? '%s';

			if ( '' === $operator ) {
				// Exact match.
				if ( is_array( $value ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $value ), $format ) );
					$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} IN ({$placeholders})", ...$value ) );
				} else {
					$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} = {$format}", $value ) );
				}
			} elseif ( 'in' === $operator && is_array( $value ) && ! empty( $value ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $value ), $format ) );
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} IN ({$placeholders})", ...$value ) );
			} elseif ( 'not_in' === $operator && is_array( $value ) && ! empty( $value ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $value ), $format ) );
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} NOT IN ({$placeholders})", ...$value ) );
			} elseif ( 'gt' === $operator ) {
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} > {$format}", $value ) );
			} elseif ( 'gte' === $operator ) {
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} >= {$format}", $value ) );
			} elseif ( 'lt' === $operator ) {
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} < {$format}", $value ) );
			} elseif ( 'lte' === $operator ) {
				$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$field} <= {$format}", $value ) );
			}
		}
	}

	/**
	 * Build LIKE search clauses on searchable fields.
	 *
	 * @param string $search Search term.
	 */
	protected function build_query_search( string $search ): void {
		global $wpdb;

		$searchable = $this->get_searchable_fields();

		if ( empty( $searchable ) || '' === $search ) {
			return;
		}

		$like_clauses = [];

		foreach ( $searchable as $field ) {
			$like_clauses[] = $wpdb->prepare( "{$field} LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$this->add_sql_clause( 'where', ' AND (' . implode( ' OR ', $like_clauses ) . ')' );
	}

	/**
	 * Build date range WHERE clauses.
	 *
	 * @param array $date_query Date query args with 'column', 'after', 'before'.
	 */
	protected function build_query_date( array $date_query ): void {
		global $wpdb;

		$column = $date_query['column'] ?? '';
		$fields = $this->get_fields();

		if ( ! in_array( $column, $fields, true ) ) {
			return;
		}

		if ( ! empty( $date_query['after'] ) ) {
			$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$column} >= %s", $date_query['after'] ) );
		}

		if ( ! empty( $date_query['before'] ) ) {
			$this->add_sql_clause( 'where', $wpdb->prepare( " AND {$column} <= %s", $date_query['before'] ) );
		}
	}

	/**
	 * Generate a cache key for query args.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return string MD5 hash.
	 */
	protected function get_query_cache_key( array $args ): string {
		return md5( wp_json_encode( $args ) );
	}

	/**
	 * Prepare a WHERE clause from an associative array.
	 *
	 * Supports both single values and arrays (generates IN clauses).
	 *
	 * @param array $data Column => value conditions.
	 *
	 * @return string
	 */
	protected function prepare_where_clause( array $data ): string {
		global $wpdb;

		$where                                      = [ '1=1' ];
		$field_format                               = $this->get_fields_with_format();
		$field_format[ $this->get_id_field_name() ] = $this->get_id_field_format();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
				$where[]      = $wpdb->prepare( "{$key} IN ({$placeholders})", ...$value );
			} else {
				$format  = $field_format[ $key ] ?? '%s';
				$where[] = $wpdb->prepare( "{$key} = {$format}", $value );
			}
		}

		return implode( ' AND ', $where );
	}

	/**
	 * Insert a record into the database.
	 *
	 * @param array $data Data to insert.
	 *
	 * @return int The inserted ID, or 0 on failure.
	 */
	protected function insert( array $data ): int {
		global $wpdb;

		$format      = $this->get_fields_format();
		$table_name  = $this->get_table_name_with_prefix();
		$hook_prefix = $this->get_hook_prefix();

		$result = $wpdb->insert(
			$table_name,
			apply_filters( $hook_prefix . 'insert_data', $data ),
			apply_filters( $hook_prefix . 'insert_data_format', $format, $data )
		);

		do_action( $hook_prefix . 'after_insert', $result, $data );

		return $result ? $wpdb->insert_id : 0;
	}

	/**
	 * Map model data to database columns.
	 *
	 * @param ModelInterface $model The model.
	 *
	 * @return array
	 */
	protected function map_model_to_db_data( ModelInterface &$model ): array {
		$data = [];

		foreach ( $this->get_fields() as $db_field_name ) {
			if ( method_exists( $this, 'get_' . $db_field_name ) ) {
				$val = call_user_func( [ $this, 'get_' . $db_field_name ], $model, 'edit' );
			} else {
				$val = call_user_func( [ $model, 'get_' . $db_field_name ], 'edit' );
			}

			if ( $val instanceof \DateTimeInterface ) {
				$val = $val->format( $this->get_date_format_for_field( $db_field_name ) );
			}

			$data[ $db_field_name ] = $val;
		}

		return $data;
	}

	/**
	 * Map raw database row to model data.
	 *
	 * @param object $raw_data The raw database row.
	 *
	 * @return array
	 */
	protected function map_db_raw_to_model_data( $raw_data ): array {
		$data = [];

		foreach ( $this->get_fields() as $db_field_name ) {
			$data[ $db_field_name ] = $raw_data->{$db_field_name} ?? null;
		}

		return apply_filters( $this->get_hook_prefix() . 'map_db_raw_to_model_data', $data, $raw_data );
	}

	/**
	 * Get the date format for a database field.
	 *
	 * @param string $db_field_name Field name.
	 *
	 * @return string
	 */
	protected function get_date_format_for_field( string $db_field_name ): string {
		return 'Y-m-d H:i:s';
	}

	/**
	 * Get the selected columns as a comma-separated string.
	 *
	 * @return string
	 */
	protected function get_selected_columns(): string {
		$selections = apply_filters( $this->get_hook_prefix() . 'selected_columns', $this->selected_columns );

		return implode( ', ', $selections );
	}

	/**
	 * Get the hook prefix.
	 *
	 * @return string
	 */
	protected function get_hook_prefix(): string {
		if ( $this->hook_prefix ) {
			return $this->hook_prefix;
		}

		$table_name = $this->get_table_name();

		return "{$table_name}_";
	}

	/**
	 * Set the hook prefix.
	 *
	 * @param string $prefix The hook prefix (e.g., 'dokan_vendor_balance_').
	 */
	public function set_hook_prefix( string $prefix ): void {
		$this->hook_prefix = $prefix;
	}

	/**
	 * Get the table name with WordPress prefix.
	 *
	 * @return string
	 */
	protected function get_table_name_with_prefix(): string {
		global $wpdb;

		$table_name = $this->get_table_name();

		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		return $table_name;
	}

	/**
	 * Get the name of the ID field. Override for custom ID columns.
	 *
	 * @return string
	 */
	public function get_id_field_name(): string {
		return 'id';
	}

	/**
	 * Get the format of the ID field.
	 *
	 * @return string
	 */
	protected function get_id_field_format(): string {
		return '%d';
	}

	/**
	 * Get the field names.
	 *
	 * @return array
	 */
	protected function get_fields(): array {
		return array_keys( $this->get_fields_with_format() );
	}

	/**
	 * Get the field formats as an indexed array.
	 *
	 * @return array
	 */
	protected function get_fields_format(): array {
		return array_values( $this->get_fields_with_format() );
	}
}
