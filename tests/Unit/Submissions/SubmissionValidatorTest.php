<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Submissions;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;
use Reinventx\Submissions\SubmissionValidator;
use PHPUnit\Framework\TestCase;

final class SubmissionValidatorTest extends TestCase {

	private FieldTypeRegistry $types;

	private SubmissionValidator $validator;

	protected function setUp(): void {
		$this->types     = FieldTypeRegistry::withDefaults();
		$this->validator = new SubmissionValidator( $this->types );
	}

	private function config(): FormConfig {
		return FormConfig::fromArray(
			array(
				'fields' => array(
					array(
						'id'       => 'name',
						'type'     => 'text',
						'label'    => 'Name',
						'required' => true,
					),
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
					array(
						'id'       => 'message',
						'type'     => 'textarea',
						'label'    => 'Message',
						'required' => false,
					),
				),
			),
			$this->types
		);
	}

	public function testValidInputIsSanitizedAndAccepted(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'    => "  Jane\x00 Doe ",
				'email'   => ' jane@example.com ',
				'message' => "Hello\r\nWorld",
			)
		);

		$this->assertTrue( $result->ok() );
		$this->assertSame(
			array(
				'name'    => 'Jane Doe',
				'email'   => 'jane@example.com',
				'message' => "Hello\nWorld",
			),
			$result->data
		);
	}

	public function testMissingRequiredFieldFails(): void {
		$result = $this->validator->validate(
			$this->config(),
			array( 'email' => 'jane@example.com' )
		);

		$this->assertFalse( $result->ok() );
		$this->assertSame( array( 'name' => 'required' ), $result->errors );
	}

	public function testWhitespaceOnlyRequiredFieldFails(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'  => "   \n ",
				'email' => 'jane@example.com',
			)
		);

		$this->assertFalse( $result->ok() );
		$this->assertSame( array( 'name' => 'required' ), $result->errors );
	}

	public function testInvalidEmailFails(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'  => 'Jane',
				'email' => 'not-an-email',
			)
		);

		$this->assertFalse( $result->ok() );
		$this->assertSame( array( 'email' => 'invalid_email' ), $result->errors );
	}

	public function testMultipleErrorsAreCollected(): void {
		$result = $this->validator->validate(
			$this->config(),
			array( 'email' => 'nope' )
		);

		$this->assertSame(
			array(
				'name'  => 'required',
				'email' => 'invalid_email',
			),
			$result->errors
		);
	}

	public function testUnknownInputKeysAreIgnored(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'     => 'Jane',
				'email'    => 'jane@example.com',
				'is_admin' => '1',
				'evil'     => '<script>alert(1)</script>',
			)
		);

		$this->assertTrue( $result->ok() );
		$this->assertSame( array( 'name', 'email', 'message' ), array_keys( $result->data ) );
	}

	public function testOptionalEmptyFieldIsStoredEmpty(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'  => 'Jane',
				'email' => 'jane@example.com',
			)
		);

		$this->assertTrue( $result->ok() );
		$this->assertSame( '', $result->data['message'] );
	}

	public function testNonScalarInputBecomesEmptyString(): void {
		$result = $this->validator->validate(
			$this->config(),
			array(
				'name'  => array( 'nested' => 'array' ),
				'email' => 'jane@example.com',
			)
		);

		$this->assertFalse( $result->ok() );
		$this->assertSame( array( 'name' => 'required' ), $result->errors );
	}
}
