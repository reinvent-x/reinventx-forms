<?php
declare(strict_types=1);

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This repository is the
// one sanctioned home for direct queries against the custom leads table.

namespace Reinventx\Leads;

use Reinventx\Database\Tables;
use Reinventx\Submissions\SubmissionContext;
use wpdb;

/**
 * Persistence for leads. All SQL touching the leads table lives here.
 * M2 ships insert/find; pagination and filtering arrive with the inbox (M3).
 */
final class LeadRepository {

	public function __construct(
		private readonly wpdb $db,
		private readonly Tables $tables,
	) {
	}

	/**
	 * @param array<string, string> $data Validated field id → value map.
	 */
	public function insert( int $form_id, array $data, SubmissionContext $context ): Lead {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->tables->leads(),
			array(
				'form_id'      => $form_id,
				'status'       => Lead::STATUS_NEW,
				'data'         => (string) wp_json_encode( $data ),
				'source_url'   => $context->sourceUrl,
				'source_title' => $context->sourceTitle,
				'referrer_url' => $context->referrerUrl,
				'user_agent'   => $context->userAgent,
				'ip_hash'      => $context->ipHash,
				'submitted_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new Lead(
			(int) $this->db->insert_id,
			$form_id,
			Lead::STATUS_NEW,
			$data,
			$context->sourceUrl,
			$context->sourceTitle,
			$context->referrerUrl,
			$context->userAgent,
			$context->ipHash,
			$now
		);
	}

	/**
	 * One page of leads, newest first, optionally filtered.
	 */
	public function paginate( int $page, int $per_page, ?int $form_id = null, ?string $status = null ): LeadPage {
		$table = $this->tables->leads();
		$where = array();
		$args  = array();

		if ( null !== $form_id ) {
			$where[] = 'form_id = %d';
			$args[]  = $form_id;
		}

		if ( null !== $status ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}

		$where_sql = array() === $where ? '' : ' WHERE ' . implode( ' AND ', $where );

		if ( array() === $args ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" );
		} else {
			$total = (int) $this->db->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$this->db->prepare( "SELECT COUNT(*) FROM {$table}{$where_sql}", $args )
			);
		}

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table}{$where_sql} ORDER BY submitted_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return new LeadPage(
			array_map( array( $this, 'hydrate' ), $rows ?: array() ),
			$total,
			$page,
			$per_page
		);
	}

	/**
	 * Leads holding $email in any submitted value, oldest first.
	 *
	 * The LIKE narrows candidates in SQL; the exact, case-insensitive match
	 * happens in PHP against decoded values, so a lead is only returned when
	 * a field actually equals the address rather than merely containing it.
	 * Ordered by id so paging stays stable while an eraser deletes rows.
	 *
	 * @return array<int, Lead>
	 */
	public function findByEmail( string $email, int $limit, int $offset ): array {
		$table = $this->tables->leads();

		// Normalise the requested address the same way stored values are
		// compared, so a request arriving with stray whitespace still matches.
		$email = trim( $email );

		if ( '' === $email ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE data LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
				'%' . $this->db->esc_like( $email ) . '%',
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);

		$leads = array();

		foreach ( $rows ?: array() as $row ) {
			$lead = $this->hydrate( $row );

			foreach ( $lead->data as $value ) {
				if ( 0 === strcasecmp( trim( $value ), $email ) ) {
					$leads[] = $lead;
					break;
				}
			}
		}

		return $leads;
	}

	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->tables->leads(), array( 'id' => $id ), array( '%d' ) );
	}

	public function updateStatus( int $id, string $status ): bool {
		$updated = $this->db->update(
			$this->tables->leads(),
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return (bool) $updated;
	}

	public function find( int $id ): ?Lead {
		$table = $this->tables->leads();

		$row = $this->db->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrate( array $row ): Lead {
		$data = json_decode( (string) $row['data'], true );

		return new Lead(
			(int) $row['id'],
			(int) $row['form_id'],
			(string) $row['status'],
			is_array( $data ) ? $data : array(),
			null === $row['source_url'] ? null : (string) $row['source_url'],
			null === $row['source_title'] ? null : (string) $row['source_title'],
			null === $row['referrer_url'] ? null : (string) $row['referrer_url'],
			null === $row['user_agent'] ? null : (string) $row['user_agent'],
			null === $row['ip_hash'] ? null : (string) $row['ip_hash'],
			(string) $row['submitted_at']
		);
	}
}
