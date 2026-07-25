<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

/**
 * Result of running raw input through SubmissionValidator.
 */
final class ValidatedSubmission {

	/**
	 * @param array<string, string> $data   Field id → sanitized value (valid submissions).
	 * @param array<string, string> $errors Field id → error code (invalid submissions).
	 */
	private function __construct(
		public readonly array $data,
		public readonly array $errors,
	) {
	}

	/**
	 * @param array<string, string> $data
	 */
	public static function valid( array $data ): self {
		return new self( $data, array() );
	}

	/**
	 * @param array<string, string> $errors
	 */
	public static function invalid( array $errors ): self {
		return new self( array(), $errors );
	}

	public function ok(): bool {
		return array() === $this->errors;
	}
}
