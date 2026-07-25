<?php
declare(strict_types=1);

namespace Reinventx\Forms;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;

/**
 * A form's validated field configuration. Immutable.
 *
 * This is the single gate between untrusted config (REST bodies, DB rows)
 * and the rest of the codebase: anything holding a FormConfig instance may
 * assume every field is well-formed and every type is known.
 */
final class FormConfig {

	/**
	 * Version of the config shape itself, stored inside the JSON. Bump when
	 * the structure changes; older shapes get upgraded in fromArray().
	 */
	public const VERSION = 1;

	/**
	 * @param Field[] $fields
	 */
	private function __construct( public readonly array $fields ) {
	}

	/**
	 * Build from an untrusted array, collecting every problem found.
	 *
	 * @param array<string, mixed> $data
	 * @throws InvalidFormConfig When the config is malformed.
	 */
	public static function fromArray( array $data, FieldTypeRegistry $types ): self {
		$errors = array();

		$version = $data['version'] ?? self::VERSION;

		if ( ! is_int( $version ) || $version < 1 || $version > self::VERSION ) {
			throw new InvalidFormConfig( array( 'config.version_unsupported' ) );
		}

		$raw_fields = $data['fields'] ?? array();

		if ( ! is_array( $raw_fields ) || ( array() !== $raw_fields && ! array_is_list( $raw_fields ) ) ) {
			throw new InvalidFormConfig( array( 'config.fields_not_a_list' ) );
		}

		$fields   = array();
		$seen_ids = array();

		foreach ( $raw_fields as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				$errors[] = "fields.{$index}.not_an_object";
				continue;
			}

			$field_errors = array();

			$id       = $raw['id'] ?? null;
			$type     = $raw['type'] ?? null;
			$label    = $raw['label'] ?? null;
			$required = $raw['required'] ?? false;

			if ( ! is_string( $id ) || 1 !== preg_match( Field::ID_PATTERN, $id ) ) {
				$field_errors[] = "fields.{$index}.id_invalid";
			} elseif ( isset( $seen_ids[ $id ] ) ) {
				$field_errors[] = "fields.{$index}.id_duplicate";
			} else {
				$seen_ids[ $id ] = true;
			}

			if ( ! is_string( $type ) || ! $types->has( $type ) ) {
				$field_errors[] = "fields.{$index}.type_unknown";
			}

			if ( ! is_string( $label ) || '' === trim( $label ) || mb_strlen( $label ) > Field::MAX_LABEL ) {
				$field_errors[] = "fields.{$index}.label_invalid";
			}

			if ( ! is_bool( $required ) ) {
				$field_errors[] = "fields.{$index}.required_not_boolean";
			}

			if ( array() === $field_errors ) {
				$fields[] = new Field( (string) $id, (string) $type, trim( (string) $label ), (bool) $required );
			} else {
				$errors = array_merge( $errors, $field_errors );
			}
		}

		if ( array() !== $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error codes are internal constants, never user input, and are returned as JSON, not printed.
			throw new InvalidFormConfig( $errors );
		}

		return new self( $fields );
	}

	/**
	 * @throws InvalidFormConfig When the JSON is malformed or fails validation.
	 */
	public static function fromJson( string $json, FieldTypeRegistry $types ): self {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			throw new InvalidFormConfig( array( 'config.invalid_json' ) );
		}

		return self::fromArray( $data, $types );
	}

	/**
	 * @return array{version: int, fields: array<int, array{id: string, type: string, label: string, required: bool}>}
	 */
	public function toArray(): array {
		return array(
			'version' => self::VERSION,
			'fields'  => array_map(
				static fn ( Field $field ): array => $field->toArray(),
				$this->fields
			),
		);
	}

	public function toJson(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This class stays WordPress-free on purpose; toArray() only yields UTF-8-safe scalars.
		return (string) json_encode( $this->toArray() );
	}
}
