<?php
declare(strict_types=1);

namespace Reinventx\Leads;

/**
 * An internal follow-up note on a lead. Author attribution is a user id;
 * resolving it to a display name is the HTTP layer's concern.
 */
final class LeadNote {

	public function __construct(
		public readonly int $id,
		public readonly int $leadId,
		public readonly int $userId,
		public readonly string $note,
		public readonly string $createdAt,
	) {
	}
}
