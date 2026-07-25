import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Inbox as InboxIcon,
	LayoutList,
	Settings as SettingsIcon,
} from 'lucide-react';

import { Button } from './components/ui/button';
import FormEditor from './FormEditor';
import FormsList from './FormsList';
import Inbox from './Inbox';
import LeadDetail from './LeadDetail';
import Settings from './Settings';

type View =
	| { name: 'inbox' }
	| { name: 'lead'; leadId: number }
	| { name: 'forms' }
	| { name: 'form-editor'; formId: number | null }
	| { name: 'settings' };

function sectionOf( view: View ): 'inbox' | 'forms' | 'settings' {
	if ( view.name === 'inbox' || view.name === 'lead' ) {
		return 'inbox';
	}

	if ( view.name === 'settings' ) {
		return 'settings';
	}

	return 'forms';
}

export default function App() {
	const [ view, setView ] = useState< View >( { name: 'inbox' } );
	const section = sectionOf( view );

	return (
		<div className="flex flex-col gap-5 py-2">
			<nav
				className="flex gap-1"
				aria-label={ __( 'Reinventx Forms', 'reinventx-forms' ) }
			>
				<Button
					variant={ section === 'inbox' ? 'secondary' : 'ghost' }
					size="sm"
					onClick={ () => setView( { name: 'inbox' } ) }
				>
					<InboxIcon />
					{ __( 'Inbox', 'reinventx-forms' ) }
				</Button>
				<Button
					variant={ section === 'forms' ? 'secondary' : 'ghost' }
					size="sm"
					onClick={ () => setView( { name: 'forms' } ) }
				>
					<LayoutList />
					{ __( 'Forms', 'reinventx-forms' ) }
				</Button>
				<Button
					variant={ section === 'settings' ? 'secondary' : 'ghost' }
					size="sm"
					onClick={ () => setView( { name: 'settings' } ) }
				>
					<SettingsIcon />
					{ __( 'Settings', 'reinventx-forms' ) }
				</Button>
			</nav>

			{ view.name === 'inbox' && (
				<Inbox
					onOpenLead={ ( leadId ) =>
						setView( { name: 'lead', leadId } )
					}
				/>
			) }

			{ view.name === 'lead' && (
				<LeadDetail
					leadId={ view.leadId }
					onBack={ () => setView( { name: 'inbox' } ) }
				/>
			) }

			{ view.name === 'forms' && (
				<FormsList
					onCreate={ () =>
						setView( { name: 'form-editor', formId: null } )
					}
					onEdit={ ( formId ) =>
						setView( { name: 'form-editor', formId } )
					}
				/>
			) }

			{ view.name === 'form-editor' && (
				<FormEditor
					formId={ view.formId }
					onDone={ () => setView( { name: 'forms' } ) }
				/>
			) }

			{ view.name === 'settings' && <Settings /> }
		</div>
	);
}
