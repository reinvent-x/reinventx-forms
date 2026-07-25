<?php
declare(strict_types=1);

namespace Reinventx\Http;

use Reinventx\Forms\Form;
use Reinventx\Forms\FormRepository;
use Reinventx\Leads\Lead;
use Reinventx\Leads\LeadNote;
use Reinventx\Leads\LeadNoteRepository;
use Reinventx\Leads\LeadRepository;
use Reinventx\Leads\LeadStatuses;
use Reinventx\Leads\LeadStatusService;
use Reinventx\Setup\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST endpoints for the inbox: reinventx-forms/v1/leads.
 *
 * Lead data is raw visitor input; these responses return it verbatim as
 * JSON (inert by definition) and the admin SPA renders it as text only.
 */
final class LeadsController {

	public const REST_NAMESPACE = 'reinventx-forms/v1';

	private const MAX_PER_PAGE = 100;
	private const MAX_NOTE     = 5000;

	public function __construct(
		private readonly LeadRepository $leads,
		private readonly LeadNoteRepository $notes,
		private readonly FormRepository $forms,
		private readonly LeadStatusService $status_service,
	) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/leads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'canManageLeads' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/leads/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'canManageLeads' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'updateStatus' ),
					'permission_callback' => array( $this, 'canManageLeads' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/leads/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createNote' ),
					'permission_callback' => array( $this, 'canManageLeads' ),
				),
			)
		);
	}

	public function canManageLeads(): bool|WP_Error {
		if ( current_user_can( Capabilities::MANAGE_LEADS ) ) {
			return true;
		}

		return new WP_Error(
			'rvtx_forbidden',
			__( 'You are not allowed to manage Reinventx Forms leads.', 'reinventx-forms' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? min( $per_page, self::MAX_PER_PAGE ) : 20;

		$form_id = (int) $request->get_param( 'form_id' );
		$status  = (string) $request->get_param( 'status' );

		if ( '' !== $status && ! LeadStatuses::isValid( $status ) ) {
			return new WP_Error(
				'rvtx_invalid_status',
				__( 'Unknown lead status.', 'reinventx-forms' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->leads->paginate(
			$page,
			$per_page,
			$form_id > 0 ? $form_id : null,
			'' !== $status ? $status : null
		);

		$forms = $this->formsById();

		return new WP_REST_Response(
			array(
				'items'       => array_map(
					fn ( Lead $lead ): array => $this->summary( $lead, $forms[ $lead->formId ] ?? null ),
					$result->items
				),
				'total'       => $result->total,
				'page'        => $result->page,
				'per_page'    => $result->perPage,
				'total_pages' => $result->totalPages(),
				'statuses'    => LeadStatuses::all(),
			)
		);
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$lead = $this->leads->find( (int) $request->get_param( 'id' ) );

		if ( null === $lead ) {
			return $this->notFound();
		}

		return new WP_REST_Response( $this->detail( $lead ) );
	}

	public function updateStatus( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$status = $request->get_param( 'status' );

		if ( ! is_string( $status ) || ! LeadStatuses::isValid( $status ) ) {
			return new WP_Error(
				'rvtx_invalid_status',
				__( 'Unknown lead status.', 'reinventx-forms' ),
				array( 'status' => 400 )
			);
		}

		$lead = $this->status_service->change( (int) $request->get_param( 'id' ), $status );

		if ( null === $lead ) {
			return $this->notFound();
		}

		return new WP_REST_Response( $this->detail( $lead ) );
	}

	public function createNote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$lead = $this->leads->find( (int) $request->get_param( 'id' ) );

		if ( null === $lead ) {
			return $this->notFound();
		}

		$note = $request->get_param( 'note' );
		$note = is_string( $note ) ? trim( sanitize_textarea_field( $note ) ) : '';

		if ( '' === $note || mb_strlen( $note ) > self::MAX_NOTE ) {
			return new WP_Error(
				'rvtx_invalid_note',
				__( 'Notes must be between 1 and 5000 characters.', 'reinventx-forms' ),
				array( 'status' => 400 )
			);
		}

		$created = $this->notes->insert( $lead->id, get_current_user_id(), $note );

		return new WP_REST_Response( $this->noteToArray( $created ), 201 );
	}

	private function notFound(): WP_Error {
		return new WP_Error(
			'rvtx_not_found',
			__( 'Lead not found.', 'reinventx-forms' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * @return array<int, Form>
	 */
	private function formsById(): array {
		$map = array();

		foreach ( $this->forms->all() as $form ) {
			$map[ $form->id ] = $form;
		}

		return $map;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function summary( Lead $lead, ?Form $form ): array {
		return array(
			'id'           => $lead->id,
			'form_id'      => $lead->formId,
			'form_name'    => null !== $form ? $form->name : '',
			'status'       => $lead->status,
			'primary'      => $this->primaryValue( $lead, $form ),
			'submitted_at' => $lead->submittedAt,
		);
	}

	/**
	 * The value shown as the lead's "name" in the inbox: the first field
	 * (in form order) the visitor actually filled in.
	 */
	private function primaryValue( Lead $lead, ?Form $form ): string {
		if ( null !== $form ) {
			foreach ( $form->config->fields as $field ) {
				$value = $lead->data[ $field->id ] ?? '';

				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		foreach ( $lead->data as $value ) {
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function detail( Lead $lead ): array {
		$form   = $this->forms->find( $lead->formId );
		$fields = array();
		$seen   = array();

		if ( null !== $form ) {
			foreach ( $form->config->fields as $field ) {
				$fields[]           = array(
					'id'    => $field->id,
					'label' => $field->label,
					'value' => $lead->data[ $field->id ] ?? '',
				);
				$seen[ $field->id ] = true;
			}
		}

		// Values whose field was later removed from the form still belong
		// to the lead; show them under their raw id.
		foreach ( $lead->data as $id => $value ) {
			if ( ! isset( $seen[ $id ] ) ) {
				$fields[] = array(
					'id'    => $id,
					'label' => $id,
					'value' => $value,
				);
			}
		}

		return array(
			'id'           => $lead->id,
			'form_id'      => $lead->formId,
			'form_name'    => null !== $form ? $form->name : '',
			'status'       => $lead->status,
			'statuses'     => LeadStatuses::all(),
			'fields'       => $fields,
			'context'      => array(
				'source_url'   => $lead->sourceUrl,
				'source_title' => $lead->sourceTitle,
				'referrer_url' => $lead->referrerUrl,
				'user_agent'   => $lead->userAgent,
			),
			'submitted_at' => $lead->submittedAt,
			'notes'        => array_map(
				fn ( LeadNote $note ): array => $this->noteToArray( $note ),
				$this->notes->forLead( $lead->id )
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function noteToArray( LeadNote $note ): array {
		$user = get_userdata( $note->userId );

		return array(
			'id'         => $note->id,
			'note'       => $note->note,
			'author'     => false !== $user ? $user->display_name : __( 'Unknown user', 'reinventx-forms' ),
			'created_at' => $note->createdAt,
		);
	}
}
