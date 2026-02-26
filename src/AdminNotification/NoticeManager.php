<?php

namespace WeDevs\WPKit\AdminNotification;

use WeDevs\WPKit\AdminNotification\Contracts\NoticeProviderInterface;

/**
 * Central notice collector and manager.
 *
 * Collects notices from registered providers and WordPress filters,
 * filters dismissed notices, and sorts by priority.
 */
class NoticeManager {

	/**
	 * Registered notice providers.
	 *
	 * @var NoticeProviderInterface[]
	 */
	protected array $providers = [];

	/**
	 * WordPress filter name for collecting notices.
	 *
	 * @var string
	 */
	protected string $filter_name;

	/**
	 * Prefix for option keys.
	 *
	 * @var string
	 */
	protected string $prefix;

	/**
	 * @param string $prefix Plugin-specific prefix (e.g., 'dokan').
	 */
	public function __construct( string $prefix ) {
		$this->prefix      = $prefix;
		$this->filter_name = $prefix . '_admin_notices';
	}

	/**
	 * Register a notice provider.
	 *
	 * @param NoticeProviderInterface $provider Notice provider.
	 *
	 * @return self
	 */
	public function register_provider( NoticeProviderInterface $provider ): self {
		$this->providers[] = $provider;

		return $this;
	}

	/**
	 * Get all notices, optionally filtered by scope.
	 *
	 * @param string $scope Filter by scope: 'local', 'global', or '' for all.
	 *
	 * @return array
	 */
	public function get_notices( string $scope = '' ): array {
		$notices = [];

		// Collect from registered providers.
		foreach ( $this->providers as $provider ) {
			$provider_notices = $provider->get_notices();

			foreach ( $provider_notices as $notice ) {
				if ( $notice instanceof Notice ) {
					$notices[] = $notice->to_array();
				} else {
					$notices[] = $notice;
				}
			}
		}

		// Collect from WordPress filter (backward compatibility).
		$notices = apply_filters( $this->filter_name, $notices );

		// Filter out dismissed notices.
		$notices = $this->filter_dismissed( $notices );

		// Filter by scope.
		if ( $scope ) {
			$notices = array_filter(
				$notices,
				function ( $notice ) use ( $scope ) {
					return $scope === ( $notice['scope'] ?? 'local' );
				}
			);
		}

		// Sort by priority.
		usort(
			$notices,
			function ( $a, $b ) {
				return ( $a['priority'] ?? 10 ) - ( $b['priority'] ?? 10 );
			}
		);

		return array_values( $notices );
	}

	/**
	 * Filter out dismissed notices.
	 *
	 * @param array $notices Notices to filter.
	 *
	 * @return array
	 */
	protected function filter_dismissed( array $notices ): array {
		$dismissed = get_option( $this->prefix . '_dismissed_notices', [] );

		return array_filter(
			$notices,
			function ( $notice ) use ( $dismissed ) {
				$key = $notice['key'] ?? '';

				return ! $key || ! in_array( $key, $dismissed, true );
			}
		);
	}

	/**
	 * Get the filter name used for collecting notices.
	 *
	 * @return string
	 */
	public function get_filter_name(): string {
		return $this->filter_name;
	}

	/**
	 * Get the prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->prefix;
	}
}
