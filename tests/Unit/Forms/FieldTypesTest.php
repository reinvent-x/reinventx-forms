<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Forms;

use Reinventx\Forms\FieldTypes\EmailType;
use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FieldTypes\TextareaType;
use Reinventx\Forms\FieldTypes\TextType;
use PHPUnit\Framework\TestCase;

final class FieldTypesTest extends TestCase {

	public function testRegistryDefaultsContainTextEmailTextarea(): void {
		$registry = FieldTypeRegistry::withDefaults();

		$this->assertSame( array( 'text', 'email', 'textarea' ), $registry->slugs() );
		$this->assertTrue( $registry->has( 'email' ) );
		$this->assertFalse( $registry->has( 'checkbox' ) );
	}

	public function testRegistryGetUnknownTypeThrows(): void {
		$this->expectException( \InvalidArgumentException::class );

		( new FieldTypeRegistry() )->get( 'checkbox' );
	}

	public function testTextSanitizeStripsControlCharactersAndTrims(): void {
		$type = new TextType();

		$this->assertSame( 'Hello World', $type->sanitize( "  Hello\x00 Wor\x1Fld\n" ) );
		$this->assertSame( '', $type->sanitize( array( 'not', 'scalar' ) ) );
		$this->assertSame( '42', $type->sanitize( 42 ) );
	}

	public function testTextValidateRejectsOverlongValues(): void {
		$type = new TextType();

		$this->assertNull( $type->validate( str_repeat( 'a', TextType::MAX_LENGTH ) ) );
		$this->assertSame( 'too_long', $type->validate( str_repeat( 'a', TextType::MAX_LENGTH + 1 ) ) );
		$this->assertNull( $type->validate( '' ) );
	}

	public function testEmailSanitizeRemovesWhitespace(): void {
		$type = new EmailType();

		$this->assertSame( 'user@example.com', $type->sanitize( " user@example.com \n" ) );
	}

	public function testEmailValidate(): void {
		$type = new EmailType();

		$this->assertNull( $type->validate( '' ) );
		$this->assertNull( $type->validate( 'user@example.com' ) );
		$this->assertSame( 'invalid_email', $type->validate( 'not-an-email' ) );
		$this->assertSame( 'too_long', $type->validate( str_repeat( 'a', 250 ) . '@example.com' ) );
	}

	public function testTextareaSanitizePreservesNewlinesAndTabs(): void {
		$type = new TextareaType();

		$this->assertSame( "line one\nline\ttwo", $type->sanitize( "line one\r\nline\ttwo\x00" ) );
	}

	public function testTextareaValidateRejectsOverlongValues(): void {
		$type = new TextareaType();

		$this->assertNull( $type->validate( str_repeat( 'a', TextareaType::MAX_LENGTH ) ) );
		$this->assertSame( 'too_long', $type->validate( str_repeat( 'a', TextareaType::MAX_LENGTH + 1 ) ) );
	}
}
