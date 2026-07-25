<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Forms;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\InvalidFormConfig;
use PHPUnit\Framework\TestCase;

final class FormConfigTest extends TestCase {

	private FieldTypeRegistry $types;

	protected function setUp(): void {
		$this->types = FieldTypeRegistry::withDefaults();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function validConfig(): array {
		return array(
			'version' => 1,
			'fields'  => array(
				array(
					'id'       => 'full_name',
					'type'     => 'text',
					'label'    => 'Full name',
					'required' => true,
				),
				array(
					'id'       => 'email',
					'type'     => 'email',
					'label'    => 'Email address',
					'required' => false,
				),
			),
		);
	}

	public function testValidConfigRoundTripsThroughArrayAndJson(): void {
		$config = FormConfig::fromArray( $this->validConfig(), $this->types );

		$this->assertCount( 2, $config->fields );
		$this->assertSame( 'full_name', $config->fields[0]->id );
		$this->assertTrue( $config->fields[0]->required );

		$rebuilt = FormConfig::fromJson( $config->toJson(), $this->types );

		$this->assertSame( $config->toArray(), $rebuilt->toArray() );
		$this->assertSame( FormConfig::VERSION, $config->toArray()['version'] );
	}

	public function testEmptyFieldsListIsAllowed(): void {
		$config = FormConfig::fromArray( array( 'fields' => array() ), $this->types );

		$this->assertSame( array(), $config->fields );
	}

	public function testMissingVersionDefaultsToCurrent(): void {
		$config = FormConfig::fromArray( array( 'fields' => array() ), $this->types );

		$this->assertSame( FormConfig::VERSION, $config->toArray()['version'] );
	}

	public function testUnsupportedVersionIsRejected(): void {
		try {
			FormConfig::fromArray( array( 'version' => 99 ), $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'config.version_unsupported' ), $e->errors );
		}
	}

	public function testUnknownFieldTypeIsRejected(): void {
		$config                      = $this->validConfig();
		$config['fields'][0]['type'] = 'checkbox';

		try {
			FormConfig::fromArray( $config, $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'fields.0.type_unknown' ), $e->errors );
		}
	}

	public function testInvalidFieldIdIsRejected(): void {
		$config                    = $this->validConfig();
		$config['fields'][0]['id'] = 'has spaces!';

		try {
			FormConfig::fromArray( $config, $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'fields.0.id_invalid' ), $e->errors );
		}
	}

	public function testDuplicateFieldIdsAreRejected(): void {
		$config                    = $this->validConfig();
		$config['fields'][1]['id'] = 'full_name';

		try {
			FormConfig::fromArray( $config, $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'fields.1.id_duplicate' ), $e->errors );
		}
	}

	public function testAllErrorsAreCollectedAcrossFields(): void {
		$config = array(
			'fields' => array(
				array(
					'id'    => 'bad id',
					'type'  => 'checkbox',
					'label' => '',
				),
				'not-an-object',
			),
		);

		try {
			FormConfig::fromArray( $config, $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame(
				array(
					'fields.0.id_invalid',
					'fields.0.type_unknown',
					'fields.0.label_invalid',
					'fields.1.not_an_object',
				),
				$e->errors
			);
		}
	}

	public function testNonListFieldsAreRejected(): void {
		try {
			FormConfig::fromArray( array( 'fields' => array( 'a' => array() ) ), $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'config.fields_not_a_list' ), $e->errors );
		}
	}

	public function testMalformedJsonIsRejected(): void {
		try {
			FormConfig::fromJson( '{not json', $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'config.invalid_json' ), $e->errors );
		}
	}

	public function testRequiredMustBeBoolean(): void {
		$config                          = $this->validConfig();
		$config['fields'][0]['required'] = 'yes';

		try {
			FormConfig::fromArray( $config, $this->types );
			$this->fail( 'Expected InvalidFormConfig' );
		} catch ( InvalidFormConfig $e ) {
			$this->assertSame( array( 'fields.0.required_not_boolean' ), $e->errors );
		}
	}
}
