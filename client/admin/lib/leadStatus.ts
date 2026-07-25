import { __ } from '@wordpress/i18n';

import type { LeadStatus } from '@/types';

/**
 * Labels and badge styling for the follow-up pipeline. The list itself is
 * owned by the server (LeadStatuses.php) and arrives with REST responses;
 * this maps known statuses to translated labels and falls back to the raw
 * value for anything unknown (forward-compatible with custom statuses).
 *
 * @param status Status value from the REST API.
 */
export function leadStatusLabel( status: string ): string {
	switch ( status ) {
		case 'new':
			return __( 'New', 'reinventx-forms' );
		case 'contacted':
			return __( 'Contacted', 'reinventx-forms' );
		case 'qualified':
			return __( 'Qualified', 'reinventx-forms' );
		case 'won':
			return __( 'Won', 'reinventx-forms' );
		case 'lost':
			return __( 'Lost', 'reinventx-forms' );
		case 'spam':
			return __( 'Spam', 'reinventx-forms' );
		default:
			return status;
	}
}

export function leadStatusBadgeVariant(
	status: LeadStatus
): 'default' | 'secondary' | 'outline' {
	if ( status === 'new' ) {
		return 'default';
	}

	if ( status === 'lost' || status === 'spam' ) {
		return 'outline';
	}

	return 'secondary';
}
