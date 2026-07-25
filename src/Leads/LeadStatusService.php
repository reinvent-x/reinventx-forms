<?php
declare(strict_types=1);

namespace Reinventx\Leads;

/**
 * The only way a lead's status changes. Owning this in one place is what
 * makes rvtx_lead_status_changed a reliable extension point (M4+
 * notifications and automation hang off it).
 */
final class LeadStatusService {

	public function __construct( private readonly LeadRepository $leads ) {
	}

	/**
	 * @throws \InvalidArgumentException When the status is not in the pipeline.
	 */
	public function change( int $lead_id, string $status ): ?Lead {
		if ( ! LeadStatuses::isValid( $status ) ) {
			throw new \InvalidArgumentException( 'Unknown lead status.' );
		}

		$lead = $this->leads->find( $lead_id );

		if ( null === $lead ) {
			return null;
		}

		if ( $lead->status === $status ) {
			return $lead;
		}

		$this->leads->updateStatus( $lead_id, $status );

		$updated = $this->leads->find( $lead_id );

		if ( null === $updated ) {
			return null;
		}

		/**
		 * Fires after a lead's status has changed.
		 *
		 * @param Lead   $updated The lead with its new status.
		 * @param string $from    Previous status.
		 * @param string $to      New status.
		 */
		do_action( 'rvtx_lead_status_changed', $updated, $lead->status, $status );

		return $updated;
	}
}
