import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ArrowLeft } from 'lucide-react';

import { addLeadNote, fetchLead, isApiError, updateLeadStatus } from './api';
import { Alert, AlertDescription } from './components/ui/alert';
import { Badge } from './components/ui/badge';
import { Button } from './components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from './components/ui/card';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from './components/ui/select';
import { Textarea } from './components/ui/textarea';
import { leadStatusBadgeVariant, leadStatusLabel } from './lib/leadStatus';
import type { LeadDetail as LeadDetailData, LeadStatus } from './types';

interface Props {
	leadId: number;
	onBack: () => void;
}

function safeHref( url: string ): string | null {
	return /^https?:\/\//i.test( url ) ? url : null;
}

function ContextRow( { label, value }: { label: string; value: string } ) {
	const href = safeHref( value );

	return (
		<div className="flex flex-col gap-0.5">
			<dt className="text-xs font-medium text-muted-foreground">
				{ label }
			</dt>
			<dd className="break-all text-sm">
				{ href ? (
					<a
						href={ href }
						target="_blank"
						rel="noreferrer noopener"
						className="underline underline-offset-2"
					>
						{ value }
					</a>
				) : (
					value
				) }
			</dd>
		</div>
	);
}

export default function LeadDetail( { leadId, onBack }: Props ) {
	const [ lead, setLead ] = useState< LeadDetailData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ note, setNote ] = useState( '' );
	const [ savingNote, setSavingNote ] = useState( false );

	useEffect( () => {
		let cancelled = false;

		fetchLead( leadId )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setLead( data );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError(
						isApiError( e )
							? e.message
							: __(
									'Could not load the lead.',
									'reinventx-forms'
							  )
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ leadId ] );

	const onStatusChange = async ( status: LeadStatus ) => {
		if ( ! lead ) {
			return;
		}

		try {
			setLead( await updateLeadStatus( lead.id, status ) );
		} catch ( e ) {
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not update the status.', 'reinventx-forms' )
			);
		}
	};

	const onAddNote = async () => {
		if ( ! lead || note.trim() === '' ) {
			return;
		}

		setSavingNote( true );
		setError( null );

		try {
			const created = await addLeadNote( lead.id, note.trim() );

			setLead( { ...lead, notes: [ ...lead.notes, created ] } );
			setNote( '' );
		} catch ( e ) {
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not save the note.', 'reinventx-forms' )
			);
		} finally {
			setSavingNote( false );
		}
	};

	if ( lead === null ) {
		return (
			<div className="flex flex-col gap-4">
				<div>
					<Button variant="ghost" size="sm" onClick={ onBack }>
						<ArrowLeft />
						{ __( 'Back to inbox', 'reinventx-forms' ) }
					</Button>
				</div>
				{ error ? (
					<Alert variant="destructive">
						<AlertDescription>{ error }</AlertDescription>
					</Alert>
				) : (
					<p className="text-muted-foreground">
						{ __( 'Loading…', 'reinventx-forms' ) }
					</p>
				) }
			</div>
		);
	}

	return (
		<div className="flex max-w-3xl flex-col gap-4">
			<div className="flex items-center justify-between">
				<Button variant="ghost" size="sm" onClick={ onBack }>
					<ArrowLeft />
					{ __( 'Back to inbox', 'reinventx-forms' ) }
				</Button>

				<div className="flex items-center gap-2">
					<Badge variant={ leadStatusBadgeVariant( lead.status ) }>
						{ leadStatusLabel( lead.status ) }
					</Badge>
					<div className="w-40">
						<Select
							value={ lead.status }
							onValueChange={ ( value ) =>
								onStatusChange( value as LeadStatus )
							}
						>
							<SelectTrigger
								aria-label={ __(
									'Lead status',
									'reinventx-forms'
								) }
							>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								{ lead.statuses.map( ( option ) => (
									<SelectItem key={ option } value={ option }>
										{ leadStatusLabel( option ) }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					</div>
				</div>
			</div>

			{ error && (
				<Alert variant="destructive">
					<AlertDescription>{ error }</AlertDescription>
				</Alert>
			) }

			<Card>
				<CardHeader>
					<CardTitle>
						{ __( 'Submission', 'reinventx-forms' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					<dl className="flex flex-col gap-3">
						{ lead.fields.map( ( field ) => (
							<div
								key={ field.id }
								className="flex flex-col gap-0.5"
							>
								<dt className="text-xs font-medium text-muted-foreground">
									{ field.label }
								</dt>
								<dd className="whitespace-pre-wrap break-words text-sm">
									{ field.value || '—' }
								</dd>
							</div>
						) ) }
					</dl>
				</CardContent>
			</Card>

			<Card>
				<CardHeader>
					<CardTitle>{ __( 'Source', 'reinventx-forms' ) }</CardTitle>
				</CardHeader>
				<CardContent>
					<dl className="flex flex-col gap-3">
						<ContextRow
							label={ __( 'Form', 'reinventx-forms' ) }
							value={ lead.form_name }
						/>
						{ lead.context.source_url && (
							<ContextRow
								label={ __( 'Page URL', 'reinventx-forms' ) }
								value={ lead.context.source_url }
							/>
						) }
						{ lead.context.source_title && (
							<ContextRow
								label={ __( 'Page title', 'reinventx-forms' ) }
								value={ lead.context.source_title }
							/>
						) }
						{ lead.context.referrer_url && (
							<ContextRow
								label={ __( 'Referrer', 'reinventx-forms' ) }
								value={ lead.context.referrer_url }
							/>
						) }
						{ lead.context.user_agent && (
							<ContextRow
								label={ __( 'Browser', 'reinventx-forms' ) }
								value={ lead.context.user_agent }
							/>
						) }
						<ContextRow
							label={ __( 'Submitted (UTC)', 'reinventx-forms' ) }
							value={ lead.submitted_at }
						/>
					</dl>
				</CardContent>
			</Card>

			<Card>
				<CardHeader>
					<CardTitle>{ __( 'Notes', 'reinventx-forms' ) }</CardTitle>
				</CardHeader>
				<CardContent className="flex flex-col gap-4">
					{ lead.notes.length === 0 && (
						<p className="text-sm text-muted-foreground">
							{ __( 'No notes yet.', 'reinventx-forms' ) }
						</p>
					) }

					{ lead.notes.map( ( item ) => (
						<div key={ item.id } className="rounded-lg border p-3">
							<p className="whitespace-pre-wrap break-words text-sm">
								{ item.note }
							</p>
							<p className="mt-1.5 text-xs text-muted-foreground">
								{ item.author } · { item.created_at }
							</p>
						</div>
					) ) }

					<div className="flex flex-col gap-2">
						<label
							className="text-sm font-medium"
							htmlFor="rvtx-new-note"
						>
							{ __( 'Add a note', 'reinventx-forms' ) }
						</label>
						<Textarea
							id="rvtx-new-note"
							value={ note }
							maxLength={ 5000 }
							rows={ 3 }
							onChange={ ( e ) => setNote( e.target.value ) }
						/>
						<div>
							<Button
								size="sm"
								disabled={ savingNote || note.trim() === '' }
								onClick={ onAddNote }
							>
								{ savingNote
									? __( 'Saving…', 'reinventx-forms' )
									: __( 'Add note', 'reinventx-forms' ) }
							</Button>
						</div>
					</div>
				</CardContent>
			</Card>
		</div>
	);
}
