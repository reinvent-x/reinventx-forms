<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Setup\Activator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class FormsRestTest extends ReinventxTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );
	}

	public function tear_down(): void {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function validPayload(): array {
		return array(
			'name'   => 'Contact form',
			'config' => array(
				'version' => 1,
				'fields'  => array(
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => 'Email address',
						'required' => true,
					),
				),
			),
		);
	}

	/**
	 * @param array<string, mixed>|null  $body
	 * @param array<string, string> $query
	 */
	private function request( string $method, string $route, ?array $body = null, array $query = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );

		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		if ( array() !== $query ) {
			$request->set_query_params( $query );
		}

		return $this->server->dispatch( $request );
	}

	private function actAsAdmin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function testRoutesAreRegistered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/reinventx-forms/v1/forms', $routes );
		$this->assertArrayHasKey( '/reinventx-forms/v1/forms/(?P<id>\d+)', $routes );
	}

	public function testLoggedOutRequestIsRejectedWithAuthStatus(): void {
		wp_set_current_user( 0 );

		$response = $this->request( 'GET', '/reinventx-forms/v1/forms' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'rvtx_forbidden', $response->get_data()['code'] );
	}

	public function testUserWithoutCapabilityIsForbidden(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->request( 'GET', '/reinventx-forms/v1/forms' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rvtx_forbidden', $response->get_data()['code'] );
	}

	public function testCreateAndFetchRoundTrip(): void {
		$this->actAsAdmin();

		$created = $this->request( 'POST', '/reinventx-forms/v1/forms', $this->validPayload() );

		$this->assertSame( 201, $created->get_status() );

		$data = $created->get_data();

		$this->assertSame( 'Contact form', $data['name'] );
		$this->assertSame( 'active', $data['status'] );
		$this->assertSame( 'email', $data['config']['fields'][0]['id'] );

		$fetched = $this->request( 'GET', '/reinventx-forms/v1/forms/' . $data['id'] );

		$this->assertSame( 200, $fetched->get_status() );
		$this->assertSame( $data['id'], $fetched->get_data()['id'] );

		$listed = $this->request( 'GET', '/reinventx-forms/v1/forms' );

		$this->assertSame( 200, $listed->get_status() );
		$this->assertCount( 1, $listed->get_data() );
	}

	public function testCreateWithInvalidConfigReturns400WithErrorCodes(): void {
		$this->actAsAdmin();

		$payload = $this->validPayload();

		$payload['config']['fields'][0]['type'] = 'checkbox';

		$response = $this->request( 'POST', '/reinventx-forms/v1/forms', $payload );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_invalid_config', $response->get_data()['code'] );
		$this->assertSame( array( 'fields.0.type_unknown' ), $response->get_data()['data']['errors'] );
	}

	public function testCreateWithEmptyNameReturns400(): void {
		$this->actAsAdmin();

		$payload         = $this->validPayload();
		$payload['name'] = '   ';

		$response = $this->request( 'POST', '/reinventx-forms/v1/forms', $payload );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_invalid_name', $response->get_data()['code'] );
	}

	public function testUpdateChangesForm(): void {
		$this->actAsAdmin();

		$created = $this->request( 'POST', '/reinventx-forms/v1/forms', $this->validPayload() );
		$id      = $created->get_data()['id'];

		$payload         = $this->validPayload();
		$payload['name'] = 'Renamed form';

		$updated = $this->request( 'PUT', '/reinventx-forms/v1/forms/' . $id, $payload );

		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'Renamed form', $updated->get_data()['name'] );
	}

	public function testMissingFormReturns404(): void {
		$this->actAsAdmin();

		$response = $this->request( 'GET', '/reinventx-forms/v1/forms/999999' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rvtx_not_found', $response->get_data()['code'] );
	}

	public function testDeleteArchivesFormAndHidesItFromDefaultList(): void {
		$this->actAsAdmin();

		$created = $this->request( 'POST', '/reinventx-forms/v1/forms', $this->validPayload() );
		$id      = $created->get_data()['id'];

		$deleted = $this->request( 'DELETE', '/reinventx-forms/v1/forms/' . $id );

		$this->assertSame( 200, $deleted->get_status() );
		$this->assertTrue( $deleted->get_data()['archived'] );

		$default_list = $this->request( 'GET', '/reinventx-forms/v1/forms' );
		$this->assertCount( 0, $default_list->get_data() );

		$archived_list = $this->request( 'GET', '/reinventx-forms/v1/forms', null, array( 'status' => 'archived' ) );
		$this->assertCount( 1, $archived_list->get_data() );
		$this->assertSame( $id, $archived_list->get_data()[0]['id'] );
	}
}
