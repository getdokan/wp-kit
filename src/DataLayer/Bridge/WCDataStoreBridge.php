<?php

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
	 * @param DataStoreInterface $wpkit_store The WPKit data store.
	 */
	public function __construct( DataStoreInterface $wpkit_store ) {
		$this->wpkit_store = $wpkit_store;
	}

	/**
	 * {@inheritdoc}
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
	 */
	public function read( &$data ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->read( $model );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function update( &$data ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->update( $model );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( &$data, $args = [] ) {
		if ( $data instanceof WCModelAdapter ) {
			$model = $data->get_wpkit_model();
			$this->wpkit_store->delete( $model, $args );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function read_meta( &$data ) {
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_meta( &$data, $meta ) {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_meta( &$data, $meta ) {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_meta( &$data, $meta ) {
		return false;
	}
}
