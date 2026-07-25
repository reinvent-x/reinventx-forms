<?php
declare(strict_types=1);

namespace Reinventx\Forms\FieldTypes;

/**
 * Behaviour of one field type: how to clean a raw submitted value and how
 * to judge the cleaned value.
 *
 * Implementations must be pure PHP (no WordPress functions) so they stay
 * unit-testable and reusable in any context.
 */
interface FieldType {

	public function slug(): string;

	/**
	 * Coerce an untrusted raw value into a safe string.
	 */
	public function sanitize( mixed $value ): string;

	/**
	 * Validate an already-sanitized value.
	 *
	 * The required-check is the caller's job — an empty value is always
	 * acceptable here so optional fields can be left blank.
	 *
	 * @return string|null Error code, or null when the value is valid.
	 */
	public function validate( string $value ): ?string;
}
