<?php
declare(strict_types=1);

namespace Reinventx\Rendering;

use Reinventx\Forms\FormRepository;
use Reinventx\Forms\FormStatus;
use Reinventx\Http\SubmissionsController;
use Reinventx\Plugin;
use Reinventx\Submissions\SubmissionContext;
use Reinventx\Submissions\SubmissionHandler;
use Reinventx\Submissions\SubmissionOutcome;

/**
 * The one embedding path: the shortcode and the block both delegate here,
 * which is what guarantees they render identically (MILESTONES M4).
 *
 * Also owns the no-JS fallback: the rendered form POSTs back to the page,
 * and this embed processes that POST before re-rendering.
 */
final class FormEmbed {

	public function __construct(
		private readonly Plugin $plugin,
		private readonly FormRepository $forms,
		private readonly SubmissionHandler $handler,
		private readonly FormRenderer $renderer,
	) {
	}

	public function render( int $form_id ): string {
		$form = $form_id > 0 ? $this->forms->find( $form_id ) : null;

		if ( null === $form || FormStatus::Active !== $form->status ) {
			// Nothing for visitors; a visible placeholder for people who can
			// edit content (also what the block's editor preview shows).
			return current_user_can( 'edit_posts' )
				? sprintf(
					'<div class="rvtx-form rvtx-placeholder">%s</div>',
					esc_html__( 'This Reinventx form is unavailable — it may have been archived. Only editors see this notice.', 'reinventx-forms' )
				)
				: '';
		}

		$state = $this->maybeHandlePost( $form_id );

		$this->enqueueScript();

		return $this->renderer->render(
			$form,
			$state,
			$this->currentUrl(),
			rest_url( SubmissionsController::REST_NAMESPACE . '/submissions' )
		);
	}

	private function maybeHandlePost( int $form_id ): RenderState {
		// The no-JS path: the form POSTs back to the page it lives on. A
		// signed, form-bound timestamp token stands in for a nonce, which
		// page caching would break (verified in SubmissionHandler).
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( 'POST' !== $request_method ) {
			return RenderState::blank();
		}

		$posted_form_id = isset( $_POST['rvtx_form_id'] ) && is_scalar( $_POST['rvtx_form_id'] )
			? absint( wp_unslash( $_POST['rvtx_form_id'] ) )
			: 0;

		if ( $form_id !== $posted_form_id ) {
			return RenderState::blank();
		}

		$raw_fields = array();

		if ( isset( $_POST['rvtx_fields'] ) && is_array( $_POST['rvtx_fields'] ) ) {
			// Field values are intentionally not run through a generic WP
			// sanitizer here: SubmissionValidator sanitizes each one with
			// its field type's own rules before anything is stored.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( wp_unslash( $_POST['rvtx_fields'] ) as $key => $value ) {
				if ( is_string( $key ) && is_scalar( $value ) ) {
					$raw_fields[ $key ] = (string) $value;
				}
			}
		}

		$issued_at = isset( $_POST['rvtx_issued_at'] ) && is_scalar( $_POST['rvtx_issued_at'] )
			? absint( wp_unslash( $_POST['rvtx_issued_at'] ) )
			: 0;

		$submission = array(
			'token'     => $this->postString( 'rvtx_token' ),
			'issued_at' => $issued_at,
			'website'   => $this->postString( SubmissionHandler::HONEYPOT_FIELD ),
			'fields'    => $raw_fields,
		);

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : null;

		/**
		 * Filters whether a keyed hash of the visitor's IP is stored with the
		 * lead. Return false to persist nothing IP-derived on the lead row.
		 *
		 * Rate limiting is unaffected: it uses a separate hash, derived under
		 * a different context and held only in a short-lived transient, so
		 * opting out of IP storage does not weaken abuse protection.
		 *
		 * @param bool $store Default true.
		 */
		$store_ip_hash = (bool) apply_filters( 'rvtx_store_ip_hash', true );

		$context = SubmissionContext::fromRaw(
			esc_url_raw( $this->postString( 'rvtx_source_url' ) ),
			sanitize_text_field( $this->postString( 'rvtx_source_title' ) ),
			isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) ) : null,
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : null,
			$ip,
			wp_salt( 'auth' ),
			$store_ip_hash
		);
		// phpcs:enable

		$outcome = $this->handler->handle( $form_id, $submission, $context );

		return match ( $outcome->status ) {
			SubmissionOutcome::CREATED => RenderState::succeeded(),
			SubmissionOutcome::INVALID => RenderState::failed(
				$outcome->fieldErrors,
				array_map( 'sanitize_textarea_field', $raw_fields )
			),
			default => RenderState::rejectedSubmission(),
		};
	}

	private function postString( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Token-guarded no-JS path; see maybeHandlePost().
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
		// phpcs:enable
	}

	private function enqueueScript(): void {
		$asset_file = $this->plugin->dir() . 'build/public-form.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rvtx-public-form',
			$this->plugin->url() . 'build/public-form.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	private function currentUrl(): string {
		if ( is_singular() ) {
			return (string) get_permalink();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		return esc_url_raw( home_url( (string) $request_uri ) );
	}
}
