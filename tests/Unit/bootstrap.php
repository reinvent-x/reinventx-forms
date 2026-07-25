<?php
/**
 * Unit test bootstrap: Composer autoload only, WordPress is never loaded.
 *
 * Code under unit test must not call WordPress functions. If a class needs
 * WordPress, it gets an integration test instead.
 *
 * @package Reinventx
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
