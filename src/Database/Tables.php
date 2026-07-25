<?php
declare(strict_types=1);

namespace Reinventx\Database;

/**
 * Single registry for Reinventx Forms table names.
 *
 * Every query in the codebase must take table names from here — never build
 * them inline. Takes the prefix as a plain string so it stays testable
 * without WordPress.
 */
final class Tables {

	public function __construct( private readonly string $prefix ) {
	}

	public function forms(): string {
		return $this->prefix . 'rvtx_forms';
	}

	public function leads(): string {
		return $this->prefix . 'rvtx_leads';
	}

	public function leadNotes(): string {
		return $this->prefix . 'rvtx_lead_notes';
	}

	/**
	 * All tables, in an order that is safe for dropping (children first).
	 *
	 * @return string[]
	 */
	public function all(): array {
		return array( $this->leadNotes(), $this->leads(), $this->forms() );
	}
}
