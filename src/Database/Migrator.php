<?php
declare(strict_types=1);

namespace Reinventx\Database;

use wpdb;

/**
 * Installs and upgrades the Reinventx Forms schema.
 *
 * The installed version lives in an option; migrate() replays every step
 * above it, in order. Steps must be idempotent — activation can run them
 * again at any time.
 */
final class Migrator {

	public const SCHEMA_VERSION = 1;
	public const OPTION         = 'rvtx_schema_version';

	public function __construct(
		private readonly wpdb $wpdb,
		private readonly Schema $schema,
	) {
	}

	public function installedVersion(): int {
		return (int) get_option( self::OPTION, 0 );
	}

	public function needsMigration(): bool {
		return $this->installedVersion() < self::SCHEMA_VERSION;
	}

	public function migrate(): void {
		$installed = $this->installedVersion();

		if ( $installed < 1 ) {
			$this->migrateToV1();
		}

		update_option( self::OPTION, self::SCHEMA_VERSION );
	}

	private function migrateToV1(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$statements = $this->schema->createTableStatements( $this->wpdb->get_charset_collate() );

		foreach ( $statements as $sql ) {
			dbDelta( $sql );
		}
	}
}
