<?php
declare(strict_types=1);

namespace Reinventx\Http;

use Reinventx\Submissions\ErrorMessages;
use Reinventx\Submissions\SubmissionContext;
use Reinventx\Submissions\SubmissionHandler;
use Reinventx\Submissions\SubmissionOutcome;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public, unauthenticated endpoint the enhancement script POSTs to:
 * reinventx-forms/v1/submissions.
 *
 * Hardening lives in SubmissionHandler (honeypot, signed timestamp token,
 * rate limit, per-field validation); this class only speaks HTTP. It never
 * reveals which guard rejected a submission — bots get one generic error.
 */
final class SubmissionsController {

	public const REST_NAMESPACE = 'reinventx-forms/v1';

	public function __construct( private readonly SubmissionHandler $handler ) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/submissions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					// Public by design: visitors are anonymous. The guards
					// in SubmissionHandler are the actual gate.
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! str_contains( (string) $request->get_header( 'content-type' ), 'application/json' ) ) {
			return new WP_Error(
				'rvtx_invalid_content_type',
				__( 'Submissions must be sent as JSON.', 'reinventx-forms' ),
				array( 'status' => 415 )
			);
		}

		$fields = $request->get_param( 'fields' );

		$submission = array(
			'token'     => (string) $request->get_param( 'token' ),
			'issued_at' => (int) $request->get_param( 'issued_at' ),
			'website'   => (string) $request->get_param( 'website' ),
			'fields'    => is_array( $fields ) ? $fields : array(),
		);

		$source_url   = $request->get_param( 'source_url' );
		$source_title = $request->get_param( 'source_title' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Hashed immediately; the raw IP is never stored.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : null;

		/** This filter is documented in src/Rendering/FormEmbed.php. */
		$store_ip_hash = (bool) apply_filters( 'rvtx_store_ip_hash', true );

		$context = SubmissionContext::fromRaw(
			is_string( $source_url ) ? esc_url_raw( $source_url ) : null,
			is_string( $source_title ) ? sanitize_text_field( $source_title ) : null,
			$request->get_header( 'referer' ),
			$request->get_header( 'user-agent' ),
			$ip,
			wp_salt( 'auth' ),
			$store_ip_hash
		);

		$outcome = $this->handler->handle(
			(int) $request->get_param( 'form_id' ),
			$submission,
			$context
		);

		return match ( $outcome->status ) {
			SubmissionOutcome::CREATED => new WP_REST_Response(
				array(
					'status'  => 'created',
					'message' => __( 'Thanks! Your message has been received.', 'reinventx-forms' ),
				),
				201
			),
			SubmissionOutcome::INVALID => new WP_Error(
				'rvtx_invalid_fields',
				__( 'Please correct the highlighted fields.', 'reinventx-forms' ),
				array(
					'status'   => 400,
					'errors'   => $outcome->fieldErrors,
					'messages' => ErrorMessages::forErrors( $outcome->fieldErrors ),
				)
			),
			SubmissionOutcome::RATE_LIMITED => new WP_Error(
				'rvtx_rate_limited',
				__( 'Too many submissions. Please wait a minute and try again.', 'reinventx-forms' ),
				array( 'status' => 429 )
			),
			SubmissionOutcome::NOT_FOUND => new WP_Error(
				'rvtx_not_found',
				__( 'This form is no longer available.', 'reinventx-forms' ),
				array( 'status' => 404 )
			),
			default => new WP_Error(
				'rvtx_rejected',
				__( 'Your submission could not be processed. Please try again.', 'reinventx-forms' ),
				array( 'status' => 400 )
			),
		};
	}
}
