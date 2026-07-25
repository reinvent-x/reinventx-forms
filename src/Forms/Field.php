<?php
declare(strict_types=1);

namespace Reinventx\Forms;

/**
 * A single field within a form's config. Immutable value object.
 *
 * Construction does not validate — FormConfig::fromArray() is the only
 * gate through which untrusted field data enters.
 */
final class Field {

	public const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';
	public const MAX_LABEL  = 200;

	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $label,
		public readonly bool $required,
	) {
	}

	/**
	 * @return array{id: string, type: string, label: string, required: bool}
	 */
	public function toArray(): array {
		return array(
			'id'       => $this->id,
			'type'     => $this->type,
			'label'    => $this->label,
			'required' => $this->required,
		);
	}
}
