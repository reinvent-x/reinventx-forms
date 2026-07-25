<?php
declare(strict_types=1);

namespace Reinventx\Setup;

use Reinventx\Plugin;

/**
 * Runs on plugin activation. Must be idempotent — WordPress can fire
 * activation on an already-installed site (reactivation, updates).
 */
final class Activator {

	public static function activate(): void {
		Plugin::migrator()->migrate();
		Capabilities::grant();
	}
}
