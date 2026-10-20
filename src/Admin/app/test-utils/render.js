/**
 * Minimal jsdom render helper (no extra npm packages): React root + act.
 */

import { createRoot } from '@wordpress/element';
// React itself ships with @wordpress/element; act() is only exported there.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const mounted = new Set();

/**
 * Unmount everything rendered so far (call from afterEach).
 */
export async function cleanup() {
	for ( const handle of Array.from( mounted ) ) {
		await handle.unmount();
	}
}

/**
 * Run a store dispatch inside act().
 *
 * @param {Function} fn Callback.
 */
export async function inAct( fn ) {
	await act( async () => {
		await fn();
	} );
}

/**
 * Render into a detached container attached to document.body.
 *
 * @param {import('react').ReactElement} element Element.
 * @return {Promise<{container:HTMLElement,unmount:Function}>} Handle.
 */
export async function render( element ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	await act( async () => {
		root.render( element );
	} );
	await flush();
	const handle = {
		container,
		unmount: async () => {
			if ( ! mounted.has( handle ) ) {
				return;
			}
			mounted.delete( handle );
			await act( async () => root.unmount() );
			container.remove();
		},
	};
	mounted.add( handle );
	return handle;
}

/**
 * Let pending promises and React updates settle.
 *
 * @param {number} [rounds] Microtask rounds.
 */
export async function flush( rounds = 6 ) {
	for ( let i = 0; i < rounds; i++ ) {
		await act( async () => {
			await Promise.resolve();
		} );
	}
}

/**
 * Click an element inside act().
 *
 * @param {Element} el Element.
 */
export async function click( el ) {
	await act( async () => {
		el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
	} );
	await flush();
}

/**
 * Buttons whose text content includes the given text.
 *
 * @param {Element} root Root.
 * @param {string}  text Text.
 * @return {HTMLButtonElement[]} Buttons.
 */
export function buttonsByText( root, text ) {
	return Array.from( root.querySelectorAll( 'button, a' ) ).filter( ( b ) =>
		( b.textContent || '' ).includes( text )
	);
}

export { act };
