<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\Form;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\FormRepository;
use Reinventx\Leads\Lead;
use Reinventx\Plugin;
use Reinventx\Setup\Activator;
use Reinventx\Submissions\RateLimiter;
use Reinventx\Submissions\SubmissionToken;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SubmissionsRestTest extends ReinventxTestCase {

	private WP_REST_Server $server;

	private FormRepository $forms;

	private Form $form;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wp_rest_server, $wpdb;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );

		$types       = FieldTypeRegistry::withDefaults();
		$this->forms = new FormRepository( $wpdb, $this->tables(), $types );

		$this->form = $this->forms->insert(
			'Contact',
			FormConfig::fromArray(
				array(
					'fields' => array(
						array(
							'id'       => 'name',
							'type'     => 'text',
							'label'    => 'Name',
							'required' => true,
						),
						array(
							'id'       => 'email',
							'type'     => 'email',
							'label'    => 'Email',
							'required' => true,
						),
					),
				),
				$types
			)
		);

		// Unique client per test so rate-limiter transients never leak
		// across tests (DDL in tear_down commits them).
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 ) . '-' . uniqid();

		wp_set_current_user( 0 );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function payload( array $overrides = array() ): array {
		$issued_at = time() - SubmissionToken::MIN_AGE_SECONDS - 2;

		return array_merge(
			array(
				'form_id'      => $this->form->id,
				'token'        => Plugin::submissionToken()->issue( $this->form->id, $issued_at ),
				'issued_at'    => $issued_at,
				'website'      => '',
				'source_url'   => 'https://example.com/contact',
				'source_title' => 'Contact us',
				'fields'       => array(
					'name'  => 'Jane Doe',
					'email' => 'jane@example.com',
				),
			),
			$overrides
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function submit( array $payload ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/reinventx-forms/v1/submissions' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'User-Agent', 'Mozilla/5.0 Test' );
		$request->set_header( 'Referer', 'https://google.com/search' );
		$request->set_body( (string) wp_json_encode( $payload ) );

		return $this->server->dispatch( $request );
	}

	private function leadCount(): int {
		global $wpdb;

		$table = $this->tables()->leads();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function testValidSubmissionStoresLeadWithContext(): void {
		$captured = null;

		add_action(
			'rvtx_lead_created',
			static function ( Lead $lead ) use ( &$captured ) {
				$captured = $lead;
			}
		);

		$response = $this->submit( $this->payload() );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'created', $response->get_data()['status'] );

		$this->assertInstanceOf( Lead::class, $captured );
		$this->assertSame( $this->form->id, $captured->formId );
		$this->assertSame( 'Jane Doe', $captured->data['name'] );
		$this->assertSame( 'jane@example.com', $captured->data['email'] );
		$this->assertSame( 'https://example.com/contact', $captured->sourceUrl );
		$this->assertSame( 'Contact us', $captured->sourceTitle );
		$this->assertSame( 'https://google.com/search', $captured->referrerUrl );
		$this->assertSame( 'Mozilla/5.0 Test', $captured->userAgent );
		$this->assertNotNull( $captured->ipHash );
		$this->assertSame( Lead::STATUS_NEW, $captured->status );
	}

	public function testMissingRequiredAndBadEmailReturnFieldErrorsAndStoreNothing(): void {
		$response = $this->submit(
			$this->payload( array( 'fields' => array( 'email' => 'nope' ) ) )
		);

		$this->assertSame( 400, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( 'rvtx_invalid_fields', $data['code'] );
		$this->assertSame(
			array(
				'name'  => 'required',
				'email' => 'invalid_email',
			),
			$data['data']['errors']
		);
		$this->assertArrayHasKey( 'name', $data['data']['messages'] );
		$this->assertSame( 0, $this->leadCount() );
	}

	public function testHoneypotFilledIsRejectedAndStoresNothing(): void {
		$response = $this->submit(
			$this->payload( array( 'website' => 'https://spam.example' ) )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_rejected', $response->get_data()['code'] );
		$this->assertSame( 0, $this->leadCount() );
	}

	public function testTooFastSubmissionIsRejected(): void {
		$issued_at = time();

		$response = $this->submit(
			$this->payload(
				array(
					'issued_at' => $issued_at,
					'token'     => Plugin::submissionToken()->issue( $this->form->id, $issued_at ),
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_rejected', $response->get_data()['code'] );
		$this->assertSame( 0, $this->leadCount() );
	}

	public function testForgedTokenIsRejected(): void {
		$response = $this->submit(
			$this->payload( array( 'token' => str_repeat( 'a', 64 ) ) )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_rejected', $response->get_data()['code'] );
		$this->assertSame( 0, $this->leadCount() );
	}

	public function testRateLimitKicksInAfterMaxSubmissions(): void {
		for ( $i = 0; $i < RateLimiter::MAX_PER_WINDOW; $i++ ) {
			$this->assertSame( 201, $this->submit( $this->payload() )->get_status() );
		}

		$response = $this->submit( $this->payload() );

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( 'rvtx_rate_limited', $response->get_data()['code'] );
		$this->assertSame( RateLimiter::MAX_PER_WINDOW, $this->leadCount() );
	}

	public function testArchivedFormReturns404(): void {
		$this->forms->archive( $this->form->id );

		$response = $this->submit( $this->payload() );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 0, $this->leadCount() );
	}

	public function testNonJsonContentTypeIsRejected(): void {
		$request = new WP_REST_Request( 'POST', '/reinventx-forms/v1/submissions' );
		$request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $this->payload() );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 415, $response->get_status() );
	}

	public function testIpHashStorageCanBeFilteredOff(): void {
		add_filter( 'rvtx_store_ip_hash', '__return_false' );

		$captured = null;

		add_action(
			'rvtx_lead_created',
			static function ( Lead $lead ) use ( &$captured ) {
				$captured = $lead;
			}
		);

		$this->assertSame( 201, $this->submit( $this->payload() )->get_status() );
		$this->assertInstanceOf( Lead::class, $captured );
		$this->assertNull( $captured->ipHash );
	}

	public function testRateLimitThresholdIsFilterable(): void {
		add_filter(
			'rvtx_rate_limit_max',
			static fn (): int => 1
		);

		$this->assertSame( 201, $this->submit( $this->payload() )->get_status() );

		$response = $this->submit( $this->payload() );

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( 1, $this->leadCount() );
	}

	public function testScriptPayloadIsStoredRaw(): void {
		$payload = $this->payload(
			array(
				'fields' => array(
					'name'  => '<script>alert("xss")</script>',
					'email' => 'jane@example.com',
				),
			)
		);

		$captured = null;

		add_action(
			'rvtx_lead_created',
			static function ( Lead $lead ) use ( &$captured ) {
				$captured = $lead;
			}
		);

		$this->assertSame( 201, $this->submit( $payload )->get_status() );
		$this->assertInstanceOf( Lead::class, $captured );
		$this->assertSame( '<script>alert("xss")</script>', $captured->data['name'] );
	}
}
