<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Database;

use Reinventx\Database\Schema;
use Reinventx\Database\Tables;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase {

	private const CHARSET = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

	/**
	 * @return array<string, string>
	 */
	private function statements(): array {
		return ( new Schema( new Tables( 'wp_' ) ) )->createTableStatements( self::CHARSET );
	}

	public function testDefinesAllThreeTables(): void {
		$statements = $this->statements();

		$this->assertSame(
			array( 'wp_rvtx_forms', 'wp_rvtx_leads', 'wp_rvtx_lead_notes' ),
			array_keys( $statements )
		);
	}

	public function testEveryStatementIsDbDeltaCompatible(): void {
		foreach ( $this->statements() as $table => $sql ) {
			$this->assertStringStartsWith( "CREATE TABLE {$table} (", $sql, $table );
			// dbDelta requires exactly two spaces after PRIMARY KEY.
			$this->assertStringContainsString( 'PRIMARY KEY  (id)', $sql, $table );
			$this->assertStringContainsString( self::CHARSET, $sql, $table );
			$this->assertStringNotContainsString( '`', $sql, $table );
		}
	}

	public function testLeadsTableCapturesSourceContext(): void {
		$leads = $this->statements()['wp_rvtx_leads'];

		foreach ( array( 'form_id', 'status', 'data', 'source_url', 'source_title', 'referrer_url', 'user_agent', 'ip_hash', 'submitted_at' ) as $column ) {
			$this->assertStringContainsString( $column, $leads );
		}

		$this->assertStringContainsString( 'KEY form_status (form_id,status)', $leads );
	}

	public function testLeadNotesTableTracksAuthorship(): void {
		$notes = $this->statements()['wp_rvtx_lead_notes'];

		$this->assertStringContainsString( 'lead_id bigint(20) unsigned NOT NULL', $notes );
		$this->assertStringContainsString( 'user_id bigint(20) unsigned NOT NULL', $notes );
	}
}
