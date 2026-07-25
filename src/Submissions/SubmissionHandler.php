<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

use Reinventx\Forms\FormRepository;
use Reinventx\Forms\FormStatus;
use Reinventx\Leads\LeadRepository;

/**
 * The one path a submission takes to storage, whatever transport carried
 * it (REST from the enhancement script, or the no-JS page POST).
 *
 * Order matters: cheap anonymous rejections (missing form, honeypot,
 * token) run before the rate limiter records an attempt, and field
 * validation runs last.
 */
final class SubmissionHandler {

	public const HONEYPOT_FIELD = 'rvtx_website';

	public function __construct(
		private readonly FormRepository $forms,
		private readonly SubmissionValidator $validator,
		private readonly SubmissionToken $token,
		private readonly RateLimiter $rate_limiter,
		private readonly LeadRepository $leads,
	) {
	}

	/**
	 * @param array{token?: string, issued_at?: int, website?: string, fields?: array<string, mixed>} $submission
	 */
	public function handle( int $form_id, array $submission, SubmissionContext $context ): SubmissionOutcome {
		$form = $this->forms->find( $form_id );

		if ( null === $form || FormStatus::Active !== $form->status ) {
			return SubmissionOutcome::notFound();
		}

		if ( '' !== ( $submission['website'] ?? '' ) ) {
			return SubmissionOutcome::rejected();
		}

		$issued_at = (int) ( $submission['issued_at'] ?? 0 );
		$token     = (string) ( $submission['token'] ?? '' );

		if ( ! $this->token->verify( $form_id, $issued_at, $token, time() ) ) {
			return SubmissionOutcome::rejected();
		}

		// Keyed on rateLimitKey, not ipHash: the throttle must keep working
		// on sites that opt out of storing the IP hash.
		if ( null !== $context->rateLimitKey && ! $this->rate_limiter->allow( $context->rateLimitKey ) ) {
			return SubmissionOutcome::rateLimited();
		}

		$fields = $submission['fields'] ?? array();
		$result = $this->validator->validate( $form->config, is_array( $fields ) ? $fields : array() );

		if ( ! $result->ok() ) {
			return SubmissionOutcome::invalid( $result->errors );
		}

		$lead = $this->leads->insert( $form_id, $result->data, $context );

		/**
		 * Fires after a lead has been stored.
		 *
		 * @param \Reinventx\Leads\Lead $lead The stored lead.
		 */
		do_action( 'rvtx_lead_created', $lead );

		return SubmissionOutcome::created( $lead );
	}
}
