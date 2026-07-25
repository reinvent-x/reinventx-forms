<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Database\Migrator;
use Reinventx\Plugin;
use Reinventx\Setup\Activator;
use Reinventx\Setup\Capabilities;

final class ActivationTest extends ReinventxTestCase {

	public function testActivationCreatesAllTables(): void {
		Activator::activate();

		foreach ( $this->tables()->all() as $table ) {
			$this->assertTrue( $this->tableExists( $table ), "Missing table: {$table}" );
		}
	}

	public function testActivationSetsSchemaVersionOption(): void {
		Activator::activate();

		$this->assertSame( Migrator::SCHEMA_VERSION, (int) get_option( Migrator::OPTION ) );
	}

	public function testActivationIsIdempotent(): void {
		Activator::activate();
		Activator::activate();

		foreach ( $this->tables()->all() as $table ) {
			$this->assertTrue( $this->tableExists( $table ), "Missing table after re-activation: {$table}" );
		}

		$this->assertSame( Migrator::SCHEMA_VERSION, (int) get_option( Migrator::OPTION ) );
	}

	public function testAdministratorReceivesReinventxCapabilities(): void {
		Activator::activate();

		$role = get_role( 'administrator' );

		$this->assertNotNull( $role );

		foreach ( Capabilities::all() as $capability ) {
			$this->assertTrue( $role->has_cap( $capability ), "Missing capability: {$capability}" );
		}
	}

	public function testMigratorReportsNoPendingMigrationAfterActivation(): void {
		Activator::activate();

		$this->assertFalse( Plugin::migrator()->needsMigration() );
	}
}
