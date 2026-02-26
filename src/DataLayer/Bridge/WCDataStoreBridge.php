<?php
/**
 * Bridge between WPKit DataStoreInterface and WC_Object_Data_Store_Interface.
 *
 * @package WeDevs\WPKit\DataLayer\Bridge
 */

namespace WeDevs\WPKit\DataLayer\Bridge;

use WeDevs\WPKit\DataLayer\Contracts\DataStoreInterface;

// Guard: Skip class definition if WooCommerce is not installed.
if ( ! class_exists( 'WC_Object_Data_Store_Interface' ) ) {
	return;
}

/**
 * Bridge between WPKit DataStoreInterface and WC_Object_Data_Store_Interface.
 *
 * Use this to register WPKit data stores with WooCommerce's data store system:
 *
 *   add_filter('woocommerce_data_stores', function($stores) {
 *       $stores['my-type'] = new WCDataStoreBridge($myWpkitStore);
 *       return $stores;
 *   });
 */
class WCDataStoreBridge implements \WC_Object_Data_Store_Interface {

	/**
	 * The underlying WPKit data store.
	 *
	 * @var DataStoreInterface
	 */
	protected DataStoreInterface $wpkit_store;

	/**
	 * Constructor.
	 *
	 * @param DataStoreInterface $wpkit_store The WPKit data store.
	 */
	public function __construct( DataStoreInterface $wpkit_store ) {
		$this->wpkit_store = $wpkit_store;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed $data Data object to create.
	 */
	public function create( &$data ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->create( $model );
			$data->set_id( $model->get_id() );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed $data Data object to read.
	 */
	public function read( &$data ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->read( $model );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed $data Data object to update.
	 */
	public function update( &$data ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->update( $model );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed $data Data object to delete.
	 * @param array $args Additional arguments.
	 */
	public function delete( &$data, $args = [] ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->delete( $model, $args );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed $data Data object to read meta for.
	 */
	public function read_meta( &$data ) {
		return [];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed  $data Data object.
	 * @param object $meta Meta object to delete.
	 */
	public function delete_meta( &$data, $meta ) {
		return false;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed  $data Data object.
	 * @param object $meta Meta object to add.
	 */
	public function add_meta( &$data, $meta ) {
		return false;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param mixed  $data Data object.
	 * @param object $meta Meta object to update.
	 */
	public function update_meta( &$data, $meta ) {
		return false;
	}
}
