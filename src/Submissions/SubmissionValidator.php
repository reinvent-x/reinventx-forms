<?php
declare(strict_types=1);

namespace Reinventx\Submissions;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;

/**
 * Runs raw visitor input through each field's sanitize → validate pipeline.
 *
 * Pure PHP (no WordPress functions): the security-critical path of the
 * whole product must be trivially unit-testable.
 */
final class SubmissionValidator {

	public const ERROR_REQUIRED = 'required';

	public function __construct( private readonly FieldTypeRegistry $types ) {
	}

	/**
	 * Only ids present in the form config are read — anything else in the
	 * input is ignored, so unknown keys can never reach storage.
	 *
	 * @param array<string, mixed> $input Raw field id → value map.
	 */
	public function validate( FormConfig $config, array $input ): ValidatedSubmission {
		$data   = array();
		$errors = array();

		foreach ( $config->fields as $field ) {
			$type  = $this->types->get( $field->type );
			$value = $type->sanitize( $input[ $field->id ] ?? '' );

			if ( $field->required && '' === $value ) {
				$errors[ $field->id ] = self::ERROR_REQUIRED;
				continue;
			}

			$code = $type->validate( $value );

			if ( null !== $code ) {
				$errors[ $field->id ] = $code;
				continue;
			}

			$data[ $field->id ] = $value;
		}

		return array() === $errors
			? ValidatedSubmission::valid( $data )
			: ValidatedSubmission::invalid( $errors );
	}
}
