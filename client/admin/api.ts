import apiFetch from '@wordpress/api-fetch';

import type {
	Form,
	FormPayload,
	FormStatus,
	LeadDetail,
	LeadNote,
	LeadsPage,
	LeadStatus,
} from './types';

const BASE = '/reinventx-forms/v1/forms';

/**
 * Error body produced by WP_Error responses from our controller.
 */
export interface ApiError {
	code: string;
	message: string;
	data?: {
		status?: number;
		errors?: string[];
	};
}

export function isApiError( value: unknown ): value is ApiError {
	return (
		typeof value === 'object' &&
		value !== null &&
		typeof ( value as ApiError ).code === 'string' &&
		typeof ( value as ApiError ).message === 'string'
	);
}

export function fetchForms( status: FormStatus ): Promise< Form[] > {
	return apiFetch( { path: `${ BASE }?status=${ status }` } );
}

export function fetchForm( id: number ): Promise< Form > {
	return apiFetch( { path: `${ BASE }/${ id }` } );
}

export function createForm( payload: FormPayload ): Promise< Form > {
	return apiFetch( { path: BASE, method: 'POST', data: payload } );
}

export function updateForm(
	id: number,
	payload: FormPayload
): Promise< Form > {
	return apiFetch( {
		path: `${ BASE }/${ id }`,
		method: 'PUT',
		data: payload,
	} );
}

export function archiveForm( id: number ): Promise< { archived: boolean } > {
	return apiFetch( { path: `${ BASE }/${ id }`, method: 'DELETE' } );
}

export function fetchAllForms(): Promise< Form[] > {
	return apiFetch( { path: `${ BASE }?status=all` } );
}

const LEADS = '/reinventx-forms/v1/leads';

export interface LeadFilters {
	page: number;
	formId: number | null;
	status: LeadStatus | null;
}

export function fetchLeads( filters: LeadFilters ): Promise< LeadsPage > {
	const params = new URLSearchParams( { page: String( filters.page ) } );

	if ( filters.formId !== null ) {
		params.set( 'form_id', String( filters.formId ) );
	}

	if ( filters.status !== null ) {
		params.set( 'status', filters.status );
	}

	return apiFetch( { path: `${ LEADS }?${ params.toString() }` } );
}

export function fetchLead( id: number ): Promise< LeadDetail > {
	return apiFetch( { path: `${ LEADS }/${ id }` } );
}

export function updateLeadStatus(
	id: number,
	status: LeadStatus
): Promise< LeadDetail > {
	return apiFetch( {
		path: `${ LEADS }/${ id }`,
		method: 'PATCH',
		data: { status },
	} );
}

export function addLeadNote( id: number, note: string ): Promise< LeadNote > {
	return apiFetch( {
		path: `${ LEADS }/${ id }/notes`,
		method: 'POST',
		data: { note },
	} );
}

export interface Settings {
	delete_data_on_uninstall: boolean;
}

export function fetchSettings(): Promise< Settings > {
	return apiFetch( { path: '/reinventx-forms/v1/settings' } );
}

export function updateSettings( settings: Settings ): Promise< Settings > {
	return apiFetch( {
		path: '/reinventx-forms/v1/settings',
		method: 'PUT',
		data: settings,
	} );
}
