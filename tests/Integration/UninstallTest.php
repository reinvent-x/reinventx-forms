<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Database\Migrator;
use Reinventx\Setup\Activator;
use Reinventx\Setup\Capabilities;
use Reinventx\Setup\Uninstaller;

final class UninstallTest extends ReinventxTestCase {

	public function set_up(): void {
		parent::set_up();

		Activator::activate();
	}

	public function tear_down(): void {
		delete_option( Uninstaller::DELETE_DATA_OPTION );

		parent::tear_down();
	}

	public function testUninstallPreservesDataByDefault(): void {
		delete_option( Uninstaller::DELETE_DATA_OPTION );

		Uninstaller::uninstall();

		foreach ( $this->tables()->all() as $table ) {
			$this->assertTrue( $this->tableExists( $table ), "Table should survive uninstall: {$table}" );
		}

		$this->assertSame( Migrator::SCHEMA_VERSION, (int) get_option( Migrator::OPTION ) );
	}

	public function testUninstallDeletesEverythingWhenOptedIn(): void {
		update_option( Uninstaller::DELETE_DATA_OPTION, '1' );

		Uninstaller::uninstall();

		foreach ( $this->tables()->all() as $table ) {
			$this->assertFalse( $this->tableExists( $table ), "Table should be dropped: {$table}" );
		}

		$this->assertFalse( get_option( Migrator::OPTION ) );
		$this->assertFalse( get_option( Uninstaller::DELETE_DATA_OPTION ) );

		$role = get_role( 'administrator' );

		$this->assertNotNull( $role );

		foreach ( Capabilities::all() as $capability ) {
			$this->assertFalse( $role->has_cap( $capability ), "Capability should be revoked: {$capability}" );
		}
	}
}
