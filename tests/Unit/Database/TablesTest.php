<?php
declare(strict_types=1);

namespace Reinventx\Tests\Unit\Database;

use Reinventx\Database\Tables;
use PHPUnit\Framework\TestCase;

final class TablesTest extends TestCase {

	public function testTableNamesUseSitePrefixAndPluginPrefix(): void {
		$tables = new Tables( 'wp_' );

		$this->assertSame( 'wp_rvtx_forms', $tables->forms() );
		$this->assertSame( 'wp_rvtx_leads', $tables->leads() );
		$this->assertSame( 'wp_rvtx_lead_notes', $tables->leadNotes() );
	}

	public function testMultisiteStylePrefixIsRespected(): void {
		$tables = new Tables( 'wp_3_' );

		$this->assertSame( 'wp_3_rvtx_forms', $tables->forms() );
	}

	public function testAllListsEveryTableChildrenFirst(): void {
		$tables = new Tables( 'wp_' );

		$this->assertSame(
			array( 'wp_rvtx_lead_notes', 'wp_rvtx_leads', 'wp_rvtx_forms' ),
			$tables->all()
		);
	}
}
