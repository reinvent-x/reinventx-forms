import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { fetchAllForms, fetchLeads, isApiError } from './api';
import { Alert, AlertDescription } from './components/ui/alert';
import { Badge } from './components/ui/badge';
import { Button } from './components/ui/button';
import { Card } from './components/ui/card';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from './components/ui/select';
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from './components/ui/table';
import { leadStatusBadgeVariant, leadStatusLabel } from './lib/leadStatus';
import type { Form, LeadsPage, LeadStatus } from './types';

interface Props {
	onOpenLead: ( leadId: number ) => void;
}

const ALL = 'all';

export default function Inbox( { onOpenLead }: Props ) {
	const [ page, setPage ] = useState( 1 );
	const [ formId, setFormId ] = useState< number | null >( null );
	const [ status, setStatus ] = useState< LeadStatus | null >( null );
	const [ forms, setForms ] = useState< Form[] >( [] );
	const [ data, setData ] = useState< LeadsPage | null >( null );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		fetchAllForms()
			.then( setForms )
			.catch( () => setForms( [] ) );
	}, [] );

	const load = useCallback( async () => {
		setError( null );

		try {
			setData( await fetchLeads( { page, formId, status } ) );
		} catch ( e ) {
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not load leads.', 'reinventx-forms' )
			);
		}
	}, [ page, formId, status ] );

	useEffect( () => {
		load();
	}, [ load ] );

	return (
		<div className="flex flex-col gap-4">
			<div className="flex flex-wrap items-center gap-3">
				<div className="w-56">
					<Select
						value={ formId === null ? ALL : String( formId ) }
						onValueChange={ ( value ) => {
							setPage( 1 );
							setFormId( value === ALL ? null : Number( value ) );
						} }
					>
						<SelectTrigger
							aria-label={ __(
								'Filter by form',
								'reinventx-forms'
							) }
						>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value={ ALL }>
								{ __( 'All forms', 'reinventx-forms' ) }
							</SelectItem>
							{ forms.map( ( form ) => (
								<SelectItem
									key={ form.id }
									value={ String( form.id ) }
								>
									{ form.name }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</div>

				<div className="w-44">
					<Select
						value={ status ?? ALL }
						onValueChange={ ( value ) => {
							setPage( 1 );
							setStatus(
								value === ALL ? null : ( value as LeadStatus )
							);
						} }
					>
						<SelectTrigger
							aria-label={ __(
								'Filter by status',
								'reinventx-forms'
							) }
						>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value={ ALL }>
								{ __( 'All statuses', 'reinventx-forms' ) }
							</SelectItem>
							{ ( data?.statuses ?? [] ).map( ( option ) => (
								<SelectItem key={ option } value={ option }>
									{ leadStatusLabel( option ) }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</div>
			</div>

			{ error && (
				<Alert variant="destructive">
					<AlertDescription>{ error }</AlertDescription>
				</Alert>
			) }

			{ data === null && ! error && (
				<p className="text-muted-foreground">
					{ __( 'Loading…', 'reinventx-forms' ) }
				</p>
			) }

			{ data !== null && data.items.length === 0 && (
				<Card className="flex items-center justify-center p-12">
					<p className="text-muted-foreground">
						{ __(
							'No leads yet. Publish a form and they will land here.',
							'reinventx-forms'
						) }
					</p>
				</Card>
			) }

			{ data !== null && data.items.length > 0 && (
				<Card className="overflow-hidden">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>
									{ __( 'Lead', 'reinventx-forms' ) }
								</TableHead>
								<TableHead>
									{ __( 'Form', 'reinventx-forms' ) }
								</TableHead>
								<TableHead>
									{ __( 'Status', 'reinventx-forms' ) }
								</TableHead>
								<TableHead>
									{ __(
										'Submitted (UTC)',
										'reinventx-forms'
									) }
								</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{ data.items.map( ( lead ) => (
								<TableRow
									key={ lead.id }
									className="cursor-pointer"
									onClick={ () => onOpenLead( lead.id ) }
								>
									<TableCell className="font-medium">
										<button
											type="button"
											className="text-left underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
											onClick={ ( event ) => {
												event.stopPropagation();
												onOpenLead( lead.id );
											} }
										>
											{ lead.primary ||
												sprintf(
													/* translators: %d: lead id. */
													__(
														'Lead #%d',
														'reinventx-forms'
													),
													lead.id
												) }
										</button>
									</TableCell>
									<TableCell className="text-muted-foreground">
										{ lead.form_name }
									</TableCell>
									<TableCell>
										<Badge
											variant={ leadStatusBadgeVariant(
												lead.status
											) }
										>
											{ leadStatusLabel( lead.status ) }
										</Badge>
									</TableCell>
									<TableCell className="text-muted-foreground">
										{ lead.submitted_at }
									</TableCell>
								</TableRow>
							) ) }
						</TableBody>
					</Table>
				</Card>
			) }

			{ data !== null && data.total_pages > 1 && (
				<div className="flex items-center justify-between">
					<p className="text-sm text-muted-foreground">
						{ sprintf(
							/* translators: 1: current page, 2: total pages, 3: total leads. */
							__(
								'Page %1$d of %2$d (%3$d leads)',
								'reinventx-forms'
							),
							data.page,
							data.total_pages,
							data.total
						) }
					</p>
					<div className="flex gap-2">
						<Button
							variant="outline"
							size="sm"
							disabled={ page <= 1 }
							onClick={ () => setPage( ( p ) => p - 1 ) }
						>
							<ChevronLeft />
							{ __( 'Previous', 'reinventx-forms' ) }
						</Button>
						<Button
							variant="outline"
							size="sm"
							disabled={ page >= data.total_pages }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ __( 'Next', 'reinventx-forms' ) }
							<ChevronRight />
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}
