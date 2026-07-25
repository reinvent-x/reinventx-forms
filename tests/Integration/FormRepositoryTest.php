<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Forms\FieldTypes\FieldTypeRegistry;
use Reinventx\Forms\FormConfig;
use Reinventx\Forms\FormRepository;
use Reinventx\Forms\FormStatus;
use Reinventx\Setup\Activator;

final class FormRepositoryTest extends ReinventxTestCase {

	private FormRepository $repository;

	private FieldTypeRegistry $types;

	public function set_up(): void {
		parent::set_up();

		Activator::activate();

		global $wpdb;

		$this->types      = FieldTypeRegistry::withDefaults();
		$this->repository = new FormRepository( $wpdb, $this->tables(), $this->types );
	}

	private function sampleConfig(): FormConfig {
		return FormConfig::fromArray(
			array(
				'fields' => array(
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => 'Email address',
						'required' => true,
					),
				),
			),
			$this->types
		);
	}

	public function testInsertAndFindRoundTrip(): void {
		$created = $this->repository->insert( 'Contact form', $this->sampleConfig() );

		$this->assertGreaterThan( 0, $created->id );
		$this->assertSame( FormStatus::Active, $created->status );

		$found = $this->repository->find( $created->id );

		$this->assertNotNull( $found );
		$this->assertSame( 'Contact form', $found->name );
		$this->assertSame( $created->config->toArray(), $found->config->toArray() );
		$this->assertSame( $created->createdAt, $found->createdAt );
	}

	public function testFindReturnsNullForMissingId(): void {
		$this->assertNull( $this->repository->find( 999999 ) );
	}

	public function testAllFiltersByStatus(): void {
		$first  = $this->repository->insert( 'First', $this->sampleConfig() );
		$second = $this->repository->insert( 'Second', $this->sampleConfig() );

		$this->repository->archive( $first->id );

		$active = $this->repository->all( FormStatus::Active );
		$this->assertCount( 1, $active );
		$this->assertSame( $second->id, $active[0]->id );

		$archived = $this->repository->all( FormStatus::Archived );
		$this->assertCount( 1, $archived );
		$this->assertSame( $first->id, $archived[0]->id );

		$this->assertCount( 2, $this->repository->all() );
	}

	public function testUpdateChangesNameAndConfig(): void {
		$created = $this->repository->insert( 'Old name', $this->sampleConfig() );

		$new_config = FormConfig::fromArray( array( 'fields' => array() ), $this->types );
		$updated    = $this->repository->update( $created->id, 'New name', $new_config );

		$this->assertNotNull( $updated );
		$this->assertSame( 'New name', $updated->name );
		$this->assertSame( array(), $updated->config->fields );

		$reloaded = $this->repository->find( $created->id );
		$this->assertNotNull( $reloaded );
		$this->assertSame( 'New name', $reloaded->name );
		$this->assertSame( $created->createdAt, $reloaded->createdAt );
	}

	public function testUpdateReturnsNullForMissingId(): void {
		$this->assertNull( $this->repository->update( 999999, 'Name', $this->sampleConfig() ) );
	}

	public function testArchiveMarksFormArchived(): void {
		$created = $this->repository->insert( 'To archive', $this->sampleConfig() );

		$this->assertTrue( $this->repository->archive( $created->id ) );

		$reloaded = $this->repository->find( $created->id );
		$this->assertNotNull( $reloaded );
		$this->assertSame( FormStatus::Archived, $reloaded->status );
	}

	public function testArchiveReturnsFalseForMissingId(): void {
		$this->assertFalse( $this->repository->archive( 999999 ) );
	}
}
