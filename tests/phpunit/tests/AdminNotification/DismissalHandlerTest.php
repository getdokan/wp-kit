<?php

namespace WeDevs\WPKit\Tests\AdminNotification;

use WeDevs\WPKit\AdminNotification\DismissalHandler;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class DismissalHandlerTest extends TestCase {

	/**
	 * @var DismissalHandler
	 */
	private DismissalHandler $handler;

	protected function setUp(): void {
		parent::setUp();
		$this->handler = new DismissalHandler( 'testplugin' );
	}

	public function test_register_hooks_ajax_action(): void {
		Functions\expect( 'add_action' )
			->with( 'wp_ajax_testplugin_dismiss_notice', [ $this->handler, 'handle_dismiss' ] )
			->once();

		$this->handler->register();
	}

	public function test_handle_dismiss_adds_key_to_dismissed_list(): void {
		$_POST['key'] = 'my_notice_key';

		Functions\expect( 'check_ajax_referer' )
			->with( 'testplugin_admin' )
			->once();

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( true );

		Functions\expect( 'sanitize_text_field' )
			->once()
			->andReturnFirstArg();

		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->with( 'testplugin_dismissed_notices', [ 'my_notice_key' ] )
			->once();

		Functions\expect( 'wp_send_json_success' )->once();

		$this->handler->handle_dismiss();

		unset( $_POST['key'] );
	}

	public function test_handle_dismiss_rejects_unauthorized_user(): void {
		Functions\expect( 'check_ajax_referer' )->once();

		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->with( [ 'message' => 'Unauthorized.' ], 403 )
			->once();

		$this->handler->handle_dismiss();
	}

	public function test_handle_dismiss_rejects_missing_key(): void {
		// No $_POST['key'] set.
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );

		Functions\expect( 'wp_send_json_error' )
			->with( [ 'message' => 'Missing notice key.' ], 400 )
			->once();

		$this->handler->handle_dismiss();
	}

	public function test_handle_dismiss_skips_duplicate_keys(): void {
		$_POST['key'] = 'already_dismissed';

		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->andReturn( true );
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'wp_unslash' )->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [ 'already_dismissed' ] );

		// update_option should NOT be called since key is already dismissed.
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_send_json_success' )->once();

		$this->handler->handle_dismiss();

		unset( $_POST['key'] );
	}
}
