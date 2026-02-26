<?php
/**
 * Interface for data models.
 *
 * @package WeDevs\WPKit\DataLayer\Contracts
 */

namespace WeDevs\WPKit\DataLayer\Contracts;

/**
 * Interface for data models.
 */
interface ModelInterface {

	/**
	 * Get the object ID.
	 *
	 * @return int
	 */
	public function get_id(): int;

	/**
	 * Set the object ID.
	 *
	 * @param int $id Object ID.
	 */
	public function set_id( int $id ): void;

	/**
	 * Save the object (create or update).
	 *
	 * @return int The object ID.
	 */
	public function save(): int;

	/**
	 * Delete the object.
	 *
	 * @param bool $force_delete Whether to force delete.
	 *
	 * @return bool
	 */
	public function delete( bool $force_delete = false ): bool;

	/**
	 * Get all data including pending changes.
	 *
	 * @return array
	 */
	public function get_data(): array;

	/**
	 * Get pending changes.
	 *
	 * @return array
	 */
	public function get_changes(): array;

	/**
	 * Apply pending changes to core data.
	 */
	public function apply_changes(): void;

	/**
	 * Check if the object has been read from the database.
	 *
	 * @return bool
	 */
	public function get_object_read(): bool;

	/**
	 * Set whether the object has been read from the database.
	 *
	 * @param bool $read Read state.
	 */
	public function set_object_read( bool $read = true ): void;

	/**
	 * Get the data store instance.
	 *
	 * @return DataStoreInterface|null
	 */
	public function get_data_store(): ?DataStoreInterface;

	/**
	 * Get the object type identifier.
	 *
	 * @return string
	 */
	public function get_object_type(): string;
}
