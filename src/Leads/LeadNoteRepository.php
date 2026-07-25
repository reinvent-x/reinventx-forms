<?php
declare(strict_types=1);

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This repository is the
// one sanctioned home for direct queries against the lead notes table.

namespace Reinventx\Leads;

use Reinventx\Database\Tables;
use wpdb;

/**
 * Persistence for lead notes.
 */
final class LeadNoteRepository {

	public function __construct(
		private readonly wpdb $db,
		private readonly Tables $tables,
	) {
	}

	public function insert( int $lead_id, int $user_id, string $note ): LeadNote {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->tables->leadNotes(),
			array(
				'lead_id'    => $lead_id,
				'user_id'    => $user_id,
				'note'       => $note,
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return new LeadNote( (int) $this->db->insert_id, $lead_id, $user_id, $note, $now );
	}

	/**
	 * Notes for one lead, oldest first (a timeline reads downward).
	 *
	 * @return LeadNote[]
	 */
	public function forLead( int $lead_id ): array {
		$table = $this->tables->leadNotes();

		$rows = $this->db->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->db->prepare( "SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC, id ASC", $lead_id ),
			ARRAY_A
		);

		return array_map(
			static fn ( array $row ): LeadNote => new LeadNote(
				(int) $row['id'],
				(int) $row['lead_id'],
				(int) $row['user_id'],
				(string) $row['note'],
				(string) $row['created_at']
			),
			$rows ?: array()
		);
	}

	/**
	 * Drop every note attached to a lead. Used when the lead itself is
	 * erased, so notes about a person never outlive the person's record.
	 */
	public function deleteForLead( int $lead_id ): int {
		return (int) $this->db->delete( $this->tables->leadNotes(), array( 'lead_id' => $lead_id ), array( '%d' ) );
	}
}
