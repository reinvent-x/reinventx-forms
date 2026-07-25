<?php
declare(strict_types=1);

namespace Reinventx\Forms\FieldTypes;

/**
 * Email address. Empty is allowed (optional fields); anything non-empty
 * must satisfy PHP's email filter.
 */
final class EmailType implements FieldType {

	public const MAX_LENGTH = 254;

	public function slug(): string {
		return 'email';
	}

	public function sanitize( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) preg_replace( '/[\x00-\x1F\x7F\s]/u', '', (string) $value );

		return $value;
	}

	public function validate( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}

		if ( mb_strlen( $value ) > self::MAX_LENGTH ) {
			return 'too_long';
		}

		if ( false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return 'invalid_email';
		}

		return null;
	}
}
