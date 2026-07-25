<?php
declare(strict_types=1);

namespace Reinventx\Rendering;

/**
 * What the renderer should show besides the blank form: a success message
 * after a no-JS submission, or field errors with the visitor's values
 * preserved so nothing they typed is lost.
 */
final class RenderState {

	/**
	 * @param array<string, string> $fieldErrors Field id → error code.
	 * @param array<string, string> $values      Field id → value to re-display.
	 */
	private function __construct(
		public readonly bool $success,
		public readonly bool $rejected,
		public readonly array $fieldErrors,
		public readonly array $values,
	) {
	}

	public static function blank(): self {
		return new self( false, false, array(), array() );
	}

	public static function succeeded(): self {
		return new self( true, false, array(), array() );
	}

	public static function rejectedSubmission(): self {
		return new self( false, true, array(), array() );
	}

	/**
	 * @param array<string, string> $field_errors
	 * @param array<string, string> $values
	 */
	public static function failed( array $field_errors, array $values ): self {
		return new self( false, false, $field_errors, $values );
	}
}
