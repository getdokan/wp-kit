<?php

namespace WeDevs\WPKit\Migration;

/**
 * WP Cron-based background process for batch operations.
 *
 * Provides async batch processing without requiring WooCommerce's
 * WP_Background_Process class. Uses WordPress options for queue storage
 * and WP Cron for deferred execution.
 *
 * Usage:
 *   class MyProcess extends BackgroundProcess {
 *       protected string $action = 'my_process';
 *
 *       protected function task( $item ) {
 *           // Process $item. Return false to complete, or return item to re-queue.
 *           return false;
 *       }
 *   }
 *
 *   $process = new MyProcess();
 *   $process->init_hooks();
 *   $process->push_to_queue( $items );
 *   $process->dispatch();
 */
abstract class BackgroundProcess {

	/**
	 * Action identifier. Override in subclasses.
	 *
	 * @var string
	 */
	protected string $action = '';

	/**
	 * Prefix for option keys and hooks. Must be set by consumer subclasses.
	 *
	 * @var string
	 */
	protected string $prefix = '';

	/**
	 * Time limit per batch in seconds.
	 *
	 * @var int
	 */
	protected int $time_limit = 20;

	public function __construct() {
		if ( ! $this->action ) {
			$this->action = $this->prefix . '_' . md5( static::class );
		}
	}

	/**
	 * Process a single item from the queue.
	 *
	 * @param mixed $item Queue item to process.
	 *
	 * @return mixed|false Return item to re-queue, or false to remove from queue.
	 */
	abstract protected function task( $item );

	/**
	 * Push items to the queue.
	 *
	 * @param array $items Items to add to the queue.
	 *
	 * @return self
	 */
	public function push_to_queue( array $items ): self {
		$batch_key = $this->get_batch_key();
		$existing  = get_option( $batch_key, [] );
		$existing  = array_merge( $existing, $items );

		update_option( $batch_key, $existing, false );

		// Track total items for progress reporting.
		$total_key     = $this->get_total_key();
		$current_total = (int) get_option( $total_key, 0 );
		update_option( $total_key, $current_total + count( $items ), false );

		return $this;
	}

	/**
	 * Dispatch the background process via WP Cron.
	 */
	public function dispatch(): void {
		if ( ! wp_next_scheduled( $this->get_cron_hook() ) ) {
			wp_schedule_single_event( time(), $this->get_cron_hook() );
		}
	}

	/**
	 * Register WordPress hooks. Call during plugin bootstrap.
	 */
	public function init_hooks(): void {
		add_action( $this->get_cron_hook(), [ $this, 'handle_cron' ] );
	}

	/**
	 * Handle the cron event - process items in batch.
	 */
	public function handle_cron(): void {
		$batch_key = $this->get_batch_key();
		$items     = get_option( $batch_key, [] );

		if ( empty( $items ) ) {
			$this->complete();
			return;
		}

		$start_time = time();

		while ( ! empty( $items ) && ( time() - $start_time ) < $this->time_limit ) {
			$item   = array_shift( $items );
			$result = $this->task( $item );

			if ( false !== $result ) {
				$items[] = $result;
			}
		}

		update_option( $batch_key, $items, false );

		if ( ! empty( $items ) ) {
			wp_schedule_single_event( time() + 10, $this->get_cron_hook() );
		} else {
			delete_option( $batch_key );
			delete_option( $this->get_total_key() );
			$this->complete();
		}
	}

	/**
	 * Called when all items are processed. Override for cleanup.
	 */
	protected function complete(): void {
		// Override in subclass for post-completion logic.
	}

	/**
	 * Check if the process is currently running.
	 *
	 * @return bool
	 */
	public function is_processing(): bool {
		return (bool) get_option( $this->get_batch_key(), false );
	}

	/**
	 * Cancel the background process.
	 */
	public function cancel(): void {
		delete_option( $this->get_batch_key() );
		delete_option( $this->get_total_key() );
		wp_clear_scheduled_hook( $this->get_cron_hook() );
	}

	/**
	 * Get the progress of the background process.
	 *
	 * @return array{is_processing: bool, total: int, completed: int, remaining: int, percentage: int}
	 */
	public function get_progress(): array {
		$total     = (int) get_option( $this->get_total_key(), 0 );
		$remaining = count( get_option( $this->get_batch_key(), [] ) );
		$completed = max( 0, $total - $remaining );

		return [
			'is_processing' => $this->is_processing(),
			'total'         => $total,
			'completed'     => $completed,
			'remaining'     => $remaining,
			'percentage'    => $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0,
		];
	}

	/**
	 * Get the option key for the batch queue.
	 *
	 * @return string
	 */
	protected function get_batch_key(): string {
		return $this->prefix . '_bg_' . $this->action;
	}

	/**
	 * Get the option key for the total items count.
	 *
	 * @return string
	 */
	protected function get_total_key(): string {
		return $this->prefix . '_bg_' . $this->action . '_total';
	}

	/**
	 * Get the WP Cron hook name.
	 *
	 * @return string
	 */
	protected function get_cron_hook(): string {
		return $this->prefix . '_process_' . $this->action;
	}
}
