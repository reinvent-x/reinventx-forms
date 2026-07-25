/**
 * Progressive enhancement for Reinventx Forms public forms.
 *
 * Must stay framework-free and dependency-free: this file is the entire
 * public payload (ARCHITECTURE §4). Without it, the form still POSTs back
 * to the page and gets a server-rendered result — everything here is an
 * upgrade, not a requirement. Error messages arrive pre-translated from
 * the server, so no i18n runtime is needed.
 */

interface ErrorBody {
	message?: string;
	data?: {
		errors?: Record< string, string >;
		messages?: Record< string, string >;
	};
}

function enhance( form: HTMLFormElement ): void {
	const endpoint = form.dataset.rvtxEndpoint;

	if ( ! endpoint ) {
		return;
	}

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		clearErrors( form );

		const submit = form.querySelector< HTMLButtonElement >(
			'button[type="submit"]'
		);

		if ( submit ) {
			submit.disabled = true;
		}

		try {
			const response = await fetch( endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload( form ) ),
			} );

			const body = ( await response.json() ) as ErrorBody;

			if ( response.ok ) {
				showSuccess( form, body.message ?? '' );
				return;
			}

			renderErrors( form, body );

			if ( ! hasFieldErrors( body ) ) {
				showMessage( form, body.message ?? genericError( form ) );
			}
		} catch {
			showMessage( form, genericError( form ) );
		} finally {
			if ( submit ) {
				submit.disabled = false;
			}
		}
	} );
}

function payload( form: HTMLFormElement ): Record< string, unknown > {
	const data = new FormData( form );
	const fields: Record< string, string > = {};

	data.forEach( ( value, key ) => {
		const match = key.match( /^rvtx_fields\[(.+)\]$/ );

		if ( match ) {
			fields[ match[ 1 ] ] = String( value );
		}
	} );

	return {
		form_id: Number( data.get( 'rvtx_form_id' ) ),
		token: String( data.get( 'rvtx_token' ) ?? '' ),
		issued_at: Number( data.get( 'rvtx_issued_at' ) ),
		website: String( data.get( 'rvtx_website' ) ?? '' ),
		source_url: String( data.get( 'rvtx_source_url' ) ?? '' ),
		source_title: String( data.get( 'rvtx_source_title' ) ?? '' ),
		fields,
	};
}

function hasFieldErrors( body: ErrorBody ): boolean {
	return Object.keys( body.data?.messages ?? {} ).length > 0;
}

function clearErrors( form: HTMLFormElement ): void {
	form.querySelectorAll< HTMLElement >( '[data-rvtx-error-for]' ).forEach(
		( el ) => {
			el.hidden = true;
			el.textContent = '';
		}
	);
	form.querySelectorAll< HTMLElement >( '[aria-invalid]' ).forEach( ( el ) =>
		el.removeAttribute( 'aria-invalid' )
	);

	const message = form.querySelector< HTMLElement >( '[data-rvtx-message]' );

	if ( message ) {
		message.hidden = true;
		message.textContent = '';
	}
}

function renderErrors( form: HTMLFormElement, body: ErrorBody ): void {
	const messages = body.data?.messages ?? {};

	Object.keys( messages ).forEach( ( fieldId ) => {
		const error = form.querySelector< HTMLElement >(
			`[data-rvtx-error-for="${ fieldId }"]`
		);

		if ( error ) {
			error.textContent = messages[ fieldId ];
			error.hidden = false;
		}

		const field = form.querySelector< HTMLElement >(
			`[data-rvtx-field="${ fieldId }"] input, [data-rvtx-field="${ fieldId }"] textarea`
		);

		if ( field ) {
			field.setAttribute( 'aria-invalid', 'true' );
		}
	} );

	const first = form.querySelector< HTMLElement >(
		'[data-rvtx-error-for]:not([hidden])'
	);

	if ( first ) {
		first.scrollIntoView( { block: 'nearest' } );
	}
}

function showMessage( form: HTMLFormElement, text: string ): void {
	const el = form.querySelector< HTMLElement >( '[data-rvtx-message]' );

	if ( el ) {
		el.textContent = text;
		el.hidden = false;
	}
}

function showSuccess( form: HTMLFormElement, text: string ): void {
	const wrapper = document.createElement( 'div' );

	wrapper.className = 'rvtx-form rvtx-success';
	wrapper.setAttribute( 'role', 'status' );
	wrapper.textContent = text || genericSuccess( form );
	form.replaceWith( wrapper );
}

// Fallback strings if the response body is unusable; the server normally
// supplies translated text.
function genericError( form: HTMLFormElement ): string {
	return (
		form.dataset.rvtxErrorText ?? 'Something went wrong. Please try again.'
	);
}

function genericSuccess( form: HTMLFormElement ): string {
	return (
		form.dataset.rvtxSuccessText ??
		'Thanks! Your message has been received.'
	);
}

document
	.querySelectorAll< HTMLFormElement >( 'form[data-rvtx-form]' )
	.forEach( enhance );

export {};
