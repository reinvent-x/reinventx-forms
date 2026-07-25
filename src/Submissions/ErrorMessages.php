<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

/**
 * Maps machine error codes to visitor-facing sentences.
 *
 * Translation happens server-side so the public script stays tiny and
 * never needs an i18n runtime.
 */
final class ErrorMessages {

	public static function forCode( string $code ): string {
		return match ( $code ) {
			SubmissionValidator::ERROR_REQUIRED => __( 'This field is required.', 'reinventx-forms' ),
			'invalid_email' => __( 'Enter a valid email address.', 'reinventx-forms' ),
			'too_long' => __( 'This value is too long.', 'reinventx-forms' ),
			default => __( 'This value is not valid.', 'reinventx-forms' ),
		};
	}

	/**
	 * @param array<string, string> $errors Field id → error code.
	 * @return array<string, string> Field id → translated message.
	 */
	public static function forErrors( array $errors ): array {
		return array_map( array( self::class, 'forCode' ), $errors );
	}
}
