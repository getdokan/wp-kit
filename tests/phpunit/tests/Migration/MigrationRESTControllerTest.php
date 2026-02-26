<?php

namespace WeDevs\WPKit\Tests\Migration;

use WeDevs\WPKit\Migration\MigrationManager;
use WeDevs\WPKit\Migration\MigrationRegistry;
use WeDevs\WPKit\Migration\MigrationRESTController;
use WeDevs\WPKit\Migration\MigrationStatus;
use WeDevs\WPKit\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class MigrationRESTControllerTest extends TestCase {

	/**
	 * @var MigrationManager|Mockery\MockInterface
	 */
	private $manager;

	/**
	 * @var MigrationRESTController
	 */
	private MigrationRESTController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->manager    = Mockery::mock( MigrationManager::class );
		$this->controller = new MigrationRESTController( $this->manager, 'testplugin/v1' );
	}

	public function test_register_routes_registers_status_and_upgrade(): void {
		Functions\expect( 'register_rest_route' )
			->twice();

		$this->controller->register_routes();
	}

	public function test_get_status_returns_migration_info(): void {
		$status = Mockery::mock( MigrationStatus::class );
		$status->shouldReceive( 'get_summary' )->once()->andReturn( [
			'total'     => 2,
			'completed' => 2,
			'failed'    => 0,
			'running'   => 0,
			'pending'   => 0,
		] );
		$status->shouldReceive( 'get_log' )->once()->andReturn( [] );
		$status->shouldReceive( 'is_running' )->once()->andReturn( false );

		$this->manager->shouldReceive( 'get_status' )->once()->andReturn( $status );
		$this->manager->shouldReceive( 'is_upgrade_required' )->once()->andReturn( false );

		$registry = Mockery::mock( MigrationRegistry::class );
		$registry->shouldReceive( 'get_db_installed_version' )->once()->andReturn( '1.2.0' );
		$registry->shouldReceive( 'get_plugin_version' )->once()->andReturn( '1.2.0' );
		$this->manager->shouldReceive( 'get_registry' )->andReturn( $registry );

		$request  = Mockery::mock( \WP_REST_Request::class );
		$response = $this->controller->get_status( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->status );
		$this->assertFalse( $response->data['is_running'] );
		$this->assertFalse( $response->data['is_upgrade_required'] );
		$this->assertSame( '1.2.0', $response->data['db_version'] );
	}

	public function test_do_upgrade_returns_error_when_ongoing(): void {
		$this->manager->shouldReceive( 'has_ongoing_process' )->once()->andReturn( true );

		$request  = Mockery::mock( \WP_REST_Request::class );
		$response = $this->controller->do_upgrade( $request );

		$this->assertSame( 400, $response->status );
		$this->assertSame( 'Upgrade already in progress.', $response->data['message'] );
	}

	public function test_do_upgrade_returns_error_when_not_required(): void {
		$this->manager->shouldReceive( 'has_ongoing_process' )->once()->andReturn( false );
		$this->manager->shouldReceive( 'is_upgrade_required' )->once()->andReturn( false );

		$request  = Mockery::mock( \WP_REST_Request::class );
		$response = $this->controller->do_upgrade( $request );

		$this->assertSame( 400, $response->status );
		$this->assertSame( 'No upgrade required.', $response->data['message'] );
	}

	public function test_do_upgrade_runs_upgrade_successfully(): void {
		$this->manager->shouldReceive( 'has_ongoing_process' )->once()->andReturn( false );
		$this->manager->shouldReceive( 'is_upgrade_required' )->once()->andReturn( true );
		$this->manager->shouldReceive( 'do_upgrade' )->once();

		$request  = Mockery::mock( \WP_REST_Request::class );
		$response = $this->controller->do_upgrade( $request );

		$this->assertSame( 201, $response->status );
		$this->assertTrue( $response->data['success'] );
	}

	public function test_check_permission_requires_update_plugins(): void {
		Functions\expect( 'current_user_can' )
			->with( 'update_plugins' )
			->once()
			->andReturn( true );

		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_check_permission_denies_unauthorized_user(): void {
		Functions\expect( 'current_user_can' )
			->with( 'update_plugins' )
			->once()
			->andReturn( false );

		$this->assertFalse( $this->controller->check_permission() );
	}
}
