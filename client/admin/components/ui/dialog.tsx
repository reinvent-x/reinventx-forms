import * as DialogPrimitive from '@radix-ui/react-dialog';
import { __ } from '@wordpress/i18n';
import { X } from 'lucide-react';
import type { ComponentProps } from 'react';

import { portalContainer } from '@/lib/portal';
import { cn } from '@/lib/utils';

function Dialog( props: ComponentProps< typeof DialogPrimitive.Root > ) {
	return <DialogPrimitive.Root data-slot="dialog" { ...props } />;
}

function DialogTrigger(
	props: ComponentProps< typeof DialogPrimitive.Trigger >
) {
	return <DialogPrimitive.Trigger data-slot="dialog-trigger" { ...props } />;
}

function DialogOverlay( {
	className,
	...props
}: ComponentProps< typeof DialogPrimitive.Overlay > ) {
	return (
		<DialogPrimitive.Overlay
			data-slot="dialog-overlay"
			className={ cn( 'fixed inset-0 z-50 bg-black/60', className ) }
			{ ...props }
		/>
	);
}

function DialogContent( {
	className,
	children,
	...props
}: ComponentProps< typeof DialogPrimitive.Content > ) {
	return (
		<DialogPrimitive.Portal container={ portalContainer() }>
			<DialogOverlay />
			<DialogPrimitive.Content
				data-slot="dialog-content"
				className={ cn(
					'fixed left-1/2 top-1/2 z-50 grid w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl border bg-background p-6 shadow-lg',
					className
				) }
				{ ...props }
			>
				{ children }
				<DialogPrimitive.Close className="absolute right-4 top-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring">
					<X className="size-4" />
					<span className="sr-only">
						{ __( 'Close', 'reinventx-forms' ) }
					</span>
				</DialogPrimitive.Close>
			</DialogPrimitive.Content>
		</DialogPrimitive.Portal>
	);
}

function DialogHeader( { className, ...props }: ComponentProps< 'div' > ) {
	return (
		<div
			data-slot="dialog-header"
			className={ cn( 'flex flex-col gap-1.5', className ) }
			{ ...props }
		/>
	);
}

function DialogFooter( { className, ...props }: ComponentProps< 'div' > ) {
	return (
		<div
			data-slot="dialog-footer"
			className={ cn( 'flex justify-end gap-2', className ) }
			{ ...props }
		/>
	);
}

function DialogTitle( {
	className,
	...props
}: ComponentProps< typeof DialogPrimitive.Title > ) {
	return (
		<DialogPrimitive.Title
			data-slot="dialog-title"
			className={ cn( 'text-lg font-semibold leading-none', className ) }
			{ ...props }
		/>
	);
}

function DialogDescription( {
	className,
	...props
}: ComponentProps< typeof DialogPrimitive.Description > ) {
	return (
		<DialogPrimitive.Description
			data-slot="dialog-description"
			className={ cn( 'text-sm text-muted-foreground', className ) }
			{ ...props }
		/>
	);
}

export {
	Dialog,
	DialogTrigger,
	DialogContent,
	DialogHeader,
	DialogFooter,
	DialogTitle,
	DialogDescription,
};
