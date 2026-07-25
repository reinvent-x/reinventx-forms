/**
 * Editor side of the reinventx-forms/form block. Rendering is server-side
 * (the block and the shortcode share one PHP renderer), so this file only
 * provides the form picker and a live preview.
 *
 * Unlike the admin SPA, this runs inside the block editor — matching
 * Gutenberg with @wordpress/components is correct here (ARCHITECTURE §4).
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import {
	Notice,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

interface FormOption {
	id: number;
	name: string;
}

interface EditProps {
	attributes: { formId: number };
	setAttributes: ( attributes: { formId: number } ) => void;
}

function useForms(): { forms: FormOption[] | null; failed: boolean } {
	const [ forms, setForms ] = useState< FormOption[] | null >( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		apiFetch< FormOption[] >( {
			path: '/reinventx-forms/v1/forms?status=active',
		} )
			.then( ( items ) =>
				setForms( items.map( ( { id, name } ) => ( { id, name } ) ) )
			)
			.catch( () => {
				setFailed( true );
				setForms( [] );
			} );
	}, [] );

	return { forms, failed };
}

function formOptions( forms: FormOption[] ) {
	return [
		{ label: __( 'Select a form…', 'reinventx-forms' ), value: '0' },
		...forms.map( ( form ) => ( {
			label: form.name,
			value: String( form.id ),
		} ) ),
	];
}

function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps();
	const { forms, failed } = useForms();
	const { formId } = attributes;

	const picker =
		forms === null ? (
			<Spinner />
		) : (
			<SelectControl
				label={ __( 'Form', 'reinventx-forms' ) }
				value={ String( formId ) }
				options={ formOptions( forms ) }
				onChange={ ( value ) =>
					setAttributes( { formId: Number( value ) } )
				}
				__nextHasNoMarginBottom
			/>
		);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'reinventx-forms' ) }>
					{ picker }
				</PanelBody>
			</InspectorControls>

			{ failed && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Could not load the forms list. You may not have permission to manage Reinventx Forms.',
						'reinventx-forms'
					) }
				</Notice>
			) }

			{ formId === 0 ? (
				<Placeholder
					icon="email-alt2"
					label={ __( 'Reinventx Form', 'reinventx-forms' ) }
					instructions={ __(
						'Choose which form to show here.',
						'reinventx-forms'
					) }
				>
					{ picker }
				</Placeholder>
			) : (
				<ServerSideRender
					block="reinventx-forms/form"
					attributes={ { formId } }
				/>
			) }
		</div>
	);
}

// Settings mirror blocks/form/block.json (the server-side source of
// truth); the client copy exists because registerBlockType's types
// require them and older editors do not merge server metadata.
registerBlockType< { formId: number } >( 'reinventx-forms/form', {
	title: __( 'Reinventx Form', 'reinventx-forms' ),
	category: 'widgets',
	attributes: {
		formId: { type: 'number', default: 0 },
	},
	edit: Edit,
	save: () => null,
} );
