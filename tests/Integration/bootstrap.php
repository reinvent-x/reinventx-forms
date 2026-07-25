<?php
/**
 * Integration test bootstrap: boots the WordPress test suite (wp-phpunit)
 * and loads Reinventx Forms as a must-use plugin.
 *
 * Runs inside the wp-env tests container:
 *   npm run test:php
 *
 * @package Reinventx
 */

declare(strict_types=1);

$rvtx_root = dirname( __DIR__, 2 );

require_once $rvtx_root . '/vendor/autoload.php';

$rvtx_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );

if ( false === $rvtx_wp_phpunit ) {
	$rvtx_wp_phpunit = $rvtx_root . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $rvtx_wp_phpunit . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $rvtx_root ): void {
		require $rvtx_root . '/reinventx-forms.php';
	}
);

require $rvtx_wp_phpunit . '/includes/bootstrap.php';
