<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Setup\Activator;
use Reinventx\Setup\Uninstaller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SettingsRestTest extends ReinventxTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );

		delete_option( Uninstaller::DELETE_DATA_OPTION );
	}

	public function tear_down(): void {
		delete_option( Uninstaller::DELETE_DATA_OPTION );

		parent::tear_down();
	}

	private function request( string $method, ?array $body = null ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/reinventx-forms/v1/settings' );

		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}

	public function testDeniedForLoggedOutAndNonAdmins(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET' )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
		$this->assertSame(
			403,
			$this->request( 'PUT', array( 'delete_data_on_uninstall' => true ) )->get_status()
		);
	}

	public function testDefaultsToFalse(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request( 'GET' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['delete_data_on_uninstall'] );
	}

	public function testToggleRoundTripsAndPersists(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$on = $this->request( 'PUT', array( 'delete_data_on_uninstall' => true ) );

		$this->assertSame( 200, $on->get_status() );
		$this->assertTrue( $on->get_data()['delete_data_on_uninstall'] );
		$this->assertTrue( (bool) get_option( Uninstaller::DELETE_DATA_OPTION ) );

		$off = $this->request( 'PUT', array( 'delete_data_on_uninstall' => false ) );

		$this->assertFalse( $off->get_data()['delete_data_on_uninstall'] );
		$this->assertFalse( (bool) get_option( Uninstaller::DELETE_DATA_OPTION ) );
	}

	public function testNonBooleanValueIsRejected(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request( 'PUT', array( 'delete_data_on_uninstall' => 'yes' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_invalid_setting', $response->get_data()['code'] );
	}
}
