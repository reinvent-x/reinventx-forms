<?php
declare(strict_types=1);

namespace Reinventx\Forms;

/**
 * A stored form. Immutable snapshot of one row in the forms table.
 *
 * Timestamps are UTC "Y-m-d H:i:s" strings, matching what the repository
 * writes to the datetime columns.
 */
final class Form {

	public const MAX_NAME = 190;

	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly FormStatus $status,
		public readonly FormConfig $config,
		public readonly string $createdAt,
		public readonly string $updatedAt,
	) {
	}

	/**
	 * Shape returned by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'         => $this->id,
			'name'       => $this->name,
			'status'     => $this->status->value,
			'config'     => $this->config->toArray(),
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		);
	}
}
