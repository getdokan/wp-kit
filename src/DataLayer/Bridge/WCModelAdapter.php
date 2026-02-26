<?php

namespace WeDevs\WPKit\DataLayer\Bridge;

use WeDevs\WPKit\DataLayer\Contracts\ModelInterface;

// Guard: Skip class definition if WooCommerce is not installed.
if ( ! class_exists( 'WC_Data' ) ) {
	return;
}

/**
 * Adapter that wraps a WPKit model as a WC_Data object.
 *
 * Uses composition: holds a reference to the WPKit model and delegates calls.
 */
class WCModelAdapter extends \WC_Data {

	/**
	 * The underlying WPKit model.
	 *
	 * @var ModelInterface
	 */
	protected ModelInterface $wpkit_model;

	/**
	 * @param ModelInterface $model The WPKit model to wrap.
	 * @param int            $id    Optional object ID.
	 */
	public function __construct( ModelInterface $model, int $id = 0 ) {
		$this->wpkit_model = $model;
		$this->object_type = $model->get_object_type();
		$this->data        = $model->get_data();

		parent::__construct( $id );
	}

	/**
	 * Get the underlying WPKit model.
	 *
	 * @return ModelInterface
	 */
	public function get_wpkit_model(): ModelInterface {
		return $this->wpkit_model;
	}

	/**
	 * Delegate save to the WPKit model.
	 *
	 * @return int
	 */
	public function save() {
		// Sync any WC_Data changes back to the WPKit model.
		foreach ( $this->get_changes() as $prop => $value ) {
			$setter = "set_{$prop}";

			if ( is_callable( [ $this->wpkit_model, $setter ] ) ) {
				$this->wpkit_model->{$setter}( $value );
			}
		}

		return $this->wpkit_model->save();
	}

	/**
	 * Delegate delete to the WPKit model.
	 *
	 * @param bool $force_delete Whether to force delete.
	 *
	 * @return bool
	 */
	public function delete( $force_delete = false ) {
		return $this->wpkit_model->delete( $force_delete );
	}
}
