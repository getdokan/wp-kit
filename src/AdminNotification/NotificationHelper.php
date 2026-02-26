<?php

namespace WeDevs\WPKit\AdminNotification;

/**
 * Static helper methods for creating notice data structures.
 */
class NotificationHelper {

	/**
	 * Create an info notice array.
	 *
	 * @param string $title       Notice title.
	 * @param string $description Notice description.
	 * @param array  $extra       Additional notice properties.
	 *
	 * @return array
	 */
	public static function info( string $title, string $description, array $extra = [] ): array {
		return array_merge(
			[
				'type'        => 'info',
				'title'       => $title,
				'description' => $description,
				'priority'    => 10,
				'scope'       => 'local',
			],
			$extra
		);
	}

	/**
	 * Create a warning notice array.
	 *
	 * @param string $title       Notice title.
	 * @param string $description Notice description.
	 * @param array  $extra       Additional notice properties.
	 *
	 * @return array
	 */
	public static function warning( string $title, string $description, array $extra = [] ): array {
		return array_merge(
			[
				'type'        => 'warning',
				'title'       => $title,
				'description' => $description,
				'priority'    => 5,
				'scope'       => 'local',
			],
			$extra
		);
	}

	/**
	 * Create an error notice array.
	 *
	 * @param string $title       Notice title.
	 * @param string $description Notice description.
	 * @param array  $extra       Additional notice properties.
	 *
	 * @return array
	 */
	public static function error( string $title, string $description, array $extra = [] ): array {
		return array_merge(
			[
				'type'        => 'error',
				'title'       => $title,
				'description' => $description,
				'priority'    => 1,
				'scope'       => 'global',
			],
			$extra
		);
	}

	/**
	 * Create a success notice array.
	 *
	 * @param string $title       Notice title.
	 * @param string $description Notice description.
	 * @param array  $extra       Additional notice properties.
	 *
	 * @return array
	 */
	public static function success( string $title, string $description, array $extra = [] ): array {
		return array_merge(
			[
				'type'        => 'success',
				'title'       => $title,
				'description' => $description,
				'priority'    => 10,
				'scope'       => 'local',
			],
			$extra
		);
	}

	/**
	 * Build an AJAX action button config.
	 *
	 * @param string $text         Button text.
	 * @param string $ajax_action  The AJAX action name.
	 * @param string $nonce_action The nonce action.
	 * @param array  $extra        Additional action properties.
	 *
	 * @return array
	 */
	public static function ajax_action( string $text, string $ajax_action, string $nonce_action, array $extra = [] ): array {
		return array_merge(
			[
				'type'      => 'primary',
				'text'      => $text,
				'ajax_data' => [
					'action'   => $ajax_action,
					'_wpnonce' => wp_create_nonce( $nonce_action ),
				],
			],
			$extra
		);
	}

	/**
	 * Build a URL link action button config.
	 *
	 * @param string $text   Button text.
	 * @param string $url    Target URL.
	 * @param string $target Link target (_self, _blank).
	 *
	 * @return array
	 */
	public static function link_action( string $text, string $url, string $target = '_self' ): array {
		return [
			'type'   => 'primary',
			'text'   => $text,
			'action' => $url,
			'target' => $target,
		];
	}
}
