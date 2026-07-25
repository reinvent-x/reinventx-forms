import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Archive, Pencil, Plus } from 'lucide-react';

import { archiveForm, fetchForms, isApiError } from './api';
import { Alert, AlertDescription } from './components/ui/alert';
import { Badge } from './components/ui/badge';
import { Button } from './components/ui/button';
import { Card } from './components/ui/card';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from './components/ui/dialog';
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from './components/ui/table';
import { Tabs, TabsList, TabsTrigger } from './components/ui/tabs';
import type { Form, FormStatus } from './types';

interface Props {
	onCreate: () => void;
	onEdit: ( formId: number ) => void;
}

export default function FormsList( { onCreate, onEdit }: Props ) {
	const [ status, setStatus ] = useState< FormStatus >( 'active' );
	const [ forms, setForms ] = useState< Form[] | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ archiving, setArchiving ] = useState< Form | null >( null );

	const load = useCallback( async () => {
		setForms( null );
		setError( null );

		try {
			setForms( await fetchForms( status ) );
		} catch ( e ) {
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not load forms.', 'reinventx-forms' )
			);
		}
	}, [ status ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const confirmArchive = async () => {
		if ( ! archiving ) {
			return;
		}

		try {
			await archiveForm( archiving.id );
			setArchiving( null );
			await load();
		} catch ( e ) {
			setArchiving( null );
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not archive the form.', 'reinventx-forms' )
			);
		}
	};

	return (
		<div className="flex flex-col gap-4">
			<div className="flex items-center justify-between">
				<Tabs
					value={ status }
					onValueChange={ ( value ) =>
						setStatus( value as FormStatus )
					}
				>
					<TabsList>
						<TabsTrigger value="active">
							{ __( 'Active', 'reinventx-forms' ) }
						</TabsTrigger>
						<TabsTrigger value="archived">
							{ __( 'Archived', 'reinventx-forms' ) }
						</TabsTrigger>
					</TabsList>
				</Tabs>

				<Button onClick={ onCreate }>
					<Plus />
					{ __( 'Add form', 'reinventx-forms' ) }
				</Button>
			</div>

			{ error && (
				<Alert variant="destructive">
					<AlertDescription>{ error }</AlertDescription>
				</Alert>
			) }

			{ forms === null && ! error && (
				<p className="text-muted-foreground">
					{ __( 'Loading…', 'reinventx-forms' ) }
				</p>
			) }

			{ forms !== null && forms.length === 0 && (
				<Card className="flex items-center justify-center p-12">
					<p className="text-muted-foreground">
						{ status === 'active'
							? __(
									'No forms yet. Create your first form to start collecting leads.',
									'reinventx-forms'
							  )
							: __( 'No archived forms.', 'reinventx-forms' ) }
					</p>
				</Card>
			) }

			{ forms !== null && forms.length > 0 && (
				<Card className="overflow-hidden">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>
									{ __( 'Name', 'reinventx-forms' ) }
								</TableHead>
								<TableHead>
									{ __( 'Fields', 'reinventx-forms' ) }
								</TableHead>
								<TableHead>
									{ __(
										'Last updated (UTC)',
										'reinventx-forms'
									) }
								</TableHead>
								<TableHead className="text-right">
									{ __( 'Actions', 'reinventx-forms' ) }
								</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{ forms.map( ( form ) => (
								<TableRow key={ form.id }>
									<TableCell className="font-medium">
										{ form.name }
										{ form.status === 'archived' && (
											<Badge
												variant="secondary"
												className="ml-2"
											>
												{ __(
													'Archived',
													'reinventx-forms'
												) }
											</Badge>
										) }
									</TableCell>
									<TableCell>
										{ form.config.fields.length }
									</TableCell>
									<TableCell className="text-muted-foreground">
										{ form.updated_at }
									</TableCell>
									<TableCell className="text-right">
										<div className="flex justify-end gap-2">
											<Button
												variant="outline"
												size="sm"
												onClick={ () =>
													onEdit( form.id )
												}
											>
												<Pencil />
												{ __(
													'Edit',
													'reinventx-forms'
												) }
											</Button>
											{ form.status === 'active' && (
												<Button
													variant="ghost"
													size="sm"
													onClick={ () =>
														setArchiving( form )
													}
												>
													<Archive />
													{ __(
														'Archive',
														'reinventx-forms'
													) }
												</Button>
											) }
										</div>
									</TableCell>
								</TableRow>
							) ) }
						</TableBody>
					</Table>
				</Card>
			) }

			<Dialog
				open={ archiving !== null }
				onOpenChange={ ( open ) => ! open && setArchiving( null ) }
			>
				<DialogContent>
					<DialogHeader>
						<DialogTitle>
							{ __( 'Archive this form?', 'reinventx-forms' ) }
						</DialogTitle>
						<DialogDescription>
							{ archiving &&
								sprintf(
									/* translators: %s: form name. */
									__(
										'“%s” will stop accepting submissions. Its leads are kept and the form can still be edited later.',
										'reinventx-forms'
									),
									archiving.name
								) }
						</DialogDescription>
					</DialogHeader>
					<DialogFooter>
						<Button
							variant="outline"
							onClick={ () => setArchiving( null ) }
						>
							{ __( 'Cancel', 'reinventx-forms' ) }
						</Button>
						<Button
							variant="destructive"
							onClick={ confirmArchive }
						>
							{ __( 'Archive form', 'reinventx-forms' ) }
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	);
}
