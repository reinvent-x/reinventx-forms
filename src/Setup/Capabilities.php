<?php
declare(strict_types=1);

namespace Reinventx\Setup;

/**
 * Reinventx Forms custom capabilities.
 *
 * Forms management and lead (inbox) access are separate capabilities so a
 * future release can give e.g. a sales role inbox access without form
 * editing. Both are granted to administrators on activation.
 */
final class Capabilities {

	public const MANAGE_FORMS = 'rvtx_manage_forms';
	public const MANAGE_LEADS = 'rvtx_manage_leads';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::MANAGE_FORMS, self::MANAGE_LEADS );
	}

	public static function grant(): void {
		$role = get_role( 'administrator' );

		if ( null === $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	public static function revoke(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $capability ) {
				if ( $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
