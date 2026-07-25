<?php
declare(strict_types=1);

namespace Reinventx\Leads;

/**
 * The follow-up pipeline. Statuses are strings end to end (column, REST,
 * UI) so a future "custom statuses" feature only replaces this list's
 * source, not any types or schema.
 */
final class LeadStatuses {

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( Lead::STATUS_NEW, 'contacted', 'qualified', 'won', 'lost', 'spam' );
	}

	public static function isValid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}
}
