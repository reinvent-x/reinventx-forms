<?php
declare(strict_types=1);

namespace Reinventx\Tests\Integration;

use Reinventx\Database\Migrator;
use Reinventx\Database\Tables;
use WP_UnitTestCase;

/**
 * Base class for integration tests that need the plugin's real tables.
 *
 * The WP test suite rewrites CREATE TABLE to CREATE TEMPORARY TABLE, but
 * our tests must exercise real DDL (temporary tables are invisible to
 * SHOW TABLES and to dbDelta's ALTER path), so the rewrite filters are
 * removed and tables are dropped explicitly in tear_down().
 */
abstract class ReinventxTestCase extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	public function tear_down(): void {
		global $wpdb;

		// Delete the option BEFORE dropping tables: DROP TABLE is DDL and
		// implicitly commits the test transaction, which makes the deletion
		// durable. The other way round, the suite's rollback resurrects the
		// option and later tests skip table creation.
		delete_option( Migrator::OPTION );

		foreach ( $this->tables()->all() as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		parent::tear_down();
	}

	protected function tables(): Tables {
		global $wpdb;

		return new Tables( $wpdb->prefix );
	}

	protected function tableExists( string $table ): bool {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
