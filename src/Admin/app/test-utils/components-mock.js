/**
 * Light stand-ins for the three @wordpress/components used by the settings
 * and wizard pages. The real package pulls ESM-only deps that the default
 * wp-scripts jest preset does not transform; e2e covers the real Modal.
 */

import { createElement } from '@wordpress/element';

export function Button( {
	href,
	children,
	isBusy,
	variant,
	__next40pxDefaultSize,
	...rest
} ) {
	void variant;
	void __next40pxDefaultSize;
	if ( href ) {
		return createElement( 'a', { href, ...rest }, children );
	}
	return createElement(
		'button',
		{ 'aria-busy': isBusy ? 'true' : undefined, ...rest },
		children
	);
}

export function Modal( {
	title,
	onRequestClose,
	closeButtonLabel,
	className,
	children,
} ) {
	return createElement(
		'div',
		{
			role: 'dialog',
			'aria-modal': 'true',
			className: [ 'components-modal__frame', className ]
				.filter( Boolean )
				.join( ' ' ),
		},
		createElement( 'h1', null, title ),
		createElement( 'button', {
			type: 'button',
			'aria-label': closeButtonLabel || 'Close',
			onClick: onRequestClose,
		} ),
		children
	);
}

export function Snackbar( { children } ) {
	return createElement(
		'div',
		{ className: 'components-snackbar' },
		children
	);
}
