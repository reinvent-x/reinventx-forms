<?php
/**
 * Reinventx Forms uninstall handler.
 *
 * Data is preserved unless the site owner explicitly opted in to deletion
 * (the "delete data on uninstall" setting ships with the settings UI in a
 * later milestone; the option is honored from day one).
 *
 * @package Reinventx
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rvtx_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $rvtx_autoload ) ) {
	return;
}

require_once $rvtx_autoload;

\Reinventx\Setup\Uninstaller::uninstall();
