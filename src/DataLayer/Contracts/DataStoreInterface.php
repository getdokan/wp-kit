<?php

namespace WeDevs\WPKit\DataLayer\Contracts;

/**
 * Interface for data stores handling CRUD operations.
 */
interface DataStoreInterface {

	/**
	 * Create a new record.
	 *
	 * @param ModelInterface $model The model to create.
	 *
	 * @return int The inserted ID.
	 */
	public function create( ModelInterface &$model );

	/**
	 * Read a record from the database.
	 *
	 * @param ModelInterface $model The model to populate.
	 *
	 * @throws \Exception If the record is not found.
	 */
	public function read( ModelInterface &$model );

	/**
	 * Update an existing record.
	 *
	 * @param ModelInterface $model The model to update.
	 *
	 * @return int|false Number of rows updated, or false on error.
	 */
	public function update( ModelInterface &$model );

	/**
	 * Delete a record.
	 *
	 * @param ModelInterface $model The model to delete.
	 * @param array          $args  Additional arguments.
	 */
	public function delete( ModelInterface &$model, array $args = [] );

	/**
	 * Delete records matching criteria.
	 *
	 * @param array $data Associative array of column => value conditions.
	 *
	 * @return int Number of deleted rows.
	 */
	public function delete_by( array $data ): int;

	/**
	 * Update records matching criteria.
	 *
	 * @param array $where          Conditions for matching records.
	 * @param array $data_to_update Data to update.
	 *
	 * @return int Number of updated rows.
	 */
	public function update_by( array $where, array $data_to_update ): int;

	/**
	 * Query records with WP_Query-style arguments.
	 *
	 * @param array $args Query arguments (per_page, page, orderby, order, search, etc.).
	 *
	 * @return array {
	 *     items: object[]|int[]|int,
	 *     total: int,
	 *     per_page: int,
	 *     current_page: int,
	 *     total_pages: int,
	 * }
	 */
	public function query( array $args = [] ): array;
}
