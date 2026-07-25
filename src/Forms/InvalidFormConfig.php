<?php
declare(strict_types=1);

namespace Reinventx\Forms;

/**
 * Thrown when untrusted form config fails validation.
 *
 * Carries machine-readable error codes (e.g. "fields.0.type_unknown") so
 * the REST layer can return them verbatim and the admin UI can map them
 * to translated messages.
 */
final class InvalidFormConfig extends \InvalidArgumentException {

	/**
	 * @param string[] $errors Error codes describing every failure found.
	 */
	public function __construct( public readonly array $errors ) {
		parent::__construct( 'Invalid form config: ' . implode( ', ', $errors ) );
	}
}
