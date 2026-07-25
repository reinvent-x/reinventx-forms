import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	fetchSettings,
	isApiError,
	updateSettings,
	type Settings as SettingsData,
} from './api';
import { Alert, AlertDescription } from './components/ui/alert';
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from './components/ui/card';
import { Switch } from './components/ui/switch';

export default function Settings() {
	const [ settings, setSettings ] = useState< SettingsData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		fetchSettings()
			.then( setSettings )
			.catch( ( e ) =>
				setError(
					isApiError( e )
						? e.message
						: __( 'Could not load settings.', 'reinventx-forms' )
				)
			);
	}, [] );

	const onToggle = async ( checked: boolean ) => {
		if ( ! settings ) {
			return;
		}

		setSaving( true );
		setError( null );

		try {
			setSettings(
				await updateSettings( {
					...settings,
					delete_data_on_uninstall: checked,
				} )
			);
		} catch ( e ) {
			setError(
				isApiError( e )
					? e.message
					: __( 'Could not save the setting.', 'reinventx-forms' )
			);
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="flex max-w-2xl flex-col gap-4">
			{ error && (
				<Alert variant="destructive">
					<AlertDescription>{ error }</AlertDescription>
				</Alert>
			) }

			{ settings === null && ! error && (
				<p className="text-muted-foreground">
					{ __( 'Loading…', 'reinventx-forms' ) }
				</p>
			) }

			{ settings !== null && (
				<Card>
					<CardHeader>
						<CardTitle>
							{ __( 'Data', 'reinventx-forms' ) }
						</CardTitle>
						<CardDescription>
							{ __(
								'What happens to Reinventx Forms data when the plugin is deleted.',
								'reinventx-forms'
							) }
						</CardDescription>
					</CardHeader>
					<CardContent>
						<div className="flex items-start gap-3">
							<Switch
								id="rvtx-delete-data"
								checked={ settings.delete_data_on_uninstall }
								disabled={ saving }
								onCheckedChange={ onToggle }
							/>
							<div className="flex flex-col gap-1">
								<label
									className="text-sm font-medium"
									htmlFor="rvtx-delete-data"
								>
									{ __(
										'Delete all data on uninstall',
										'reinventx-forms'
									) }
								</label>
								<p className="text-sm text-muted-foreground">
									{ __(
										'Off by default. When enabled, deleting the plugin permanently removes every form, lead, and note. When disabled, your data survives uninstall and reinstall.',
										'reinventx-forms'
									) }
								</p>
							</div>
						</div>
					</CardContent>
				</Card>
			) }
		</div>
	);
}
