<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

/**
 * Signed timestamp token embedded in every rendered form.
 *
 * Replaces a nonce (which page caching would break) as the proof that the
 * submission came from a form we rendered: the token is form-bound, and its
 * timestamp powers the time-trap — humans do not submit within seconds of
 * the page being rendered, and tokens do not live forever.
 */
final class SubmissionToken {

	public const MIN_AGE_SECONDS = 3;
	public const MAX_AGE_SECONDS = 86400;

	public function __construct( private readonly string $secret ) {
	}

	public function issue( int $form_id, int $issued_at ): string {
		return hash_hmac( 'sha256', $form_id . '|' . $issued_at, $this->secret );
	}

	public function verify( int $form_id, int $issued_at, string $token, int $now ): bool {
		if ( ! hash_equals( $this->issue( $form_id, $issued_at ), $token ) ) {
			return false;
		}

		$age = $now - $issued_at;

		return $age >= self::MIN_AGE_SECONDS && $age <= self::MAX_AGE_SECONDS;
	}
}
