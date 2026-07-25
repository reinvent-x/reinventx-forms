<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Leads\Lead;
use Reinventx\Leads\LeadRepository;
use Reinventx\Setup\Activator;
use Reinventx\Submissions\SubmissionContext;

final class LeadRepositoryTest extends ReinventxTestCase {

	private LeadRepository $repository;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wpdb;

		$this->repository = new LeadRepository( $wpdb, $this->tables() );
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

	public function testInsertAndFindRoundTrip(): void {
		$data = array(
			'name'    => 'Jane Doe',
			'email'   => 'jane@example.com',
			'message' => "line one\nline two",
		);

		$created = $this->repository->insert( 42, $data, $this->context() );

		$this->assertGreaterThan( 0, $created->id );
		$this->assertSame( Lead::STATUS_NEW, $created->status );

		$found = $this->repository->find( $created->id );

		$this->assertNotNull( $found );
		$this->assertSame( 42, $found->formId );
		$this->assertSame( $data, $found->data );
		$this->assertSame( 'https://example.com/contact', $found->sourceUrl );
		$this->assertSame( 'Contact us', $found->sourceTitle );
		$this->assertSame( 'https://google.com/search', $found->referrerUrl );
		$this->assertSame( 'Mozilla/5.0 Test', $found->userAgent );
		$this->assertSame( $created->submittedAt, $found->submittedAt );
	}

	public function testIpIsStoredAsHashNotPlaintext(): void {
		$created = $this->repository->insert( 1, array(), $this->context() );
		$found   = $this->repository->find( $created->id );

		$this->assertNotNull( $found );
		$this->assertNotNull( $found->ipHash );
		$this->assertStringNotContainsString( '203.0.113.7', $found->ipHash );
		$this->assertSame( 64, strlen( $found->ipHash ) );
	}

	public function testScriptPayloadIsStoredVerbatimAndInert(): void {
		$payload = '<script>alert("xss")</script>';
		$created = $this->repository->insert(
			1,
			array( 'name' => $payload ),
			$this->context()
		);

		$found = $this->repository->find( $created->id );

		$this->assertNotNull( $found );
		// Stored raw (inert data); escaping is an output-time concern.
		$this->assertSame( $payload, $found->data['name'] );
	}

	public function testFindReturnsNullForMissingId(): void {
		$this->assertNull( $this->repository->find( 999999 ) );
	}
}
