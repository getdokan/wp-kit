<?php
/**
 * Interface for notice providers.
 *
 * @package WeDevs\WPKit\AdminNotification
 */

namespace WeDevs\WPKit\AdminNotification\Contracts;

/**
 * Interface for notice providers.
 *
 * Plugins implement this to supply notices to the NoticeManager.
 */
interface NoticeProviderInterface {

	/**
	 * Return an array of notices.
	 *
	 * Each notice should be either a Notice object or an associative array with keys:
	 * type, title, description, priority, scope, actions, is_dismissible, key.
	 *
	 * @return array
	 */
	public function get_notices(): array;
}
