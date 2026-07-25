<?php
declare(strict_types=1);

namespace Reinventx\Forms\FieldTypes;

/**
 * Single-line text. Control characters are stripped, whitespace trimmed.
 */
final class TextType implements FieldType {

	public const MAX_LENGTH = 1000;

	public function slug(): string {
		return 'text';
	}

	public function sanitize( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value );

		return trim( $value );
	}

	public function validate( string $value ): ?string {
		if ( mb_strlen( $value ) > self::MAX_LENGTH ) {
			return 'too_long';
		}

		return null;
	}
}
