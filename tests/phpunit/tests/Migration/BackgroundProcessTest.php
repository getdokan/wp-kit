<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\BackgroundProcess;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Concrete implementation for testing.
 */
class TestProcess extends BackgroundProcess {

	protected string $prefix = 'testplugin';
	protected string $action = 'test_process';

	public array $processed_items = [];

	protected function task( $item ) {
		$this->processed_items[] = $item;
		return false; // Mark as complete.
	}

	public bool $complete_called = false;

	protected function complete(): void {
		$this->complete_called = true;
	}
}

class BackgroundProcessTest extends TestCase {

	/**
	 * @var TestProcess
	 */
	private TestProcess $process;

	protected function setUp(): void {
		parent::setUp();
		$this->process = new TestProcess();
	}

	public function test_push_to_queue_stores_items_in_option(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', [] )
			->once()
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->with( 'testplugin_bg_test_process', [ 'item1', 'item2' ], false )
			->once();

		$result = $this->process->push_to_queue( [ 'item1', 'item2' ] );

		$this->assertSame( $this->process, $result );
	}

	public function test_push_to_queue_appends_to_existing_items(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', [] )
			->once()
			->andReturn( [ 'existing' ] );

		Functions\expect( 'update_option' )
			->with( 'testplugin_bg_test_process', [ 'existing', 'new_item' ], false )
			->once();

		$this->process->push_to_queue( [ 'new_item' ] );
	}

	public function test_dispatch_schedules_cron_event(): void {
		Functions\expect( 'wp_next_scheduled' )
			->with( 'testplugin_process_test_process' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_schedule_single_event' )
			->once();

		$this->process->dispatch();
	}

	public function test_dispatch_skips_if_already_scheduled(): void {
		Functions\expect( 'wp_next_scheduled' )
			->with( 'testplugin_process_test_process' )
			->once()
			->andReturn( time() + 60 );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->process->dispatch();
	}

	public function test_init_hooks_registers_cron_action(): void {
		Functions\expect( 'add_action' )
			->with( 'testplugin_process_test_process', [ $this->process, 'handle_cron' ] )
			->once();

		$this->process->init_hooks();
	}

	public function test_handle_cron_processes_items(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', [] )
			->once()
			->andReturn( [ 'item_a', 'item_b' ] );

		// After processing all items, the empty array is written back before delete.
		Functions\expect( 'update_option' )
			->with( 'testplugin_bg_test_process', [], false )
			->once();

		Functions\expect( 'delete_option' )
			->with( 'testplugin_bg_test_process' )
			->once();

		$this->process->handle_cron();

		$this->assertSame( [ 'item_a', 'item_b' ], $this->process->processed_items );
		$this->assertTrue( $this->process->complete_called );
	}

	public function test_handle_cron_completes_on_empty_queue(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', [] )
			->once()
			->andReturn( [] );

		$this->process->handle_cron();

		$this->assertTrue( $this->process->complete_called );
	}

	public function test_is_processing_checks_option(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', false )
			->once()
			->andReturn( [ 'item' ] );

		$this->assertTrue( $this->process->is_processing() );
	}

	public function test_is_processing_returns_false_when_empty(): void {
		Functions\expect( 'get_option' )
			->with( 'testplugin_bg_test_process', false )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->process->is_processing() );
	}

	public function test_cancel_clears_queue_and_unschedules(): void {
		Functions\expect( 'delete_option' )
			->with( 'testplugin_bg_test_process' )
			->once();

		Functions\expect( 'wp_clear_scheduled_hook' )
			->with( 'testplugin_process_test_process' )
			->once();

		$this->process->cancel();
	}
}
