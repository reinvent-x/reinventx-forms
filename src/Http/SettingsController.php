<?php
declare(strict_types=1);

namespace Reinventx\Http;

use Reinventx\Setup\Uninstaller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Plugin settings: reinventx-forms/v1/settings.
 *
 * Gated on manage_options (not the Reinventx Forms capabilities): the only
 * setting so far authorizes destroying all plugin data on uninstall,
 * which is a site-owner decision, not a lead-manager one.
 */
final class SettingsController {

	public const REST_NAMESPACE = 'reinventx-forms/v1';

	public function registerRoutes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'canManageOptions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'canManageOptions' ),
				),
			)
		);
	}

	public function canManageOptions(): bool|WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rvtx_forbidden',
			__( 'You are not allowed to manage Reinventx Forms settings.', 'reinventx-forms' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function show(): WP_REST_Response {
		return new WP_REST_Response( $this->settings() );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$value = $request->get_param( 'delete_data_on_uninstall' );

		if ( ! is_bool( $value ) ) {
			return new WP_Error(
				'rvtx_invalid_setting',
				__( 'delete_data_on_uninstall must be true or false.', 'reinventx-forms' ),
				array( 'status' => 400 )
			);
		}

		update_option( Uninstaller::DELETE_DATA_OPTION, $value ? '1' : '' );

		return new WP_REST_Response( $this->settings() );
	}

	/**
	 * @return array{delete_data_on_uninstall: bool}
	 */
	private function settings(): array {
		return array(
			'delete_data_on_uninstall' => (bool) get_option( Uninstaller::DELETE_DATA_OPTION ),
		);
	}
}
