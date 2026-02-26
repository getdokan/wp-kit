<?php

namespace WeDevs\WPKit\Tests\AdminNotification;

use WeDevs\WPKit\AdminNotification\Contracts\NoticeProviderInterface;
use WeDevs\WPKit\AdminNotification\Notice;
use WeDevs\WPKit\AdminNotification\NoticeManager;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class NoticeManagerTest extends TestCase {

	/**
	 * @var NoticeManager
	 */
	private NoticeManager $manager;

	protected function setUp(): void {
		parent::setUp();
		$this->manager = new NoticeManager( 'testplugin' );
	}

	public function test_constructor_sets_prefix_and_filter_name(): void {
		$this->assertSame( 'testplugin', $this->manager->get_prefix() );
		$this->assertSame( 'testplugin_admin_notices', $this->manager->get_filter_name() );
	}

	public function test_register_provider_returns_self_for_chaining(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )->andReturn( [] );

		$result = $this->manager->register_provider( $provider );

		$this->assertSame( $this->manager, $result );
	}

	public function test_get_notices_collects_from_providers(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )
			->once()
			->andReturn( [
				[
					'type'     => 'info',
					'title'    => 'Provider Notice',
					'priority' => 10,
				],
			] );

		$this->manager->register_provider( $provider );

		Functions\expect( 'apply_filters' )
			->with( 'testplugin_admin_notices', Mockery::any() )
			->once()
			->andReturnUsing( function () { return func_get_args()[1]; } );

		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [] );

		$notices = $this->manager->get_notices();

		$this->assertCount( 1, $notices );
		$this->assertSame( 'Provider Notice', $notices[0]['title'] );
	}

	public function test_get_notices_converts_notice_objects_to_arrays(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )
			->once()
			->andReturn( [
				new Notice( [
					'type'  => 'warning',
					'title' => 'Object Notice',
					'key'   => 'obj_notice',
				] ),
			] );

		$this->manager->register_provider( $provider );

		Functions\expect( 'apply_filters' )->andReturnUsing( function () { return func_get_args()[1]; } );
		Functions\expect( 'get_option' )->andReturn( [] );

		$notices = $this->manager->get_notices();

		$this->assertCount( 1, $notices );
		$this->assertIsArray( $notices[0] );
		$this->assertSame( 'Object Notice', $notices[0]['title'] );
	}

	public function test_get_notices_filters_by_scope(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )
			->once()
			->andReturn( [
				[ 'type' => 'info', 'title' => 'Local', 'scope' => 'local', 'priority' => 10 ],
				[ 'type' => 'error', 'title' => 'Global', 'scope' => 'global', 'priority' => 1 ],
			] );

		$this->manager->register_provider( $provider );

		Functions\expect( 'apply_filters' )->andReturnUsing( function () { return func_get_args()[1]; } );
		Functions\expect( 'get_option' )->andReturn( [] );

		$global_notices = $this->manager->get_notices( 'global' );

		$this->assertCount( 1, $global_notices );
		$this->assertSame( 'Global', $global_notices[0]['title'] );

		// Re-setup for local scope test.
		$provider2 = Mockery::mock( NoticeProviderInterface::class );
		$provider2->shouldReceive( 'get_notices' )->andReturn( [
			[ 'type' => 'info', 'title' => 'Local', 'scope' => 'local', 'priority' => 10 ],
			[ 'type' => 'error', 'title' => 'Global', 'scope' => 'global', 'priority' => 1 ],
		] );

		$manager2 = new NoticeManager( 'testplugin' );
		$manager2->register_provider( $provider2 );

		$local_notices = $manager2->get_notices( 'local' );

		$this->assertCount( 1, $local_notices );
		$this->assertSame( 'Local', $local_notices[0]['title'] );
	}

	public function test_get_notices_excludes_dismissed(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )
			->once()
			->andReturn( [
				[ 'type' => 'info', 'title' => 'Keep', 'key' => 'keep_notice', 'priority' => 10 ],
				[ 'type' => 'info', 'title' => 'Dismiss', 'key' => 'dismissed_notice', 'priority' => 10 ],
			] );

		$this->manager->register_provider( $provider );

		Functions\expect( 'apply_filters' )->andReturnUsing( function () { return func_get_args()[1]; } );
		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [ 'dismissed_notice' ] );

		$notices = $this->manager->get_notices();

		$this->assertCount( 1, $notices );
		$this->assertSame( 'Keep', $notices[0]['title'] );
	}

	public function test_get_notices_sorts_by_priority(): void {
		$provider = Mockery::mock( NoticeProviderInterface::class );
		$provider->shouldReceive( 'get_notices' )
			->once()
			->andReturn( [
				[ 'type' => 'info', 'title' => 'Low Priority', 'priority' => 20 ],
				[ 'type' => 'error', 'title' => 'High Priority', 'priority' => 1 ],
				[ 'type' => 'warning', 'title' => 'Medium Priority', 'priority' => 10 ],
			] );

		$this->manager->register_provider( $provider );

		Functions\expect( 'apply_filters' )->andReturnUsing( function () { return func_get_args()[1]; } );
		Functions\expect( 'get_option' )->andReturn( [] );

		$notices = $this->manager->get_notices();

		$this->assertSame( 'High Priority', $notices[0]['title'] );
		$this->assertSame( 'Medium Priority', $notices[1]['title'] );
		$this->assertSame( 'Low Priority', $notices[2]['title'] );
	}

	public function test_get_notices_applies_wordpress_filter(): void {
		Functions\expect( 'apply_filters' )
			->with( 'testplugin_admin_notices', [] )
			->once()
			->andReturn( [
				[ 'type' => 'info', 'title' => 'From Filter', 'priority' => 10 ],
			] );

		Functions\expect( 'get_option' )->andReturn( [] );

		$notices = $this->manager->get_notices();

		$this->assertCount( 1, $notices );
		$this->assertSame( 'From Filter', $notices[0]['title'] );
	}
}
