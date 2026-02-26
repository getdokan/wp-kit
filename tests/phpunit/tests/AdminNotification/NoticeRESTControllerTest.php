<?php

namespace WeDevs\WPKit\Tests\AdminNotification;

use WeDevs\WPKit\AdminNotification\NoticeManager;
use WeDevs\WPKit\AdminNotification\NoticeRESTController;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class NoticeRESTControllerTest extends TestCase {

	/**
	 * @var NoticeManager|Mockery\MockInterface
	 */
	private $manager;

	/**
	 * @var NoticeRESTController
	 */
	private NoticeRESTController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->manager    = Mockery::mock( NoticeManager::class );
		$this->controller = new NoticeRESTController( $this->manager, 'testplugin/v1' );
	}

	public function test_register_routes_calls_register_rest_route(): void {
		Functions\expect( 'register_rest_route' )
			->twice();

		$this->controller->register_routes();
	}

	public function test_get_admin_notices_returns_notices(): void {
		$notices = [
			[ 'type' => 'info', 'title' => 'Test', 'description' => 'A notice.' ],
		];

		$this->manager
			->shouldReceive( 'get_notices' )
			->with( '' )
			->once()
			->andReturn( $notices );

		$rest_response = new \WP_REST_Response( $notices );

		Functions\expect( 'rest_ensure_response' )
			->once()
			->andReturn( $rest_response );

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'scope' )
			->andReturn( '' );

		$result = $this->controller->get_admin_notices( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame( $notices, $result->data );
	}

	public function test_get_admin_notices_filters_by_scope(): void {
		$this->manager
			->shouldReceive( 'get_notices' )
			->with( 'global' )
			->once()
			->andReturn( [] );

		Functions\expect( 'rest_ensure_response' )
			->once()
			->andReturn( new \WP_REST_Response( [] ) );

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'scope' )
			->andReturn( 'global' );

		$this->controller->get_admin_notices( $request );
	}

	public function test_dismiss_notice_adds_key_to_dismissed_list(): void {
		$this->manager
			->shouldReceive( 'get_prefix' )
			->andReturn( 'testplugin' );

		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->with( 'testplugin_dismissed_notices', [ 'my_notice_key' ] )
			->once();

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'key' )
			->andReturn( 'my_notice_key' );

		$response = $this->controller->dismiss_notice( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( [ 'success' => true ], $response->data );
	}

	public function test_dismiss_notice_skips_duplicate_keys(): void {
		$this->manager
			->shouldReceive( 'get_prefix' )
			->andReturn( 'testplugin' );

		Functions\expect( 'get_option' )
			->with( 'testplugin_dismissed_notices', [] )
			->once()
			->andReturn( [ 'already_dismissed' ] );

		Functions\expect( 'update_option' )->never();

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'key' )
			->andReturn( 'already_dismissed' );

		$response = $this->controller->dismiss_notice( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
	}

	public function test_check_permission_requires_manage_options(): void {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_check_permission_denies_unauthorized_user(): void {
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->controller->check_permission() );
	}
}
