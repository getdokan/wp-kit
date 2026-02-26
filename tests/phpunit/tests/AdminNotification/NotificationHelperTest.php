<?php

namespace WeDevs\WPKit\Tests\AdminNotification;

use WeDevs\WPKit\AdminNotification\NotificationHelper;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;

class NotificationHelperTest extends TestCase {

	public function test_info_creates_info_notice(): void {
		$result = NotificationHelper::info( 'Info Title', 'Info description.' );

		$this->assertSame( 'info', $result['type'] );
		$this->assertSame( 'Info Title', $result['title'] );
		$this->assertSame( 'Info description.', $result['description'] );
		$this->assertSame( 10, $result['priority'] );
		$this->assertSame( 'local', $result['scope'] );
	}

	public function test_warning_creates_warning_notice(): void {
		$result = NotificationHelper::warning( 'Warn Title', 'Warn desc.' );

		$this->assertSame( 'warning', $result['type'] );
		$this->assertSame( 'Warn Title', $result['title'] );
		$this->assertSame( 5, $result['priority'] );
		$this->assertSame( 'local', $result['scope'] );
	}

	public function test_error_creates_error_notice_with_global_scope(): void {
		$result = NotificationHelper::error( 'Error Title', 'Error desc.' );

		$this->assertSame( 'error', $result['type'] );
		$this->assertSame( 1, $result['priority'] );
		$this->assertSame( 'global', $result['scope'] );
	}

	public function test_success_creates_success_notice(): void {
		$result = NotificationHelper::success( 'Success', 'All good.' );

		$this->assertSame( 'success', $result['type'] );
		$this->assertSame( 10, $result['priority'] );
		$this->assertSame( 'local', $result['scope'] );
	}

	public function test_extra_params_override_defaults(): void {
		$result = NotificationHelper::info( 'Title', 'Desc', [
			'priority' => 1,
			'scope'    => 'global',
			'key'      => 'custom_key',
		] );

		$this->assertSame( 1, $result['priority'] );
		$this->assertSame( 'global', $result['scope'] );
		$this->assertSame( 'custom_key', $result['key'] );
	}

	public function test_ajax_action_creates_button_config(): void {
		Functions\expect( 'wp_create_nonce' )
			->with( 'my_nonce_action' )
			->once()
			->andReturn( 'nonce_value_123' );

		$result = NotificationHelper::ajax_action( 'Click Me', 'my_ajax_action', 'my_nonce_action' );

		$this->assertSame( 'primary', $result['type'] );
		$this->assertSame( 'Click Me', $result['text'] );
		$this->assertSame( 'my_ajax_action', $result['ajax_data']['action'] );
		$this->assertSame( 'nonce_value_123', $result['ajax_data']['_wpnonce'] );
	}

	public function test_ajax_action_accepts_extra_params(): void {
		Functions\expect( 'wp_create_nonce' )->andReturn( 'nonce' );

		$result = NotificationHelper::ajax_action( 'Btn', 'action', 'nonce_action', [
			'type' => 'secondary',
		] );

		$this->assertSame( 'secondary', $result['type'] );
	}

	public function test_link_action_creates_url_button_config(): void {
		$result = NotificationHelper::link_action( 'Go', 'https://example.com' );

		$this->assertSame( 'primary', $result['type'] );
		$this->assertSame( 'Go', $result['text'] );
		$this->assertSame( 'https://example.com', $result['action'] );
		$this->assertSame( '_self', $result['target'] );
	}

	public function test_link_action_with_blank_target(): void {
		$result = NotificationHelper::link_action( 'External', 'https://example.com', '_blank' );

		$this->assertSame( '_blank', $result['target'] );
	}
}
