<?php

namespace WeDevs\WPKit\AdminNotification;

/**
 * Value object representing an admin notice.
 */
class Notice {

	/**
	 * Notice type: 'info', 'success', 'warning', 'error'.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Notice title.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * Notice description/content.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Priority (lower = higher priority).
	 *
	 * @var int
	 */
	public int $priority;

	/**
	 * Scope: 'local' (plugin pages only) or 'global' (all admin pages).
	 *
	 * @var string
	 */
	public string $scope;

	/**
	 * Action button configurations.
	 *
	 * @var array
	 */
	public array $actions;

	/**
	 * Whether the notice can be dismissed.
	 *
	 * @var bool
	 */
	public bool $is_dismissible;

	/**
	 * Unique notice identifier for dismissal tracking.
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * @param array $args Notice arguments.
	 */
	public function __construct( array $args = [] ) {
		$this->type           = $args['type'] ?? 'info';
		$this->title          = $args['title'] ?? '';
		$this->description    = $args['description'] ?? '';
		$this->priority       = $args['priority'] ?? 10;
		$this->scope          = $args['scope'] ?? 'local';
		$this->actions        = $args['actions'] ?? [];
		$this->is_dismissible = $args['is_dismissible'] ?? false;
		$this->key            = $args['key'] ?? '';
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'type'           => $this->type,
			'title'          => $this->title,
			'description'    => $this->description,
			'priority'       => $this->priority,
			'scope'          => $this->scope,
			'actions'        => $this->actions,
			'is_dismissible' => $this->is_dismissible,
			'key'            => $this->key,
		];
	}
}
