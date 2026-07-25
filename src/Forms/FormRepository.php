<?php
declare(strict_types=1);

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This repository is the
// one sanctioned home for direct queries against the custom forms table.

namespace Reinventx\Forms;

use Reinventx\Database\Tables;
use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use wpdb;

/**
 * Persistence for forms. All SQL touching the forms table lives here.
 */
final class FormRepository {

	public function __construct(
		private readonly wpdb $db,
		private readonly Tables $tables,
		private readonly FieldTypeRegistry $types,
	) {
	}

	public function insert( string $name, FormConfig $config ): Form {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->tables->forms(),
			array(
				'name'       => $name,
				'status'     => FormStatus::Active->value,
				'config'     => $config->toJson(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return new Form( (int) $this->db->insert_id, $name, FormStatus::Active, $config, $now, $now );
	}

	public function find( int $id ): ?Form {
		$table = $this->tables->forms();

		$row = $this->db->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * All forms, newest activity first, optionally filtered by status.
	 *
	 * @return Form[]
	 */
	public function all( ?FormStatus $status = null ): array {
		$table = $this->tables->forms();

		if ( null === $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $this->db->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC", ARRAY_A );
		} else {
			$rows = $this->db->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->db->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC, id DESC", $status->value ),
				ARRAY_A
			);
		}

		return array_map( array( $this, 'hydrate' ), $rows ?: array() );
	}

	public function update( int $id, string $name, FormConfig $config ): ?Form {
		$existing = $this->find( $id );

		if ( null === $existing ) {
			return null;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->update(
			$this->tables->forms(),
			array(
				'name'       => $name,
				'config'     => $config->toJson(),
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return new Form( $id, $name, $existing->status, $config, $existing->createdAt, $now );
	}

	/**
	 * Archive a form (forms are never hard-deleted; leads reference them).
	 */
	public function archive( int $id ): bool {
		$updated = $this->db->update(
			$this->tables->forms(),
			array(
				'status'     => FormStatus::Archived->value,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return (bool) $updated;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrate( array $row ): Form {
		return new Form(
			(int) $row['id'],
			(string) $row['name'],
			FormStatus::from( (string) $row['status'] ),
			FormConfig::fromJson( (string) $row['config'], $this->types ),
			(string) $row['created_at'],
			(string) $row['updated_at']
		);
	}
}
