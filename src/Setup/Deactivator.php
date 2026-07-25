<?php
declare(strict_types=1);

namespace Reinventx\Setup;

/**
 * Runs on plugin deactivation.
 *
 * Deliberately does nothing to data: deactivation is not consent to delete
 * forms or leads. Cleanup belongs to the uninstaller, gated by an explicit
 * opt-in.
 */
final class Deactivator {

	public static function deactivate(): void {
		// Intentionally empty for now (rewrite rules, cron, etc. come later).
	}
}
