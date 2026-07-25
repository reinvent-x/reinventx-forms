<?php
declare(strict_types=1);

namespace Reinventx\Forms\FieldTypes;

/**
 * The set of field types a form config may use.
 *
 * v0.1 ships a fixed set; a public filter hook for third-party types is a
 * deliberate later addition, so the extension seam is this class, not a hook.
 */
final class FieldTypeRegistry {

	/** @var array<string, FieldType> */
	private array $types = array();

	public static function withDefaults(): self {
		$registry = new self();
		$registry->register( new TextType() );
		$registry->register( new EmailType() );
		$registry->register( new TextareaType() );

		return $registry;
	}

	public function register( FieldType $type ): void {
		$this->types[ $type->slug() ] = $type;
	}

	public function has( string $slug ): bool {
		return isset( $this->types[ $slug ] );
	}

	public function get( string $slug ): FieldType {
		if ( ! $this->has( $slug ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer error surfaced in logs, never rendered to a browser.
			throw new \InvalidArgumentException( "Unknown field type: {$slug}" );
		}

		return $this->types[ $slug ];
	}

	/**
	 * @return string[]
	 */
	public function slugs(): array {
		return array_keys( $this->types );
	}
}
