<?php

namespace WeDevs\WPKit\Tests\AdminNotification;

use WeDevs\WPKit\AdminNotification\Notice;
use WeDevs\WPKit\Tests\TestCase;

class NoticeTest extends TestCase {

	public function test_constructor_sets_defaults(): void {
		$notice = new Notice();

		$this->assertSame( 'info', $notice->type );
		$this->assertSame( '', $notice->title );
		$this->assertSame( '', $notice->description );
		$this->assertSame( 10, $notice->priority );
		$this->assertSame( 'local', $notice->scope );
		$this->assertSame( [], $notice->actions );
		$this->assertFalse( $notice->is_dismissible );
		$this->assertSame( '', $notice->key );
	}

	public function test_constructor_accepts_all_properties(): void {
		$notice = new Notice( [
			'type'           => 'error',
			'title'          => 'Critical Error',
			'description'    => 'Something went wrong.',
			'priority'       => 1,
			'scope'          => 'global',
			'actions'        => [ [ 'text' => 'Fix', 'action' => '/fix' ] ],
			'is_dismissible' => true,
			'key'            => 'critical_error',
		] );

		$this->assertSame( 'error', $notice->type );
		$this->assertSame( 'Critical Error', $notice->title );
		$this->assertSame( 'Something went wrong.', $notice->description );
		$this->assertSame( 1, $notice->priority );
		$this->assertSame( 'global', $notice->scope );
		$this->assertCount( 1, $notice->actions );
		$this->assertTrue( $notice->is_dismissible );
		$this->assertSame( 'critical_error', $notice->key );
	}

	public function test_to_array_returns_all_properties(): void {
		$notice = new Notice( [
			'type'           => 'warning',
			'title'          => 'Warning Title',
			'description'    => 'Warning description.',
			'priority'       => 5,
			'scope'          => 'local',
			'actions'        => [],
			'is_dismissible' => true,
			'key'            => 'warning_key',
		] );

		$array = $notice->to_array();

		$this->assertSame( 'warning', $array['type'] );
		$this->assertSame( 'Warning Title', $array['title'] );
		$this->assertSame( 'Warning description.', $array['description'] );
		$this->assertSame( 5, $array['priority'] );
		$this->assertSame( 'local', $array['scope'] );
		$this->assertSame( [], $array['actions'] );
		$this->assertTrue( $array['is_dismissible'] );
		$this->assertSame( 'warning_key', $array['key'] );
	}

	public function test_to_array_roundtrip(): void {
		$input = [
			'type'           => 'success',
			'title'          => 'Done',
			'description'    => 'All good.',
			'priority'       => 10,
			'scope'          => 'local',
			'actions'        => [],
			'is_dismissible' => false,
			'key'            => 'done',
		];

		$notice = new Notice( $input );

		$this->assertSame( $input, $notice->to_array() );
	}

	public function test_partial_args_use_defaults_for_missing(): void {
		$notice = new Notice( [
			'title' => 'Only Title',
			'type'  => 'error',
		] );

		$this->assertSame( 'error', $notice->type );
		$this->assertSame( 'Only Title', $notice->title );
		$this->assertSame( '', $notice->description );
		$this->assertSame( 10, $notice->priority );
	}
}
