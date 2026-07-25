<?php
declare(strict_types=1);

namespace Reinventx\Forms\FieldTypes;

/**
 * Multi-line text. Newlines and tabs survive; other control characters do not.
 */
final class TextareaType implements FieldType {

	public const MAX_LENGTH = 20000;

	public function slug(): string {
		return 'textarea';
	}

	public function sanitize( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = str_replace( "\r\n", "\n", (string) $value );
		$value = (string) preg_replace( '/[^\P{C}\n\t]/u', '', $value );

		return trim( $value );
	}

	public function validate( string $value ): ?string {
		if ( mb_strlen( $value ) > self::MAX_LENGTH ) {
			return 'too_long';
		}

		return null;
	}
}
