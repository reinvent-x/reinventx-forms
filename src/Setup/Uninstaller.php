<?php
declare(strict_types=1);

namespace Reinventx\Setup;

use Reinventx\Database\Migrator;
use Reinventx\Database\Tables;

/**
 * Runs on plugin uninstall (from uninstall.php).
 *
 * Destructive by design, so it is double-gated: WordPress only calls it on
 * explicit deletion, and it still refuses to touch data unless the site
 * owner opted in via the delete-data option.
 */
final class Uninstaller {

	public const DELETE_DATA_OPTION = 'rvtx_delete_data_on_uninstall';

	public static function uninstall(): void {
		if ( ! get_option( self::DELETE_DATA_OPTION ) ) {
			return;
		}

		global $wpdb;

		$tables = new Tables( $wpdb->prefix );

		foreach ( $tables->all() as $table ) {
			// Table names come from the internal registry, not user input,
			// and DDL identifiers cannot be parameterized via prepare() —
			// escape and quote the identifier instead.
			$safe_table = esc_sql( $table );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$safe_table}`" );
		}

		delete_option( Migrator::OPTION );
		delete_option( self::DELETE_DATA_OPTION );

		Capabilities::revoke();
	}
}
