<?php
declare(strict_types=1);

namespace Reinventx\Leads;

/**
 * A stored lead. Immutable snapshot of one row in the leads table.
 *
 * Statuses are plain strings (not an enum) on purpose: they become
 * user-configurable later without a schema or type change (PROJECT_PLAN §5).
 */
final class Lead {

	public const STATUS_NEW = 'new';

	/**
	 * @param array<string, string> $data Field id → submitted value.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $formId,
		public readonly string $status,
		public readonly array $data,
		public readonly ?string $sourceUrl,
		public readonly ?string $sourceTitle,
		public readonly ?string $referrerUrl,
		public readonly ?string $userAgent,
		public readonly ?string $ipHash,
		public readonly string $submittedAt,
	) {
	}
}
