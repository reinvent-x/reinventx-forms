<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Database\Tables;
use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\Form;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\FormRepository;
use Reinventx\Leads\Lead;
use Reinventx\Leads\LeadRepository;
use Reinventx\Setup\Activator;
use Reinventx\Submissions\SubmissionContext;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class LeadsRestTest extends ReinventxTestCase {

	private WP_REST_Server $server;

	private FormRepository $forms;

	private LeadRepository $leads;

	private Form $form;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wp_rest_server, $wpdb;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $this->server );

		$types       = FieldTypeRegistry::withDefaults();
		$tables      = new Tables( $wpdb->prefix );
		$this->forms = new FormRepository( $wpdb, $tables, $types );
		$this->leads = new LeadRepository( $wpdb, $tables );

		$this->form = $this->forms->insert(
			'Contact',
			FormConfig::fromArray(
				array(
					'fields' => array(
						array(
							'id'       => 'name',
							'type'     => 'text',
							'label'    => 'Your name',
							'required' => true,
						),
						array(
							'id'       => 'email',
							'type'     => 'email',
							'label'    => 'Email',
							'required' => false,
						),
					),
				),
				$types
			)
		);

		$this->actAsAdmin();
	}

	private function actAsAdmin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function context(): SubmissionContext {
		return SubmissionContext::fromRaw(
			'https://example.com/contact',
			'Contact us',
			'https://google.com/search',
			'Mozilla/5.0 Test',
			'203.0.113.7',
			'secret'
		);
	}

	private function seedLead( string $name = 'Jane Doe', ?int $form_id = null ): Lead {
		return $this->leads->insert(
			$form_id ?? $this->form->id,
			array(
				'name'  => $name,
				'email' => 'jane@example.com',
			),
			$this->context()
		);
	}

	private function request( string $method, string $route, ?array $body = null ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );

		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}

	public function testLoggedOutIsDenied(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->request( 'GET', '/reinventx-forms/v1/leads' )->get_status() );
	}

	public function testNonPrivilegedUserIsDeniedOnEveryRoute(): void {
		$lead = $this->seedLead();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->request( 'GET', '/reinventx-forms/v1/leads' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/reinventx-forms/v1/leads/' . $lead->id )->get_status() );
		$this->assertSame(
			403,
			$this->request( 'PATCH', '/reinventx-forms/v1/leads/' . $lead->id, array( 'status' => 'contacted' ) )->get_status()
		);
		$this->assertSame(
			403,
			$this->request( 'POST', '/reinventx-forms/v1/leads/' . $lead->id . '/notes', array( 'note' => 'hi' ) )->get_status()
		);
	}

	public function testIndexListsNewestFirstWithFormNameAndPrimary(): void {
		global $wpdb;

		$older = $this->seedLead( 'Older Lead' );
		$newer = $this->seedLead( 'Newer Lead' );

		// Force distinct timestamps (both inserts happen in the same second).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->tables()->leads(),
			array( 'submitted_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ) ),
			array( 'id' => $older->id )
		);

		$response = $this->request( 'GET', '/reinventx-forms/v1/leads' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $data['total'] );
		$this->assertSame( $newer->id, $data['items'][0]['id'] );
		$this->assertSame( 'Newer Lead', $data['items'][0]['primary'] );
		$this->assertSame( 'Contact', $data['items'][0]['form_name'] );
		$this->assertSame( 'new', $data['items'][0]['status'] );
	}

	public function testIndexFiltersByFormAndStatus(): void {
		$other_form = $this->forms->insert(
			'Other',
			FormConfig::fromArray( array( 'fields' => array() ), FieldTypeRegistry::withDefaults() )
		);

		$mine  = $this->seedLead( 'Mine' );
		$other = $this->seedLead( 'Other lead', $other_form->id );

		$this->leads->updateStatus( $other->id, 'contacted' );

		$request = new WP_REST_Request( 'GET', '/reinventx-forms/v1/leads' );
		$request->set_query_params( array( 'form_id' => (string) $this->form->id ) );
		$data = $this->server->dispatch( $request )->get_data();

		$this->assertSame( 1, $data['total'] );
		$this->assertSame( $mine->id, $data['items'][0]['id'] );

		$request = new WP_REST_Request( 'GET', '/reinventx-forms/v1/leads' );
		$request->set_query_params( array( 'status' => 'contacted' ) );
		$data = $this->server->dispatch( $request )->get_data();

		$this->assertSame( 1, $data['total'] );
		$this->assertSame( $other->id, $data['items'][0]['id'] );
	}

	public function testIndexPaginatesPastOnePage(): void {
		for ( $i = 1; $i <= 25; $i++ ) {
			$this->seedLead( 'Lead ' . $i );
		}

		$request = new WP_REST_Request( 'GET', '/reinventx-forms/v1/leads' );
		$request->set_query_params(
			array(
				'per_page' => '10',
				'page'     => '3',
			)
		);
		$data = $this->server->dispatch( $request )->get_data();

		$this->assertSame( 25, $data['total'] );
		$this->assertSame( 3, $data['total_pages'] );
		$this->assertCount( 5, $data['items'] );
	}

	public function testIndexRejectsUnknownStatusFilter(): void {
		$request = new WP_REST_Request( 'GET', '/reinventx-forms/v1/leads' );
		$request->set_query_params( array( 'status' => 'bogus' ) );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	public function testDetailShowsFieldsContextAndNotes(): void {
		$lead = $this->seedLead();

		$this->request( 'POST', '/reinventx-forms/v1/leads/' . $lead->id . '/notes', array( 'note' => 'Called them.' ) );

		$data = $this->request( 'GET', '/reinventx-forms/v1/leads/' . $lead->id )->get_data();

		$this->assertSame( 'Contact', $data['form_name'] );
		$this->assertSame(
			array(
				array(
					'id'    => 'name',
					'label' => 'Your name',
					'value' => 'Jane Doe',
				),
				array(
					'id'    => 'email',
					'label' => 'Email',
					'value' => 'jane@example.com',
				),
			),
			$data['fields']
		);
		$this->assertSame( 'https://example.com/contact', $data['context']['source_url'] );
		$this->assertSame( 'Contact us', $data['context']['source_title'] );
		$this->assertSame( 'https://google.com/search', $data['context']['referrer_url'] );
		$this->assertNotEmpty( $data['submitted_at'] );
		$this->assertCount( 1, $data['notes'] );
		$this->assertSame( 'Called them.', $data['notes'][0]['note'] );
	}

	public function testDetailKeepsValuesForRemovedFields(): void {
		$lead = $this->leads->insert(
			$this->form->id,
			array(
				'name'  => 'Jane',
				'ghost' => 'value from a deleted field',
			),
			$this->context()
		);

		$data = $this->request( 'GET', '/reinventx-forms/v1/leads/' . $lead->id )->get_data();

		$ids = array_column( $data['fields'], 'id' );

		$this->assertContains( 'ghost', $ids );
	}

	public function testStatusChangePersistsAndFiresAction(): void {
		$lead     = $this->seedLead();
		$captured = null;

		add_action(
			'rvtx_lead_status_changed',
			static function ( Lead $updated, string $from, string $to ) use ( &$captured ) {
				$captured = array( $updated->id, $from, $to );
			},
			10,
			3
		);

		$response = $this->request(
			'PATCH',
			'/reinventx-forms/v1/leads/' . $lead->id,
			array( 'status' => 'contacted' )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'contacted', $response->get_data()['status'] );
		$this->assertSame( array( $lead->id, 'new', 'contacted' ), $captured );

		$list = $this->request( 'GET', '/reinventx-forms/v1/leads' )->get_data();

		$this->assertSame( 'contacted', $list['items'][0]['status'] );
	}

	public function testSameStatusChangeDoesNotFireAction(): void {
		$lead  = $this->seedLead();
		$fired = false;

		add_action(
			'rvtx_lead_status_changed',
			static function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->request( 'PATCH', '/reinventx-forms/v1/leads/' . $lead->id, array( 'status' => 'new' ) );

		$this->assertFalse( $fired );
	}

	public function testInvalidStatusReturns400(): void {
		$lead = $this->seedLead();

		$response = $this->request(
			'PATCH',
			'/reinventx-forms/v1/leads/' . $lead->id,
			array( 'status' => 'bogus' )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rvtx_invalid_status', $response->get_data()['code'] );
	}

	public function testNoteSavesWithAuthorAttribution(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Alice Admin',
			)
		);

		wp_set_current_user( $user_id );

		$lead = $this->seedLead();

		$first = $this->request(
			'POST',
			'/reinventx-forms/v1/leads/' . $lead->id . '/notes',
			array( 'note' => 'First call made.' )
		);

		$this->assertSame( 201, $first->get_status() );
		$this->assertSame( 'Alice Admin', $first->get_data()['author'] );

		$this->request(
			'POST',
			'/reinventx-forms/v1/leads/' . $lead->id . '/notes',
			array( 'note' => 'Second follow-up.' )
		);

		$notes = $this->request( 'GET', '/reinventx-forms/v1/leads/' . $lead->id )->get_data()['notes'];

		$this->assertSame(
			array( 'First call made.', 'Second follow-up.' ),
			array_column( $notes, 'note' )
		);
	}

	public function testEmptyNoteReturns400(): void {
		$lead = $this->seedLead();

		$response = $this->request(
			'POST',
			'/reinventx-forms/v1/leads/' . $lead->id . '/notes',
			array( 'note' => "  \n " )
		);

		$this->assertSame( 400, $response->get_status() );
	}

	public function testMissingLeadReturns404OnEveryRoute(): void {
		$this->assertSame( 404, $this->request( 'GET', '/reinventx-forms/v1/leads/999999' )->get_status() );
		$this->assertSame(
			404,
			$this->request( 'PATCH', '/reinventx-forms/v1/leads/999999', array( 'status' => 'contacted' ) )->get_status()
		);
		$this->assertSame(
			404,
			$this->request( 'POST', '/reinventx-forms/v1/leads/999999/notes', array( 'note' => 'x' ) )->get_status()
		);
	}

	public function testHostileHtmlComesBackVerbatimInJson(): void {
		$payload = '<script>alert("xss")</script><img src=x onerror=alert(1)>';

		$lead = $this->leads->insert(
			$this->form->id,
			array( 'name' => $payload ),
			$this->context()
		);

		$data = $this->request( 'GET', '/reinventx-forms/v1/leads/' . $lead->id )->get_data();

		// Raw in JSON (inert by definition); the SPA renders it as text.
		$this->assertSame( $payload, $data['fields'][0]['value'] );
	}
}
