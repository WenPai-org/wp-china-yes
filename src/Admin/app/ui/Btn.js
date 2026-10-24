/**
 * Thin adapter: package Btn; map product `isBusy` to package `busy` (.is-busy).
 * Call sites still pass WordPress-components-style `isBusy`; package Btn
 * accepts `busy` and applies `.is-busy` + aria-busy (never `.btn.is-loading`).
 * Do not forward `isBusy` to the DOM (React unknown-prop warning).
 */

import { createElement } from '@wordpress/element';
import { Btn as UiBtn } from '@wenpai/wpcy-admin-ui';

/**
 * @param {Object}                    props
 * @param {boolean}                   [props.isBusy]
 * @param {boolean}                   [props.busy]
 * @param {boolean}                   [props.disabled]
 * @param {import('react').ReactNode} [props.children]
 */
export default function Btn( {
	isBusy = false,
	busy = false,
	disabled = false,
	children,
	...rest
} ) {
	const isBusyState = Boolean( isBusy || busy );
	return createElement(
		UiBtn,
		{
			...rest,
			busy: isBusyState,
			disabled: Boolean( disabled || isBusyState ),
		},
		children
	);
}

export { Btn };
