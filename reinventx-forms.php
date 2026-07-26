<?php
/**
 * Plugin Name:       Reinventx Forms
 * Plugin URI:        https://github.com/reinvent-x/reinventx-forms
 * Description:       Standalone form and lead management for WordPress — create forms, capture leads with source context, and track follow-up status.
 * Version:           0.1.2
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Melad Samuel
 * Author URI:        https://profiles.wordpress.org/meladsamuel/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       reinventx-forms
 *
 * @package Reinventx
 */

// This file must stay parseable on old PHP versions so the guards below can
// run and fail gracefully; keep modern syntax out of it.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: current PHP version. */
					__( 'Reinventx Forms requires PHP 8.1 or newer. Your site is running PHP %s, so the plugin is inactive.', 'reinventx-forms' ),
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);

	return;
}

define( 'REINVENTX_VERSION', '0.1.2' );
define( 'REINVENTX_FILE', __FILE__ );

$rvtx_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $rvtx_autoload ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Reinventx Forms is missing its autoloader. If you are running from source, run "composer install" in the plugin directory.', 'reinventx-forms' );
			echo '</p></div>';
		}
	);

	return;
}

require $rvtx_autoload;

register_activation_hook( __FILE__, array( \Reinventx\Setup\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Reinventx\Setup\Deactivator::class, 'deactivate' ) );

\Reinventx\Plugin::boot( __FILE__ );
