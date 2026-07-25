<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\FormRepository;
use Reinventx\Leads\LeadNoteRepository;
use Reinventx\Leads\LeadRepository;
use Reinventx\Privacy\PersonalData;
use Reinventx\Setup\Activator;
use Reinventx\Submissions\SubmissionContext;

final class PersonalDataTest extends ReinventxTestCase {

	private PersonalData $privacy;
	private LeadRepository $leads;
	private LeadNoteRepository $notes;
	private FormRepository $forms;
	private int $form_id;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wpdb;

		$types       = FieldTypeRegistry::withDefaults();
		$this->forms = new FormRepository( $wpdb, $this->tables(), $types );
		$this->leads = new LeadRepository( $wpdb, $this->tables() );
		$this->notes = new LeadNoteRepository( $wpdb, $this->tables() );

		$this->privacy = new PersonalData( $this->leads, $this->notes, $this->forms );

		$config = FormConfig::fromArray(
			array(
				'fields' => array(
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => 'Email address',
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
			$types
		);

		$this->form_id = $this->forms->insert( 'Contact', $config )->id;
	}

	private function context(): SubmissionContext {
		return SubmissionContext::fromRaw(
			'https://example.com/contact',
			'Contact us',
			'https://google.com/search',
			'Mozilla/5.0 Test',
			'203.0.113.7',
			'test-secret'
		);
	}

	private function lead( string $email, string $message = 'hello' ): int {
		return $this->leads->insert(
			$this->form_id,
			array(
				'email'   => $email,
				'message' => $message,
			),
			$this->context()
		)->id;
	}

	public function testExporterAndEraserAreRegisteredWithCore(): void {
		$this->privacy->register();

		$this->assertArrayHasKey(
			PersonalData::GROUP,
			apply_filters( 'wp_privacy_personal_data_exporters', array() )
		);
		$this->assertArrayHasKey(
			PersonalData::GROUP,
			apply_filters( 'wp_privacy_personal_data_erasers', array() )
		);
	}

	public function testExportReturnsOnlyTheMatchingPersonsLeads(): void {
		$this->lead( 'jane@example.com' );
		$this->lead( 'someone-else@example.com' );

		$result = $this->privacy->export( 'jane@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['data'] );
		$this->assertSame( PersonalData::GROUP, $result['data'][0]['group_id'] );
	}

	public function testExportUsesFieldLabelsAndIncludesContext(): void {
		$this->lead( 'jane@example.com', 'please call me' );

		$item  = $this->privacy->export( 'jane@example.com' )['data'][0]['data'];
		$pairs = array_column( $item, 'value', 'name' );

		$this->assertSame( 'jane@example.com', $pairs['Email address'] );
		$this->assertSame( 'please call me', $pairs['Message'] );
		$this->assertSame( 'Contact', $pairs['Form'] );
		$this->assertSame( 'https://google.com/search', $pairs['Referrer'] );
		$this->assertSame( 'Mozilla/5.0 Test', $pairs['Browser user agent'] );
	}

	public function testExportIsCaseInsensitiveOnTheAddress(): void {
		$this->lead( 'Jane@Example.com' );

		$this->assertCount( 1, $this->privacy->export( 'jane@example.com' )['data'] );
	}

	/**
	 * A lead merely containing the address as a substring is not that
	 * person's record and must not be exported.
	 */
	public function testExportIgnoresSubstringOnlyMatches(): void {
		$this->lead( 'not-jane@example.com.au', 'jane@example.com is my colleague' );

		$this->assertSame( array(), $this->privacy->export( 'jane@example.com' )['data'] );
	}

	public function testExportReturnsNothingForAnUnknownAddress(): void {
		$this->lead( 'jane@example.com' );

		$result = $this->privacy->export( 'nobody@example.com' );

		$this->assertSame( array(), $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	public function testEraseRemovesTheLeadAndLeavesOthersAlone(): void {
		$jane  = $this->lead( 'jane@example.com' );
		$other = $this->lead( 'someone-else@example.com' );

		$result = $this->privacy->erase( 'jane@example.com' );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNull( $this->leads->find( $jane ) );
		$this->assertNotNull( $this->leads->find( $other ) );
	}

	/**
	 * Notes are written about a person, so they must not outlive the lead.
	 */
	public function testEraseAlsoRemovesNotesAttachedToTheLead(): void {
		$jane = $this->lead( 'jane@example.com' );

		$this->notes->insert( $jane, 1, 'Called, left voicemail.' );
		$this->assertCount( 1, $this->notes->forLead( $jane ) );

		$this->privacy->erase( 'jane@example.com' );

		$this->assertSame( array(), $this->notes->forLead( $jane ) );
	}

	public function testEraseReportsNothingRemovedForAnUnknownAddress(): void {
		$this->lead( 'jane@example.com' );

		$result = $this->privacy->erase( 'nobody@example.com' );

		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['done'] );
	}
}
