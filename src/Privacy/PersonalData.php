<?php
declare(strict_types=1);

namespace Reinventx\Privacy;

use Reinventx\Forms\FormRepository;
use Reinventx\Leads\Lead;
use Reinventx\Leads\LeadNoteRepository;
use Reinventx\Leads\LeadRepository;

/**
 * Participation in WordPress core's personal-data tools.
 *
 * A lead is personal data by definition, so Tools → Export/Erase Personal
 * Data must be able to reach it. Leads are matched by any submitted value
 * equal to the requested address, which covers email fields and an address
 * typed into a plain text field alike.
 */
final class PersonalData {

	public const GROUP = 'reinventx-forms-leads';

	/**
	 * Leads handled per exporter/eraser page. Core calls back repeatedly
	 * until done, so this only bounds one request's work.
	 */
	private const PER_PAGE = 50;

	public function __construct(
		private readonly LeadRepository $leads,
		private readonly LeadNoteRepository $notes,
		private readonly FormRepository $forms,
	) {
	}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function registerExporter( array $exporters ): array {
		$exporters[ self::GROUP ] = array(
			'exporter_friendly_name' => __( 'Reinventx Forms leads', 'reinventx-forms' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers
	 * @return array<string, array<string, mixed>>
	 */
	public function registerEraser( array $erasers ): array {
		$erasers[ self::GROUP ] = array(
			'eraser_friendly_name' => __( 'Reinventx Forms leads', 'reinventx-forms' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$page  = max( 1, $page );
		$found = $this->leads->findByEmail( $email_address, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		$data = array();

		foreach ( $found as $lead ) {
			$data[] = array(
				'group_id'    => self::GROUP,
				'group_label' => __( 'Form submissions', 'reinventx-forms' ),
				'item_id'     => 'rvtx-lead-' . $lead->id,
				'data'        => $this->exportItem( $lead ),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $found ) < self::PER_PAGE,
		);
	}

	/**
	 * $page is part of core's eraser signature but deliberately unused: each
	 * pass deletes what it finds, so the next batch shifts down into the same
	 * window. Honouring the offset would step over leads never examined.
	 *
	 * @param int $page Unused. Core's paging cursor.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 *
	 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		$found = $this->leads->findByEmail( $email_address, self::PER_PAGE, 0 );

		$removed = false;

		foreach ( $found as $lead ) {
			$this->notes->deleteForLead( $lead->id );

			if ( $this->leads->delete( $lead->id ) ) {
				$removed = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $found ) < self::PER_PAGE,
		);
	}

	/**
	 * One lead flattened into core's name/value pairs, using the form's
	 * current field labels where they still exist.
	 *
	 * @return array<int, array{name: string, value: string}>
	 */
	private function exportItem( Lead $lead ): array {
		$form   = $this->forms->find( $lead->formId );
		$labels = array();

		if ( null !== $form ) {
			foreach ( $form->config->fields as $field ) {
				$labels[ $field->id ] = $field->label;
			}
		}

		$item = array(
			array(
				'name'  => __( 'Form', 'reinventx-forms' ),
				'value' => null === $form ? (string) $lead->formId : $form->name,
			),
			array(
				'name'  => __( 'Submitted at (UTC)', 'reinventx-forms' ),
				'value' => $lead->submittedAt,
			),
			array(
				'name'  => __( 'Status', 'reinventx-forms' ),
				'value' => $lead->status,
			),
		);

		foreach ( $lead->data as $field_id => $value ) {
			$item[] = array(
				'name'  => $labels[ $field_id ] ?? $field_id,
				'value' => $value,
			);
		}

		$context = array(
			__( 'Submitted from (URL)', 'reinventx-forms' ) => $lead->sourceUrl,
			__( 'Submitted from (page)', 'reinventx-forms' ) => $lead->sourceTitle,
			__( 'Referrer', 'reinventx-forms' )           => $lead->referrerUrl,
			__( 'Browser user agent', 'reinventx-forms' ) => $lead->userAgent,
		);

		foreach ( $context as $name => $value ) {
			if ( null !== $value && '' !== $value ) {
				$item[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}

		return $item;
	}
}
