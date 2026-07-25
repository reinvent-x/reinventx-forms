<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

use Reinventx\Leads\Lead;

/**
 * What happened to a submission attempt. Shared by the REST endpoint and
 * the no-JS fallback so both paths behave identically.
 */
final class SubmissionOutcome {

	public const CREATED      = 'created';
	public const NOT_FOUND    = 'not_found';
	public const REJECTED     = 'rejected';
	public const RATE_LIMITED = 'rate_limited';
	public const INVALID      = 'invalid';

	/**
	 * @param array<string, string> $fieldErrors Field id → error code.
	 */
	private function __construct(
		public readonly string $status,
		public readonly ?Lead $lead,
		public readonly array $fieldErrors,
	) {
	}

	public static function created( Lead $lead ): self {
		return new self( self::CREATED, $lead, array() );
	}

	public static function notFound(): self {
		return new self( self::NOT_FOUND, null, array() );
	}

	public static function rejected(): self {
		return new self( self::REJECTED, null, array() );
	}

	public static function rateLimited(): self {
		return new self( self::RATE_LIMITED, null, array() );
	}

	/**
	 * @param array<string, string> $field_errors
	 */
	public static function invalid( array $field_errors ): self {
		return new self( self::INVALID, null, $field_errors );
	}
}
